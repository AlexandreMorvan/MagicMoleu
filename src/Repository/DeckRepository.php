<?php

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Deck>
 */
class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * Deck avec ses cartes, leurs données oracle et impressions, en une requête.
     */
    public function findWithCards(int $id): ?Deck
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.cards', 'dc')->addSelect('dc')
            ->leftJoin('dc.oracleCard', 'o')->addSelect('o')
            ->leftJoin('o.faces', 'f')->addSelect('f')
            ->leftJoin('dc.printing', 'p')->addSelect('p')
            ->where('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Deck>
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['updatedAt' => 'DESC']);
    }

    /**
     * @return list<Deck>
     */
    public function findLatestPublic(int $limit): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.owner', 'u')->addSelect('u')
            ->where('d.public = true')
            ->orderBy('d.updatedAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
