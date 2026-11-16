import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bottomCount, canMulligan, deal, draw, keep, landCount, mulligan, shuffle } from '../../assets/game/goldfish.js';

const deck = Array.from({ length: 99 }, (_, i) => ({ name: `Carte ${i}`, land: i < 36 }));
// Tirage déterministe : toujours le même « hasard »
const rng = () => 0.42;

test('le mélange garde toutes les cartes', () => {
    const shuffled = shuffle(deck, Math.random);
    assert.equal(shuffled.length, 99);
    assert.deepEqual([...shuffled].sort((a, b) => a.name.localeCompare(b.name)), [...deck].sort((a, b) => a.name.localeCompare(b.name)));
});

test('une nouvelle main fait 7 cartes, le reste forme la bibliothèque', () => {
    const state = deal(deck, rng);
    assert.equal(state.hand.length, 7);
    assert.equal(state.library.length, 92);
    assert.equal(state.mulligans, 0);
    assert.equal(state.kept, false);
});

test('le premier mulligan est gratuit en Commander, les suivants coûtent une carte', () => {
    let state = deal(deck, rng);
    assert.equal(bottomCount(state), 0);
    state = mulligan(state, deck, rng);
    assert.equal(state.hand.length, 7, 'on repioche toujours 7 cartes');
    assert.equal(bottomCount(state), 0, 'premier mulligan gratuit');
    state = mulligan(state, deck, rng);
    assert.equal(bottomCount(state), 1);
    state = mulligan(state, deck, rng);
    assert.equal(bottomCount(state), 2);
});

test('garder la main exige de choisir exactement le bon nombre de cartes à mettre en dessous', () => {
    let state = mulligan(mulligan(deal(deck, rng), deck, rng), deck, rng); // 2 mulligans : 1 carte en dessous
    assert.equal(keep(state, []), state, 'refusé sans carte choisie');
    assert.equal(keep(state, [0, 1]), state, 'refusé avec trop de cartes');

    const bottomed = state.hand[3];
    state = keep(state, [3]);
    assert.equal(state.kept, true);
    assert.equal(state.hand.length, 6);
    assert.equal(state.library.at(-1), bottomed, 'la carte va en dessous de la bibliothèque');
    assert.equal(state.library.length + state.hand.length, 99);
});

test('on ne peut plus faire de mulligan une fois la main gardée, ni au-delà de 7', () => {
    let state = keep(deal(deck, rng));
    assert.equal(canMulligan(state), false);
    state = deal(deck, rng);
    for (let i = 0; i < 10; i++) {
        state = mulligan(state, deck, rng);
    }
    assert.equal(bottomCount(state), 6, 'au pire, on garde une seule carte');
});

test('la pioche prend la carte du dessus et avance le tour', () => {
    let state = keep(deal(deck, rng));
    const top = state.library[0];
    state = draw(state);
    assert.equal(state.hand.at(-1), top);
    assert.equal(state.hand.length, 8);
    assert.equal(state.turn, 2);
    assert.equal(draw(deal(deck, rng)).hand.length, 7, 'pas de pioche avant de garder');
});

test('compte les terrains', () => {
    assert.equal(landCount([{ land: true }, { land: false }, { land: true }]), 2);
});
