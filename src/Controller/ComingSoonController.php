<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages d'attente des sections à venir (deckbuilder : étape 3, spoilers : étape 4).
 */
class ComingSoonController extends AbstractController
{
    #[Route('/decks', name: 'app_deck_index', methods: ['GET'])]
    public function decks(): Response
    {
        return $this->render('coming_soon.html.twig', [
            'title' => 'Decks',
            'pitch' => 'Construis tes decks Standard et Commander, et vérifie leur légalité face aux ban lists.',
        ]);
    }

    #[Route('/spoilers', name: 'app_spoilers', methods: ['GET'])]
    public function spoilers(): Response
    {
        return $this->render('coming_soon.html.twig', [
            'title' => 'Spoilers',
            'pitch' => 'Les dernières cartes dévoilées des prochaines extensions, dès leur annonce.',
        ]);
    }
}
