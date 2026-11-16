<?php

namespace App\Entity;

use App\Enum\DeckZone;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne d'un deck préconstruit : une impression précise, en un ou plusieurs exemplaires.
 */
#[ORM\Entity]
class PreconDeckCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'cards')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PreconDeck $deck,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private OracleCard $oracleCard,

        #[ORM\Column(length: 10, enumType: DeckZone::class)]
        private DeckZone $zone,

        #[ORM\Column]
        private int $quantity = 1,

        /** Impression du deck ; null si MTGJSON référence une impression absente de notre base */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?CardPrinting $printing = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeck(): PreconDeck
    {
        return $this->deck;
    }

    public function getOracleCard(): OracleCard
    {
        return $this->oracleCard;
    }

    public function getZone(): DeckZone
    {
        return $this->zone;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function addQuantity(int $quantity): void
    {
        $this->quantity += $quantity;
    }

    public function getPrinting(): ?CardPrinting
    {
        return $this->printing;
    }

    public function getImageUri(): ?string
    {
        return $this->printing?->getImageUri() ?? $this->oracleCard->getImageUri();
    }
}
