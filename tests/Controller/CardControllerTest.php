<?php

namespace App\Tests\Controller;

use App\Entity\OracleCard;
use App\Repository\OracleCardRepository;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Twig\Components\CardSearch;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class CardControllerTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
    }

    public function testSearchPageListsCardsLinkingToTheModal(): void
    {
        $crawler = $this->client->request('GET', '/cartes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-controller~="live"]', '10 cartes');
        self::assertCount(10, $crawler->filter('a[data-turbo-frame="card_modal"]'));
    }

    public function testSearchComponentFiltersAndPaginates(): void
    {
        $component = $this->createLiveComponent(CardSearch::class, client: $this->client);

        $html = $component->set('colors', ['C'])->render()->toString();
        self::assertStringContainsString('1 carte', $html);
        self::assertStringContainsString('Sol Ring', $html);

        $component->call('resetFilters');
        $component->call('setView', ['view' => 'list']);
        self::assertStringContainsString('<table', $component->render()->toString());
    }

    public function testLegalitiesIncludeDuelCommanderWithItsOwnBanList(): void
    {
        $crawler = $this->client->request('GET', '/cartes/'.$this->card('Sol Ring')->getId(), server: ['HTTP_TURBO_FRAME' => 'card_modal']);

        $legalities = [];
        foreach ($crawler->filter('ul li span.block') as $format) {
            $legalities[$format->textContent] = trim($format->nextSibling->nextSibling->textContent);
        }
        self::assertSame('Légale', $legalities['Commander']);
        self::assertSame('Bannie', $legalities['Duel Commander'], 'Sol Ring est banni en Duel Commander');
    }

    public function testShowRendersFrameForModalRequests(): void
    {
        $bolt = $this->card('Lightning Bolt');

        // La modale ajoute la version du site à l'URL (contrôleur Stimulus « modal »)
        $version = self::getContainer()->getParameter('app.build_version');
        $this->client->request('GET', '/cartes/'.$bolt->getId().'?v='.$version, server: ['HTTP_TURBO_FRAME' => 'card_modal']);

        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertStringStartsWith('<turbo-frame id="card_modal">', trim($html));
        self::assertStringNotContainsString('<html', $html);
        self::assertSelectorTextContains('h2', 'Lightning Bolt');
        self::assertSelectorTextContains('ul', 'Modern');
        self::assertSelectorExists('a[aria-current="true"]');

        // Le fragment de la modale est mis en cache par le navigateur
        $response = $this->client->getResponse();
        self::assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', $response->headers->get('Cache-Control'));
        self::assertContains('Turbo-Frame', $response->getVary());
    }

    public function testModalIsNotCachedWithoutTheCurrentSiteVersion(): void
    {
        $bolt = $this->card('Lightning Bolt');

        foreach (['', '?v=ancienne-version'] as $query) {
            $this->client->request('GET', '/cartes/'.$bolt->getId().$query, server: ['HTTP_TURBO_FRAME' => 'card_modal']);

            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('max-age=86400', $this->client->getResponse()->headers->get('Cache-Control'), $query ?: 'sans version');
        }
    }

    public function testModalSendsTheSiteVersion(): void
    {
        $this->client->request('GET', '/cartes');

        self::assertSelectorExists(\sprintf('dialog[data-modal-version-value="%s"]', self::getContainer()->getParameter('app.build_version')));
    }

    public function testShowFullPageWithSelectedPrinting(): void
    {
        $delver = $this->card('Delver of Secrets // Insectile Aberration');
        $printing = $delver->getPrintings()->first();

        $crawler = $this->client->request('GET', \sprintf('/cartes/%s?impression=%s', $delver->getId(), $printing->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Delver of Secrets');
        self::assertCount(2, $crawler->filter('img[alt^="Delver of Secrets"]'), 'les deux faces sont affichées');
        self::assertSelectorTextContains('section h3', 'Delver of Secrets');
    }

    public function testUnknownCardIs404(): void
    {
        $this->client->request('GET', '/cartes/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNavigationLinksWork(): void
    {
        $crawler = $this->client->request('GET', '/');
        $links = $crawler->filter('header nav a.nav-link')->each(fn ($a) => $a->attr('href'));

        self::assertSame(['/', '/cartes', '/editions', '/decks', '/spoilers', '/partie', '/connexion'], $links);
        foreach ($links as $href) {
            $this->client->request('GET', $href);
            self::assertResponseIsSuccessful($href);
        }
    }

    private function card(string $name): OracleCard
    {
        return self::getContainer()->get(OracleCardRepository::class)->findOneBy(['name' => $name]);
    }
}
