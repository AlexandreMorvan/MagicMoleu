<?php

namespace App\Entity;

use App\Enum\Color;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Face d'une carte multi-faces (transform, modal DFC, split, adventure, flip...).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_face_position', columns: ['oracle_card_id', 'position'])]
class CardFace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'faces')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OracleCard $oracleCard;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $manaCost = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typeLine = null;

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

    #[ORM\Column(type: Types::SMALLINT)]
    private int $colors = 0;

    public function __construct(OracleCard $oracleCard, int $position, string $name)
    {
        $this->oracleCard = $oracleCard;
        $this->position = $position;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOracleCard(): OracleCard
    {
        return $this->oracleCard;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getManaCost(): ?string
    {
        return $this->manaCost;
    }

    public function getTypeLine(): ?string
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

    /** @return list<Color> */
    public function getColorList(): array
    {
        return Color::fromMask($this->colors);
    }
}
