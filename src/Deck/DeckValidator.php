<?php

namespace App\Deck;

use App\Entity\CardLegality;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\OracleCard;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Enum\LegalityStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vérifie qu'un deck respecte les règles de construction de son format et les ban lists.
 * La zone « peut-être » n'est jamais prise en compte.
 */
class DeckValidator
{
    private const int CONSTRUCTED_MIN_MAIN = 60;
    private const int CONSTRUCTED_MAX_SIDE = 15;
    private const int CONSTRUCTED_MAX_COPIES = 4;
    private const int COMMANDER_DECK_SIZE = 100;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function validate(Deck $deck): DeckValidation
    {
        $format = $deck->getFormat();
        $errors = Format::Commander === $format ? $this->validateCommander($deck) : $this->validateConstructed($deck);

        return new DeckValidation([...$errors, ...$this->validateLegality($deck)]);
    }

    /**
     * @return list<string>
     */
    private function validateConstructed(Deck $deck): array
    {
        $errors = [];
        $main = $deck->countIn(DeckZone::Main);
        $side = $deck->countIn(DeckZone::Side);

        if ($main < self::CONSTRUCTED_MIN_MAIN) {
            $errors[] = \sprintf('Le deck principal doit contenir au moins %d cartes (%d actuellement).', self::CONSTRUCTED_MIN_MAIN, $main);
        }
        if ($side > self::CONSTRUCTED_MAX_SIDE) {
            $errors[] = \sprintf('La réserve ne peut pas dépasser %d cartes (%d actuellement).', self::CONSTRUCTED_MAX_SIDE, $side);
        }

        // En Vintage, une carte restreinte n'est autorisée qu'en un exemplaire
        $restricted = Format::Vintage === $deck->getFormat()
            ? array_keys(array_filter($this->legalities($deck), static fn (LegalityStatus $s) => LegalityStatus::Restricted === $s))
            : [];

        foreach ($this->copiesByCard($deck, DeckZone::Main, DeckZone::Side) as [$card, $copies]) {
            $limit = \in_array((string) $card->getId(), $restricted, true) ? 1 : $card->getCopyLimit(self::CONSTRUCTED_MAX_COPIES);
            if (null !== $limit && $copies > $limit) {
                $errors[] = \sprintf('%s : %d exemplaires (maximum %d).', $card->getName(), $copies, $limit);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateCommander(Deck $deck): array
    {
        $errors = [];
        $commanders = array_map(static fn (DeckCard $c) => $c->getOracleCard(), $deck->getCardsIn(DeckZone::Commander));
        $total = $deck->countIn(DeckZone::Commander, DeckZone::Main);

        if (!$commanders) {
            $errors[] = 'Désigne ton commandant.';
        } elseif (\count($commanders) > 2 || $deck->countIn(DeckZone::Commander) > 2) {
            $errors[] = 'Un deck Commander a au plus deux commandants.';
        } elseif (1 === \count($commanders) && !$commanders[0]->canBeCommander()) {
            $errors[] = \sprintf('%s ne peut pas être commandant (il faut une créature légendaire).', $commanders[0]->getName());
        } elseif (2 === \count($commanders) && !$this->canPair(...$commanders)) {
            $errors[] = \sprintf('%s et %s ne peuvent pas être associés comme commandants.', $commanders[0]->getName(), $commanders[1]->getName());
        }

        if (self::COMMANDER_DECK_SIZE !== $total) {
            $errors[] = \sprintf('Un deck Commander doit compter exactement %d cartes, commandant compris (%d actuellement).', self::COMMANDER_DECK_SIZE, $total);
        }

        foreach ($this->copiesByCard($deck, DeckZone::Commander, DeckZone::Main) as [$card, $copies]) {
            $limit = $card->getCopyLimit(1);
            if (null !== $limit && $copies > $limit) {
                $errors[] = \sprintf('%s : %d exemplaires (le format est singleton).', $card->getName(), $copies);
            }
        }

        if ($commanders) {
            $identity = array_reduce($commanders, static fn (int $mask, OracleCard $c) => $mask | $c->getColorIdentity(), 0);
            foreach ($deck->getCardsIn(DeckZone::Main) as $deckCard) {
                if (0 !== ($deckCard->getOracleCard()->getColorIdentity() & ~$identity)) {
                    $errors[] = \sprintf('%s sort de l\'identité couleur du commandant.', $deckCard->getOracleCard()->getName());
                }
            }
        }

        return $errors;
    }

    /**
     * Les deux cartes peuvent-elles former une paire de commandants ?
     */
    private function canPair(OracleCard $a, OracleCard $b): bool
    {
        foreach ([[$a, $b], [$b, $a]] as [$first, $second]) {
            $text = $first->getOracleText() ?? '';
            if ($first->hasKeyword('Partner with') && str_contains($text, 'Partner with '.$second->getName())) {
                return true;
            }
            if ($first->hasKeyword('Choose a background') && $first->canBeCommander() && str_contains($second->getFrontTypeLine(), 'Background')) {
                return true;
            }
            if ($first->hasKeyword('Doctor\'s companion') && $first->canBeCommander() && str_contains($second->getFrontTypeLine(), 'Time Lord Doctor')) {
                return true;
            }
        }

        $both = static fn (callable $test) => $test($a) && $test($b);

        return $both(static fn (OracleCard $c) => $c->canBeCommander())
            && ($both(static fn (OracleCard $c) => $c->hasKeyword('Partner'))
                || $both(static fn (OracleCard $c) => str_contains($c->getOracleText() ?? '', 'Friends forever')));
    }

    /**
     * @return list<string>
     */
    private function validateLegality(Deck $deck): array
    {
        $errors = [];
        $legalities = $this->legalities($deck);
        $format = $deck->getFormat();
        $seen = [];

        foreach ($deck->getCardsIn(DeckZone::Commander, DeckZone::Main, DeckZone::Side) as $deckCard) {
            $card = $deckCard->getOracleCard();
            $id = (string) $card->getId();
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            match ($legalities[$id] ?? LegalityStatus::NotLegal) {
                LegalityStatus::Banned => $errors[] = \sprintf('%s est bannie en %s.', $card->getName(), $format->label()),
                LegalityStatus::NotLegal => $errors[] = \sprintf('%s n\'est pas légale en %s.', $card->getName(), $format->label()),
                default => null,
            };
        }

        return $errors;
    }

    /**
     * Statut de chaque carte du deck dans son format, indexé par identifiant de carte oracle.
     *
     * @return array<string, LegalityStatus>
     */
    private function legalities(Deck $deck): array
    {
        $ids = array_unique(array_map(static fn (DeckCard $c) => $c->getOracleCard()->getId()->toBinary(), $deck->getCards()->toArray()));
        if (!$ids) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('o.id AS cardId', 'l.status')
            ->from(CardLegality::class, 'l')
            ->join('l.oracleCard', 'o')
            ->where('o.id IN (:ids)')
            ->andWhere('l.format = :format')
            ->setParameter('ids', array_values($ids), ArrayParameterType::BINARY)
            ->setParameter('format', $deck->getFormat())
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r) => [(string) $r['cardId'], $r['status']], $rows), 1, 0);
    }

    /**
     * Nombre total d'exemplaires de chaque carte sur les zones données.
     *
     * @return list<array{OracleCard, int}>
     */
    private function copiesByCard(Deck $deck, DeckZone ...$zones): array
    {
        $copies = [];
        foreach ($deck->getCardsIn(...$zones) as $deckCard) {
            $id = (string) $deckCard->getOracleCard()->getId();
            $copies[$id] ??= [$deckCard->getOracleCard(), 0];
            $copies[$id][1] += $deckCard->getQuantity();
        }

        return array_values($copies);
    }
}
