<?php

namespace App\Repository;

use App\Entity\CardPrinting;
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
     * Éditions contenant des cartes, de la plus récente à la plus ancienne (pour l'autocomplétion).
     *
     * @return list<array{code: string, name: string}>
     */
    public function findForChoices(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.code', 's.name')
            ->where('s.cardCount > 0')
            ->orderBy('s.releasedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

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

    /**
     * Éditions ayant au moins une carte en base, avec leur nombre de cartes, de la plus récente à la plus ancienne.
     *
     * @return list<array{set: MtgSet, cards: int}>
     */
    public function findWithCardCounts(bool $mainOnly): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s AS set', 'COUNT(p.id) AS cards')
            ->join(CardPrinting::class, 'p', 'WITH', 'p.set = s')
            ->groupBy('s.id')
            ->orderBy('s.releasedAt', 'DESC')
            ->addOrderBy('s.name', 'ASC');

        if ($mainOnly) {
            $qb->where('s.setType IN (:types)')->setParameter('types', self::MAIN_SET_TYPES);
        }

        return array_map(static fn (array $row) => ['set' => $row['set'], 'cards' => (int) $row['cards']], $qb->getQuery()->getResult());
    }
}
