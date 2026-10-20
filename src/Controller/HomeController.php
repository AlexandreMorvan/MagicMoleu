<?php

namespace App\Controller;

use App\Repository\MtgSetRepository;
use App\Repository\OracleCardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(OracleCardRepository $cards, MtgSetRepository $sets): Response
    {
        return $this->render('home/index.html.twig', [
            'card_count' => $cards->count(),
            'set_count' => $sets->count(),
            'latest_sets' => $sets->findLatestReleased(6),
        ]);
    }
}
