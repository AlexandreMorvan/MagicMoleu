<?php

namespace App\Tests\Spoiler;

use App\Scryfall\BulkUpserter;
use App\Scryfall\CardImporter;
use App\Scryfall\SetImporter;
use App\Twig\Components\SetCards;
use App\Twig\Components\SpoilerGallery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class SpoilerAndSetPagesTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use UpcomingSetTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $container = self::getContainer();
        $container->get(BulkUpserter::class)->upsert('mtg_set', [$container->get(SetImporter::class)->map(self::upcomingSet())], ['id']);
        $container->get(CardImporter::class)->import(self::upcomingCards());
    }

    public function testSpoilersPageGroupsCardsByRevealDay(): void
    {
        $crawler = $this->client->request('GET', '/spoilers');

        self::assertResponseIsSuccessful();
        $days = $crawler->filter('section h2')->each(fn ($h) => trim(preg_replace('/\s+/', ' ', $h->text())));
        self::assertSame('Aujourd\'hui', $days[0]);
        self::assertCount(2, $days, 'Lightning Bolt a été révélé il y a deux jours');
        self::assertCount(2, $crawler->filter('section')->first()->filter('li'), 'Counterspell (aperçu du jour) et Sol Ring (vu aujourd\'hui)');
        self::assertCount(2, $crawler->filter('.badge:contains("Nouveau")'));
        self::assertSelectorTextContains('button[data-live-code-param="tst"]', 'Test Set');
        self::assertSelectorExists('a[href="https://example.com/preview"]');
    }

    public function testSpoilerGalleryFilters(): void
    {
        $component = $this->createLiveComponent(SpoilerGallery::class, client: $this->client);

        $html = $component->set('rarities', ['mythic'])->render()->toString();
        self::assertStringContainsString('Counterspell', $html);
        self::assertStringNotContainsString('Sol Ring', $html);

        $html = $component->set('rarities', [])->set('colors', ['C'])->render()->toString();
        self::assertStringContainsString('Sol Ring', $html);
        self::assertStringNotContainsString('Counterspell', $html);
    }

    public function testSetPagesAndFilters(): void
    {
        $this->client->request('GET', '/editions');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/editions/tst"]');

        $crawler = $this->client->request('GET', '/editions/tst');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Test Set');
        self::assertSelectorTextContains('main header', 'À venir');
        self::assertSame(['#1', '#2', '#10'], $crawler->filter('li p.text-xs')->each(fn ($p) => trim($p->text())), 'tri par numéro de collection');

        $set = self::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\MtgSet::class)->findOneBy(['code' => 'tst']);
        $component = $this->createLiveComponent(SetCards::class, ['set' => $set], $this->client);

        $html = $component->set('colors', ['U', 'R'])->render()->toString();
        self::assertStringContainsString('Lightning Bolt', $html);
        self::assertStringContainsString('Counterspell', $html, 'plusieurs couleurs : l\'une ou l\'autre');
        self::assertStringNotContainsString('Sol Ring', $html);

        $html = $component->set('colors', [])->set('rarities', ['common'])->render()->toString();
        self::assertStringContainsString('Sol Ring', $html);
        self::assertStringNotContainsString('Lightning Bolt', $html);
    }

    public function testUnknownSetIs404(): void
    {
        $this->client->request('GET', '/editions/zzzz');

        self::assertResponseStatusCodeSame(404);
    }
}
