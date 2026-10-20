<?php

namespace App\Scryfall;

use Doctrine\DBAL\Connection;

/**
 * Importe les noms et textes imprimés dans une langue (ex. « fr ») depuis le bulk « all-cards ».
 */
class LocalizationImporter
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly BulkUpserter $upserter,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param iterable<array<string, mixed>> $cards objets carte Scryfall, toutes langues confondues
     *
     * @return int nombre de traductions écrites
     */
    public function import(iterable $cards, string $lang, ?callable $onProgress = null): int
    {
        // Seules les cartes déjà importées peuvent recevoir une traduction (clé étrangère)
        $known = array_flip($this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM oracle_card'));

        $rows = [];
        $count = 0;
        foreach ($cards as $card) {
            $row = $this->map($card, $lang);
            if (null === $row || !isset($known[BulkUpserter::uuidToHex($row['oracle_card_id'])])) {
                continue;
            }
            // Une même carte a souvent plusieurs impressions dans la langue : une seule ligne par lot,
            // en complétant les champs manquants d'une impression par ceux d'une autre
            $previous = $rows[$row['oracle_card_id']] ?? [];
            foreach ($row as $field => $value) {
                $row[$field] = $value ?? $previous[$field] ?? null;
            }
            $rows[$row['oracle_card_id']] = $row;

            if (\count($rows) >= self::BATCH_SIZE) {
                $this->flush($rows);
                $count += \count($rows);
                $rows = [];
                $onProgress && $onProgress($count);
            }
        }
        $this->flush($rows);

        return $count + \count($rows);
    }

    /**
     * @param array<string, array<string, string|null>> $rows
     */
    private function flush(array $rows): void
    {
        $this->upserter->upsert('card_localization', array_values($rows), ['oracle_card_id'], [
            'printed_name' => 'VALUES(`printed_name`)',
            // Une impression sans texte traduit n'efface pas un texte déjà connu
            'printed_type_line' => 'COALESCE(VALUES(`printed_type_line`), `printed_type_line`)',
            'printed_text' => 'COALESCE(VALUES(`printed_text`), `printed_text`)',
        ]);
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return array<string, string|null>|null
     */
    public function map(array $card, string $lang): ?array
    {
        if ($lang !== $card['lang']) {
            return null;
        }

        $faces = 'reversible_card' === $card['layout'] ? [] : ($card['card_faces'] ?? []);
        $oracleId = $card['oracle_id'] ?? $card['card_faces'][0]['oracle_id'] ?? null;
        $name = ($card['printed_name'] ?? null) ?: $this->joinFaces($faces, 'printed_name', 'name', ' // ');
        if (null === $oracleId || null === $name) {
            return null;
        }

        return [
            'oracle_card_id' => $oracleId,
            'lang' => $lang,
            'printed_name' => $name,
            'printed_type_line' => ($card['printed_type_line'] ?? null) ?: $this->joinFaces($faces, 'printed_type_line', 'type_line', ' // '),
            'printed_text' => ($card['printed_text'] ?? null) ?: $this->joinFaces($faces, 'printed_text', 'oracle_text', "\n//\n"),
        ];
    }

    /**
     * Assemble un champ traduit des faces, en retombant sur l'anglais pour une face non traduite.
     * Renvoie null si aucune face n'est traduite.
     *
     * @param list<array<string, mixed>> $faces
     */
    private function joinFaces(array $faces, string $field, string $fallback, string $glue): ?string
    {
        if (!array_filter(array_column($faces, $field))) {
            return null;
        }

        return implode($glue, array_map(static fn (array $f) => $f[$field] ?? $f[$fallback] ?? '', $faces));
    }
}
