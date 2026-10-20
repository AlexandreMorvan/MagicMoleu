<?php

namespace App\Scryfall;

use Doctrine\DBAL\Connection;

/**
 * Écrit des lignes par lots avec `INSERT ... ON DUPLICATE KEY UPDATE`.
 *
 * Bien plus rapide que l'ORM pour les ~100 000 impressions de Scryfall.
 * Les colonnes UUID (BINARY(16)) sont transmises en hexadécimal et converties par `UNHEX()`,
 * ce qui évite tout souci d'encodage des chaînes binaires.
 */
class BulkUpserter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<array<string, scalar|null>> $rows             lignes ayant toutes les mêmes clés
     * @param list<string>                     $uuidColumns      colonnes dont la valeur est un UUID
     * @param array<string, string>|null       $updates          expressions SQL par colonne pour la clause UPDATE ;
     *                                                           par défaut toutes les colonnes prennent la nouvelle valeur
     * @param array<string, string>            $updateOverrides  expressions qui remplacent celles par défaut pour certaines colonnes
     */
    public function upsert(string $table, array $rows, array $uuidColumns = [], ?array $updates = null, array $updateOverrides = []): void
    {
        if (!$rows) {
            return;
        }

        $columns = array_keys($rows[0]);
        $placeholders = array_map(
            static fn (string $column) => \in_array($column, $uuidColumns, true) ? 'UNHEX(?)' : '?',
            $columns,
        );
        $rowSql = '('.implode(', ', $placeholders).')';

        $updates ??= array_combine($columns, array_map(static fn (string $c) => "VALUES(`$c`)", $columns));
        $updates = array_merge($updates, $updateOverrides);
        $updateSql = implode(', ', array_map(
            static fn (string $column, string $expression) => "`$column` = $expression",
            array_keys($updates),
            $updates,
        ));

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = $row[$column];
                $params[] = match (true) {
                    \is_bool($value) => (int) $value,
                    null !== $value && \in_array($column, $uuidColumns, true) => self::uuidToHex($value),
                    default => $value,
                };
            }
        }

        $this->connection->executeStatement(\sprintf(
            'INSERT INTO `%s` (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', array_map(static fn (string $c) => "`$c`", $columns)),
            implode(', ', array_fill(0, \count($rows), $rowSql)),
            $updateSql,
        ), $params);
    }

    public static function uuidToHex(string $uuid): string
    {
        return str_replace('-', '', $uuid);
    }
}
