<?php

namespace App\Combo;

use App\Entity\OracleCard;
use App\Repository\OracleCardRepository;

/**
 * Retrouve dans notre base les cartes des combos, pour ouvrir leur fiche et afficher leur nom français.
 */
class ComboCardLinker
{
    public function __construct(private readonly OracleCardRepository $cards)
    {
    }

    /**
     * @param iterable<Combo> $combos
     *
     * @return array<string, OracleCard> identifiant oracle (RFC 4122) => carte
     */
    public function link(iterable $combos): array
    {
        $ids = [];
        foreach ($combos as $combo) {
            foreach ($combo->cards as $card) {
                if ($card->oracleId) {
                    $ids[$card->oracleId->toRfc4122()] = $card->oracleId;
                }
            }
        }

        $cards = [];
        foreach ($ids ? $this->cards->findBy(['id' => array_values($ids)]) : [] as $card) {
            $cards[$card->getId()->toRfc4122()] = $card;
        }

        return $cards;
    }
}
