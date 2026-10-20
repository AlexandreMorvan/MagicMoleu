<?php

namespace App\Deck;

use App\Entity\Deck;
use App\Enum\DeckZone;

/**
 * Exporte un deck en liste texte, relisible par {@see DeckListParser} et par MTG Arena / Moxfield.
 */
class DeckExporter
{
    private const array SECTIONS = [
        'Commander' => DeckZone::Commander,
        'Deck' => DeckZone::Main,
        'Sideboard' => DeckZone::Side,
        'Maybeboard' => DeckZone::Maybe,
    ];

    public function export(Deck $deck): string
    {
        $blocks = [];
        foreach (self::SECTIONS as $title => $zone) {
            $cards = $deck->getCardsIn($zone);
            if (!$cards) {
                continue;
            }
            usort($cards, static fn ($a, $b) => strcmp($a->getOracleCard()->getName(), $b->getOracleCard()->getName()));

            $lines = [$title];
            foreach ($cards as $deckCard) {
                $line = $deckCard->getQuantity().' '.$deckCard->getOracleCard()->getDeckListName();
                if ($printing = $deckCard->getPrinting()) {
                    $line .= \sprintf(' (%s) %s', strtoupper($printing->getSet()->getCode()), $printing->getCollectorNumber());
                }
                $lines[] = $line;
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks)."\n";
    }
}
