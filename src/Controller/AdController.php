<?php

namespace App\Controller;

use App\Entity\Ad;
use App\Entity\Image;
use App\Entity\User;
use App\Entity\Message;
use App\Entity\History;
use App\Entity\MessageImage;
use App\Form\AdType;
use App\Form\ContactType;
use App\Repository\AdRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdController extends AbstractController
{
    // 1. La page d'accueil avec recherche et historique permanent
    #[Route('/', name: 'app_ad_index')]
    public function index(AdRepository $adRepo, CategoryRepository $categoryRepo, Request $request, EntityManagerInterface $em): Response
    {
        $query = $request->query->get('q');
        $location = $request->query->get('location');
        
        $minPrice = $request->query->get('min') !== "" ? (float)$request->query->get('min') : null;
        $maxPrice = $request->query->get('max') !== "" ? (float)$request->query->get('max') : null;
        $categoryId = $request->query->get('category');
        $ads = $adRepo->findBySearch($query, $location, $minPrice, $maxPrice, $categoryId);
        $categories = $categoryRepo->findAll();

        // --- LOGIQUE HISTORIQUE PERMANENT (BASE DE DONNÉES) ---
        $historyAds = [];
        if ($this->getUser()) {
            // On récupère les 4 dernières entrées d'historique de l'utilisateur connecté
            $historyEntries = $em->getRepository(History::class)->findBy(
                ['user' => $this->getUser()],
                ['viewedAt' => 'DESC'],
                4
            );

            // On extrait les annonces de ces entrées
            foreach ($historyEntries as $entry) {
                $historyAds[] = $entry->getAd();
            }
        }

        return $this->render('ad/index.html.twig', [
            'ads' => $ads,
            'categories' => $categories,
            'historyAds' => $historyAds, 
        ]);
    }

    // 2. Création d'une nouvelle annonce
    #[Route('ad/new', name: 'app_ad_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ad = new Ad();
        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ad->setAuthor($this->getUser());
            $imageFiles = $form->get('imageFiles')->getData();

            if ($imageFiles) {
                foreach ($imageFiles as $imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $img = new Image();
                        $img->setName($newFilename);
                        $img->setAd($ad);
                        $entityManager->persist($img);
                    } catch (FileException $e) {
                        $this->addFlash('danger', "Erreur lors de l'upload d'une image.");
                    }
                }
            }

            $entityManager->persist($ad);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a bien été publiée !');
            return $this->redirectToRoute('app_ad_index');
        }

        return $this->render('ad/new.html.twig', [
            'ad' => $ad,
            'form' => $form,
        ]);
    }

    // 3. Affichage d'une annonce, historique et messagerie
    #[Route('/ad/{id}', name: 'app_ad_show')]
    public function show(Ad $ad, Request $request, EntityManagerInterface $em, AdRepository $adRepo): Response
    {
        // --- LOGIQUE ENREGISTREMENT HISTORIQUE ---
        $user = $this->getUser();
        if ($user) {
            $historyRepo = $em->getRepository(History::class);
            $existingHistory = $historyRepo->findOneBy([
                'user' => $user,
                'ad' => $ad
            ]);

            if (!$existingHistory) {
                $history = new History();
                $history->setUser($user);
                $history->setAd($ad);
                $history->setViewedAt(new \DateTimeImmutable());
                $em->persist($history);
            } else {
                // On met à jour la date pour que l'annonce remonte en tête de liste
                $existingHistory->setViewedAt(new \DateTimeImmutable());
            }
            $em->flush();
        }

        // Logique compte les vues et favoris sur les annonces 
            $favoritesCount = $ad->getUsers()->count();

            $viewsCount = $em->getRepository(History::class)->count(['ad' => $ad]);

        // --- FORMULAIRE DE CONTACT ---
        $message = new Message();
        $form = $this->createForm(ContactType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user) {
                $this->addFlash('danger', 'Vous devez être connecté pour envoyer un message.');
                return $this->redirectToRoute('app_login');
            }

            $images = $form->get('imageFiles')->getData();
            if ($images) {
                foreach ($images as $image) {
                    $newFilename = md5(uniqid()) . '.' . $image->guessExtension();
                    $image->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/images',
                        $newFilename
                    );
                    $messageImage = new MessageImage();
                    $messageImage->setName($newFilename);
                    $message->addImage($messageImage);
                }
            }

            $message->setSender($user);
            $message->setRecipient($ad->getAuthor());
            $message->setAd($ad);
            $message->setCreatedAt(new \DateTimeImmutable());
            $message->setIsRead(false);

            $em->persist($message);
            $em->flush();

            $this->addFlash('success', 'Votre message a bien été envoyé au vendeur !');
            return $this->redirectToRoute('app_ad_show', ['id' => $ad->getId()]);
        }

        // --- ANNONCES SIMILAIRES ---
        $similarAds = $adRepo->createQueryBuilder('a')
            ->leftJoin('a.images', 'i')
            ->addSelect('i')
            ->where('a.category = :cat')
            ->andWhere('a.id != :currentId')
            ->setParameter('cat', $ad->getCategory())
            ->setParameter('currentId', $ad->getId())
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        return $this->render('ad/show.html.twig', [
            'ad' => $ad,
            'contactForm' => $form->createView(),
            'similarAds' => $similarAds,
            'favoritesCount' => $favoritesCount, 
            'viewsCount' => $viewsCount,
        ]);
    }

    // 4. Suppression d'une image de message
    #[Route('/message/image/{id}/delete', name: 'app_message_image_delete', methods: ['POST', 'DELETE'])]
    public function deleteImage(MessageImage $image, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $image->getId(), $request->request->get('_token'))) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/images/' . $image->getName();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $em->remove($image);
            $em->flush();
            $this->addFlash('success', 'La photo a été supprimée.');
        }
        return $this->redirect($request->headers->get('referer'));
    }

    // 5. Suppression d'une annonce
    #[Route('/ad/delete/{id}', name: 'app_ad_delete', methods: ['POST', 'GET'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Ad $ad, EntityManagerInterface $em): Response
    {
        if ($ad->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous ne pouvez pas supprimer cette annonce.");
        }
        $em->remove($ad);
        $em->flush();
        $this->addFlash('success', 'Annonce supprimée avec succès.');
        return $this->redirectToRoute('app_profile');
    }

    // 6. Modification d'une annonce
    #[Route('/ad/edit/{id}', name: 'app_ad_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Ad $ad, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFiles = $form->get('imageFiles')->getData();
            if ($imageFiles) {
                foreach ($imageFiles as $imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $img = new Image();
                        $img->setName($newFilename);
                        $img->setAd($ad);
                        $entityManager->persist($img);
                    } catch (FileException $e) {
                        $this->addFlash('error', "Erreur lors de l'upload d'une image.");
                    }
                }
            }
            $entityManager->flush();
            $this->addFlash('success', 'Votre annonce a été mise à jour !');
            return $this->redirectToRoute('app_ad_index');
        }

        return $this->render('ad/edit.html.twig', [
            'ad' => $ad,
            'form' => $form,
        ]);
    }

    // 7. Gestion des favoris
    #[Route('/ad/favorite/{id}', name: 'app_ad_favorite')]
    #[IsGranted('ROLE_USER')]
    public function toggleFavorite(Ad $ad, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getFavorites()->contains($ad)) {
            $user->removeFavorite($ad);
            $this->addFlash('info', 'Annonce retirée de vos favoris.');
        } else {
            $user->addFavorite($ad);
            $this->addFlash('success', 'Annonce ajoutée aux favoris !');
        }
        $em->flush();
        return $this->redirect($_SERVER['HTTP_REFERER'] ?? $this->generateUrl('app_ad_index'));
    }
}