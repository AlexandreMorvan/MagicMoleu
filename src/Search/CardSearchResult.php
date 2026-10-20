<?php

namespace App\Search;

use App\Entity\OracleCard;

final readonly class CardSearchResult
{
    /**
     * @param list<OracleCard> $cards
     */
    public function __construct(
        public array $cards,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
