<?php

namespace App\Controller;

use App\Entity\MtgSet;
use App\Repository\MtgSetRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/editions')]
class SetController extends AbstractController
{
    #[Route('', name: 'app_set_index', methods: ['GET'])]
    public function index(MtgSetRepository $sets, #[MapQueryParameter] bool $toutes = false): Response
    {
        $byYear = [];
        foreach ($sets->findWithCardCounts(mainOnly: !$toutes) as $row) {
            $byYear[$row['set']->getReleasedAt()?->format('Y') ?? 'Sans date'][] = $row;
        }

        return $this->render('set/index.html.twig', ['sets_by_year' => $byYear, 'show_all' => $toutes]);
    }

    /**
     * Les cartes et leurs filtres sont gérés par le composant live {@see \App\Twig\Components\SetCards}.
     */
    #[Route('/{code}', name: 'app_set_show', requirements: ['code' => '[a-z0-9]{2,8}'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['code' => 'code'])] MtgSet $set, MtgSetRepository $sets): Response
    {
        return $this->render('set/show.html.twig', [
            'set' => $set,
            'parent' => $set->getParentSetCode() ? $sets->findOneBy(['code' => $set->getParentSetCode()]) : null,
        ]);
    }
}
