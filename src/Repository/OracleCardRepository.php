<?php

namespace App\Repository;

use App\Entity\OracleCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<OracleCard>
 */
class OracleCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OracleCard::class);
    }

    /** Layouts qui ne sont pas des cartes de jeu (jetons, emblèmes…), exclus des recherches par défaut. */
    public const array NON_GAME_LAYOUTS = ['token', 'double_faced_token', 'emblem', 'art_series', 'vanguard', 'planar', 'scheme'];

    /**
     * Recherche rapide sur le nom anglais ou français : correspondance exacte, puis début de nom, puis le reste.
     *
     * @return list<OracleCard>
     */
    public function searchByName(string $query, int $limit = 8): array
    {
        $escaped = addcslashes($query, '%_\\');

        return $this->createQueryBuilder('o')
            ->leftJoin('o.localizations', 'l', 'WITH', 'l.lang = :lang')
            ->addSelect('l')
            ->addSelect('CASE WHEN o.name = :exact OR l.printedName = :exact THEN 0 WHEN o.name LIKE :prefix OR l.printedName LIKE :prefix THEN 1 ELSE 2 END AS HIDDEN relevance')
            ->andWhere('o.name LIKE :contains OR l.printedName LIKE :contains')
            ->andWhere('o.layout NOT IN (:excluded)')
            ->setParameter('lang', 'fr')
            ->setParameter('exact', $query)
            ->setParameter('prefix', $escaped.'%')
            ->setParameter('contains', '%'.$escaped.'%')
            ->setParameter('excluded', self::NON_GAME_LAYOUTS)
            ->orderBy('relevance', SortDirection::Ascending)
            ->addOrderBy('o.name', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrouve une carte d'après le nom écrit dans une liste de deck : nom complet (« Fire // Ice »),
     * face principale (« Delver of Secrets ») ou nom français.
     */
    public function findOneByDeckListName(string $name): ?OracleCard
    {
        $escaped = addcslashes($name, '%_\\');

        return $this->createQueryBuilder('o')
            ->leftJoin('o.localizations', 'l', 'WITH', 'l.lang = :lang')
            ->addSelect('CASE WHEN o.name = :name THEN 0 WHEN o.name LIKE :front THEN 1 ELSE 2 END AS HIDDEN relevance')
            ->where('o.name = :name OR o.name LIKE :front OR l.printedName = :name')
            ->andWhere('o.layout NOT IN (:excluded)')
            ->setParameter('lang', 'fr')
            ->setParameter('name', $name)
            ->setParameter('front', $escaped.' // %')
            ->setParameter('excluded', self::NON_GAME_LAYOUTS)
            ->orderBy('relevance', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Planeswalkers proposés comme avatar (avec une illustration), filtrés par nom (anglais ou français) et couleur.
     *
     * @return list<OracleCard>
     */
    public function findAvatarCandidates(string $query = '', int $colorMask = 0, int $limit = 48): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.localizations', 'l', 'WITH', 'l.lang = :lang')
            ->where('o.typeLine LIKE :type')
            ->andWhere('o.artCropUri IS NOT NULL')
            ->andWhere('o.layout NOT IN (:excluded)')
            ->setParameter('lang', 'fr')
            ->setParameter('type', '%Planeswalker%')
            ->setParameter('excluded', self::NON_GAME_LAYOUTS)
            ->orderBy('o.name', SortDirection::Ascending)
            ->setMaxResults($limit);

        if ('' !== $query = trim($query)) {
            $qb->andWhere('o.name LIKE :name OR l.printedName LIKE :name')->setParameter('name', '%'.addcslashes($query, '%_\\').'%');
        }
        if ($colorMask) {
            $qb->andWhere('BIT_AND(o.colors, :mask) != 0')->setParameter('mask', $colorMask);
        }

        return $qb->getQuery()->getResult();
    }
}
