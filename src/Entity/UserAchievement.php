<?php

namespace App\Entity;

use App\Enum\Achievement;
use App\Repository\UserAchievementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_achievement', columns: ['user_id', 'achievement'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $unlockedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(length: 30, enumType: Achievement::class)]
        private Achievement $achievement,
    ) {
        $this->unlockedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAchievement(): Achievement
    {
        return $this->achievement;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }

    public function __toString(): string
    {
        return $this->achievement->label();
    }
}
