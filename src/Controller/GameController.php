<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\DeckRepository;
use App\Repository\OracleCardRepository;
use App\Security\DeckVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Suivi d'une partie de Commander (points de vie, dégâts de commandant, marqueurs), entièrement côté navigateur :
 * voir le contrôleur Stimulus « game-tracker » et assets/game/tracker.js.
 */
class GameController extends AbstractController
{
    #[Route('/partie', name: 'app_game', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user, DeckRepository $decks, #[MapQueryParameter] ?int $deck = null): Response
    {
        // Lancée depuis un deck Commander : le premier joueur reprend son pseudo et ses commandants
        $first = ['name' => $user?->getUsername() ?? '', 'commanders' => []];
        $selected = $deck ? $decks->find($deck) : null;
        if ($selected && Format::Commander === $selected->getFormat() && $this->isGranted(DeckVoter::VIEW, $selected)) {
            $first['commanders'] = array_map(static fn ($line) => $line->getOracleCard()->getName(), $selected->getCardsIn(DeckZone::Commander));
        }

        return $this->render('game/index.html.twig', ['first_player' => $first]);
    }

    /**
     * Illustrations des commandants saisis, pour décorer le panneau de chaque joueur.
     *
     * @param list<string> $noms
     */
    #[Route('/partie/illustrations', name: 'app_game_arts', methods: ['GET'])]
    public function arts(OracleCardRepository $cards, #[MapQueryParameter] array $noms = []): JsonResponse
    {
        $arts = [];
        foreach (\array_slice(array_unique(array_filter($noms, 'is_string')), 0, 12) as $name) {
            $name = mb_substr(trim($name), 0, 150);
            if ('' !== $name) {
                $arts[$name] = $cards->findOneByDeckListName($name)?->getArtCropUri();
            }
        }

        return $this->json($arts);
    }
}
