<?php

namespace App\Entity;

use App\Repository\CardCollectionRepository;
use App\Enum\CardCondition;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Collection de cartes d'un utilisateur (classeur, boîte à échanger…) : des impressions précises, normales ou foil.
 */
#[ORM\Entity(repositoryClass: CardCollectionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CardCollection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donne un nom à ta collection.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CollectionCard> */
    #[ORM\OneToMany(targetEntity: CollectionCard::class, mappedBy: 'collection', cascade: ['persist'], orphanRemoval: true)]
    private Collection $cards;

    public function __construct(User $owner)
    {
        $this->owner = $owner;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->cards = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, CollectionCard> */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function getCardCount(): int
    {
        return array_sum($this->cards->map(static fn (CollectionCard $c) => $c->getQuantity())->toArray());
    }

    public function findCard(CardPrinting $printing, bool $foil, string $lang = 'en', CardCondition $condition = CardCondition::NearMint): ?CollectionCard
    {
        return $this->cards->findFirst(static fn (int $k, CollectionCard $c) => $c->getPrinting() === $printing && $c->isFoil() === $foil
            && $c->getLang() === $lang && $c->getCondition() === $condition);
    }

    /**
     * Ajoute des exemplaires d'une impression (en cumulant avec une ligne identique : finition, langue et état).
     */
    public function addCard(CardPrinting $printing, int $quantity = 1, bool $foil = false, string $lang = 'en', CardCondition $condition = CardCondition::NearMint): CollectionCard
    {
        $collectionCard = $this->findCard($printing, $foil, $lang, $condition);
        if ($collectionCard) {
            $collectionCard->setQuantity($collectionCard->getQuantity() + $quantity);
        } else {
            $collectionCard = new CollectionCard($this, $printing, max(1, $quantity), $foil, $lang, $condition);
            $this->cards->add($collectionCard);
        }
        $this->touch();

        return $collectionCard;
    }

    /**
     * Retire toutes les cartes (import CSV en mode « remplacer »).
     */
    public function clearCards(): void
    {
        $this->cards->clear();
        $this->touch();
    }

    public function removeCard(CollectionCard $collectionCard): void
    {
        $this->cards->removeElement($collectionCard);
        $this->touch();
    }

    /**
     * Carte servant d'illustration : la plus chère de la collection.
     */
    public function getCoverCard(): ?OracleCard
    {
        $best = null;
        foreach ($this->cards as $card) {
            if (null === $best || ($card->getUnitPrice() ?? 0) > ($best->getUnitPrice() ?? 0)) {
                $best = $card;
            }
        }

        return $best?->getPrinting()->getOracleCard();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
