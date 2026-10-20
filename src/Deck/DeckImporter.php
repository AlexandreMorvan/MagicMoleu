<?php

namespace App\Deck;

use App\Entity\Deck;
use App\Repository\CardPrintingRepository;
use App\Repository\OracleCardRepository;

/**
 * Ajoute à un deck les cartes d'une liste texte.
 */
class DeckImporter
{
    public function __construct(
        private readonly DeckListParser $parser,
        private readonly DeckBuilder $builder,
        private readonly OracleCardRepository $cards,
        private readonly CardPrintingRepository $printings,
    ) {
    }

    /**
     * @return list<string> lignes dont la carte est introuvable
     */
    public function import(Deck $deck, string $list): array
    {
        $unresolved = [];
        foreach ($this->parser->parse($list) as $line) {
            $card = $this->cards->findOneByDeckListName($line->name);
            if (null === $card) {
                $unresolved[] = $line->raw;
                continue;
            }

            $printing = $line->setCode && $line->collectorNumber
                ? $this->printings->findOneBySetAndNumber($line->setCode, $line->collectorNumber)
                : null;
            if ($printing && $printing->getOracleCard() !== $card) {
                $printing = null;
            }

            $this->builder->add($deck, $card, $line->zone, $line->quantity, $printing);
        }

        return $unresolved;
    }
}
