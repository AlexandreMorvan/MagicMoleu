<?php

namespace App\Scryfall;

use App\Enum\Color;
use App\Enum\Format;
use App\Enum\LegalityStatus;

/**
 * Convertit un objet carte Scryfall en lignes pour nos tables.
 *
 * @see https://scryfall.com/docs/api/cards
 */
final class CardMapper
{
    /** Layouts qui ne sont pas de vraies cartes jouables. */
    private const array SKIPPED_LAYOUTS = ['art_series'];

    /** Traitements d'illustration qu'on évite pour l'image « par défaut » d'une carte. */
    private const array FANCY_FRAME_EFFECTS = ['showcase', 'extendedart', 'etched', 'inverted', 'fullart'];

    /** Types de promo désignant un traitement spécial (en plus de tous les « …foil »), pas un simple produit d'origine. */
    private const array TREATMENT_PROMO_TYPES = ['boosterfun', 'serialized', 'poster', 'textured', 'gilded', 'oilslick', 'neonink', 'stepandcompleat', 'concept', 'embossed', 'invisibleink'];

    /**
     * @param array<string, mixed> $card
     */
    public function map(array $card): ?MappedCard
    {
        if (\in_array($card['layout'], self::SKIPPED_LAYOUTS, true)) {
            return null;
        }

        // Une carte « reversible » imprime deux fois la même carte : c'est la face qui porte les données oracle.
        $isReversible = 'reversible_card' === $card['layout'];
        $faces = $card['card_faces'] ?? [];
        $source = $isReversible ? $faces[0] + $card : $card;
        $oracleId = $source['oracle_id'] ?? null;
        if (null === $oracleId) {
            return null;
        }
        $oracleFaces = $isReversible ? [] : $faces;

        $releasedAt = $card['released_at'];
        $imageUris = $card['image_uris'] ?? (array_is_list($faces) && isset($faces[0]['image_uris']) ? array_column($faces, 'image_uris') : []);
        $imageRank = $this->imageRank($card, $imageUris);
        $mainImage = array_is_list($imageUris) ? ($imageUris[0] ?? []) : $imageUris;

        return new MappedCard(
            set: [
                'id' => $card['set_id'],
                'code' => $card['set'],
                'name' => $card['set_name'],
                'set_type' => $card['set_type'],
            ],
            oracle: [
                'id' => $oracleId,
                'name' => $source['name'],
                'layout' => $source['layout'] ?? $card['layout'],
                'mana_cost' => $source['mana_cost'] ?? $this->joinFaces($oracleFaces, 'mana_cost', ' // '),
                'cmc' => (float) ($source['cmc'] ?? 0),
                'type_line' => $source['type_line'] ?? $this->joinFaces($oracleFaces, 'type_line', ' // ') ?? '',
                'oracle_text' => $source['oracle_text'] ?? $this->joinFaces($oracleFaces, 'oracle_text', "\n//\n"),
                'power' => $source['power'] ?? $faces[0]['power'] ?? null,
                'toughness' => $source['toughness'] ?? $faces[0]['toughness'] ?? null,
                'loyalty' => $source['loyalty'] ?? $faces[0]['loyalty'] ?? null,
                'defense' => $source['defense'] ?? $faces[0]['defense'] ?? null,
                'colors' => Color::maskFrom($source['colors'] ?? array_merge(...array_map(fn (array $f) => $this->faceColors($f), $faces ?: [[]]))),
                'color_identity' => Color::maskFrom($card['color_identity'] ?? []),
                'keywords' => json_encode($card['keywords'] ?? [], \JSON_THROW_ON_ERROR),
                'produced_mana' => json_encode($card['produced_mana'] ?? [], \JSON_THROW_ON_ERROR),
                'edhrec_rank' => $card['edhrec_rank'] ?? null,
                'reserved' => $card['reserved'] ?? false,
                'game_changer' => $card['game_changer'] ?? false,
                'first_printed_at' => $releasedAt,
                'image_uri' => null !== $imageRank ? ($mainImage['normal'] ?? null) : null,
                'art_crop_uri' => null !== $imageRank ? ($mainImage['art_crop'] ?? null) : null,
                'default_printing_id' => null !== $imageRank ? $card['id'] : null,
                'image_rank' => $imageRank,
            ],
            faces: array_map(fn (array $face, int $position) => [
                'oracle_card_id' => $oracleId,
                'position' => $position,
                'name' => $face['name'],
                'mana_cost' => ($face['mana_cost'] ?? '') ?: null,
                'type_line' => $face['type_line'] ?? null,
                'oracle_text' => $face['oracle_text'] ?? null,
                'power' => $face['power'] ?? null,
                'toughness' => $face['toughness'] ?? null,
                'loyalty' => $face['loyalty'] ?? null,
                'defense' => $face['defense'] ?? null,
                'colors' => Color::maskFrom($this->faceColors($face)),
            ], $oracleFaces, array_keys($oracleFaces)),
            printing: [
                'id' => $card['id'],
                'oracle_card_id' => $oracleId,
                'set_id' => $card['set_id'],
                'collector_number' => $card['collector_number'],
                'lang' => $card['lang'],
                'rarity' => $card['rarity'],
                'artist' => $card['artist'] ?? null,
                'flavor_text' => $card['flavor_text'] ?? $this->joinFaces($faces, 'flavor_text', "\n//\n"),
                'image_uris' => json_encode($imageUris, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
                'prices' => json_encode($card['prices'] ?? [], \JSON_THROW_ON_ERROR),
                'finishes' => json_encode($card['finishes'] ?? [], \JSON_THROW_ON_ERROR),
                'released_at' => $releasedAt,
                'promo' => $card['promo'] ?? false,
                'full_art' => $card['full_art'] ?? false,
                'digital' => $card['digital'] ?? false,
                'frame' => $card['frame'] ?? '',
                'border_color' => $card['border_color'] ?? '',
                'extra' => $this->isExtra($card),
                'scryfall_uri' => $card['scryfall_uri'] ?? '',
                // Écrit seulement à l'insertion, puis remplacé par la date d'aperçu si elle apparaît (voir CardImporter)
                'revealed_at' => $card['preview']['previewed_at'] ?? date('Y-m-d'),
                'previewed_at' => $card['preview']['previewed_at'] ?? null,
                'preview_source' => isset($card['preview']['source']) ? mb_substr($card['preview']['source'], 0, 100) : null,
                'preview_source_uri' => isset($card['preview']['source_uri']) ? mb_substr($card['preview']['source_uri'], 0, 255) : null,
            ],
            legalities: $this->mapLegalities($oracleId, $card['legalities'] ?? []),
        );
    }

    /**
     * Rang servant à choisir l'image affichée pour la carte oracle : la plus récente,
     * en privilégiant une impression papier anglaise classique. Null si l'impression n'a pas d'image exploitable.
     *
     * @param array<string, mixed>                                $card
     * @param array<string, string>|list<array<string, string>> $imageUris
     */
    private function imageRank(array $card, array $imageUris): ?int
    {
        $status = $card['image_status'] ?? 'missing';
        if (!$imageUris || \in_array($status, ['missing', 'placeholder'], true)) {
            return null;
        }

        $classic = 'en' === $card['lang']
            && !($card['digital'] ?? false)
            && !($card['promo'] ?? false)
            && !($card['oversized'] ?? false)
            && !($card['full_art'] ?? false)
            && !($card['textless'] ?? false)
            && !($card['variation'] ?? false)
            && 'borderless' !== ($card['border_color'] ?? null)
            && !array_intersect($card['frame_effects'] ?? [], self::FANCY_FRAME_EFFECTS)
            && !\in_array($card['set_type'], ['memorabilia', 'funny', 'promo'], true);

        return ($classic ? 100_000_000 : 0) + (int) str_replace('-', '', $card['released_at']);
    }

    /**
     * Impression « extra » d'une édition : variante d'illustration ou de traitement (sans bordure, showcase,
     * extended art, foil spécial, numérotée…) ou promo, par opposition aux cartes de la liste principale.
     *
     * @param array<string, mixed> $card
     */
    private function isExtra(array $card): bool
    {
        $promoTypes = $card['promo_types'] ?? [];

        return ($card['promo'] ?? false)
            || ($card['variation'] ?? false)
            || ($card['textless'] ?? false)
            || 'borderless' === ($card['border_color'] ?? null)
            || array_intersect($card['frame_effects'] ?? [], self::FANCY_FRAME_EFFECTS)
            // Les terrains de base full-art font souvent partie de la liste principale
            || (($card['full_art'] ?? false) && !str_contains($card['type_line'] ?? '', 'Basic Land'))
            || array_intersect($promoTypes, self::TREATMENT_PROMO_TYPES)
            || array_filter($promoTypes, static fn (string $type) => str_ends_with($type, 'foil'));
    }

    /**
     * @param array<string, mixed> $face
     *
     * @return list<string>
     */
    private function faceColors(array $face): array
    {
        if (isset($face['colors'])) {
            return $face['colors'];
        }
        // Les faces de cartes split n'ont pas de champ colors : on les déduit du coût
        preg_match_all('/[WUBRG]/', $face['mana_cost'] ?? '', $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @param list<array<string, mixed>> $faces
     */
    private function joinFaces(array $faces, string $field, string $glue): ?string
    {
        $values = array_filter(array_column($faces, $field), static fn ($v) => null !== $v && '' !== $v);

        return $values ? implode($glue, $values) : null;
    }

    /**
     * @param array<string, string> $legalities
     *
     * @return list<array<string, string>>
     */
    private function mapLegalities(string $oracleId, array $legalities): array
    {
        $rows = [];
        foreach ($legalities as $format => $status) {
            // Scryfall ajoute régulièrement des formats : on ignore ceux qu'on ne gère pas
            if (null === Format::tryFrom($format) || null === LegalityStatus::tryFrom($status)) {
                continue;
            }
            $rows[] = ['oracle_card_id' => $oracleId, 'format' => $format, 'status' => $status];
        }

        return $rows;
    }
}
