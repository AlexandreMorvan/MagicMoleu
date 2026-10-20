<?php

namespace App\Tests\Achievement;

use App\Achievement\AchievementChecker;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\Achievement;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\NotificationRepository;
use App\Tests\Deck\DeckTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AchievementCheckerTest extends KernelTestCase
{
    use DeckTestTrait;

    private EntityManagerInterface $em;
    private AchievementChecker $checker;
    private User $user;

    protected function setUp(): void
    {
        self::importDeckFixtures();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->checker = self::getContainer()->get(AchievementChecker::class);
        $this->user = (new User())->setUsername('Teferi')->setEmail('teferi@example.com')->setPassword('x');
        $this->em->persist($this->user);
        $this->em->flush();
    }

    public function testWelcomeThenDeckAchievements(): void
    {
        self::assertSame([Achievement::Welcome], $this->checker->check($this->user));
        self::assertSame([], $this->checker->check($this->user), 'jamais débloqué deux fois');

        $this->persistDeck(Format::Modern, ['Lightning Bolt' => 4]);
        self::assertSame([Achievement::FirstDeck], $this->checker->check($this->user));

        // Deck Commander valide et public : 5 decks au total
        $this->persistDeck(Format::Commander, ['Thrasios, Triton Hero' => [1, DeckZone::Commander], 'Tymna the Weaver' => [1, DeckZone::Commander], 'Forest' => 98], public: true);
        foreach (range(1, 3) as $i) {
            $this->persistDeck(Format::Modern, ['Forest' => 1]);
        }
        self::assertEqualsCanonicalizing(
            [Achievement::FiveDecks, Achievement::FirstValidDeck, Achievement::ValidCommanderDeck, Achievement::PublicDeck],
            $this->checker->check($this->user),
        );
    }

    public function testAnniversaries(): void
    {
        $this->checker->check($this->user);

        $inTwoYears = new \DateTimeImmutable('+2 years +1 day');
        self::assertSame([Achievement::OneYear, Achievement::TwoYears], $this->checker->check($this->user, $inTwoYears));
    }

    public function testUnlockingCreatesNotification(): void
    {
        $this->checker->check($this->user);

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $latest = $notifications->findLatestFor($this->user, 10);
        self::assertCount(1, $latest);
        self::assertSame('Succès débloqué : Bienvenue, planeswalker', $latest[0]->getTitle());
        self::assertSame('/profil#succes', $latest[0]->getLink());
        self::assertSame(1, $notifications->countUnreadFor($this->user));
        self::assertCount(1, $notifications->findToToast($this->user));
    }

    /**
     * @param array<string, int|array{int, DeckZone}> $cards
     */
    private function persistDeck(Format $format, array $cards, bool $public = false): Deck
    {
        $deck = self::buildDeck($format, $cards, $this->user)->setName('Deck')->setPublic($public);
        $this->em->persist($deck);
        $this->em->flush();

        return $deck;
    }
}
