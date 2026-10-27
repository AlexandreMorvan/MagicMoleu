<?php

namespace App\Twig\Components;

use App\Collection\CollectionBuilder;
use App\Collection\CollectionStats;
use App\Entity\CardCollection;
use App\Entity\CollectionCard;
use App\Entity\MtgSet;
use App\Enum\ColorGroup;
use App\Enum\Rarity;
use App\Repository\CardCollectionRepository;
use App\Security\CardCollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Contenu d'une collection : filtres, tri, vue en vignettes ou en liste, quantités. Chaque action est enregistrée immédiatement.
 */
#[AsLiveComponent]
class CollectionBrowser extends AbstractController
{
    use DefaultActionTrait;

    public const array VIEWS = ['grid' => 'Vignettes', 'list' => 'Liste'];
    /** Classements : par édition, prix et rareté, les cartes sont regroupées sous un en-tête. */
    public const array SORTS = ['name' => 'Nom', 'set' => 'Édition', 'price' => 'Prix', 'rarity' => 'Rareté', 'added' => 'Ajout récent'];

    /** Identifiant de la collection : non modifiable côté client (signé par LiveComponent). */
    #[LiveProp]
    public int $collectionId;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $view = 'grid';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $sort = 'name';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $rarity = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetLimit')]
    public string $color = '';

    /** Nombre de lignes affichées : une grande collection (import ManaBox) se parcourt par pages. */
    #[LiveProp]
    public int $limit = self::PAGE_SIZE;

    private const int PAGE_SIZE = 120;

    private ?CardCollection $collection = null;

    /** @var list<CollectionCard>|null */
    private ?array $lines = null;

    public function __construct(
        private readonly CardCollectionRepository $collections,
        private readonly CollectionBuilder $builder,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Charge la collection et vérifie les droits : appelé à chaque rendu et chaque action, donc à chaque requête live.
     */
    public function getCollection(): CardCollection
    {
        if (null === $this->collection) {
            $this->collection = $this->collections->findWithCards($this->collectionId) ?? throw new NotFoundHttpException();
            $this->denyAccessUnlessGranted(CardCollectionVoter::MANAGE, $this->collection);
        }

        return $this->collection;
    }

    public function getStats(): CollectionStats
    {
        return CollectionStats::fromCollection($this->getCollection());
    }

    /**
     * @return list<CollectionCard>
     */
    public function getLines(): array
    {
        return $this->lines ??= $this->filterAndSort();
    }

    public function hasMore(): bool
    {
        return \count($this->getLines()) > $this->limit;
    }

    public function resetLimit(): void
    {
        $this->limit = self::PAGE_SIZE;
    }

    #[LiveAction]
    public function more(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * @return list<CollectionCard>
     */
    private function filterAndSort(): array
    {
        $query = mb_strtolower(trim($this->query));
        $rarity = Rarity::tryFrom($this->rarity);
        $color = ColorGroup::tryFrom($this->color);

        $lines = array_values(array_filter($this->getCollection()->getCards()->toArray(), static function (CollectionCard $line) use ($query, $rarity, $color) {
            $card = $line->getOracleCard();
            if ('' !== $query && !str_contains(mb_strtolower($card->getName()), $query)
                && !str_contains(mb_strtolower($card->getLocalization('fr')?->getPrintedName() ?? ''), $query)) {
                return false;
            }

            return (null === $rarity || $line->getPrinting()->getRarity() === $rarity)
                && (null === $color || ColorGroup::of($card) === $color);
        }));

        $rarityOrder = array_flip(array_map(static fn (Rarity $r) => $r->value, Rarity::cases()));
        $key = match ($this->sort) {
            'price' => static fn (CollectionCard $l) => [-($l->getUnitPrice() ?? 0), $l->getOracleCard()->getName()],
            // Éditions de la plus récente à la plus ancienne, puis numéro de collection
            'set' => static fn (CollectionCard $l) => [-($l->getPrinting()->getSet()->getReleasedAt()?->getTimestamp() ?? 0), $l->getPrinting()->getSet()->getName(), \strlen($l->getPrinting()->getCollectorNumber()), $l->getPrinting()->getCollectorNumber()],
            'rarity' => static fn (CollectionCard $l) => [-$rarityOrder[$l->getPrinting()->getRarity()->value], $l->getOracleCard()->getName()],
            'added' => static fn (CollectionCard $l) => [-$l->getAddedAt()->getTimestamp(), -$l->getId()],
            default => static fn (CollectionCard $l) => [$l->getOracleCard()->getName(), $l->isFoil()],
        };
        usort($lines, static fn (CollectionCard $a, CollectionCard $b) => $key($a) <=> $key($b));

        return $lines;
    }

    /**
     * Cartes filtrées et triées, découpées en sections selon le tri : une par édition, par tranche de prix ou par rareté.
     * Les tris par nom et par date d'ajout donnent une seule section, sans en-tête.
     *
     * @return list<array{key: string, label: ?string, set: ?MtgSet, count: int, value: float, lines: list<CollectionCard>}>
     */
    public function getSections(): array
    {
        $keyOf = match ($this->sort) {
            'set' => static fn (CollectionCard $l) => $l->getPrinting()->getSet()->getCode(),
            'price' => static fn (CollectionCard $l) => self::priceRange($l->getUnitPrice()),
            'rarity' => static fn (CollectionCard $l) => $l->getPrinting()->getRarity()->value,
            default => static fn (CollectionCard $l) => 'all',
        };

        // Les en-têtes comptent toute la section ; seules les $limit premières lignes sont affichées
        $sections = [];
        foreach ($this->getLines() as $index => $line) {
            $key = $keyOf($line);
            if (!isset($sections[$key])) {
                $set = 'set' === $this->sort ? $line->getPrinting()->getSet() : null;
                $sections[$key] = [
                    'key' => $key,
                    'label' => match ($this->sort) {
                        'set' => $set->getName(),
                        'price' => self::PRICE_RANGES[$key],
                        'rarity' => $line->getPrinting()->getRarity()->label(),
                        default => null,
                    },
                    'set' => $set,
                    'count' => 0,
                    'value' => 0.0,
                    'lines' => [],
                ];
            }
            $sections[$key]['count'] += $line->getQuantity();
            $sections[$key]['value'] += $line->getTotalPrice();
            if ($index < $this->limit) {
                $sections[$key]['lines'][] = $line;
            }
        }

        return array_values(array_filter($sections, static fn (array $section) => [] !== $section['lines']));
    }

    /** Tranches de prix unitaire (borne basse en euros => libellé), de la plus chère à la moins chère. */
    private const array PRICE_RANGES = [
        '100' => '100 € et plus',
        '50' => '50 € – 100 €',
        '20' => '20 € – 50 €',
        '10' => '10 € – 20 €',
        '5' => '5 € – 10 €',
        '2' => '2 € – 5 €',
        '1' => '1 € – 2 €',
        '0.5' => '0,50 € – 1 €',
        '0' => 'Moins de 0,50 €',
        'none' => 'Prix inconnu',
    ];

    private static function priceRange(?float $price): string
    {
        if (null === $price) {
            return 'none';
        }
        foreach (array_keys(self::PRICE_RANGES) as $floor) {
            if ('none' !== $floor && $price >= (float) $floor) {
                return (string) $floor;
            }
        }

        return '0';
    }

    /**
     * @return list<Rarity>
     */
    public function getRarityChoices(): array
    {
        return [Rarity::Common, Rarity::Uncommon, Rarity::Rare, Rarity::Mythic, Rarity::Special];
    }

    /**
     * @return list<ColorGroup>
     */
    public function getColorChoices(): array
    {
        return ColorGroup::cases();
    }

    #[LiveAction]
    public function changeView(#[LiveArg] string $view): void
    {
        $this->view = isset(self::VIEWS[$view]) ? $view : 'grid';
    }

    #[LiveAction]
    public function changeQuantity(#[LiveArg] int $lineId, #[LiveArg] int $delta): void
    {
        $this->builder->changeQuantity($this->findLine($lineId), $delta);
        $this->em->flush();
        $this->lines = null;
    }

    #[LiveAction]
    public function remove(#[LiveArg] int $lineId): void
    {
        $this->getCollection()->removeCard($this->findLine($lineId));
        $this->em->flush();
        $this->lines = null;
    }

    private function findLine(int $id): CollectionCard
    {
        return $this->getCollection()->getCards()->findFirst(static fn (int $k, CollectionCard $c) => $c->getId() === $id)
            ?? throw new NotFoundHttpException('Carte absente de la collection.');
    }
}
