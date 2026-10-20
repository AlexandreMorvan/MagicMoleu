<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nom et texte imprimés d'une carte dans une autre langue que l'anglais.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_localization_card_lang', columns: ['oracle_card_id', 'lang'])]
#[ORM\Index(name: 'ft_localization_text', columns: ['printed_name', 'printed_text'], flags: ['fulltext'])]
class CardLocalization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'localizations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OracleCard $oracleCard;

    #[ORM\Column(length: 5)]
    private string $lang;

    #[ORM\Column(length: 255)]
    private string $printedName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $printedTypeLine = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $printedText = null;

    public function __construct(OracleCard $oracleCard, string $lang, string $printedName)
    {
        $this->oracleCard = $oracleCard;
        $this->lang = $lang;
        $this->printedName = $printedName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOracleCard(): OracleCard
    {
        return $this->oracleCard;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getPrintedName(): string
    {
        return $this->printedName;
    }

    public function getPrintedTypeLine(): ?string
    {
        return $this->printedTypeLine;
    }

    public function getPrintedText(): ?string
    {
        return $this->printedText;
    }
}
