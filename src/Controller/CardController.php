<?php

namespace App\Controller;

use App\Entity\CardLegality;
use App\Entity\CardPrinting;
use App\Entity\OracleCard;
use App\Enum\Format;
use App\Repository\CardPrintingRepository;
use App\Repository\MtgSetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class CardController extends AbstractController
{
    /** Les données des cartes ne changent qu'une fois par jour (import quotidien) */
    private const int MODAL_CACHE_TTL = 86400;

    /** Formats affichés sur la fiche d'une carte. */
    private const array DISPLAYED_FORMATS = [
        Format::Standard, Format::Pioneer, Format::Modern, Format::Legacy, Format::Vintage,
        Format::Pauper, Format::Commander, Format::Brawl, Format::Historic, Format::Timeless,
    ];

    /**
     * Les filtres sont gérés par le composant live {@see \App\Twig\Components\CardSearch}.
     */
    #[Route('/cartes', name: 'app_card_search', methods: ['GET'])]
    public function search(MtgSetRepository $sets): Response
    {
        return $this->render('card/search.html.twig', [
            'set_choices' => $sets->findForChoices(),
        ]);
    }

    /**
     * Fiche d'une carte : affichée dans la modale (requête Turbo Frame) ou en page complète.
     */
    #[Route('/cartes/{id}', name: 'app_card_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(OracleCard $card, Request $request, CardPrintingRepository $printings, EntityManagerInterface $em): Response
    {
        $cardPrintings = $printings->findForCard($card);
        $selectedId = $request->query->get('impression');
        $selected = array_find($cardPrintings, static fn (CardPrinting $p) => (string) $p->getId() === $selectedId)
            ?? array_find($cardPrintings, static fn (CardPrinting $p) => $p->getImageUri() === $card->getImageUri())
            ?? $cardPrintings[0] ?? null;

        $legalities = [];
        foreach ($em->getRepository(CardLegality::class)->findBy(['oracleCard' => $card]) as $legality) {
            $legalities[$legality->getFormat()->value] = $legality->getStatus();
        }

        $inModal = 'card_modal' === $request->headers->get('Turbo-Frame');

        $response = $this->render($inModal ? 'card/_modal_frame.html.twig' : 'card/show.html.twig', [
            'card' => $card,
            'printings' => $cardPrintings,
            'selected' => $selected,
            'formats' => self::DISPLAYED_FORMATS,
            'legalities' => $legalities,
        ]);

        if ($inModal) {
            // Le fragment de la modale ne dépend pas de l'utilisateur : le navigateur le garde 24 heures,
            // rouvrir une carte déjà vue ne refait donc pas de requête.
            $response->setPrivate()->setMaxAge(self::MODAL_CACHE_TTL);
            $response->setVary('Turbo-Frame');
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }

        return $response;
    }
}
