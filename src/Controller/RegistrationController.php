<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    /**
     * Le formulaire lui-même est géré par le composant live {@see \App\Twig\Components\RegistrationForm}.
     */
    #[Route('/inscription', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('registration/register.html.twig');
    }
}
