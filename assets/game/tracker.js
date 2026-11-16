/*
 * Suivi d'une partie de Commander : points de vie, dégâts de commandant, marqueurs et désignations des joueurs.
 * Logique pure, sans DOM : chaque fonction renvoie un nouvel état (ce qui permet d'annuler une action).
 */

export const STARTING_LIFE = 40;
export const LOW_LIFE = 10;
export const LETHAL_POISON = 10;
export const LETHAL_COMMANDER_DAMAGE = 21;
export const MIN_PLAYERS = 2;
export const MAX_PLAYERS = 6;

/** Marqueurs qu'un joueur peut avoir (règle 122.1) et la vitesse (Aetherdrift, 702.179), de 0 à 4. */
export const COUNTERS = [
    { key: 'poison', label: 'Poison', icon: '☠', lethal: LETHAL_POISON },
    { key: 'energy', label: 'Énergie', icon: '⚡' },
    { key: 'experience', label: 'Expérience', icon: '★' },
    { key: 'rad', label: 'Radiation', icon: '☢' },
    { key: 'tickets', label: 'Tickets', icon: '🎟' },
    { key: 'speed', label: 'Vitesse', icon: '»', max: 4 },
];

/** Désignations détenues par un seul joueur à la fois. */
export const DESIGNATIONS = [
    { key: 'monarch', label: 'Monarque', icon: '♛' },
    { key: 'initiative', label: 'Initiative', icon: '⚑' },
];

/**
 * @param {{players: {name: string, commanders?: string[]}[], startingLife?: number}} config
 */
export function createGame({ players, startingLife = STARTING_LIFE }) {
    const count = Math.min(MAX_PLAYERS, Math.max(MIN_PLAYERS, players.length));
    const list = players.slice(0, count).map((player, index) => ({
        id: index,
        name: (player.name || '').trim() || `Joueur ${index + 1}`,
        commanders: normalizeCommanders(player.commanders),
        life: startingLife,
        counters: Object.fromEntries(COUNTERS.map((c) => [c.key, 0])),
        commanderDamage: {},
        tax: [],
        cityBlessing: false,
        conceded: false,
    }));
    for (const player of list) {
        player.tax = player.commanders.map(() => 0);
        for (const source of list) {
            if (source.id !== player.id) {
                player.commanderDamage[source.id] = source.commanders.map(() => 0);
            }
        }
    }

    return {
        players: list,
        startingLife,
        monarch: null,
        initiative: null,
        dayNight: null,
        active: 0,
        round: 1,
    };
}

export function changeLife(state, playerId, delta) {
    return updatePlayer(state, playerId, (p) => ({ ...p, life: p.life + delta }));
}

export function changeCounter(state, playerId, key, delta) {
    const definition = COUNTERS.find((c) => c.key === key);
    if (!definition) {
        return state;
    }

    return updatePlayer(state, playerId, (p) => ({
        ...p,
        counters: { ...p.counters, [key]: clamp(p.counters[key] + delta, 0, definition.max ?? Infinity) },
    }));
}

/**
 * Dégâts infligés à un joueur par le commandant (index 0 ou 1 pour les partenaires) d'un autre joueur.
 * Ce sont de vrais dégâts : les points de vie bougent d'autant.
 */
export function changeCommanderDamage(state, targetId, sourceId, index, delta) {
    return updatePlayer(state, targetId, (p) => {
        const damage = p.commanderDamage[sourceId];
        if (!damage || damage[index] === undefined) {
            return p;
        }
        const next = Math.max(0, damage[index] + delta);
        const applied = next - damage[index];

        return {
            ...p,
            life: p.life - applied,
            commanderDamage: { ...p.commanderDamage, [sourceId]: damage.map((v, i) => (i === index ? next : v)) },
        };
    });
}

/** Taxe de commandant : nombre de lancements depuis la zone de commandement (+2 mana chacun). */
export function changeTax(state, playerId, index, delta) {
    return updatePlayer(state, playerId, (p) => ({
        ...p,
        tax: p.tax.map((v, i) => (i === index ? Math.max(0, v + delta) : v)),
    }));
}

/** Donne une désignation exclusive (monarque, initiative) à un joueur, ou la lui retire s'il l'a déjà. */
export function toggleDesignation(state, key, playerId) {
    if (!DESIGNATIONS.some((d) => d.key === key)) {
        return state;
    }

    return { ...state, [key]: state[key] === playerId ? null : playerId };
}

export function toggleCityBlessing(state, playerId) {
    return updatePlayer(state, playerId, (p) => ({ ...p, cityBlessing: !p.cityBlessing }));
}

export function toggleConceded(state, playerId) {
    return updatePlayer(state, playerId, (p) => ({ ...p, conceded: !p.conceded }));
}

/** Jour et nuit (règle 727) : absent, puis jour, puis nuit, puis jour… */
export function toggleDayNight(state) {
    return { ...state, dayNight: state.dayNight === 'day' ? 'night' : 'day' };
}

/** Passe au joueur suivant encore en jeu ; un nouveau tour de table commence en revenant au premier. */
export function nextTurn(state) {
    const count = state.players.length;
    for (let step = 1; step <= count; step++) {
        const index = (state.active + step) % count;
        if (!eliminationReason(state.players[index])) {
            return { ...state, active: index, round: index <= state.active ? state.round + 1 : state.round };
        }
    }

    return state;
}

/** Raison de l'élimination d'un joueur, ou null s'il est encore en jeu. */
export function eliminationReason(player) {
    if (player.conceded) {
        return 'A concédé';
    }
    if (player.life <= 0) {
        return 'Plus de points de vie';
    }
    if (player.counters.poison >= LETHAL_POISON) {
        return `${LETHAL_POISON} marqueurs poison`;
    }
    if (Object.values(player.commanderDamage).some((damage) => damage.some((v) => v >= LETHAL_COMMANDER_DAMAGE))) {
        return `${LETHAL_COMMANDER_DAMAGE} dégâts d'un même commandant`;
    }

    return null;
}

export function isLowLife(player) {
    return !eliminationReason(player) && player.life < LOW_LIFE;
}

/** Vrai quand une action fait passer le joueur sous le seuil des points de vie bas (déclenche l'animation). */
export function crossedLowLife(before, after) {
    return before.life >= LOW_LIFE && after.life < LOW_LIFE && after.life > 0;
}

export function winner(state) {
    const alive = state.players.filter((p) => !eliminationReason(p));

    return alive.length === 1 ? alive[0] : null;
}

function updatePlayer(state, playerId, update) {
    return { ...state, players: state.players.map((p) => (p.id === playerId ? update(p) : p)) };
}

function normalizeCommanders(commanders) {
    const names = (commanders || []).map((n) => (n || '').trim()).slice(0, 2);
    if (names.length === 0) {
        return ['Commandant'];
    }

    return names.map((name, i) => name || (i === 0 ? 'Commandant' : 'Partenaire'));
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}
