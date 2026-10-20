<?php

namespace App\Repository;

use App\Entity\MtgSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MtgSet>
 */
class MtgSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MtgSet::class);
    }

    /** Types d'éditions « principales », par opposition aux promos, tokens, produits dérivés… */
    public const array MAIN_SET_TYPES = ['core', 'expansion', 'masters', 'draft_innovation', 'commander'];

    /**
     * @return list<MtgSet>
     */
    public function findLatestReleased(int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.setType IN (:types)')
            ->andWhere('s.releasedAt <= :today')
            ->andWhere('s.digital = false')
            ->setParameter('types', self::MAIN_SET_TYPES)
            ->setParameter('today', new \DateTimeImmutable('today'), 'date_immutable')
            ->orderBy('s.releasedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
