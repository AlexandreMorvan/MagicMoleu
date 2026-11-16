<?php

namespace App\Entity;

use App\Enum\DeckZone;
use App\Repository\PreconDeckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un deck préconstruit du commerce (catalogue importé depuis MTGJSON, voir {@see \App\Precon\PreconImporter}).
 */
#[ORM\Entity(repositoryClass: PreconDeckRepository::class)]
#[ORM\Index(name: 'idx_precon_released_at', columns: ['released_at'])]
class PreconDeck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom du fichier MTGJSON (ex. « SneakAttack_ZNC »), identifiant stable du deck */
    #[ORM\Column(length: 150, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $name = '';

    /** Code de l'édition chez MTGJSON, en minuscules comme chez Scryfall */
    #[ORM\Column(length: 10)]
    private string $setCode = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MtgSet $set = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $releasedAt;

    /** Nom du ou des commandants (« A & B »), affiché et utilisé par la recherche */
    #[ORM\Column(length: 255)]
    private string $commanderNames = '';

    /** Illustration (art crop) du premier commandant */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverUri = null;

    /** Cartes de la liste MTGJSON introuvables dans notre base ; le deck est réimporté tant qu'il en reste */
    #[ORM\Column]
    private int $unresolvedCount = 0;

    /** @var Collection<int, PreconDeckCard> */
    #[ORM\OneToMany(targetEntity: PreconDeckCard::class, mappedBy: 'deck', cascade: ['persist'], orphanRemoval: true)]
    private Collection $cards;

    public function __construct(string $code)
    {
        $this->code = $code;
        $this->releasedAt = new \DateTimeImmutable('today');
        $this->cards = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
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

    public function getSetCode(): string
    {
        return $this->setCode;
    }

    public function setSetCode(string $setCode): static
    {
        $this->setCode = $setCode;

        return $this;
    }

    public function getSet(): ?MtgSet
    {
        return $this->set;
    }

    public function setSet(?MtgSet $set): static
    {
        $this->set = $set;

        return $this;
    }

    public function getReleasedAt(): \DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(\DateTimeImmutable $releasedAt): static
    {
        $this->releasedAt = $releasedAt;

        return $this;
    }

    public function getCommanderNames(): string
    {
        return $this->commanderNames;
    }

    public function getCoverUri(): ?string
    {
        return $this->coverUri;
    }

    public function getUnresolvedCount(): int
    {
        return $this->unresolvedCount;
    }

    public function setUnresolvedCount(int $unresolvedCount): static
    {
        $this->unresolvedCount = $unresolvedCount;

        return $this;
    }

    /** @return Collection<int, PreconDeckCard> */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    /** @return list<PreconDeckCard> */
    public function getCardsIn(DeckZone $zone): array
    {
        return array_values($this->cards->filter(static fn (PreconDeckCard $c) => $c->getZone() === $zone)->toArray());
    }

    public function getCardCount(): int
    {
        return array_sum($this->cards->map(static fn (PreconDeckCard $c) => $c->getQuantity())->toArray());
    }

    /**
     * Remplace la liste de cartes, et recalcule les commandants et l'illustration.
     *
     * @param list<PreconDeckCard> $cards
     */
    public function replaceCards(array $cards): void
    {
        $this->cards->clear();
        foreach ($cards as $card) {
            $this->cards->add($card);
        }

        $commanders = $this->getCardsIn(DeckZone::Commander);
        $this->commanderNames = mb_substr(implode(' & ', array_map(static fn (PreconDeckCard $c) => $c->getOracleCard()->getName(), $commanders)), 0, 255);
        $cover = $commanders[0] ?? null;
        $this->coverUri = $cover?->getPrinting()?->getImageUri('art_crop') ?? $cover?->getOracleCard()->getArtCropUri();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
