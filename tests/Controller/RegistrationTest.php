<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Twig\Components\RegistrationForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class RegistrationTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testRegisterThenLogout(): void
    {
        $client = self::createClient();
        $client->request('GET', '/inscription');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller~="live"]');

        $component = $this->createLiveComponent(RegistrationForm::class, client: $client);
        $component->submitForm($this->formData('Chandra', 'chandra@example.com'), 'save');

        self::assertResponseRedirects('/profil');
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'Chandra']);
        self::assertNotNull($user);
        self::assertNotSame('motdepasse123', $user->getPassword(), 'le mot de passe est haché');

        // L'inscription connecte directement l'utilisateur
        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Chandra');
    }

    public function testLiveValidationRejectsTakenUsernameAndMismatchedPasswords(): void
    {
        $client = self::createClient();
        $this->createUser('Jace', 'jace@example.com');

        $component = $this->createLiveComponent(RegistrationForm::class, client: $client);
        $data = $this->formData('Jace', 'autre@example.com');
        $data['registration_form']['plainPassword']['second'] = 'different123';

        $client->catchExceptions(true);
        $component->submitForm($data, 'save');

        self::assertResponseStatusCodeSame(422);
        $html = $component->render()->toString();
        self::assertStringContainsString('Ce pseudo est déjà pris.', $html);
        self::assertStringContainsString('Les deux mots de passe ne correspondent pas.', $html);
        self::assertSame(1, self::getContainer()->get(UserRepository::class)->count());
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = self::createClient();
        $this->createUser('Liliana', 'liliana@example.com');

        $client->request('GET', '/connexion');
        // Le jeton CSRF « stateless » est validé via l'en-tête Origin quand le JS n'est pas là
        $client->submitForm('Se connecter', [
            '_username' => 'liliana@example.com',
            '_password' => 'motdepasse123',
        ], serverParameters: ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/profil');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Liliana');
    }

    public function testLoginWithWrongPassword(): void
    {
        $client = self::createClient();
        $this->createUser('Gideon', 'gideon@example.com');

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            '_username' => 'gideon@example.com',
            '_password' => 'mauvais',
        ], serverParameters: ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/connexion');
        $client->followRedirect();
        self::assertSelectorExists('form .text-mana-r');
    }

    public function testProfileRequiresLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/profil');

        self::assertResponseRedirects('/connexion');
    }

    /**
     * @return array{registration_form: array<string, mixed>}
     */
    private function formData(string $username, string $email): array
    {
        return ['registration_form' => [
            'username' => $username,
            'email' => $email,
            'plainPassword' => ['first' => 'motdepasse123', 'second' => 'motdepasse123'],
        ]];
    }

    private function createUser(string $username, string $email): User
    {
        $container = self::getContainer();
        $user = (new User())->setUsername($username)->setEmail($email);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'motdepasse123'));

        $em = $container->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
