<?php

namespace App\Entity;

use App\Enum\CardCondition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exemplaires d'une impression dans une collection. Une ligne par finition (normale / foil, leur prix diffère),
 * langue et état : deux exemplaires ne se cumulent que s'ils sont identiques sur ces points.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_collection_card_copy', columns: ['collection_id', 'printing_id', 'foil', 'lang', 'card_condition'])]
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

        /** Langue de l'exemplaire (code Scryfall : en, fr, ja…) ; l'image et le prix restent ceux de l'impression. */
        #[ORM\Column(length: 5, options: ['default' => 'en'])]
        private string $lang = 'en',

        #[ORM\Column(name: 'card_condition', length: 15, enumType: CardCondition::class, options: ['default' => 'near_mint'])]
        private CardCondition $condition = CardCondition::NearMint,
    ) {
        $this->addedAt = new \DateTimeImmutable();
    }

    /** Prix d'achat unitaire, dans la devise {@see $purchaseCurrency}. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $purchaseCurrency = null;

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

    public function setAddedAt(\DateTimeImmutable $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getCondition(): CardCondition
    {
        return $this->condition;
    }

    public function getPurchasePrice(): ?float
    {
        return null !== $this->purchasePrice ? (float) $this->purchasePrice : null;
    }

    public function getPurchaseCurrency(): ?string
    {
        return $this->purchaseCurrency;
    }

    public function setPurchasePrice(?float $price, ?string $currency): static
    {
        $this->purchasePrice = null !== $price ? number_format($price, 2, '.', '') : null;
        $this->purchaseCurrency = null !== $price ? (strtoupper((string) $currency) ?: 'EUR') : null;

        return $this;
    }

    /**
     * Enregistre le prix d'achat de $added exemplaires qui viennent d'être ajoutés à la ligne :
     * avec des exemplaires déjà achetés dans la même devise, le prix devient la moyenne pondérée.
     */
    public function recordPurchase(int $added, ?float $price, ?string $currency): void
    {
        if (null === $price) {
            return;
        }

        $currency = strtoupper((string) $currency) ?: 'EUR';
        $previous = $this->getPurchasePrice();
        $previousQuantity = $this->quantity - $added;
        if (null !== $previous && $previousQuantity > 0 && $this->purchaseCurrency === $currency) {
            $price = ($previous * $previousQuantity + $price * $added) / $this->quantity;
        }

        $this->setPurchasePrice($price, $currency);
    }

    /**
     * Prix d'achat total en euros, null s'il est inconnu ou dans une autre devise.
     */
    public function getTotalPurchasePriceEur(): ?float
    {
        $price = $this->getPurchasePrice();

        return null !== $price && 'EUR' === $this->purchaseCurrency ? $price * $this->quantity : null;
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
