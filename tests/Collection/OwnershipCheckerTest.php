<?php

namespace App\Tests\Collection;

use App\Collection\OwnershipChecker;
use App\Entity\CardCollection;
use App\Entity\User;
use App\Enum\DeckZone;
use App\Enum\Format;
use App\Repository\PreconDeckRepository;
use App\Tests\Deck\DeckTestTrait;
use App\Tests\Precon\PreconTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OwnershipCheckerTest extends KernelTestCase
{
    use DeckTestTrait;
    use PreconTestTrait;

    private User $user;

    protected function setUp(): void
    {
        self::importDeckFixtures();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->user = (new User())->setUsername('Nissa')->setEmail('nissa@example.com')->setPassword('x');
        $em->persist($this->user);

        // Deux collections : les exemplaires s'additionnent
        $binder = new CardCollection($this->user);
        $binder->setName('Classeur');
        $binder->addCard(self::oracle('Thrasios, Triton Hero')->getPrintings()->first(), 1);
        $binder->addCard(self::oracle('Sol Ring')->getPrintings()->first(), 1);
        $box = new CardCollection($this->user);
        $box->setName('Boîte');
        $box->addCard(self::oracle('Sol Ring')->getPrintings()->first(), 1, foil: true);
        $em->persist($binder);
        $em->persist($box);
        $em->flush();
    }

    public function testDeckOwnership(): void
    {
        $deck = self::buildDeck(Format::Commander, [
            'Thrasios, Triton Hero' => [1, DeckZone::Commander],
            'Tymna the Weaver' => [1, DeckZone::Commander],
            'Sol Ring' => 3,
            'Forest' => 95,
            'Counterspell' => [1, DeckZone::Maybe],
        ]);

        $ownership = self::getContainer()->get(OwnershipChecker::class)->forDeck($deck, $this->user);

        // Terrains de base et zone « peut-être » ignorés : 1 Thrasios + 1 Tymna + 3 Sol Ring
        self::assertSame(5, $ownership->total);
        self::assertSame(3, $ownership->owned, 'Thrasios + 2 Sol Ring répartis dans deux collections');
        self::assertSame(60, $ownership->getPercent());
        self::assertFalse($ownership->isComplete());
        $missing = [];
        foreach ($ownership->missing as $line) {
            $missing[$line->card->getName()] = $line->quantity;
        }
        self::assertEqualsCanonicalizing(['Sol Ring' => 1, 'Tymna the Weaver' => 1], $missing);
    }

    public function testPreconPercentagesForTheCatalog(): void
    {
        $this->importPrecons();
        $repository = self::getContainer()->get(PreconDeckRepository::class);
        $breed = $repository->findOneBy(['code' => 'BreedLethality_C16']);
        $party = $repository->findOneBy(['code' => 'PartyTime_CLB']);
        $checker = self::getContainer()->get(OwnershipChecker::class);

        $percentages = $checker->percentages($repository->findCardLines([$breed->getId(), $party->getId()]), $this->user);

        // Breed Lethality : Thrasios, Tymna, Counterspell (les forêts ne comptent pas) → 1 sur 3
        self::assertSame([$breed->getId() => 33, $party->getId() => 0], $percentages);
        self::assertSame(33, $checker->forPrecon($breed, $this->user)->getPercent(), 'même calcul sur la fiche du deck');
    }
}
