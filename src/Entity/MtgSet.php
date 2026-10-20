<?php

namespace App\Entity;

use App\Repository\MtgSetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MtgSetRepository::class)]
#[ORM\Index(name: 'idx_set_released_at', columns: ['released_at'])]
class MtgSet
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,

        #[ORM\Column(length: 10, unique: true)]
        private string $code,

        #[ORM\Column(length: 255)]
        private string $name,

        #[ORM\Column(length: 30)]
        private string $setType,

        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $releasedAt = null,

        #[ORM\Column(options: ['default' => 0])]
        private int $cardCount = 0,

        #[ORM\Column(length: 255, nullable: true)]
        private ?string $iconSvgUri = null,

        #[ORM\Column(length: 10, nullable: true)]
        private ?string $parentSetCode = null,

        #[ORM\Column(options: ['default' => false])]
        private bool $digital = false,

        /** Illustration de la carte emblématique de l'édition, calculée par {@see \App\Scryfall\SetCoverUpdater}. */
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $coverArtUri = null,

        #[ORM\Column(length: 255, nullable: true)]
        private ?string $coverCardName = null,
    ) {
    }

    public function getId(): Uuid
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

    public function getSetType(): string
    {
        return $this->setType;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function getCardCount(): int
    {
        return $this->cardCount;
    }

    public function getIconSvgUri(): ?string
    {
        return $this->iconSvgUri;
    }

    public function getParentSetCode(): ?string
    {
        return $this->parentSetCode;
    }

    public function isDigital(): bool
    {
        return $this->digital;
    }

    public function getCoverArtUri(): ?string
    {
        return $this->coverArtUri;
    }

    public function getCoverCardName(): ?string
    {
        return $this->coverCardName;
    }
}
