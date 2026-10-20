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
        'image_rank' => 'IF(VALUES(`image_rank`) >= COALESCE(`image_rank`, -1), VALUES(`image_rank`), `image_rank`)',
    ];

    /** @var array<string, list<array<string, scalar|null>>> */
    private array $buffer = [];

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
            $this->upserter->upsert('oracle_card', $this->buffer['oracle_card'], ['id'], self::ORACLE_UPDATES);
            $this->upserter->upsert('card_face', $this->buffer['card_face'], ['oracle_card_id']);
            $this->upserter->upsert('card_printing', $this->buffer['card_printing'], ['id', 'oracle_card_id', 'set_id']);
            $this->upserter->upsert('card_legality', $this->buffer['card_legality'], ['oracle_card_id']);
        });

        $this->resetBuffer();
    }

    private function resetBuffer(): void
    {
        $this->buffer = array_fill_keys(['mtg_set', 'oracle_card', 'card_face', 'card_printing', 'card_legality'], []);
    }
}
