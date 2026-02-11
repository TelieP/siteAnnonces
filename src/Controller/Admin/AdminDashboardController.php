<?php

namespace App\Controller\Admin;

use App\Repository\AdRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Form\UserType;
use Symfony\Component\HttpFoundation\Request;


// Le nom de la classe ne doit PAS contenir de slash
class AdminDashboardController extends AbstractController
{
   

#[Route('/admin', name: 'app_admin_dashboard')]
public function index(AdRepository $adRepo, UserRepository $userRepo): Response
{
    // 1. On récupère les vraies statistiques depuis le Repository
    $stats = $adRepo->countAdsByDay();

    // 2. On construit le tableau de données pour Chart.js
    $chartData = [
        'labels' => $stats['labels'],
        'datasets' => [
            [
                'label' => 'Annonces publiées',
                'data' => $stats['data'],
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)', // Un joli bleu transparent
                'borderColor' => 'rgba(54, 162, 235, 1)',      // Un bleu solide pour la bordure
                'borderWidth' => 2,
                'borderRadius' => 5, // Un petit arrondi pour le style
            ]
        ]
    ];

    // 3. On envoie tout au template
    return $this->render('admin/index.html.twig', [
        'adsCount' => $adRepo->count([]),
        'usersCount' => $userRepo->count([]),
        'lastAds' => $adRepo->findBy([], ['id' => 'DESC'], 5),
        'chartData' => json_encode($chartData), // On transforme en JSON pour le JavaScript
    ]);
}

    #[Route('/admin/ad/delete/{id}', name: 'admin_ad_delete', methods: ['POST', 'GET'])]
    public function deleteAd(int $id, AdRepository $adRepo, EntityManagerInterface $em): Response
    {
    $ad = $adRepo->find($id);

    if ($ad) {
        $em->remove($ad);
        $em->flush();
        $this->addFlash('success', 'L\'annonce a été supprimée avec succès.');
    }

    return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/users', name: 'admin_users_list')]
    public function usersList(UserRepository $userRepo): Response
    {
    return $this->render('admin/users.html.twig', [
        'users' => $userRepo->findAll(),
    ]);
    }

    // src/Controller/Admin/AdminDashboardController.php

#[Route('/admin/user/delete/{id}', name: 'admin_user_delete', methods: ['POST', 'GET'])]
public function deleteUser(User $user, EntityManagerInterface $em): Response
{
    // Sécurité : on empêche l'admin de se supprimer lui-même
    if ($user === $this->getUser()) {
        $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte admin !');
        return $this->redirectToRoute('admin_users_list');
    }

    $em->remove($user);
    $em->flush();

    $this->addFlash('success', 'L\'utilisateur a été supprimé.');
    return $this->redirectToRoute('admin_users_list');
}

#[Route('/admin/user/edit/{id}', name: 'admin_user_edit')]
public function editUser(User $user, Request $request, EntityManagerInterface $em): Response
{
    // On réutilise ton formulaire UserType existant
    $form = $this->createForm(UserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', 'Le profil a été mis à jour.');
        return $this->redirectToRoute('admin_users_list');
    }

    return $this->render('admin/edit_user.html.twig', [
        'form' => $form->createView(),
        'user' => $user
    ]);
}
}