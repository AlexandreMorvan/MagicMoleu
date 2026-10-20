<?php

namespace App\Entity;

use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\DeckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeckRepository::class)]
#[ORM\Index(name: 'idx_deck_public_updated', columns: ['public', 'updated_at'])]
#[ORM\HasLifecycleCallbacks]
class Deck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donne un nom à ton deck.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: Format::class)]
    private Format $format = Format::Standard;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $public = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, DeckCard> */
    #[ORM\OneToMany(targetEntity: DeckCard::class, mappedBy: 'deck', cascade: ['persist'], orphanRemoval: true)]
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

    public function getFormat(): Format
    {
        return $this->format;
    }

    public function setFormat(Format $format): static
    {
        $this->format = $format;

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

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;

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

    /** @return Collection<int, DeckCard> */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    /**
     * Zones utilisées par le format du deck, dans l'ordre d'affichage.
     *
     * @return list<DeckZone>
     */
    public function getZones(): array
    {
        return DeckZone::forFormat($this->format);
    }

    /**
     * Carte servant d'illustration au deck : le commandant, sinon la première carte.
     */
    public function getCoverCard(): ?OracleCard
    {
        $first = $this->getCardsIn(DeckZone::Commander)[0] ?? $this->getCardsIn(DeckZone::Main)[0] ?? $this->cards->first() ?: null;

        return $first?->getOracleCard();
    }

    /** @return list<DeckCard> */
    public function getCardsIn(DeckZone ...$zones): array
    {
        return array_values($this->cards->filter(static fn (DeckCard $c) => \in_array($c->getZone(), $zones, true))->toArray());
    }

    /**
     * Nombre de cartes jouées (commandant, deck principal et réserve).
     */
    public function getCardCount(): int
    {
        return $this->countIn(DeckZone::Commander, DeckZone::Main, DeckZone::Side);
    }

    public function countIn(DeckZone ...$zones): int
    {
        return array_sum(array_map(static fn (DeckCard $c) => $c->getQuantity(), $this->getCardsIn(...$zones)));
    }

    public function findCard(OracleCard $card, DeckZone $zone): ?DeckCard
    {
        return $this->cards->findFirst(static fn (int $k, DeckCard $c) => $c->getOracleCard() === $card && $c->getZone() === $zone);
    }

    /**
     * Ajoute des exemplaires d'une carte dans une zone (en cumulant avec une ligne existante).
     */
    public function addCard(OracleCard $card, DeckZone $zone, int $quantity = 1, ?CardPrinting $printing = null): DeckCard
    {
        $deckCard = $this->findCard($card, $zone);
        if ($deckCard) {
            $deckCard->setQuantity($deckCard->getQuantity() + $quantity);
        } else {
            $deckCard = new DeckCard($this, $card, $zone, $quantity, $printing);
            $this->cards->add($deckCard);
        }
        $this->touch();

        return $deckCard;
    }

    public function removeCard(DeckCard $deckCard): void
    {
        $this->cards->removeElement($deckCard);
        $this->touch();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
