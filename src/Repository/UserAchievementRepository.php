<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserAchievement;
use App\Enum\Achievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * @return array<string, UserAchievement> indexés par code de succès
     */
    public function findUnlockedBy(User $user): array
    {
        $unlocked = [];
        foreach ($this->findBy(['user' => $user]) as $userAchievement) {
            $unlocked[$userAchievement->getAchievement()->value] = $userAchievement;
        }

        return $unlocked;
    }

    public function hasUnlocked(User $user, Achievement $achievement): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'achievement' => $achievement]);
    }
}
