<?php

namespace App\Entity;

use App\Enum\Format;
use App\Enum\LegalityStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_legality_format_status', columns: ['format', 'status'])]
class CardLegality
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(inversedBy: 'legalities')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private OracleCard $oracleCard,

        #[ORM\Id]
        #[ORM\Column(length: 20, enumType: Format::class)]
        private Format $format,

        #[ORM\Column(length: 12, enumType: LegalityStatus::class)]
        private LegalityStatus $status,
    ) {
    }

    public function getOracleCard(): OracleCard
    {
        return $this->oracleCard;
    }

    public function getFormat(): Format
    {
        return $this->format;
    }

    public function getStatus(): LegalityStatus
    {
        return $this->status;
    }
}
