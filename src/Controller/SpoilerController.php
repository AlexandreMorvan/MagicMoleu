<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpoilerController extends AbstractController
{
    /**
     * La galerie est gérée par le composant live {@see \App\Twig\Components\SpoilerGallery}.
     */
    #[Route('/spoilers', name: 'app_spoilers', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spoiler/index.html.twig');
    }
}
