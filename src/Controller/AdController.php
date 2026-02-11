<?php

namespace App\Controller;

use App\Entity\Ad;
use App\Entity\Message;
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
use App\Entity\Image;


final class AdController extends AbstractController
{
    // 1. La page qui liste toutes les annonces (Page d'accueil du clone)

#[Route('/', name: 'app_ad_index')]
public function index(AdRepository $adRepo, CategoryRepository $categoryRepo, Request $request): Response
{
    $query = $request->query->get('q');
    $location = $request->query->get('l');
    
    // On récupère et on force le type en float, ou null si c'est vide
    $minPrice = $request->query->get('min') !== "" ? (float)$request->query->get('min') : null;
    $maxPrice = $request->query->get('max') !== "" ? (float)$request->query->get('max') : null;

    $ads = $adRepo->findBySearch($query, $location, $minPrice, $maxPrice);
    $categories = $categoryRepo->findAll();

    return $this->render('ad/index.html.twig', [
        'ads' => $ads,
        'categories' => $categories,
    ]);
}

#[Route('ad/new', name: 'app_ad_new', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_USER')]
public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    $ad = new Ad();
    $form = $this->createForm(AdType::class, $ad);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        
        $ad->setAuthor($this->getUser());
        // 1. On récupère les images transmises depuis le champ 'imageFiles'
        $imageFiles = $form->get('imageFiles')->getData();

        // 2. On boucle sur chaque image
        if ($imageFiles) {
            foreach ($imageFiles as $imageFile) {
                
                // Générer un nom unique pour éviter les doublons (ex: 65cb...jpg)
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                // Déplacer le fichier physiquement dans le dossier configuré
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur si le fichier ne peut pas être déplacé
                }

                // 3. On crée l'entrée dans la table 'Image'
                $img = new Image();
                $img->setName($newFilename);
                $img->setAd($ad); // On lie l'image à l'annonce actuelle

                $entityManager->persist($img);
            }
        }

        $entityManager->persist($ad);
        $entityManager->flush();

        $this->addFlash('success', 'Votre annonce a bien été publiée avec ses photos !');

        return $this->redirectToRoute('app_ad_index');
    }

    return $this->render('ad/new.html.twig', [
        'ad' => $ad,
        'form' => $form,
    ]);
}
    #[Route('/ad/{id}', name: 'app_ad_show')]
public function show(Ad $ad, Request $request, EntityManagerInterface $em): Response
{
    $message = new Message();
    $form = $this->createForm(ContactType::class, $message);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if (!$this->getUser()) {
            $this->addFlash('danger', 'Vous devez être connecté pour envoyer un message.');
            return $this->redirectToRoute('app_login');
        }

        $message->setSender($this->getUser());
        $message->setRecipient($ad->getAuthor());
        $message->setAd($ad);
        $message->setCreatedAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        $this->addFlash('success', 'Votre message a bien été envoyé au vendeur !');
        return $this->redirectToRoute('app_ad_show', ['id' => $ad->getId()]);
    }

    return $this->render('ad/show.html.twig', [
        'ad' => $ad,
        'contactForm' => $form->createView(),
    ]);
}

    #[Route('/ad/delete/{id}', name: 'app_ad_delete', methods: ['POST', 'GET'])]
#[IsGranted('ROLE_USER')]
public function delete(Ad $ad, EntityManagerInterface $em): Response
{
    // Sécurité : on vérifie que l'utilisateur est bien l'auteur
    if ($ad->getAuthor() !== $this->getUser()) {
        throw $this->createAccessDeniedException("Vous ne pouvez pas supprimer cette annonce.");
    }

    $em->remove($ad);
    $em->flush();

    $this->addFlash('success', 'Annonce supprimée avec succès.');
    return $this->redirectToRoute('app_profile');
}

#[Route('/ad/edit/{id}', name: 'app_ad_edit')]
#[IsGranted('ROLE_USER')]
public function edit(Request $request, Ad $ad, EntityManagerInterface $entityManager): Response
{
    $form = $this->createForm(AdType::class, $ad);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        
        // 1. Récupération des nouvelles images depuis le champ non-mappé
        $imageFiles = $form->get('imageFiles')->getData();

        if ($imageFiles) {
            foreach ($imageFiles as $imageFile) {
                // Génération d'un nom unique
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                // Déplacement du fichier dans le répertoire configuré
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    
                    // 2. Création de la nouvelle entité Image
                    $img = new Image();
                    $img->setName($newFilename);
                    $img->setAd($ad); // Liaison avec l'annonce actuelle

                    $entityManager->persist($img);
                } catch (FileException $e) {
                    $this->addFlash('error', "Erreur lors de l'upload d'une image.");
                }
            }
        }

        // 3. Sauvegarde des modifications globales
        $entityManager->flush();

        $this->addFlash('success', 'Votre annonce a été mise à jour avec succès !');

        return $this->redirectToRoute('app_ad_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('ad/edit.html.twig', [
        'ad' => $ad,
        'form' => $form,
    ]);
}

  #[Route('/ad/favorite/{id}', name: 'app_ad_favorite')]
#[IsGranted('ROLE_USER')]
public function toggleFavorite(Ad $ad, EntityManagerInterface $em): Response
{
    $user = $this->getUser();

    if ($user->getFavorites()->contains($ad)) {
        $user->removeFavorite($ad);
        $this->addFlash('info', 'Annonce retirée de vos favoris.');
    } else {
        $user->addFavorite($ad);
        $this->addFlash('success', 'Annonce ajoutée aux favoris !');
    }

    $em->flush();

    // On redirige vers la page d'où l'on vient
    return $this->redirect($_SERVER['HTTP_REFERER'] ?? $this->generateUrl('app_ad_index'));
}

}