<?php

namespace App\Collection;

use App\Entity\CardCollection;
use App\Entity\CardPrinting;
use App\Enum\CardCondition;
use App\Repository\CardPrintingRepository;
use App\Repository\OracleCardRepository;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Importe dans une collection l'export CSV de l'application ManaBox (Collection → Exporter → CSV).
 *
 * Chaque ligne est rattachée à son impression par l'identifiant Scryfall, à défaut par l'édition et le numéro
 * de collection, puis par le nom de la carte (impression par défaut). Langue, état, finition, prix et date
 * d'achat sont repris. Les colonnes sont lues par leur nom : leur ordre n'a pas d'importance.
 */
class ManaboxCsvImporter
{
    /** Colonnes indispensables : la quantité, et de quoi retrouver la carte. */
    private const array REQUIRED = ['Quantity', 'Name'];

    /** Au-delà, le fichier est refusé (un export ManaBox fait rarement plus de quelques milliers de lignes). */
    public const int MAX_LINES = 20_000;

    public function __construct(
        private readonly CardPrintingRepository $printings,
        private readonly OracleCardRepository $cards,
    ) {
    }

    /**
     * @param bool $replace vider la collection avant l'import (pour réimporter un export complet sans doublons)
     *
     * @throws CsvImportException si le fichier n'est pas un export ManaBox lisible
     */
    public function import(CardCollection $collection, string $path, bool $replace = false): CsvImportResult
    {
        $rows = $this->readRows($path);
        $byScryfallId = $this->findPrintingsById(array_column($rows, 'Scryfall ID'));

        if ($replace) {
            $collection->clearCards();
        }

        $lines = $copies = $proxies = 0;
        $approximated = $notFound = [];
        foreach ($rows as $number => $row) {
            if (!$row) {
                $notFound[] = \sprintf('ligne %d : ligne illisible', $number);
                continue;
            }
            $quantity = (int) ($row['Quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            if ('true' === strtolower($row['Proxy'] ?? '')) {
                ++$proxies;
                continue;
            }

            $label = self::label($row);
            $printing = $byScryfallId[strtolower($row['Scryfall ID'] ?? '')] ?? null;
            if (null === $printing) {
                $printing = $this->findFallback($row);
                if (null === $printing) {
                    $notFound[] = \sprintf('ligne %d : %s', $number, $label);
                    continue;
                }
                $approximated[] = \sprintf('%s → %s #%s', $label, $printing->getSet()->getName(), $printing->getCollectorNumber());
            }

            $line = $collection->addCard(
                $printing,
                $quantity,
                \in_array(strtolower($row['Foil'] ?? ''), ['foil', 'etched'], true),
                self::language($row['Language'] ?? ''),
                CardCondition::tryFrom(strtolower($row['Condition'] ?? '')) ?? CardCondition::NearMint,
            );
            $price = trim($row['Purchase price'] ?? '');
            $line->recordPurchase($quantity, is_numeric($price) ? (float) $price : null, $row['Purchase price currency'] ?? null);
            if ($added = self::date($row['Added'] ?? '')) {
                // Date d'ajout dans ManaBox ; pour plusieurs lignes cumulées, la plus ancienne
                $line->setAddedAt(min($added, $line->getAddedAt()));
            }

            ++$lines;
            $copies += $quantity;
        }

        return new CsvImportResult($lines, $copies, $approximated, $notFound, $proxies);
    }

    /**
     * @return array<int, array<string, string>> lignes indexées par leur numéro dans le fichier, valeurs par nom de colonne
     */
    private function readRows(string $path): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');

        $header = null;
        $rows = [];
        foreach ($file as $index => $values) {
            if (null === $header) {
                // Retire l'éventuel BOM UTF-8 du premier nom de colonne
                $header = array_map(static fn ($name) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name)), $values);
                $missing = array_diff(self::REQUIRED, $header);
                if ($missing) {
                    throw new CsvImportException(\sprintf('Ce fichier ne ressemble pas à un export ManaBox : colonne%s %s introuvable%1$s.', \count($missing) > 1 ? 's' : '', implode(', ', $missing)));
                }
                continue;
            }
            if (\count($rows) >= self::MAX_LINES) {
                throw new CsvImportException(\sprintf('Le fichier dépasse %d lignes.', self::MAX_LINES));
            }
            // Ligne au mauvais nombre de colonnes : gardée vide pour être signalée comme illisible
            $rows[$index + 1] = \count($values) === \count($header) ? array_map('trim', array_combine($header, $values)) : [];
        }

        if (null === $header) {
            throw new CsvImportException('Le fichier est vide.');
        }

        return $rows;
    }

    /**
     * @param list<string> $ids identifiants Scryfall du fichier
     *
     * @return array<string, CardPrinting> impressions trouvées, par identifiant (minuscules)
     */
    private function findPrintingsById(array $ids): array
    {
        $uuids = array_values(array_unique(array_map(
            static fn (string $id) => Uuid::fromString($id)->toBinary(),
            array_filter($ids, static fn (string $id) => Uuid::isValid($id)),
        )));

        $found = [];
        foreach (array_chunk($uuids, 500) as $chunk) {
            $printings = $this->printings->createQueryBuilder('p')
                ->join('p.set', 's')->addSelect('s')
                ->join('p.oracleCard', 'o')->addSelect('o')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $chunk, ArrayParameterType::BINARY)
                ->getQuery()
                ->getResult();
            foreach ($printings as $printing) {
                $found[strtolower($printing->getId()->toRfc4122())] = $printing;
            }
        }

        return $found;
    }

    /**
     * Impression absente de la base (ex. éditions non anglaises comme Foreign White Border) :
     * même édition et même numéro, sinon impression par défaut de la carte du même nom.
     *
     * @param array<string, string> $row
     */
    private function findFallback(array $row): ?CardPrinting
    {
        if (($row['Set code'] ?? '') && ($row['Collector number'] ?? '')) {
            $printing = $this->printings->findOneBySetAndNumber($row['Set code'], $row['Collector number']);
            if ($printing) {
                return $printing;
            }
        }

        $card = ($row['Name'] ?? '') ? $this->cards->findOneByDeckListName($row['Name']) : null;

        return $card ? $this->printings->findDefaultFor($card) : null;
    }

    /**
     * @param array<string, string> $row
     */
    private static function label(array $row): string
    {
        $set = trim(($row['Set code'] ?? '').' '.($row['Collector number'] ?? ''));

        return ($row['Name'] ?? '?').('' !== $set ? ' ('.strtoupper($set).')' : '');
    }

    private static function language(string $code): string
    {
        $code = strtolower(trim($code));

        return 1 === preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $code) ? substr($code, 0, 5) : 'en';
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        try {
            return '' !== $value ? (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
