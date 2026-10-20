<?php

namespace App\Search;

use App\Entity\CardLegality;
use App\Entity\CardPrinting;
use App\Enum\Color;
use App\Enum\Format;
use App\Enum\LegalityStatus;
use App\Enum\Rarity;
use App\Repository\OracleCardRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Recherche multicritère sur les cartes oracle.
 *
 * Les filtres portant sur les impressions (rareté, édition) et la légalité passent
 * par des sous-requêtes EXISTS pour garder une ligne par carte.
 */
class CardSearch
{
    private const int ALL_COLORS = 31;

    private int $paramIndex = 0;

    public function __construct(private readonly OracleCardRepository $cards)
    {
    }

    public function search(CardSearchCriteria $criteria, int $page = 1, int $perPage = 60): CardSearchResult
    {
        $qb = $this->createQueryBuilder($criteria);
        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        // La jointure sur la traduction (filtrée sur une langue) ne produit qu'une ligne par carte
        $paginator = new Paginator($qb, fetchJoinCollection: false);

        return new CardSearchResult(iterator_to_array($paginator, false), \count($paginator), $page, $perPage);
    }

    public function createQueryBuilder(CardSearchCriteria $c): QueryBuilder
    {
        $this->paramIndex = 0;
        $qb = $this->cards->createQueryBuilder('o')
            ->leftJoin('o.localizations', 'l', 'WITH', 'l.lang = :lang')
            ->addSelect('l')
            ->setParameter('lang', 'fr')
            ->andWhere('o.layout NOT IN (:excludedLayouts)')
            ->setParameter('excludedLayouts', OracleCardRepository::NON_GAME_LAYOUTS);

        if ('' !== trim($c->name)) {
            $p = $this->param($qb, '%'.$this->escapeLike(trim($c->name)).'%');
            $qb->andWhere("o.name LIKE $p OR l.printedName LIKE $p");
        }

        foreach (CardSearchCriteria::terms($c->text) as $term) {
            $p = $this->param($qb, '%'.$this->escapeLike($term).'%');
            $qb->andWhere("o.oracleText LIKE $p OR l.printedText LIKE $p");
        }

        foreach (CardSearchCriteria::terms($c->type) as $term) {
            $p = $this->param($qb, '%'.$this->escapeLike($term).'%');
            $qb->andWhere("o.typeLine LIKE $p OR l.printedTypeLine LIKE $p");
        }

        $this->applyColors($qb, $c);
        $this->applyRange($qb, 'o.cmc', $c->cmcMin, $c->cmcMax);
        $this->applyStatRange($qb, 'o.power', $c->powerMin, $c->powerMax);
        $this->applyStatRange($qb, 'o.toughness', $c->toughnessMin, $c->toughnessMax);

        if ('' !== trim($c->keyword)) {
            // Les mots-clés sont stockés en JSON (["Flying","First strike"]), sensible à la casse
            $p = $this->param($qb, '%"'.$this->escapeLike(ucfirst(mb_strtolower(trim($c->keyword)))).'"%');
            $qb->andWhere("o.keywords LIKE $p");
        }

        $rarities = array_values(array_filter(array_map(Rarity::tryFrom(...), $c->rarities)));
        $set = mb_strtolower(trim($c->set));
        if ($rarities || '' !== $set) {
            $sub = $this->cards->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(CardPrinting::class, 'p')
                ->join('p.set', 's')
                ->where('p.oracleCard = o');
            if ($rarities) {
                $sub->andWhere('p.rarity IN ('.$this->param($qb, $rarities).')');
            }
            if ('' !== $set) {
                $sub->andWhere('s.code = '.$this->param($qb, $set));
            }
            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        }

        if ($format = Format::tryFrom($c->format)) {
            $statuses = match ($c->legality) {
                'banned' => [LegalityStatus::Banned],
                'restricted' => [LegalityStatus::Restricted],
                default => [LegalityStatus::Legal, LegalityStatus::Restricted],
            };
            $sub = $this->cards->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(CardLegality::class, 'cl')
                ->where('cl.oracleCard = o')
                ->andWhere('cl.format = '.$this->param($qb, $format))
                ->andWhere('cl.status IN ('.$this->param($qb, $statuses).')');
            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        }

        $this->applySort($qb, $c);

        return $qb;
    }

    private function applyColors(QueryBuilder $qb, CardSearchCriteria $c): void
    {
        $field = $c->useColorIdentity ? 'o.colorIdentity' : 'o.colors';
        $mask = Color::maskFrom($c->colors);
        $colorless = \in_array('C', $c->colors, true);

        if (0 === $mask) {
            if ($colorless) {
                $qb->andWhere("$field = 0");
            }

            return;
        }

        match ($c->colorMode) {
            CardSearchCriteria::COLORS_EXACT => $qb->andWhere("$field = ".$this->param($qb, $mask)),
            // « Au plus » : aucune couleur hors de la sélection (logique de l'identité Commander)
            CardSearchCriteria::COLORS_AT_MOST => $qb->andWhere("BIT_AND($field, ".$this->param($qb, self::ALL_COLORS & ~$mask).') = 0'),
            default => $qb->andWhere(\sprintf('BIT_AND(%s, %2$s) = %2$s', $field, $this->param($qb, $mask))),
        };
    }

    private function applyRange(QueryBuilder $qb, string $field, int|float|null $min, int|float|null $max): void
    {
        if (null !== $min) {
            $qb->andWhere("$field >= ".$this->param($qb, $min));
        }
        if (null !== $max) {
            $qb->andWhere("$field <= ".$this->param($qb, $max));
        }
    }

    /**
     * Force / endurance sont des chaînes (« * », « 1+* ») : on ne compare que les valeurs numériques.
     */
    private function applyStatRange(QueryBuilder $qb, string $field, ?int $min, ?int $max): void
    {
        if (null === $min && null === $max) {
            return;
        }
        $qb->andWhere("$field IS NOT NULL AND $field NOT LIKE '%*%'");
        $this->applyRange($qb, "($field + 0)", $min, $max);
    }

    private function applySort(QueryBuilder $qb, CardSearchCriteria $c): void
    {
        $dir = 'desc' === $c->direction ? 'DESC' : 'ASC';
        match ($c->sort) {
            'cmc' => $qb->orderBy('o.cmc', $dir),
            'edhrec' => $qb
                ->addSelect('CASE WHEN o.edhrecRank IS NULL THEN 1 ELSE 0 END AS HIDDEN noRank')
                ->orderBy('noRank', 'ASC')
                ->addOrderBy('o.edhrecRank', $dir),
            'recent' => $qb->orderBy('o.firstPrintedAt', 'ASC' === $dir ? 'DESC' : 'ASC'),
            default => $qb->orderBy('o.name', $dir),
        };
        $qb->addOrderBy('o.name', 'ASC');
    }

    private function param(QueryBuilder $qb, mixed $value): string
    {
        $name = 'p'.$this->paramIndex++;
        $qb->setParameter($name, $value);

        return ':'.$name;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
