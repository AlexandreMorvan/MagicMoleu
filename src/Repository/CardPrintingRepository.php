<?php

namespace App\Repository;

use App\Entity\CardPrinting;
use App\Entity\MtgSet;
use App\Entity\OracleCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardPrinting>
 */
class CardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardPrinting::class);
    }

    /**
     * Toutes les impressions d'une carte avec leur édition, de la plus récente à la plus ancienne.
     *
     * @return list<CardPrinting>
     */
    public function findForCard(OracleCard $card): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.set', 's')
            ->addSelect('s')
            ->where('p.oracleCard = :card')
            // L'identifiant est un UUID binaire : son type doit être précisé explicitement
            ->setParameter('card', $card->getId(), UuidType::NAME)
            ->orderBy('p.releasedAt', SortDirection::Descending)
            ->addOrderBy('s.name', SortDirection::Ascending)
            ->addOrderBy('p.collectorNumber', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Impression utilisée par défaut pour une carte (celle de son image), à défaut la plus récente.
     */
    public function findDefaultFor(OracleCard $card): ?CardPrinting
    {
        if ($id = $card->getDefaultPrintingId()) {
            $printing = $this->find($id);
            if ($printing) {
                return $printing;
            }
        }

        return $this->createQueryBuilder('p')
            ->where('p.oracleCard = :card')
            ->setParameter('card', $card->getId(), UuidType::NAME)
            ->orderBy('p.releasedAt', SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySetAndNumber(string $setCode, string $collectorNumber): ?CardPrinting
    {
        return $this->createQueryBuilder('p')
            ->join('p.set', 's')
            ->where('s.code = :code')
            ->andWhere('p.collectorNumber = :number')
            ->setParameter('code', mb_strtolower($setCode))
            ->setParameter('number', $collectorNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Ordre d'affichage des raretés, de la plus courante à la plus rare. */
    private const string RARITY_ORDER = "CASE p.rarity WHEN 'common' THEN 0 WHEN 'uncommon' THEN 1 WHEN 'rare' THEN 2 WHEN 'mythic' THEN 3 ELSE 4 END";

    /**
     * Cartes d'une édition, filtrées par rareté et couleurs, avec ou sans les extras (variantes, promos).
     *
     * @param list<string> $rarities
     * @param string       $sort     number (numéro de collection), name ou rarity
     *
     * @return list<CardPrinting>
     */
    public function findBySet(MtgSet $set, array $rarities = [], int $colorMask = 0, bool $colorless = false, string $sort = 'number', int $limit = 120, bool $extras = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.oracleCard', 'o')
            ->addSelect('o')
            ->where('p.set = :set')
            ->setParameter('set', $set->getId(), UuidType::NAME)
            ->setMaxResults($limit);

        if (!$extras) {
            $qb->andWhere('p.extra = false');
        }

        if ($rarities) {
            $qb->andWhere('p.rarity IN (:rarities)')->setParameter('rarities', $rarities);
        }
        $this->filterColors($qb, $colorMask, $colorless);

        // Numéros de collection alphanumériques (« 12 », « 12a », « 250★ ») : longueur puis valeur
        match ($sort) {
            'name' => $qb->orderBy('o.name', SortDirection::Ascending),
            'rarity' => $qb->addSelect(self::RARITY_ORDER.' AS HIDDEN rarityOrder')->orderBy('rarityOrder', SortDirection::Descending)->addOrderBy('o.name', SortDirection::Ascending),
            default => $qb->orderBy('LENGTH(p.collectorNumber)', SortDirection::Ascending)->addOrderBy('p.collectorNumber', SortDirection::Ascending),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre d'impressions d'une édition dans sa liste principale et en extras.
     *
     * @return array{main: int, extra: int}
     */
    public function countMainAndExtrasBySet(MtgSet $set): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.extra', 'COUNT(p.id) AS cards')
            ->where('p.set = :set')
            ->setParameter('set', $set->getId(), UuidType::NAME)
            ->groupBy('p.extra')
            ->getQuery()
            ->getArrayResult();

        $counts = ['main' => 0, 'extra' => 0];
        foreach ($rows as $row) {
            $counts[$row['extra'] ? 'extra' : 'main'] = (int) $row['cards'];
        }

        return $counts;
    }

    /** Types d'éditions exclus des spoilers : jetons, produits dérivés, promos, cartes numériques. */
    public const array NON_SPOILER_SET_TYPES = ['token', 'memorabilia', 'promo', 'alchemy', 'minigame', 'vanguard', 'treasure_chest'];

    /**
     * Cartes des éditions récentes ou à venir, de la plus récemment révélée à la plus ancienne.
     *
     * @param list<string> $rarities
     *
     * @return list<CardPrinting>
     */
    public function findSpoilers(\DateTimeImmutable $since, ?string $setCode = null, array $rarities = [], int $colorMask = 0, bool $colorless = false, int $limit = 60, int $offset = 0): array
    {
        $qb = $this->createSpoilerQueryBuilder($since)
            ->addSelect('s', 'o')
            ->orderBy('p.revealedAt', SortDirection::Descending)
            ->addOrderBy('s.releasedAt', SortDirection::Descending)
            ->addOrderBy('o.name', SortDirection::Ascending)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($setCode) {
            $qb->andWhere('s.code = :code')->setParameter('code', mb_strtolower($setCode));
        }
        if ($rarities) {
            $qb->andWhere('p.rarity IN (:rarities)')->setParameter('rarities', $rarities);
        }
        $this->filterColors($qb, $colorMask, $colorless);

        return $qb->getQuery()->getResult();
    }

    /**
     * Garde les cartes ayant au moins une des couleurs demandées (ou incolores si demandé) :
     * en parcourant une galerie, cocher blanc et bleu montre les cartes blanches et les bleues.
     */
    private function filterColors(QueryBuilder $qb, int $colorMask, bool $colorless): void
    {
        $conditions = [];
        if ($colorMask) {
            $conditions[] = 'BIT_AND(o.colors, :mask) != 0';
            $qb->setParameter('mask', $colorMask);
        }
        if ($colorless) {
            $conditions[] = 'o.colors = 0';
        }
        if ($conditions) {
            $qb->andWhere(implode(' OR ', $conditions));
        }
    }

    /**
     * Éditions ayant des spoilers, avec leur nombre de cartes, de la plus récente à la plus ancienne.
     *
     * @return list<array{code: string, name: string, releasedAt: ?\DateTimeImmutable, iconSvgUri: ?string, cards: int}>
     */
    public function findSpoilerSets(\DateTimeImmutable $since): array
    {
        return $this->createSpoilerQueryBuilder($since)
            ->select('s.code', 's.name', 's.releasedAt', 's.iconSvgUri', 'COUNT(p.id) AS cards')
            ->groupBy('s.id')
            ->orderBy('s.releasedAt', SortDirection::Descending)
            ->addOrderBy('s.name', SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    private function createSpoilerQueryBuilder(\DateTimeImmutable $since): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->join('p.set', 's')
            ->join('p.oracleCard', 'o')
            ->where('s.releasedAt >= :since')
            ->andWhere('s.setType NOT IN (:excludedTypes)')
            ->andWhere('s.digital = false')
            ->andWhere('p.lang = :lang')
            ->andWhere('o.layout NOT IN (:excludedLayouts)')
            ->setParameter('since', $since, 'date_immutable')
            ->setParameter('excludedTypes', self::NON_SPOILER_SET_TYPES)
            ->setParameter('lang', 'en')
            ->setParameter('excludedLayouts', OracleCardRepository::NON_GAME_LAYOUTS);
    }
}
