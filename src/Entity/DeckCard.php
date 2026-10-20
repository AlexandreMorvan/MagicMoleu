<?php

namespace App\Entity;

use App\Enum\DeckZone;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_deck_card_zone', columns: ['deck_id', 'oracle_card_id', 'zone'])]
class DeckCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'cards')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Deck $deck,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private OracleCard $oracleCard,

        #[ORM\Column(length: 10, enumType: DeckZone::class)]
        private DeckZone $zone,

        #[ORM\Column]
        private int $quantity = 1,

        /** Impression choisie (image, prix) ; null = impression par défaut de la carte. */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?CardPrinting $printing = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeck(): Deck
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

    public function setZone(DeckZone $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);

        return $this;
    }

    public function getPrinting(): ?CardPrinting
    {
        return $this->printing;
    }

    public function getImageUri(): ?string
    {
        return $this->printing?->getImageUri() ?? $this->oracleCard->getImageUri();
    }

    public function getUnitPrice(): ?float
    {
        $prices = $this->printing?->getPrices() ?? [];
        $price = $prices['eur'] ?? $prices['usd'] ?? null;

        return null !== $price ? (float) $price : null;
    }
}
