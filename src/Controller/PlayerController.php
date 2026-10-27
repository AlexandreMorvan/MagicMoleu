<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\Achievement;
use App\Repository\DeckRepository;
use App\Repository\UserAchievementRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Profil public d'un joueur : avatar, présentation, couleurs et format favoris, succès débloqués, decks publics.
 */
class PlayerController extends AbstractController
{
    #[Route('/joueurs/{username}', name: 'app_player', requirements: ['username' => '[\w.-]{3,30}'], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['username' => 'username'], message: 'Joueur introuvable.')] User $player,
        DeckRepository $decks,
        UserAchievementRepository $achievements,
    ): Response {
        $unlocked = $achievements->findUnlockedBy($player);

        return $this->render('player/show.html.twig', [
            'player' => $player,
            'decks' => $decks->findPublicByOwner($player),
            // Succès débloqués, dans l'ordre du catalogue
            'achievements' => array_values(array_filter(Achievement::cases(), static fn (Achievement $a) => isset($unlocked[$a->value]))),
            'unlocked' => $unlocked,
            'total_achievements' => \count(Achievement::cases()),
        ]);
    }
}
