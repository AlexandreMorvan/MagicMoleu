<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'attente de la section spoilers (étape 4).
 */
class ComingSoonController extends AbstractController
{
    #[Route('/spoilers', name: 'app_spoilers', methods: ['GET'])]
    public function spoilers(): Response
    {
        return $this->render('coming_soon.html.twig', [
            'title' => 'Spoilers',
            'pitch' => 'Les dernières cartes dévoilées des prochaines extensions, dès leur annonce.',
        ]);
    }
}
