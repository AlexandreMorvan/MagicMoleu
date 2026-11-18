<?php

namespace App\Tests\Account;

use App\Entity\CardCollection;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\DeckView;
use App\Enum\Format;
use App\Repository\CardPrintingRepository;
use App\Repository\DeckRepository;
use App\Repository\UserRepository;
use App\Tests\Deck\DeckTestTrait;
use App\Twig\Components\AvatarPicker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

class AccountTest extends WebTestCase
{
    use DeckTestTrait;
    use InteractsWithLiveComponents;

    private const string PASSWORD = 'ancien-mot-de-passe';

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::importDeckFixtures();
        $this->user = $this->createUser('Chandra');
    }

    public function testSettingsRequireLogin(): void
    {
        $this->client->request('GET', '/profil/parametres');

        self::assertResponseRedirects('/connexion');
    }

    public function testChangeEmailRequiresCurrentPassword(): void
    {
        $this->client->loginUser($this->user);
        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Enregistrer', [
            'account_identity[email]' => 'nalaar@example.com',
            'account_identity[currentPassword]' => 'mauvais',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#identite', 'Mot de passe incorrect.');
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nalaar@example.com']));

        $this->client->submitForm('Enregistrer', [
            'account_identity[email]' => 'nalaar@example.com',
            'account_identity[currentPassword]' => self::PASSWORD,
        ]);
        self::assertResponseRedirects('/profil/parametres#identite');

        // L'email (identifiant de connexion) a changé sans déconnecter l'utilisateur
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Email mis à jour.');
        self::assertSelectorTextContains('main', 'nalaar@example.com');
    }

    public function testUsernameCannotBeChanged(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profil/parametres');
        self::assertSelectorExists('#account_identity_username[disabled]');

        // Même en forgeant la requête, le pseudo envoyé est ignoré
        $this->client->request('POST', '/profil/parametres', ['account_identity' => [
            'username' => 'Gideon',
            'email' => 'chandra@example.com',
            'currentPassword' => self::PASSWORD,
            '_token' => $crawler->filter('#account_identity__token')->attr('value'),
        ]]);
        self::assertResponseRedirects('/profil/parametres#identite');

        $users = self::getContainer()->get(UserRepository::class);
        self::assertNull($users->findOneBy(['username' => 'Gideon']));
        self::assertNotNull($users->findOneBy(['username' => 'Chandra']));
    }

    public function testChangePassword(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profil/parametres');
        $form = $crawler->selectButton('Changer le mot de passe')->form([
            'change_password[currentPassword]' => self::PASSWORD,
            'change_password[plainPassword][first]' => 'nouveau-mot-de-passe',
            'change_password[plainPassword][second]' => 'nouveau-mot-de-passe',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/profil/parametres#mot-de-passe');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful('toujours connecté après le changement de mot de passe');
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($this->reload(), 'nouveau-mot-de-passe'));
    }

    public function testPublicProfileShowsBioColorsAndPublicDecksOnly(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profil/parametres');
        $form = $crawler->filter('#profil form')->form();
        $form['account_profile[bio]'] = 'Je brûle tout.';
        $form['account_profile[favoriteColorList]'][1]->tick(); // U
        $form['account_profile[favoriteColorList]'][3]->tick(); // R
        $form['account_profile[preferredFormat]'] = 'modern';
        $this->client->submit($form);
        self::assertResponseRedirects('/profil/parametres#profil');

        $this->persistDeck('Burn public', true);
        $this->persistDeck('Brouillon secret', false);

        $this->client->request('GET', '/joueurs/Chandra');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main header', 'Izzet');
        self::assertSelectorTextContains('main header', 'Modern');
        self::assertSelectorTextContains('main header', 'Je brûle tout.');
        self::assertSelectorTextContains('main', 'Burn public');
        self::assertSelectorTextNotContains('main', 'Brouillon secret');

        // Le format préféré est proposé à la création d'un deck
        $crawler = $this->client->request('GET', '/decks/nouveau');
        self::assertSame('modern', $crawler->filter('#deck_format option[selected]')->attr('value'));

        $this->client->request('GET', '/joueurs/Inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPreferences(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profil/parametres');
        $form = $crawler->filter('#preferences form')->form();
        $toasts = $form['preferences[notificationToasts]'];
        self::assertInstanceOf(ChoiceFormField::class, $toasts);
        $toasts->untick();
        $form['preferences[defaultDeckView]'] = 'visuelle';
        $form['preferences[defaultCollectionView]'] = 'list';
        $this->client->submit($form);
        self::assertResponseRedirects('/profil/parametres#preferences');

        $this->client->followRedirect();
        self::assertSelectorNotExists('[data-live-name-value="NotificationToasts"]');

        // Vue visuelle par défaut sur un deck, sauf si l'URL en demande une autre
        $deck = $this->persistDeck('Burn', true);
        $crawler = $this->client->request('GET', '/decks/'.$deck->getId());
        self::assertGreaterThan(0, $crawler->filter('main .card-tile')->count());
        $crawler = $this->client->request('GET', '/decks/'.$deck->getId().'?vue=liste');
        self::assertCount(0, $crawler->filter('main .card-tile'));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $collection = (new CardCollection($this->reload()))->setName('Classeur');
        $collection->addCard(self::getContainer()->get(CardPrintingRepository::class)->findDefaultFor(self::oracle('Lightning Bolt')));
        $em->persist($collection);
        $em->flush();
        $this->client->request('GET', '/collections/'.$collection->getId());
        self::assertSelectorExists('main table', 'vue liste par défaut');
    }

    public function testChooseAndClearPlaneswalkerAvatar(): void
    {
        $this->client->loginUser($this->user);
        $html = $this->avatarPicker()->set('query', 'jace')->render()->toString();
        self::assertStringContainsString('Jace, the Mind Sculptor', $html);
        self::assertStringNotContainsString('Lightning Bolt', $html);

        // Chaque action redirige vers « Mon compte » : un composant neuf par action
        $jace = self::oracle('Jace, the Mind Sculptor');
        $this->avatarPicker()->call('choose', ['cardId' => (string) $jace->getId()]);
        self::assertResponseRedirects('/profil/parametres#avatar');
        self::assertSame('Jace, the Mind Sculptor', $this->avatarName());

        // Seuls les planeswalkers sont acceptés
        $this->avatarPicker()->call('choose', ['cardId' => (string) self::oracle('Lightning Bolt')->getId()]);
        self::assertSame('Jace, the Mind Sculptor', $this->avatarName());

        $this->client->request('GET', '/profil/parametres');
        self::assertSelectorExists(\sprintf('header img[src="%s"]', $jace->getArtCropUri()), 'avatar dans le menu');

        $this->avatarPicker()->call('clear');
        self::assertNull($this->reload()->getAvatarCard());
    }

    public function testExportData(): void
    {
        $this->persistDeck('Burn', false);
        $this->client->loginUser($this->user);
        $this->client->request('GET', '/profil/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('attachment; filename="grenier-a-mana-chandra-', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Chandra', $data['account']['username']);
        self::assertSame('Burn', $data['decks'][0]['name']);
        self::assertSame(['zone' => 'main', 'quantity' => 4, 'name' => 'Lightning Bolt'], array_intersect_key($data['decks'][0]['cards'][0], array_flip(['zone', 'quantity', 'name'])));
    }

    public function testDeleteAccount(): void
    {
        $deckId = $this->persistDeck('Burn', true)->getId();
        $userId = $this->user->getId();
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', '/profil/parametres');
        $form = $crawler->filter('#suppression form');
        self::assertSame('Supprimer ton compte « Chandra » ?', $form->attr('data-turbo-confirm'), 'confirmation SweetAlert');

        $this->client->submit($form->form(['delete_account[password]' => 'mauvais']));
        self::assertResponseStatusCodeSame(422);
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->find($userId));

        $crawler = $this->client->request('GET', '/profil/parametres');
        $this->client->submit($crawler->filter('#suppression form')->form(['delete_account[password]' => self::PASSWORD]));
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Le compte « Chandra » et toutes ses données ont été supprimés.');
        self::assertSelectorTextContains('header', 'Connexion', 'déconnecté');

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(UserRepository::class)->find($userId));
        self::assertNull(self::getContainer()->get(DeckRepository::class)->find($deckId));
    }

    public function testForgotPassword(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->click($crawler->selectLink('Mot de passe oublié ?')->link());
        $this->client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => 'chandra@example.com']);
        self::assertResponseRedirects('/mot-de-passe-oublie/email-envoye');

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'chandra@example.com');
        self::assertEmailHtmlBodyContains($email, 'Choisir un nouveau mot de passe');
        self::assertEmailHtmlBodyContains($email, 'LE GRENIER À MANA');
        self::assertEmailAddressContains($email, 'From', 'no-reply@grenieramana.local');
        preg_match('#https?://[^/]+(/mot-de-passe-oublie/reinitialiser/[^"]+)#', (string) $email->getHtmlBody(), $link);

        // Le jeton passe en session et disparaît de l'URL
        $this->client->request('GET', $link[1]);
        self::assertResponseRedirects('/mot-de-passe-oublie/reinitialiser');
        $this->client->followRedirect();
        $this->client->submitForm('Enregistrer le mot de passe', [
            'change_password[plainPassword][first]' => 'mot-de-passe-retrouve',
            'change_password[plainPassword][second]' => 'mot-de-passe-retrouve',
        ]);
        self::assertResponseRedirects('/connexion');
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($this->reload(), 'mot-de-passe-retrouve'));

        // Un lien ne sert qu'une fois
        $this->client->request('GET', $link[1]);
        $this->client->followRedirect();
        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    public function testForgotPasswordDoesNotRevealUnknownEmails(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => 'personne@example.com']);

        self::assertResponseRedirects('/mot-de-passe-oublie/email-envoye');
        self::assertQueuedEmailCount(0);
    }

    private function avatarName(): ?string
    {
        return $this->reload()->getAvatarCard()?->getName();
    }

    private function avatarPicker(): TestLiveComponent
    {
        return $this->createLiveComponent(AvatarPicker::class, [], $this->client)->actingAs($this->reload());
    }

    private function persistDeck(string $name, bool $public): Deck
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        // Après une requête HTTP, le noyau a redémarré : l'utilisateur est rechargé dans le nouveau gestionnaire d'entités
        $owner = $em->find(User::class, $this->user->getId()) ?? throw new \RuntimeException('Utilisateur introuvable.');
        $deck = self::buildDeck(Format::Modern, ['Lightning Bolt' => 4], $owner)->setName($name)->setPublic($public);
        $em->persist($deck);
        $em->flush();

        return $deck;
    }

    private function createUser(string $username): User
    {
        $user = (new User())->setUsername($username)->setEmail(strtolower($username).'@example.com');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function reload(): User
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(UserRepository::class)->find($this->user->getId())
            ?? throw new \RuntimeException('Utilisateur supprimé.');
    }
}
