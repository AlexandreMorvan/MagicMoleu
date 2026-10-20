<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, NotificationRepository $notifications): Response
    {
        $latest = $notifications->findLatestFor($user, 100);
        // Ouvrir la liste complète vaut lecture
        $notifications->markAllReadFor($user);

        return $this->render('notification/index.html.twig', ['notifications' => $latest]);
    }
}
