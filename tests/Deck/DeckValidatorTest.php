<?php

namespace App\Tests\Deck;

use App\Deck\DeckValidator;
use App\Enum\DeckZone;
use App\Enum\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeckValidatorTest extends KernelTestCase
{
    use DeckTestTrait;

    protected function setUp(): void
    {
        self::importDeckFixtures();
    }

    /**
     * @param array<string, int|array{int, DeckZone}> $cards
     * @param list<string>                            $expectedErrors
     */
    #[DataProvider('decks')]
    public function testValidation(Format $format, array $cards, array $expectedErrors): void
    {
        $validation = self::getContainer()->get(DeckValidator::class)->validate(self::buildDeck($format, $cards));

        self::assertEqualsCanonicalizing($expectedErrors, $validation->errors);
        self::assertSame([] === $expectedErrors, $validation->isValid());
    }

    public static function decks(): iterable
    {
        $commander = [1, DeckZone::Commander];

        yield 'Modern valide' => [Format::Modern, ['Lightning Bolt' => 4, 'Counterspell' => 4, 'Forest' => 52], []];
        yield 'Modern : trop peu de cartes' => [Format::Modern, ['Lightning Bolt' => 4, 'Forest' => 20], [
            'Le deck principal doit contenir au moins 60 cartes (24 actuellement).',
        ]];
        yield 'Modern : 5 exemplaires, réserve comprise' => [Format::Modern, ['Lightning Bolt' => 4, 'Forest' => 56, 'Delver of Secrets' => [1, DeckZone::Side], 'Lightning Bolt ' => [1, DeckZone::Side]], [
            'Lightning Bolt : 5 exemplaires (maximum 4).',
        ]];
        yield 'Relentless Rats : nombre illimité' => [Format::Modern, ['Relentless Rats' => 20, 'Forest' => 40], []];
        yield 'Réserve de 16 cartes' => [Format::Modern, ['Forest' => 60, 'Counterspell' => [4, DeckZone::Side], 'Relentless Rats' => [12, DeckZone::Side]], [
            'La réserve ne peut pas dépasser 15 cartes (16 actuellement).',
        ]];
        yield 'Les cartes « peut-être » sont ignorées' => [Format::Modern, ['Forest' => 60, 'Badgermole Cub' => [9, DeckZone::Maybe]], []];
        yield 'Standard : bannie et non légale' => [Format::Standard, ['Badgermole Cub' => 4, 'Lightning Bolt' => 4, 'Forest' => 52], [
            'Badgermole Cub est bannie en Standard.',
            'Lightning Bolt n\'est pas légale en Standard.',
        ]];

        yield 'Commander : partenaires valides' => [Format::Commander, ['Thrasios, Triton Hero' => $commander, 'Tymna the Weaver' => $commander, 'Forest' => 98], []];
        yield 'Commander : background valide, carte hors identité' => [Format::Commander, ['Wilson, Refined Grizzly' => $commander, 'Raised by Giants' => $commander, 'Forest' => 97, 'Counterspell' => 1], [
            'Counterspell sort de l\'identité couleur du commandant.',
        ]];
        yield 'Commander : paire invalide' => [Format::Commander, ['Thrasios, Triton Hero' => $commander, 'Wilson, Refined Grizzly' => $commander, 'Forest' => 98], [
            'Thrasios, Triton Hero et Wilson, Refined Grizzly ne peuvent pas être associés comme commandants.',
        ]];
        yield 'Commander : planeswalker non commandant' => [Format::Commander, ['Jace, the Mind Sculptor' => $commander, 'Counterspell' => 1, 'Forest' => 98], [
            'Jace, the Mind Sculptor ne peut pas être commandant (il faut une créature légendaire).',
            'Forest sort de l\'identité couleur du commandant.',
        ]];
        yield 'Commander : singleton et taille' => [Format::Commander, ['Thrasios, Triton Hero' => $commander, 'Counterspell' => 2, 'Forest' => 50], [
            'Counterspell : 2 exemplaires (le format est singleton).',
            'Un deck Commander doit compter exactement 100 cartes, commandant compris (53 actuellement).',
        ]];
        yield 'Commander : sans commandant' => [Format::Commander, ['Forest' => 100], ['Désigne ton commandant.']];
    }
}
