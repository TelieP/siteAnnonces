<?php

namespace App\Controller;

use App\Repository\AdRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\MessageRepository;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\UserType;
use Symfony\Component\HttpFoundation\Request;

#[IsGranted('ROLE_USER')] // Sécurité : il faut être connecté
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(AdRepository $adRepository): Response
    {
        // On récupère uniquement les annonces de l'utilisateur connecté
        $user = $this->getUser();
        $myAds = $adRepository->findBy(['author' => $user], ['updatedAt' => 'DESC']);

        return $this->render('profile/index.html.twig', [
            'ads' => $myAds,
        ]);
    }

    #[Route('/profile/favorites', name: 'app_profile_favorites')]
    #[IsGranted('ROLE_USER')]
    public function favorites(): Response
    {
    return $this->render('profile/favorites.html.twig', [
        'ads' => $this->getUser()->getFavorites(),
    ]);
    }

    #[Route('/profile/messages', name: 'app_profile_messages')]
    #[IsGranted('ROLE_USER')]
    public function messages(MessageRepository $messageRepository): Response
    {
    return $this->render('profile/messages.html.twig', [
        'receivedMessages' => $messageRepository->findBy(['recipient' => $this->getUser()], ['createdAt' => 'DESC']),
        'sentMessages' => $messageRepository->findBy(['sender' => $this->getUser()], ['createdAt' => 'DESC']),
        ]);
    }

#[Route('/message/read/{id}', name: 'app_message_read')]
public function read(Message $message, EntityManagerInterface $em): Response
{
    if ($this->getUser() === $message->getRecipient()) {
        $message->setIsRead(true);
        $em->flush();
    }

    // On redirige vers l'annonce, mais on ajoute une ancre "#contact" 
    // pour descendre directement au formulaire de réponse
    return $this->redirect($this->generateUrl('app_ad_show', [
        'id' => $message->getAd()->getId()
    ]) . '#contact');
}

#[Route('/message/delete/{id}', name: 'app_message_delete')]
public function delete(Message $message, EntityManagerInterface $em): Response
{
    // On vérifie que l'utilisateur a le droit de supprimer (soit l'envoyeur, soit le receveur)
    if ($this->getUser() === $message->getRecipient() || $this->getUser() === $message->getSender()) {
        $em->remove($message);
        $em->flush();
        $this->addFlash('success', 'Message supprimé avec succès.');
    }

    return $this->redirectToRoute('app_profile_messages');
}

#[Route('/profile/account', name: 'app_profile_account')]
public function account(): Response
{
    return $this->render('profile/account.html.twig');
}



#[Route('/profile/edit', name: 'app_profile_edit')]
public function edit(Request $request, EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    $form = $this->createForm(UserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', 'Profil mis à jour avec succès !');

        return $this->redirectToRoute('app_profile_account');
    }

    return $this->render('profile/edit.html.twig', [
        'form' => $form->createView(),
    ]);
}


}
