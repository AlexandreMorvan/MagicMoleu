<?php

namespace App\Precon;

use App\Deck\DeckBuilder;
use App\Entity\Deck;
use App\Entity\PreconDeck;
use App\Entity\User;
use App\Enum\Format;

/**
 * Crée pour un utilisateur un deck modifiable à partir d'un deck préconstruit.
 */
class PreconDeckCopier
{
    public function __construct(private readonly DeckBuilder $builder)
    {
    }

    public function copy(PreconDeck $precon, User $owner): Deck
    {
        $deck = (new Deck($owner))
            ->setName(mb_substr($precon->getName(), 0, 100))
            ->setFormat(Format::Commander)
            ->setBasedOn($precon);

        foreach ($precon->getCards() as $card) {
            $this->builder->add($deck, $card->getOracleCard(), $card->getZone(), $card->getQuantity(), $card->getPrinting());
        }

        return $deck;
    }
}
