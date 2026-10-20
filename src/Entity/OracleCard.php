<?php

namespace App\Entity;

use App\Enum\CardCategory;
use App\Enum\Color;
use App\Repository\OracleCardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Une carte « oracle » : la carte en tant que règle du jeu, indépendamment de ses impressions.
 * Alimentée par l'import Scryfall (DBAL), d'où l'absence de setters.
 */
#[ORM\Entity(repositoryClass: OracleCardRepository::class)]
#[ORM\Index(name: 'idx_oracle_name', columns: ['name'])]
#[ORM\Index(name: 'idx_oracle_cmc', columns: ['cmc'])]
#[ORM\Index(name: 'idx_oracle_colors', columns: ['colors'])]
#[ORM\Index(name: 'idx_oracle_color_identity', columns: ['color_identity'])]
#[ORM\Index(name: 'ft_oracle_text', columns: ['name', 'type_line', 'oracle_text'], flags: ['fulltext'])]
class OracleCard
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 30)]
    private string $layout;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $manaCost = null;

    #[ORM\Column]
    private float $cmc = 0;

    #[ORM\Column(length: 255)]
    private string $typeLine = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $oracleText = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $power = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $toughness = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $loyalty = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $defense = null;

    /** Bitmask WUBRG, voir {@see Color}. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $colors = 0;

    /** Bitmask WUBRG, voir {@see Color}. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $colorIdentity = 0;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $keywords = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $producedMana = [];

    #[ORM\Column(nullable: true)]
    private ?int $edhrecRank = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $reserved = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $gameChanger = false;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstPrintedAt = null;

    /** Image « normal » de l'impression papier la plus récente, pour l'affichage en liste. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUri = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artCropUri = null;

    /**
     * Impression d'où vient l'image (utilisée par défaut dans les decks). Simple identifiant, sans clé étrangère :
     * l'import écrit les cartes oracle avant leurs impressions.
     */
    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $defaultPrintingId = null;

    /** Rang de l'impression dont vient l'image, calculé par {@see \App\Scryfall\CardMapper}. */
    #[ORM\Column(nullable: true)]
    private ?int $imageRank = null;

    /** @var Collection<int, CardFace> */
    #[ORM\OneToMany(targetEntity: CardFace::class, mappedBy: 'oracleCard')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $faces;

    /** @var Collection<int, CardPrinting> */
    #[ORM\OneToMany(targetEntity: CardPrinting::class, mappedBy: 'oracleCard')]
    #[ORM\OrderBy(['releasedAt' => 'DESC'])]
    private Collection $printings;

    /** @var Collection<int, CardLegality> */
    #[ORM\OneToMany(targetEntity: CardLegality::class, mappedBy: 'oracleCard')]
    private Collection $legalities;

    /** @var Collection<int, CardLocalization> */
    #[ORM\OneToMany(targetEntity: CardLocalization::class, mappedBy: 'oracleCard')]
    private Collection $localizations;

    public function __construct(Uuid $id, string $name, string $layout)
    {
        $this->id = $id;
        $this->name = $name;
        $this->layout = $layout;
        $this->faces = new ArrayCollection();
        $this->printings = new ArrayCollection();
        $this->legalities = new ArrayCollection();
        $this->localizations = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getManaCost(): ?string
    {
        return $this->manaCost;
    }

    public function getCmc(): float
    {
        return $this->cmc;
    }

    public function getTypeLine(): string
    {
        return $this->typeLine;
    }

    public function getOracleText(): ?string
    {
        return $this->oracleText;
    }

    public function getPower(): ?string
    {
        return $this->power;
    }

    public function getToughness(): ?string
    {
        return $this->toughness;
    }

    public function getLoyalty(): ?string
    {
        return $this->loyalty;
    }

    public function getDefense(): ?string
    {
        return $this->defense;
    }

    public function getColors(): int
    {
        return $this->colors;
    }

    /** @return list<Color> */
    public function getColorList(): array
    {
        return Color::fromMask($this->colors);
    }

    public function getColorIdentity(): int
    {
        return $this->colorIdentity;
    }

    /** @return list<Color> */
    public function getColorIdentityList(): array
    {
        return Color::fromMask($this->colorIdentity);
    }

    /** @return list<string> */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /** @return list<string> */
    public function getProducedMana(): array
    {
        return $this->producedMana;
    }

    public function getEdhrecRank(): ?int
    {
        return $this->edhrecRank;
    }

    public function isReserved(): bool
    {
        return $this->reserved;
    }

    public function isGameChanger(): bool
    {
        return $this->gameChanger;
    }

    public function getFirstPrintedAt(): ?\DateTimeImmutable
    {
        return $this->firstPrintedAt;
    }

    public function getImageUri(): ?string
    {
        return $this->imageUri;
    }

    public function getDefaultPrintingId(): ?Uuid
    {
        return $this->defaultPrintingId;
    }

    public function getArtCropUri(): ?string
    {
        return $this->artCropUri;
    }

    /** @return Collection<int, CardFace> */
    public function getFaces(): Collection
    {
        return $this->faces;
    }

    /** @return Collection<int, CardPrinting> */
    public function getPrintings(): Collection
    {
        return $this->printings;
    }

    /** @return Collection<int, CardLegality> */
    public function getLegalities(): Collection
    {
        return $this->legalities;
    }

    /** @return Collection<int, CardLocalization> */
    public function getLocalizations(): Collection
    {
        return $this->localizations;
    }

    /**
     * Ligne de type de la face principale (« Creature — Human Wizard » pour Delver of Secrets).
     */
    public function getFrontTypeLine(): string
    {
        return explode(' // ', $this->typeLine)[0];
    }

    public function getCategory(): CardCategory
    {
        return CardCategory::fromTypeLine($this->getFrontTypeLine());
    }

    public function isLand(): bool
    {
        return CardCategory::Land === $this->getCategory();
    }

    public function isBasicLand(): bool
    {
        $type = $this->getFrontTypeLine();

        return str_contains($type, 'Basic') && str_contains($type, 'Land');
    }

    /**
     * Nombre maximal d'exemplaires dans un deck : celui du format, sauf si la carte en décide autrement.
     * Null = illimité (terrains de base, « A deck can have any number of cards named… ») ;
     * « A deck can have up to seven cards named… » donne 7.
     */
    public function getCopyLimit(int $formatLimit): ?int
    {
        if ($this->isBasicLand() || str_contains($this->oracleText ?? '', 'A deck can have any number of cards named')) {
            return null;
        }
        if (preg_match('/A deck can have up to (\w+) cards named/', $this->oracleText ?? '', $m)) {
            $words = ['two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];

            return $words[strtolower($m[1])] ?? (int) $m[1];
        }

        return $formatLimit;
    }

    public function canBeCommander(): bool
    {
        $type = $this->getFrontTypeLine();

        return (str_contains($type, 'Legendary') && str_contains($type, 'Creature'))
            || str_contains($this->oracleText ?? '', 'can be your commander');
    }

    public function hasKeyword(string $keyword): bool
    {
        return \in_array(mb_strtolower($keyword), array_map(mb_strtolower(...), $this->keywords), true);
    }

    /**
     * Nom utilisé dans les listes de decks : la face principale pour une carte recto-verso,
     * le nom complet pour une carte split (« Fire // Ice »).
     */
    public function getDeckListName(): string
    {
        return 'split' === $this->layout ? $this->name : explode(' // ', $this->name)[0];
    }

    public function getLocalization(string $lang): ?CardLocalization
    {
        return $this->localizations->findFirst(static fn (int $k, CardLocalization $l) => $l->getLang() === $lang);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
