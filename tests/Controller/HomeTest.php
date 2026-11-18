<?php

namespace App\Tests\Controller;

use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Scryfall\LocalizationImporter;
use App\Twig\Components\QuickSearch;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class HomeTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testHomePageShowsStats(): void
    {
        $client = self::createClient();
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section.grid', '10');
        self::assertPageTitleSame('Le Grenier à Mana');
        self::assertSelectorTextContains('header nav', 'Le Grenier à Mana');
    }

    public function testQuickSearchFindsCardsByFrenchOrEnglishName(): void
    {
        $container = self::getContainer();
        $container->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        $container->get(LocalizationImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards_fr.jsonl'), 'fr');

        $component = $this->createLiveComponent(QuickSearch::class);

        $html = $component->set('query', 'foudre')->render()->toString();
        self::assertStringContainsString('Lightning Bolt', $html);

        $html = $component->set('query', 'sol r')->render()->toString();
        self::assertStringContainsString('Sol Ring', $html);
        self::assertStringNotContainsString('Lightning Bolt', $html);

        $html = $component->set('query', 'zzzz')->render()->toString();
        self::assertStringContainsString('Aucune carte', $html);
    }
}
