<?php

namespace App\Scryfall;

/**
 * Importe la liste complète des éditions depuis `GET /sets`.
 */
class SetImporter
{
    public function __construct(
        private readonly ScryfallClient $client,
        private readonly BulkUpserter $upserter,
    ) {
    }

    /**
     * @return int nombre d'éditions importées
     */
    public function import(): int
    {
        $rows = [];
        foreach ($this->client->paginate('/sets') as $set) {
            $rows[] = $this->map($set);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->upserter->upsert('mtg_set', $chunk, ['id']);
        }

        return \count($rows);
    }

    /**
     * @param array<string, mixed> $set
     *
     * @return array<string, scalar|null>
     */
    public function map(array $set): array
    {
        return [
            'id' => $set['id'],
            'code' => $set['code'],
            'name' => $set['name'],
            'set_type' => $set['set_type'],
            'released_at' => $set['released_at'] ?? null,
            'card_count' => $set['card_count'] ?? 0,
            'icon_svg_uri' => $set['icon_svg_uri'] ?? null,
            'parent_set_code' => $set['parent_set_code'] ?? null,
            'digital' => $set['digital'] ?? false,
        ];
    }
}
