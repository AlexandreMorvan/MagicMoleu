<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findLatestFor(User $user, int $limit): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function countUnreadFor(User $user): int
    {
        return $this->count(['user' => $user, 'readAt' => null]);
    }

    /**
     * Notifications pas encore affichées en toast.
     *
     * @return list<Notification>
     */
    public function findToToast(User $user): array
    {
        return $this->findBy(['user' => $user, 'toastedAt' => null], ['createdAt' => 'ASC', 'id' => 'ASC'], 5);
    }

    public function markAllReadFor(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable(), 'datetime_immutable')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
