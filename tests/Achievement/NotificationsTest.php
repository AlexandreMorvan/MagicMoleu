<?php

namespace App\Tests\Achievement;

use App\Achievement\NotificationCenter;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Twig\Components\NotificationBell;
use App\Twig\Components\NotificationToasts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class NotificationsTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testBellShowsUnreadAndToastsOnlyOnce(): void
    {
        $client = self::createClient();
        $user = $this->createUserWithNotification();
        $client->loginUser($user);

        $toasts = $this->createLiveComponent(NotificationToasts::class, client: $client)->actingAs($user);
        self::assertStringContainsString('id="toast-', $toasts->render()->toString());
        self::assertStringNotContainsString('id="toast-', $toasts->refresh()->render()->toString(), 'un toast ne s\'affiche qu\'une fois');

        $component = $this->createLiveComponent(NotificationBell::class, client: $client)->actingAs($user);
        self::assertStringContainsString('Notifications (2 non lues)', $component->render()->toString(), 'la notification de test et le succès « Bienvenue » débloqué à la première requête');

        $component->call('markAllRead');
        self::assertSame(0, self::getContainer()->get(NotificationRepository::class)->countUnreadFor($user));
    }

    public function testNotificationsPageMarksAllRead(): void
    {
        $client = self::createClient();
        $user = $this->createUserWithNotification();
        $client->loginUser($user);

        $client->request('GET', '/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Nouvelle extension');
        self::assertSame(0, self::getContainer()->get(NotificationRepository::class)->countUnreadFor($user));
    }

    public function testRegistrationAndProfileShowWelcomeAchievement(): void
    {
        $client = self::createClient();
        $user = $this->createUserWithNotification();
        $client->loginUser($user);

        // La première page de la journée lance la vérification des succès (dont « Bienvenue »)
        $client->request('GET', '/profil');

        self::assertSelectorTextContains('#succes', '1 / 13 débloqués');
        self::assertSelectorTextContains('#succes', 'Bienvenue, planeswalker');
    }

    private function createUserWithNotification(): User
    {
        $container = self::getContainer();
        $user = (new User())->setUsername('Saheeli')->setEmail('saheeli@example.com')->setPassword('x');
        $em = $container->get(EntityManagerInterface::class);
        $em->persist($user);
        $container->get(NotificationCenter::class)->notify($user, 'Nouvelle extension', 'Les spoilers sont arrivés !');
        $em->flush();

        return $user;
    }
}
