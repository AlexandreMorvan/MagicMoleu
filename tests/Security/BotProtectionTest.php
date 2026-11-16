<?php

namespace App\Tests\Security;

use App\Repository\OracleCardRepository;
use App\Repository\UserRepository;
use App\Scryfall\CardImporter;
use App\Scryfall\JsonlReader;
use App\Twig\Components\RegistrationForm;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Limites de débit, champ piège, en-têtes de sécurité et hôtes de confiance.
 */
class BotProtectionTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Les compteurs des limiteurs vivent en mémoire le temps du noyau : il ne doit pas redémarrer entre deux requêtes
        $this->client->disableReboot();
    }

    public function testRegistrationIsLimitedPerIp(): void
    {
        foreach (['Ajani', 'Basri', 'Chandra'] as $name) {
            $this->register($name);
            self::assertResponseRedirects('/profil', message: $name);
        }

        $html = $this->register('Dovin');

        self::assertStringContainsString('Trop de comptes créés depuis ta connexion', $html);
        self::assertNull($this->users()->findOneBy(['username' => 'Dovin']));
        self::assertSame(3, $this->users()->count());
    }

    public function testHoneypotRejectsBots(): void
    {
        $html = $this->register('Robot', website: 'https://spam.example');

        self::assertStringContainsString('Inscription refusée.', $html);
        self::assertSame(0, $this->users()->count());
    }

    public function testHoneypotIsHiddenAndNotRightBeforeThePasswordFields(): void
    {
        $crawler = $this->client->request('GET', '/inscription');

        self::assertSelectorExists('.sr-only[aria-hidden="true"] input[name="registration_form[website]"]');
        // Les navigateurs remplissent automatiquement le champ texte qui précède le mot de passe (l'identifiant) :
        // le champ piège doit venir après les mots de passe pour ne pas être rempli à la place d'un humain
        $fields = $crawler->filter('form[name="registration_form"] input:not([type="hidden"])')->each(fn ($i) => $i->attr('name'));
        self::assertGreaterThan(array_search('registration_form[plainPassword][second]', $fields, true), array_search('registration_form[website]', $fields, true));
    }

    public function testFilledHoneypotIsNotReportedWhileTyping(): void
    {
        $component = $this->createLiveComponent(RegistrationForm::class, client: $this->client);

        // Validation en direct (changement de champ) avec le champ piège rempli par un gestionnaire de mots de passe
        $html = $component->set('registration_form.website', 'Chandra')->set('registration_form.username', 'Chandra')->render()->toString();

        self::assertStringNotContainsString('Inscription refusée', $html);
    }

    public function testPasswordResetRequestsAreLimitedPerIp(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->client->request('GET', '/mot-de-passe-oublie');
            $this->client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => "inconnu$i@example.com"]);
            self::assertResponseRedirects('/mot-de-passe-oublie/email-envoye');
        }

        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => 'inconnu6@example.com']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Trop de demandes depuis ta connexion');
    }

    public function testCombosPagesAreLimitedPerIp(): void
    {
        self::getContainer()->get(CardImporter::class)->import(JsonlReader::read(__DIR__.'/../fixtures/scryfall_cards.jsonl'));
        self::getContainer()->set('commander_spellbook.client', new MockHttpClient(
            new JsonMockResponse(['next' => null, 'results' => []]),
            'https://backend.commanderspellbook.com',
        ));
        $url = '/cartes/'.self::getContainer()->get(OracleCardRepository::class)->findOneBy(['name' => 'Sol Ring'])->getId().'/combos';

        for ($i = 1; $i <= 30; ++$i) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful("requête $i");
        }

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(429);
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
    }

    public function testSecurityHeaders(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertStringContainsString("frame-ancestors 'none'", $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertResponseNotHasHeader('Strict-Transport-Security', 'HSTS uniquement en production');
    }

    public function testUntrustedHostIsRejected(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie', server: ['HTTP_HOST' => 'attaquant.example']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testRobotsTxtExcludesPrivateAndCostlyPages(): void
    {
        $robots = (string) file_get_contents(__DIR__.'/../../public/robots.txt');

        self::assertStringContainsString('Disallow: /admin', $robots);
        self::assertStringContainsString('Disallow: /cartes/*/combos', $robots);
    }

    private function register(string $username, string $website = ''): string
    {
        $component = $this->createLiveComponent(RegistrationForm::class, client: $this->client);
        // Formulaire invalide : la réponse 422 est rendue au lieu de lever l'exception
        $this->client->catchExceptions(true);
        $component->submitForm(['registration_form' => [
            'username' => $username,
            'email' => strtolower($username).'@example.com',
            'website' => $website,
            'plainPassword' => ['first' => 'motdepasse123', 'second' => 'motdepasse123'],
        ]], 'save');

        return (string) $this->client->getResponse()->getContent();
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }
}
