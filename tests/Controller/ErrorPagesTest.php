<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ErrorPagesTest extends WebTestCase
{
    public function testNotFoundPage(): void
    {
        // Sans le mode debug, Symfony affiche les pages d'erreur de l'application
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/une-page-qui-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page introuvable');
        self::assertSelectorTextContains('.error-card', 'Exil sans retour');
        self::assertSelectorExists('a[href="/"]');
    }

    public function testForbiddenPage(): void
    {
        $client = self::createClient(['debug' => false]);
        $user = (new \App\Entity\User())->setUsername('Curieux')->setEmail('curieux@example.com')->setPassword('x');
        $owner = (new \App\Entity\User())->setUsername('Proprio')->setEmail('proprio@example.com')->setPassword('x');
        $deck = (new \App\Entity\Deck($owner))->setName('Secret');
        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        array_map($em->persist(...), [$user, $owner, $deck]);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/decks/'.$deck->getId());

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.error-card', 'Contresort');
    }
}
