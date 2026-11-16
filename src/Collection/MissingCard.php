<?php

namespace App\Collection;

use App\Entity\OracleCard;

final readonly class MissingCard
{
    public function __construct(
        public OracleCard $card,
        public int $quantity,
        /** Prix unitaire de l'impression du deck, en euros (sinon en dollars) */
        public ?float $unitPrice,
    ) {
    }
}
