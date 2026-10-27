<?php

namespace App\Search;

/**
 * Critères de recherche de cartes, tels que saisis dans le formulaire.
 */
final class CardSearchCriteria
{
    public const string COLORS_INCLUDE = 'include';
    public const string COLORS_EXACT = 'exact';
    public const string COLORS_AT_MOST = 'atmost';

    public const array SORTS = ['name', 'cmc', 'edhrec', 'recent'];

    /**
     * @param list<string> $colors   symboles W, U, B, R, G et C (incolore)
     * @param list<string> $rarities valeurs de {@see \App\Enum\Rarity}
     */
    public function __construct(
        public string $name = '',
        public string $text = '',
        public string $type = '',
        public array $colors = [],
        public string $colorMode = self::COLORS_INCLUDE,
        public bool $useColorIdentity = false,
        public ?float $cmcMin = null,
        public ?float $cmcMax = null,
        public ?int $powerMin = null,
        public ?int $powerMax = null,
        public ?int $toughnessMin = null,
        public ?int $toughnessMax = null,
        public array $rarities = [],
        public string $set = '',
        public string $format = '',
        public string $legality = 'legal',
        public string $keyword = '',
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {
    }

    /**
     * Découpe une saisie en termes, en gardant les expressions entre guillemets : `"draw a card" flying`.
     *
     * @return list<string>
     */
    public static function terms(string $input): array
    {
        preg_match_all('/"([^"]*)"|(\S+)/u', $input, $matches, \PREG_SET_ORDER);

        // Groupe 2 : mot isolé ; groupe 1 : expression entre guillemets (le groupe 2 est alors absent)
        $terms = array_map(static fn (array $m) => trim($m[2] ?? $m[1]), $matches);

        return array_values(array_filter($terms, static fn (string $t) => '' !== $t));
    }
}
