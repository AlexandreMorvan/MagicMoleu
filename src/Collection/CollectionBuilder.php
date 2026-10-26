<?php

namespace App\Collection;

use App\Entity\CardCollection;
use App\Entity\CollectionCard;
use App\Entity\Deck;
use App\Enum\DeckZone;
use App\Repository\CardPrintingRepository;

/**
 * Ajoute des cartes à une collection, une impression à la fois ou tout un deck.
 */
class CollectionBuilder
{
    public function __construct(private readonly CardPrintingRepository $printings)
    {
    }

    /**
     * Ajoute les cartes jouées d'un deck (commandant, deck principal, réserve ; pas les « peut-être »),
     * avec l'impression choisie dans le deck, à défaut l'impression par défaut de la carte.
     *
     * @return int nombre d'exemplaires ajoutés
     */
    public function addDeck(CardCollection $collection, Deck $deck): int
    {
        $added = 0;
        foreach ($deck->getCardsIn(DeckZone::Commander, DeckZone::Main, DeckZone::Side) as $deckCard) {
            $printing = $deckCard->getPrinting() ?? $this->printings->findDefaultFor($deckCard->getOracleCard());
            if (null === $printing) {
                continue;
            }

            // Une impression qui n'existe qu'en foil est forcément possédée en foil
            $collection->addCard($printing, $deckCard->getQuantity(), $printing->isFoilOnly());
            $added += $deckCard->getQuantity();
        }

        return $added;
    }

    /**
     * Change la quantité d'une ligne ; à zéro, la ligne est retirée.
     */
    public function changeQuantity(CollectionCard $line, int $delta): void
    {
        if ($line->getQuantity() + $delta <= 0) {
            $line->getCollection()->removeCard($line);
        } else {
            $line->setQuantity($line->getQuantity() + $delta);
            $line->getCollection()->touch();
        }
    }
}
