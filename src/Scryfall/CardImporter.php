<?php

namespace App\Scryfall;

use Doctrine\DBAL\Connection;

/**
 * Importe (ou met à jour) les cartes d'un fichier bulk Scryfall dans la base.
 */
class CardImporter
{
    private const int BATCH_SIZE = 500;

    private const array ORACLE_UPDATES = [
        'name' => 'VALUES(`name`)',
        'layout' => 'VALUES(`layout`)',
        'mana_cost' => 'VALUES(`mana_cost`)',
        'cmc' => 'VALUES(`cmc`)',
        'type_line' => 'VALUES(`type_line`)',
        'oracle_text' => 'VALUES(`oracle_text`)',
        'power' => 'VALUES(`power`)',
        'toughness' => 'VALUES(`toughness`)',
        'loyalty' => 'VALUES(`loyalty`)',
        'defense' => 'VALUES(`defense`)',
        'colors' => 'VALUES(`colors`)',
        'color_identity' => 'VALUES(`color_identity`)',
        'keywords' => 'VALUES(`keywords`)',
        'produced_mana' => 'VALUES(`produced_mana`)',
        'edhrec_rank' => 'VALUES(`edhrec_rank`)',
        'reserved' => 'VALUES(`reserved`)',
        'game_changer' => 'VALUES(`game_changer`)',
        'first_printed_at' => 'LEAST(COALESCE(`first_printed_at`, VALUES(`first_printed_at`)), VALUES(`first_printed_at`))',
        // On ne garde l'image que si elle provient d'une impression mieux classée ;
        // image_rank doit être mis à jour en dernier car MariaDB applique les affectations dans l'ordre.
        'image_uri' => 'IF(VALUES(`image_rank`) >= COALESCE(`image_rank`, -1), VALUES(`image_uri`), `image_uri`)',
        'art_crop_uri' => 'IF(VALUES(`image_rank`) >= COALESCE(`image_rank`, -1), VALUES(`art_crop_uri`), `art_crop_uri`)',
        'default_printing_id' => 'IF(VALUES(`image_rank`) >= COALESCE(`image_rank`, -1), VALUES(`default_printing_id`), `default_printing_id`)',
        'image_rank' => 'IF(VALUES(`image_rank`) >= COALESCE(`image_rank`, -1), VALUES(`image_rank`), `image_rank`)',
    ];

    /**
     * Lignes en attente d'écriture, par table ; les éditions sont indexées par identifiant pour n'être écrites qu'une fois.
     *
     * @var array{mtg_set: array<string, array<string, scalar|null>>, oracle_card: list<array<string, scalar|null>>, card_face: list<array<string, scalar|null>>, card_printing: list<array<string, scalar|null>>, card_legality: list<array<string, scalar|null>>}
     */
    private array $buffer = ['mtg_set' => [], 'oracle_card' => [], 'card_face' => [], 'card_printing' => [], 'card_legality' => []];

    public function __construct(
        private readonly CardMapper $mapper,
        private readonly BulkUpserter $upserter,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param iterable<array<string, mixed>>     $cards      objets carte Scryfall
     * @param (callable(int): void)|null         $onProgress appelé après chaque lot avec le nombre de cartes traitées
     *
     * @return int nombre d'impressions importées
     */
    public function import(iterable $cards, ?callable $onProgress = null): int
    {
        $count = 0;
        $this->resetBuffer();

        foreach ($cards as $card) {
            $mapped = $this->mapper->map($card);
            if (null === $mapped) {
                continue;
            }

            $this->buffer['mtg_set'][$mapped->set['id']] = $mapped->set;
            $this->buffer['oracle_card'][] = $mapped->oracle;
            array_push($this->buffer['card_face'], ...$mapped->faces);
            $this->buffer['card_printing'][] = $mapped->printing;
            array_push($this->buffer['card_legality'], ...$mapped->legalities);

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flush();
                $onProgress && $onProgress($count);
            }
        }

        $this->flush();
        $onProgress && $onProgress($count);

        return $count;
    }

    private function flush(): void
    {
        $this->connection->transactional(function () {
            // Les sets ne sont créés ici que s'ils n'existent pas encore ; app:scryfall:import-sets les complète.
            $this->upserter->upsert('mtg_set', array_values($this->buffer['mtg_set']), ['id'], ['id' => '`id`']);
            $this->upserter->upsert('oracle_card', $this->buffer['oracle_card'], ['id', 'default_printing_id'], self::ORACLE_UPDATES);
            $this->upserter->upsert('card_face', $this->buffer['card_face'], ['oracle_card_id']);
            $this->releaseCollectorNumbers($this->buffer['card_printing']);
            $this->upserter->upsert('card_printing', $this->buffer['card_printing'], ['id', 'oracle_card_id', 'set_id'], updateOverrides: [
                'revealed_at' => 'COALESCE(VALUES(`previewed_at`), `revealed_at`)',
            ]);
            $this->upserter->upsert('card_legality', $this->buffer['card_legality'], ['oracle_card_id']);
        });

        $this->resetBuffer();
    }

    /**
     * Scryfall renumérote parfois une édition (ex. un numéro 37 qui passe au 36 et le 38 au 37).
     * Avant d'écrire un lot, les impressions qui occupent un (édition, numéro, langue) visé par une autre
     * impression du lot reçoivent un numéro provisoire unique, « ~ » suivi du début de leur identifiant :
     * sans cela, l'upsert violerait l'unicité, ou remplacerait l'identifiant de l'ancienne impression.
     * Si elles figurent encore dans l'import, leur propre ligne leur rend ensuite leur vrai numéro.
     *
     * @param list<array<string, scalar|null>> $printings
     */
    private function releaseCollectorNumbers(array $printings): void
    {
        if (!$printings) {
            return;
        }

        $keys = [];
        $keysWithId = [];
        $keyParams = [];
        $keyWithIdParams = [];
        foreach ($printings as $printing) {
            $key = [BulkUpserter::uuidToHex($printing['set_id']), $printing['collector_number'], $printing['lang']];
            $keys[] = '(UNHEX(?), ?, ?)';
            $keysWithId[] = '(UNHEX(?), ?, ?, UNHEX(?))';
            array_push($keyParams, ...$key);
            array_push($keyWithIdParams, ...$key);
            $keyWithIdParams[] = BulkUpserter::uuidToHex($printing['id']);
        }

        $this->connection->executeStatement(\sprintf(
            "UPDATE `card_printing` SET `collector_number` = CONCAT('~', LEFT(LOWER(HEX(`id`)), 19))
             WHERE (`set_id`, `collector_number`, `lang`) IN (%s)
               AND (`set_id`, `collector_number`, `lang`, `id`) NOT IN (%s)",
            implode(', ', $keys),
            implode(', ', $keysWithId),
        ), [...$keyParams, ...$keyWithIdParams]);
    }

    private function resetBuffer(): void
    {
        $this->buffer = ['mtg_set' => [], 'oracle_card' => [], 'card_face' => [], 'card_printing' => [], 'card_legality' => []];
    }
}
