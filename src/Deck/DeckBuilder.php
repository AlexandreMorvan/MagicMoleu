<?php

namespace App\Deck;

use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\OracleCard;
use App\Enum\DeckZone;
use App\Repository\CardPrintingRepository;

/**
 * Ajoute des cartes à un deck en choisissant l'impression par défaut et la zone adaptée au format.
 */
class DeckBuilder
{
    public function __construct(private readonly CardPrintingRepository $printings)
    {
    }

    public function add(Deck $deck, OracleCard $card, DeckZone $zone = DeckZone::Main, int $quantity = 1, ?CardPrinting $printing = null): DeckCard
    {
        $zone = $this->zoneFor($deck, $zone);
        if (null === $deck->findCard($card, $zone)) {
            $printing ??= $this->printings->findDefaultFor($card);
        }

        return $deck->addCard($card, $zone, $quantity, $printing);
    }

    /**
     * Ramène une zone inexistante dans le format vers la plus proche (pas de réserve en Commander, etc.).
     */
    public function zoneFor(Deck $deck, DeckZone $zone): DeckZone
    {
        if (\in_array($zone, DeckZone::forFormat($deck->getFormat()), true)) {
            return $zone;
        }

        return DeckZone::Side === $zone ? DeckZone::Maybe : DeckZone::Main;
    }
}
