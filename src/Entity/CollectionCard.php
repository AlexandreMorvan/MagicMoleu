<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Exemplaires d'une impression dans une collection ; les foils ont leur propre ligne (leur prix diffère).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_collection_printing_foil', columns: ['collection_id', 'printing_id', 'foil'])]
class CollectionCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'cards')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CardCollection $collection,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CardPrinting $printing,

        #[ORM\Column]
        private int $quantity = 1,

        #[ORM\Column(options: ['default' => false])]
        private bool $foil = false,
    ) {
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollection(): CardCollection
    {
        return $this->collection;
    }

    public function getPrinting(): CardPrinting
    {
        return $this->printing;
    }

    public function getOracleCard(): OracleCard
    {
        return $this->printing->getOracleCard();
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

    public function isFoil(): bool
    {
        return $this->foil;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    /**
     * Prix unitaire en euros (à défaut en dollars), foil ou non selon l'exemplaire.
     */
    public function getUnitPrice(): ?float
    {
        $prices = $this->printing->getPrices();
        $price = $this->foil
            ? ($prices['eur_foil'] ?? $prices['usd_foil'] ?? null)
            : ($prices['eur'] ?? $prices['usd'] ?? null);

        return null !== $price ? (float) $price : null;
    }

    public function getTotalPrice(): float
    {
        return ($this->getUnitPrice() ?? 0) * $this->quantity;
    }
}
