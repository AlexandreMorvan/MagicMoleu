<?php

namespace App\Scryfall;

use Doctrine\DBAL\Connection;

/**
 * Choisit pour chaque édition une carte emblématique dont l'illustration sert de fond à sa vignette :
 * en priorité une mythique, puis une rare, la plus jouée en Commander (meilleur rang EDHREC).
 *
 * La requête parcourt toutes les impressions (quelques secondes) : elle est lancée après les imports,
 * jamais à l'affichage.
 */
class SetCoverUpdater
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return int nombre d'éditions mises à jour
     */
    public function update(): int
    {
        return $this->connection->executeStatement(<<<'SQL'
            UPDATE mtg_set s
            JOIN (
                SELECT p.set_id, o.name,
                       COALESCE(JSON_VALUE(p.image_uris, '$.art_crop'), JSON_VALUE(p.image_uris, '$[0].art_crop')) AS art,
                       ROW_NUMBER() OVER (
                           PARTITION BY p.set_id
                           ORDER BY FIELD(p.rarity, 'mythic', 'rare') = 0, FIELD(p.rarity, 'mythic', 'rare'),
                                    o.edhrec_rank IS NULL, o.edhrec_rank, LENGTH(p.collector_number), p.collector_number
                       ) AS rn
                FROM card_printing p
                JOIN oracle_card o ON o.id = p.oracle_card_id
                WHERE o.type_line NOT LIKE 'Basic%'
                  AND COALESCE(JSON_VALUE(p.image_uris, '$.art_crop'), JSON_VALUE(p.image_uris, '$[0].art_crop')) IS NOT NULL
            ) cover ON cover.set_id = s.id AND cover.rn = 1
            SET s.cover_art_uri = cover.art, s.cover_card_name = LEFT(cover.name, 255)
            SQL);
    }
}
