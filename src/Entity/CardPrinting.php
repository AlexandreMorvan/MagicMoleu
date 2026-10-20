<?php

namespace App\Entity;

use App\Enum\Rarity;
use App\Repository\CardPrintingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Une impression précise d'une carte : édition, numéro, langue, illustration, prix.
 */
#[ORM\Entity(repositoryClass: CardPrintingRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_printing_set_number_lang', columns: ['set_id', 'collector_number', 'lang'])]
#[ORM\Index(name: 'idx_printing_released_at', columns: ['released_at'])]
// Couvre le filtre de rareté de la recherche (EXISTS sur rarity + oracle_card_id) sans lire les lignes
#[ORM\Index(name: 'idx_printing_rarity_oracle', columns: ['rarity', 'oracle_card_id'])]
#[ORM\Index(name: 'idx_printing_revealed_at', columns: ['revealed_at'])]
class CardPrinting
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'printings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OracleCard $oracleCard;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private MtgSet $set;

    #[ORM\Column(length: 20)]
    private string $collectorNumber;

    #[ORM\Column(length: 5)]
    private string $lang;

    #[ORM\Column(length: 10, enumType: Rarity::class)]
    private Rarity $rarity;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $flavorText = null;

    /**
     * Pour une carte simple : {small, normal, large, png, art_crop, border_crop}.
     * Pour une carte recto-verso : une liste de ces objets, un par face.
     *
     * @var array<string, string>|list<array<string, string>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $imageUris = [];

    /** @var array<string, ?string> */
    #[ORM\Column(type: Types::JSON)]
    private array $prices = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $finishes = [];

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $releasedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $promo = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $fullArt = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $digital = false;

    #[ORM\Column(length: 10)]
    private string $frame = '';

    #[ORM\Column(length: 20)]
    private string $borderColor = '';

    #[ORM\Column(length: 255)]
    private string $scryfallUri = '';

    /**
     * Date de révélation : celle de l'aperçu officiel si Scryfall la connaît,
     * sinon le jour où l'import a vu la carte pour la première fois.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $revealedAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $previewedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $previewSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewSourceUri = null;

    public function __construct(Uuid $id, OracleCard $oracleCard, MtgSet $set, string $collectorNumber, string $lang, Rarity $rarity, \DateTimeImmutable $releasedAt)
    {
        $this->id = $id;
        $this->oracleCard = $oracleCard;
        $this->set = $set;
        $this->collectorNumber = $collectorNumber;
        $this->lang = $lang;
        $this->rarity = $rarity;
        $this->releasedAt = $releasedAt;
        $this->revealedAt = min($releasedAt, new \DateTimeImmutable('today'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOracleCard(): OracleCard
    {
        return $this->oracleCard;
    }

    public function getSet(): MtgSet
    {
        return $this->set;
    }

    public function getCollectorNumber(): string
    {
        return $this->collectorNumber;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getRarity(): Rarity
    {
        return $this->rarity;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function getFlavorText(): ?string
    {
        return $this->flavorText;
    }

    /** @return array<string, string>|list<array<string, string>> */
    public function getImageUris(): array
    {
        return $this->imageUris;
    }

    /**
     * URI d'image pour une face donnée (0 = recto), quel que soit le layout.
     */
    public function getImageUri(string $size = 'normal', int $face = 0): ?string
    {
        if (array_is_list($this->imageUris)) {
            return $this->imageUris[$face][$size] ?? null;
        }

        return 0 === $face ? ($this->imageUris[$size] ?? null) : null;
    }

    /**
     * Images de chaque face imprimée (une seule pour une carte simple, deux pour une carte recto-verso).
     *
     * @return list<array<string, string>>
     */
    public function getFaceImages(): array
    {
        $images = array_is_list($this->imageUris) ? $this->imageUris : [$this->imageUris];

        return array_values(array_filter($images, static fn (array $uris) => isset($uris['normal'])));
    }

    /** @return array<string, ?string> */
    public function getPrices(): array
    {
        return $this->prices;
    }

    /** @return list<string> */
    public function getFinishes(): array
    {
        return $this->finishes;
    }

    public function getReleasedAt(): \DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function isPromo(): bool
    {
        return $this->promo;
    }

    public function isFullArt(): bool
    {
        return $this->fullArt;
    }

    public function isDigital(): bool
    {
        return $this->digital;
    }

    public function getFrame(): string
    {
        return $this->frame;
    }

    public function getBorderColor(): string
    {
        return $this->borderColor;
    }

    public function getScryfallUri(): string
    {
        return $this->scryfallUri;
    }

    public function getRevealedAt(): \DateTimeImmutable
    {
        return $this->revealedAt;
    }

    public function getPreviewedAt(): ?\DateTimeImmutable
    {
        return $this->previewedAt;
    }

    public function getPreviewSource(): ?string
    {
        return $this->previewSource;
    }

    public function getPreviewSourceUri(): ?string
    {
        return $this->previewSourceUri;
    }
}
