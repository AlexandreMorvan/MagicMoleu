import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    changeCommanderDamage, changeCounter, changeLife, changeTax, createGame, crossedLowLife, eliminationReason,
    isLowLife, nextTurn, toggleCityBlessing, toggleConceded, toggleDayNight, toggleDesignation, winner,
} from '../../assets/game/tracker.js';
import { seatLayout } from '../../assets/game/seats.js';

const game = () => createGame({ players: [
    { name: 'Alex', commanders: ['Thrasios', 'Tymna'] },
    { name: 'Bea', commanders: ['Atraxa'] },
    { name: '' },
    { name: 'Dom' },
] });

test('partie Commander : 40 points de vie, noms par défaut, dégâts de commandant par source', () => {
    const state = game();
    assert.equal(state.players.length, 4);
    assert.ok(state.players.every((p) => p.life === 40));
    assert.equal(state.players[2].name, 'Joueur 3');
    assert.deepEqual(state.players[1].commanderDamage[0], [0, 0], 'deux partenaires = deux compteurs');
    assert.deepEqual(state.players[0].commanderDamage[1], [0]);
    assert.equal(state.players[0].commanderDamage[0], undefined, 'pas de dégâts de son propre commandant');
    assert.deepEqual(state.players[0].tax, [0, 0]);
});

test('entre 2 et 6 joueurs', () => {
    assert.equal(createGame({ players: [{}] }).players.length, 1, 'un seul joueur fourni : on ne l\'invente pas');
    assert.equal(createGame({ players: Array(8).fill({}) }).players.length, 6);
});

test('les dégâts de commandant font perdre autant de points de vie, et 21 éliminent', () => {
    let state = game();
    state = changeCommanderDamage(state, 1, 0, 1, 5); // Tymna inflige 5 à Bea
    assert.equal(state.players[1].life, 35);
    assert.deepEqual(state.players[1].commanderDamage[0], [0, 5]);
    state = changeCommanderDamage(state, 1, 0, 1, -10);
    assert.deepEqual(state.players[1].commanderDamage[0], [0, 0], 'jamais négatif');
    assert.equal(state.players[1].life, 40, 'seuls les dégâts réellement retirés sont rendus');

    state = changeCommanderDamage(state, 1, 0, 0, 15);
    state = changeCommanderDamage(state, 1, 0, 1, 15);
    assert.equal(state.players[1].life, 10);
    assert.equal(eliminationReason(state.players[1]), null, '15 + 15 de deux commandants différents ne s\'additionnent pas');
    state = changeCommanderDamage(state, 1, 0, 0, 6);
    assert.equal(state.players[1].life, 4);
    assert.match(eliminationReason(state.players[1]), /21 dégâts/, 'éliminé avec encore 4 points de vie');
});

test('marqueurs : jamais négatifs, vitesse plafonnée à 4, 10 poisons éliminent', () => {
    let state = game();
    state = changeCounter(state, 0, 'energy', -3);
    assert.equal(state.players[0].counters.energy, 0);
    state = changeCounter(state, 0, 'speed', 9);
    assert.equal(state.players[0].counters.speed, 4);
    for (const key of ['experience', 'rad', 'tickets']) {
        state = changeCounter(state, 0, key, 2);
        assert.equal(state.players[0].counters[key], 2, key);
    }
    assert.equal(changeCounter(state, 0, 'inconnu', 1), state);
    state = changeCounter(state, 0, 'poison', 10);
    assert.match(eliminationReason(state.players[0]), /poison/);
});

test('points de vie bas : signalés sous 10, animation seulement au franchissement du seuil', () => {
    let state = changeLife(game(), 0, -30);
    assert.equal(isLowLife(state.players[0]), false, '10 points de vie : pas encore bas');
    const before = state.players[0];
    state = changeLife(state, 0, -1);
    assert.equal(isLowLife(state.players[0]), true);
    assert.equal(crossedLowLife(before, state.players[0]), true);
    assert.equal(crossedLowLife(state.players[0], changeLife(state, 0, -1).players[0]), false, 'déjà sous le seuil');
    state = changeLife(state, 0, -9);
    assert.equal(isLowLife(state.players[0]), false, 'éliminé, plus « bas »');
    assert.match(eliminationReason(state.players[0]), /points de vie/);
});

test('monarque et initiative : un seul joueur à la fois', () => {
    let state = toggleDesignation(game(), 'monarch', 1);
    assert.equal(state.monarch, 1);
    state = toggleDesignation(state, 'monarch', 2);
    assert.equal(state.monarch, 2);
    state = toggleDesignation(state, 'monarch', 2);
    assert.equal(state.monarch, null);
    state = toggleDesignation(state, 'initiative', 0);
    assert.equal(state.initiative, 0);
    assert.equal(toggleDesignation(state, 'inconnue', 0), state);
});

test('bénédiction de la cité, taxe de commandant, jour et nuit', () => {
    let state = toggleCityBlessing(game(), 3);
    assert.equal(state.players[3].cityBlessing, true);
    state = changeTax(state, 0, 1, 2);
    assert.deepEqual(state.players[0].tax, [0, 2]);
    state = changeTax(state, 0, 1, -5);
    assert.deepEqual(state.players[0].tax, [0, 0]);
    state = toggleDayNight(state);
    assert.equal(state.dayNight, 'day');
    assert.equal(toggleDayNight(state).dayNight, 'night');
    assert.equal(toggleDayNight(toggleDayNight(state)).dayNight, 'day');
});

test('tour suivant : saute les joueurs éliminés et compte les tours de table', () => {
    let state = toggleConceded(game(), 1);
    state = nextTurn(state);
    assert.equal(state.active, 2, 'Bea a concédé');
    state = nextTurn(nextTurn(state));
    assert.equal(state.active, 0);
    assert.equal(state.round, 2);
});

test('le dernier joueur en jeu gagne', () => {
    let state = game();
    assert.equal(winner(state), null);
    for (const id of [1, 2, 3]) {
        state = toggleConceded(state, id);
    }
    assert.equal(winner(state).name, 'Alex');
});


test('paysage : rangée du bas de gauche à droite, rangée du haut retournée', () => {
    const { rows, places } = seatLayout(4, false);
    assert.equal(rows, 2);
    assert.deepEqual(places.map((p) => [p.row, p.column, p.rotation]), [[2, 1, 0], [2, 7, 0], [1, 7, 180], [1, 1, 180]]);
    assert.deepEqual(seatLayout(3, false).places.map((p) => p.span), [6, 6, 12]);
    assert.ok(seatLayout(4, false, false).places.every((p) => p.rotation === 0), 'face à face désactivé');
});

test('portrait : joueurs sur les côtés tournés de ±90°, le joueur impair en haut', () => {
    const four = seatLayout(4, true);
    assert.equal(four.columns, 2);
    // Ordre du jeu depuis le coin inférieur gauche : bas gauche, bas droit, haut droit, haut gauche
    assert.deepEqual(four.places.map((p) => [p.row, p.column, p.rotation]), [[2, 1, 90], [2, 2, 270], [1, 2, 270], [1, 1, 90]]);

    const five = seatLayout(5, true);
    assert.equal(five.rows, 3);
    assert.deepEqual(five.places[3], { row: 1, column: 1, span: 2, rotation: 180 });

    const six = seatLayout(6, true);
    const cells = new Set(six.places.map((p) => `${p.row}-${p.column}`));
    assert.equal(cells.size, 6, 'aucune place en double');
});

test('portrait à 2 joueurs : face à face, comme en paysage', () => {
    assert.deepEqual(seatLayout(2, true).places.map((p) => p.rotation), [0, 180]);
});
