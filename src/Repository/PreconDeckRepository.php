<?php

namespace App\Repository;

use App\Entity\PreconDeck;
use App\Entity\PreconDeckCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreconDeck>
 */
class PreconDeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreconDeck::class);
    }

    /**
     * Decks du plus récent au plus ancien, filtrés par nom du deck, de l'édition ou du commandant, et par année de sortie.
     *
     * @return list<PreconDeck>
     */
    public function search(string $query, int $limit, int $offset = 0, ?int $year = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.set', 's')->addSelect('s')
            ->orderBy('d.releasedAt', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $query = trim($query);
        if ('' !== $query) {
            $qb->andWhere('d.name LIKE :q OR d.commanderNames LIKE :q OR s.name LIKE :q OR d.setCode = :code')
                ->setParameter('q', '%'.addcslashes($query, '%_\\').'%')
                ->setParameter('code', mb_strtolower($query));
        }

        if (null !== $year) {
            $qb->andWhere('d.releasedAt BETWEEN :from AND :to')
                ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
                ->setParameter('to', new \DateTimeImmutable($year.'-12-31'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Années de sortie des decks, de la plus récente à la plus ancienne.
     *
     * @return list<int>
     */
    public function findYears(): array
    {
        $dates = $this->createQueryBuilder('d')->select('DISTINCT d.releasedAt')->getQuery()->getSingleColumnResult();
        $years = array_values(array_unique(array_map(static fn (string $date) => (int) substr($date, 0, 4), $dates)));
        rsort($years);

        return $years;
    }

    /**
     * Lignes de plusieurs decks, sans hydrater les entités : pour calculer la part possédée de chaque vignette.
     *
     * @param list<int> $deckIds
     *
     * @return array<int, list<array{card: string, quantity: int, basic: bool}>>
     */
    public function findCardLines(array $deckIds): array
    {
        if (!$deckIds) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.deck) AS deck', 'o.id AS card', 'c.quantity', 'o.typeLine')
            ->from(PreconDeckCard::class, 'c')
            ->join('c.oracleCard', 'o')
            ->where('c.deck IN (:ids)')
            ->setParameter('ids', $deckIds)
            ->getQuery()
            ->getResult();

        $lines = array_fill_keys($deckIds, []);
        foreach ($rows as $row) {
            $front = explode(' // ', $row['typeLine'])[0];
            $lines[(int) $row['deck']][] = [
                'card' => $row['card']->toRfc4122(),
                'quantity' => (int) $row['quantity'],
                'basic' => str_contains($front, 'Basic') && str_contains($front, 'Land'),
            ];
        }

        return $lines;
    }

    /**
     * État de l'import de chaque deck connu : code => nombre de cartes introuvables.
     *
     * @return array<string, int>
     */
    public function findUnresolvedCounts(): array
    {
        $rows = $this->createQueryBuilder('d')->select('d.code', 'd.unresolvedCount')->getQuery()->getArrayResult();

        return array_column($rows, 'unresolvedCount', 'code');
    }
}
