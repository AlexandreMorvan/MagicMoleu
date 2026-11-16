/*
 * Main de départ (« goldfish ») d'un deck Commander : mélange, main de 7, mulligan de Londres et pioche.
 * Logique pure, sans DOM : chaque fonction renvoie un nouvel état. Le tirage aléatoire est injectable (tests).
 *
 * Mulligan de Londres (règle 103.5) : on remélange, on repioche 7 cartes, puis on en met autant en dessous de
 * la bibliothèque que de mulligans pris. En multijoueur, et donc en Commander, le premier est gratuit (103.5c).
 */

export const HAND_SIZE = 7;

/** Mélange de Fisher-Yates ; rng renvoie un nombre dans [0, 1). */
export function shuffle(cards, rng = Math.random) {
    const result = [...cards];
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(rng() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }

    return result;
}

/** Nouvelle main : bibliothèque mélangée, 7 cartes en main, aucun mulligan. */
export function deal(deck, rng = Math.random) {
    return drawOpeningHand(deck, 0, rng);
}

/** Nombre de cartes à mettre en dessous pour garder la main actuelle. */
export function bottomCount(state) {
    return Math.max(0, state.mulligans - 1);
}

export function canMulligan(state) {
    return !state.kept && bottomCount({ mulligans: state.mulligans + 1 }) < HAND_SIZE;
}

export function mulligan(state, deck, rng = Math.random) {
    if (!canMulligan(state)) {
        return state;
    }

    return drawOpeningHand(deck, state.mulligans + 1, rng);
}

/**
 * Garde la main en mettant en dessous de la bibliothèque les cartes choisies (indices dans la main).
 * Refusé si le nombre de cartes choisies ne correspond pas aux mulligans pris.
 */
export function keep(state, bottomIndexes = []) {
    const indexes = [...new Set(bottomIndexes)];
    if (state.kept || indexes.length !== bottomCount(state) || indexes.some((i) => i < 0 || i >= state.hand.length)) {
        return state;
    }

    return {
        ...state,
        hand: state.hand.filter((_, i) => !indexes.includes(i)),
        library: [...state.library, ...indexes.map((i) => state.hand[i])],
        kept: true,
        turn: 1,
    };
}

/** Pioche du tour suivant (pas de pioche sautée au premier tour en multijoueur, règle 103.8). */
export function draw(state) {
    if (!state.kept || state.library.length === 0) {
        return state;
    }

    return {
        ...state,
        hand: [...state.hand, state.library[0]],
        library: state.library.slice(1),
        turn: state.turn + 1,
    };
}

export function landCount(cards) {
    return cards.filter((card) => card.land).length;
}

function drawOpeningHand(deck, mulligans, rng) {
    const library = shuffle(deck, rng);

    return {
        hand: library.slice(0, HAND_SIZE),
        library: library.slice(HAND_SIZE),
        mulligans,
        kept: false,
        turn: 0,
    };
}
