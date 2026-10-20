<?php

namespace App\Controller;

use App\Repository\CardPrintingRepository;
use App\Repository\MtgSetRepository;
use App\Repository\OracleCardRepository;
use App\Scryfall\SpoilerSynchronizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(OracleCardRepository $cards, MtgSetRepository $sets, CardPrintingRepository $printings): Response
    {
        return $this->render('home/index.html.twig', [
            'card_count' => $cards->count(),
            'set_count' => $sets->count(),
            'latest_sets' => $sets->findLatestReleased(6),
            'latest_spoilers' => $printings->findSpoilers(
                new \DateTimeImmutable(\sprintf('today -%d days', SpoilerSynchronizer::WINDOW_DAYS)),
                rarities: ['rare', 'mythic'],
                limit: 6,
            ),
        ]);
    }
}
