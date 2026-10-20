<?php

namespace App\Tests\Search;

use App\Entity\OracleCard;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\LocalizationImporter;
use App\Search\CardSearch;
use App\Search\CardSearchCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Cartes du jeu de test : Lightning Bolt (R), Delver of Secrets (U), Fire // Ice (RU),
 * Bonecrusher Giant (R), Sol Ring (incolore), Shatterskull Smashing (R), Jace, the Mind Sculptor (U),
 * Arlinn (RG), Adrix and Nev (GU), Badgermole Cub (G, bannie en Standard).
 */
class CardSearchTest extends KernelTestCase
{
    protected function setUp(): void
    {
        $container = self::getContainer();
        $container->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        $container->get(LocalizationImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards_fr.jsonl'), 'fr');
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('criteriaProvider')]
    public function testFilters(CardSearchCriteria $criteria, array $expected): void
    {
        $result = self::getContainer()->get(CardSearch::class)->search($criteria);

        $names = array_map(static fn (OracleCard $c) => $c->getName(), $result->cards);
        sort($names);
        sort($expected);
        self::assertSame($expected, $names);
        self::assertSame(\count($expected), $result->total);
    }

    public static function criteriaProvider(): iterable
    {
        $red = ['Bonecrusher Giant // Stomp', 'Lightning Bolt', 'Shatterskull Smashing // Shatterskull, the Hammer Pass'];

        yield 'nom anglais' => [new CardSearchCriteria(name: 'bolt'), ['Lightning Bolt']];
        yield 'nom français' => [new CardSearchCriteria(name: 'foudre'), ['Lightning Bolt']];
        yield 'texte de règles, expression' => [new CardSearchCriteria(text: '"3 damage to any target"'), ['Lightning Bolt']];
        yield 'texte de règles en français' => [new CardSearchCriteria(text: 'blessures'), ['Lightning Bolt']];
        yield 'type, plusieurs mots' => [new CardSearchCriteria(type: 'legendary planeswalker'), ['Arlinn, the Pack\'s Hope // Arlinn, the Moon\'s Fury', 'Jace, the Mind Sculptor']];
        yield 'couleurs exactes' => [new CardSearchCriteria(colors: ['R'], colorMode: CardSearchCriteria::COLORS_EXACT), $red];
        yield 'au moins rouge' => [new CardSearchCriteria(colors: ['R']), [...$red, 'Arlinn, the Pack\'s Hope // Arlinn, the Moon\'s Fury', 'Fire // Ice']];
        yield 'au plus vert-bleu (identité)' => [
            new CardSearchCriteria(colors: ['G', 'U'], colorMode: CardSearchCriteria::COLORS_AT_MOST, useColorIdentity: true),
            ['Adrix and Nev, Twincasters', 'Badgermole Cub', 'Delver of Secrets // Insectile Aberration', 'Jace, the Mind Sculptor', 'Sol Ring'],
        ];
        yield 'incolore' => [new CardSearchCriteria(colors: ['C']), ['Sol Ring']];
        yield 'valeur de mana' => [new CardSearchCriteria(cmcMin: 4, cmcMax: 4), ['Arlinn, the Pack\'s Hope // Arlinn, the Moon\'s Fury', 'Adrix and Nev, Twincasters', 'Fire // Ice', 'Jace, the Mind Sculptor']];
        yield 'force minimum' => [new CardSearchCriteria(powerMin: 4), ['Bonecrusher Giant // Stomp']];
        yield 'rareté' => [new CardSearchCriteria(rarities: ['uncommon']), ['Sol Ring']];
        yield 'édition' => [new CardSearchCriteria(set: 'M10'), ['Lightning Bolt']];
        yield 'banni en Standard' => [new CardSearchCriteria(format: 'standard', legality: 'banned'), ['Badgermole Cub']];
        yield 'mot-clé' => [new CardSearchCriteria(keyword: 'ward'), ['Adrix and Nev, Twincasters']];
    }

    public function testSortAndPagination(): void
    {
        $search = self::getContainer()->get(CardSearch::class);

        $page = $search->search(new CardSearchCriteria(sort: 'cmc', direction: 'desc'), 1, 3);
        self::assertSame(10, $page->total);
        self::assertSame(4, $page->pageCount());
        self::assertCount(3, $page->cards);
        self::assertSame(4.0, $page->cards[0]->getCmc());

        $last = $search->search(new CardSearchCriteria(sort: 'cmc', direction: 'desc'), 4, 3);
        self::assertCount(1, $last->cards);
    }
}
