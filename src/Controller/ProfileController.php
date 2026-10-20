<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\Achievement;
use App\Repository\DeckRepository;
use App\Repository\UserAchievementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function show(#[CurrentUser] User $user, DeckRepository $decks, UserAchievementRepository $achievements): Response
    {
        return $this->render('profile/show.html.twig', [
            'decks' => $decks->findByOwner($user),
            'achievements' => Achievement::cases(),
            'unlocked' => $achievements->findUnlockedBy($user),
        ]);
    }
}
