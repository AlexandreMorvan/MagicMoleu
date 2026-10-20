<?php

namespace App\Twig\Components;

use App\Entity\OracleCard;
use App\Repository\OracleCardRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Recherche instantanée par nom (anglais ou français) sur la page d'accueil.
 */
#[AsLiveComponent]
class QuickSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(private readonly OracleCardRepository $cards)
    {
    }

    /**
     * @return list<OracleCard>
     */
    public function getResults(): array
    {
        $query = trim($this->query);

        return mb_strlen($query) >= 2 ? $this->cards->searchByName($query) : [];
    }
}
