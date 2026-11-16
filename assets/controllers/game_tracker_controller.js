import { Controller } from '@hotwired/stimulus';
import {
    COUNTERS, DESIGNATIONS, LETHAL_COMMANDER_DAMAGE, LETHAL_POISON, MAX_PLAYERS,
    changeCommanderDamage, changeCounter, changeLife, changeTax, createGame, crossedLowLife, eliminationReason,
    isLowLife, nextTurn, toggleCityBlessing, toggleConceded, toggleDayNight, toggleDesignation, winner,
} from '../game/tracker.js';
import { seatLayout } from '../game/seats.js';

const STORAGE_KEY = 'manaforge.partie';
const HISTORY_SIZE = 50;
/** Couleur d'accent de chaque place (identique à l'écran de configuration). */
const ACCENTS = ['#8b5cf6', '#22d3ee', '#d946ef', '#2fa86a', '#f59e0b', '#3d8fd6'];
/** Couleur de chaque type de marqueur. */
const COUNTER_COLORS = { poison: '#4ade80', energy: '#facc15', experience: '#c084fc', rad: '#a3e635', tickets: '#f472b6', speed: '#22d3ee' };
const HOLD_DELAY = 450;
const HOLD_INTERVAL = 110;

/*
 * Suivi d'une partie de Commander, façon application de table : plateau plein écran, une tuile par joueur
 * (illustration du commandant, énorme total), joueurs d'en face retournés. On tape la moitié gauche ou droite
 * d'une tuile pour retirer ou ajouter un point de vie ; marqueurs et dégâts de commandant s'ouvrent depuis
 * les icônes de la tuile.
 *
 * Toute la logique est dans game/tracker.js. Le plateau est construit une fois, puis mis à jour à chaque action.
 * La partie est gardée dans le navigateur (localStorage) et chaque action peut être annulée.
 */
export default class extends Controller {
    static values = { artsUrl: String };
    static targets = [
        'setup', 'board', 'seats', 'playerCount', 'playerRow', 'startingLife',
        'menu', 'menuButton', 'roundBadge', 'turnInfo', 'winner', 'undoButton', 'dayNightButton', 'facingButton',
    ];

    connect() {
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.portrait = window.matchMedia('(orientation: portrait)');
        this.onOrientation = () => this.state && this.layout();
        this.portrait.addEventListener('change', this.onOrientation);
        this.onKeydown = (event) => {
            if (event.key === 'Escape') {
                this.closeMenu();
                this.closeSheets();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
        const saved = load();
        if (saved) {
            this.state = saved.state;
            this.history = saved.history ?? [];
            this.facing = saved.facing ?? true;
            this.showBoard();
        } else {
            this.showSetup();
        }
    }

    disconnect() {
        this.stopHold();
        this.portrait.removeEventListener('change', this.onOrientation);
        document.removeEventListener('keydown', this.onKeydown);
        document.documentElement.classList.remove('game-table-open');
        this.wakeLock?.release().catch(() => {});
    }

    // ---------- Configuration ----------

    showSetup() {
        this.boardTarget.hidden = true;
        this.setupTarget.hidden = false;
        document.documentElement.classList.remove('game-table-open');
        this.showPlayerRows();
    }

    selectedPlayerCount() {
        return Number(this.playerCountTargets.find((radio) => radio.checked)?.value ?? 4);
    }

    showPlayerRows() {
        const count = this.selectedPlayerCount();
        this.playerRowTargets.forEach((row, index) => { row.hidden = index >= count; });
    }

    async start(event) {
        const submit = event.target.querySelector('[type="submit"]');
        submit.disabled = true;
        const count = Math.min(MAX_PLAYERS, this.selectedPlayerCount());
        const players = this.playerRowTargets.slice(0, count).map((row) => ({
            name: row.querySelector('[data-field="name"]').value,
            commanders: [row.querySelector('[data-field="commander"]').value, row.querySelector('[data-field="partner"]').value]
                .filter((name, index) => index === 0 || name.trim() !== ''),
        }));
        const typed = players.map((p) => p.commanders.map((name) => name.trim()));
        const startingLife = Math.max(1, Number(this.startingLifeTarget.value) || 40);

        const game = createGame({ players, startingLife });
        // Illustration du premier commandant de chaque joueur, s'il a été saisi et trouvé
        const arts = await this.fetchArts(typed.flat().filter(Boolean));
        game.players = game.players.map((player, i) => ({ ...player, art: arts[typed[i][0]] ?? null }));

        this.state = game;
        this.history = [];
        this.facing = true;
        this.save();
        submit.disabled = false;
        this.showBoard();
        this.fullscreen();
    }

    async fetchArts(names) {
        if (names.length === 0) {
            return {};
        }
        try {
            const query = new URLSearchParams(names.map((name) => ['noms[]', name]));
            const response = await fetch(`${this.artsUrlValue}?${query}`, { headers: { Accept: 'application/json' } });

            return response.ok ? await response.json() : {};
        } catch {
            return {}; // hors ligne : pas d'illustration, la partie fonctionne quand même
        }
    }

    reset() {
        if (!window.confirm('Abandonner la partie en cours et en commencer une nouvelle ?')) {
            return;
        }
        this.state = null;
        this.history = [];
        clear();
        this.closeMenu();
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
        this.showSetup();
    }

    // ---------- Menu ----------

    openMenu() {
        this.closeSheets();
        this.menuTarget.hidden = false;
    }

    closeMenu() {
        this.menuTarget.hidden = true;
    }

    keepMenuOpen(event) {
        event.stopPropagation();
    }

    toggleFacing() {
        this.facing = !this.facing;
        this.save();
        this.layout();
        this.update();
    }

    /** Plein écran et écran toujours allumé, quand le navigateur le permet. */
    async fullscreen() {
        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
            }
        } catch {
            // refusé (iOS, iframe…) : le plateau occupe déjà toute la fenêtre
        }
        try {
            this.wakeLock = await navigator.wakeLock?.request('screen');
        } catch {
            // pas de verrouillage d'écran disponible
        }
    }

    // ---------- Actions ----------

    /** Clic sur une commande du plateau ; ignoré s'il termine un appui maintenu, qui a déjà agi. */
    act({ params }) {
        if (this.skipClick) {
            this.skipClick = false;

            return;
        }
        this.perform(params);
    }

    /** Appui maintenu sur une zone de points de vie : l'action se répète. */
    hold({ params }) {
        this.stopHold();
        this.holdTimer = setTimeout(() => {
            this.skipClick = true;
            this.perform(params);
            this.repeatTimer = setInterval(() => this.perform(params), HOLD_INTERVAL);
        }, HOLD_DELAY);
    }

    stopHold() {
        clearTimeout(this.holdTimer);
        clearInterval(this.repeatTimer);
    }

    openSheet({ params }) {
        this.closeSheets();
        this.views.get(params.player).sheets[params.sheet].hidden = false;
    }

    closeSheets() {
        this.views?.forEach((view) => Object.values(view.sheets).forEach((sheet) => { sheet.hidden = true; }));
    }

    perform({ op, player, delta, key, source, index }) {
        const actions = {
            life: () => changeLife(this.state, player, delta),
            counter: () => changeCounter(this.state, player, key, delta),
            damage: () => changeCommanderDamage(this.state, player, source, index, delta),
            tax: () => changeTax(this.state, player, index, delta),
            designation: () => toggleDesignation(this.state, key, player),
            blessing: () => toggleCityBlessing(this.state, player),
            concede: () => toggleConceded(this.state, player),
        };
        if (actions[op]) {
            this.apply(actions[op]());
        }
    }

    nextTurn() {
        this.apply(nextTurn(this.state));
    }

    dayNight() {
        this.apply(toggleDayNight(this.state));
    }

    undo() {
        if (this.history.length === 0) {
            return;
        }
        this.state = this.history.pop();
        this.save();
        this.update();
    }

    apply(next) {
        if (next === this.state) {
            return;
        }
        const previous = this.state;
        this.history = [...this.history, previous].slice(-HISTORY_SIZE);
        this.state = next;
        this.save();
        this.update();

        next.players.forEach((player, i) => {
            const before = previous.players[i];
            const view = this.views.get(player.id);
            if (player.life !== before.life) {
                this.showDelta(view, player.life - before.life);
            }
            if (crossedLowLife(before, player)) {
                this.lowLifeAlert(view);
            }
        });
    }

    /** Variation « −7 » sous le total, qui cumule les changements rapprochés. */
    showDelta(view, delta) {
        view.deltaTotal = (view.deltaTimer ? view.deltaTotal : 0) + delta;
        clearTimeout(view.deltaTimer);
        view.delta.textContent = view.deltaTotal > 0 ? `+${view.deltaTotal}` : String(view.deltaTotal);
        view.delta.classList.toggle('tile-delta-gain', view.deltaTotal > 0);
        view.delta.classList.add('tile-delta-visible');
        view.deltaTimer = setTimeout(() => {
            view.delta.classList.remove('tile-delta-visible');
            view.deltaTimer = null;
        }, 2000);
    }

    /**
     * Passage sous 10 points de vie : éclair rouge sur la tuile, secousse et total qui grossit.
     * Web Animations plutôt qu'une classe CSS : l'effet part à coup sûr, même si la classe était déjà posée.
     * Avec « réduire les animations », seul l'éclair (sans mouvement) est joué.
     */
    lowLifeAlert(view) {
        view.flash.animate([{ opacity: 0 }, { opacity: 0.85, offset: 0.15 }, { opacity: 0 }], { duration: 1000, easing: 'ease-out' });
        if (this.reducedMotion.matches) {
            return;
        }
        view.body.animate([
            { transform: 'translateX(0)' }, { transform: 'translateX(-12px) rotate(-0.8deg)' }, { transform: 'translateX(10px) rotate(0.6deg)' },
            { transform: 'translateX(-7px)' }, { transform: 'translateX(4px)' }, { transform: 'translateX(0)' },
        ], { duration: 550, easing: 'ease-out' });
        view.life.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.4)', offset: 0.3 }, { transform: 'scale(1)' }], { duration: 750, easing: 'cubic-bezier(.2,.8,.2,1)' });
    }

    // ---------- Plateau ----------

    showBoard() {
        this.setupTarget.hidden = true;
        this.boardTarget.hidden = false;
        this.closeMenu();
        document.documentElement.classList.add('game-table-open');

        this.views = new Map();
        this.seatsTarget.replaceChildren(...this.state.players.map((player) => this.buildTile(player)));
        this.layout();
        this.update();
    }

    /**
     * Place les joueurs autour du téléphone posé à plat, chacun lisant sa tuile dans son sens.
     * Paysage : une rangée en bas, une en haut retournée (face à face).
     * Portrait (à partir de 3 joueurs) : deux colonnes de joueurs assis sur les côtés, tuiles tournées de ±90°,
     * et le joueur en trop (nombre impair) en haut sur toute la largeur.
     */
    layout() {
        const seats = seatLayout(this.state.players.length, this.portrait.matches, this.facing);
        this.seatsTarget.style.gridTemplateColumns = `repeat(${seats.columns}, minmax(0, 1fr))`;
        this.seatsTarget.style.gridTemplateRows = `repeat(${seats.rows}, minmax(0, 1fr))`;
        this.state.players.forEach((player, i) => {
            const { row, column, span, rotation } = seats.places[i];
            const tile = this.views.get(player.id).tile;
            tile.style.gridArea = `${row} / ${column} / span 1 / span ${span}`;
            tile.dataset.rotation = String(rotation);
        });
    }

    buildTile(player) {
        const view = { counters: {}, damage: {}, tax: [], designations: {}, sheets: {} };
        const tile = el('article', 'tile');
        tile.style.setProperty('--accent', ACCENTS[player.id % ACCENTS.length]);
        view.tile = tile;

        // Contenu retourné d'un bloc pour les joueurs d'en face (la secousse s'applique à lui)
        const body = el('div', 'tile-body');
        view.body = body;
        if (player.art) {
            const art = el('img', 'tile-art');
            art.src = player.art;
            art.alt = '';
            body.append(art);
        }
        body.append(el('div', 'tile-shade'));
        view.flash = el('div', 'tile-flash');

        // Moitié gauche : −1, moitié droite : +1 (appui maintenu : ça défile)
        body.append(
            lifeZone('−', { op: 'life', player: player.id, delta: -1 }, 'tile-zone-minus', `Retirer 1 point de vie à ${player.name}`),
            lifeZone('+', { op: 'life', player: player.id, delta: 1 }, 'tile-zone-plus', `Ajouter 1 point de vie à ${player.name}`),
        );

        // Centre : nom, total, variation, statut
        const center = el('div', 'tile-center');
        view.name = el('p', 'tile-name', player.name);
        view.life = el('output', 'tile-life');
        view.life.setAttribute('aria-label', `Points de vie de ${player.name}`);
        view.delta = el('span', 'tile-delta');
        view.status = el('p', 'tile-status hidden');
        const lifeWrap = el('div', 'relative');
        lifeWrap.append(view.life, view.delta);
        center.append(view.name, lifeWrap);

        // Haut de la tuile : tour en cours et désignations
        view.badges = el('div', 'tile-badges');

        // Bas de la tuile : dégâts de commandant et marqueurs
        view.damageButton = sheetButton('⚔', 'Dégâts de commandant', player.id, 'damage', 'tile-icon tile-icon-left');
        view.countersButton = sheetButton('✦', 'Marqueurs et désignations', player.id, 'counters', 'tile-icon tile-icon-right');

        body.append(view.flash, center, view.status, view.badges, view.damageButton, view.countersButton,
            this.buildDamageSheet(player, view), this.buildCountersSheet(player, view));
        tile.append(body);
        this.views.set(player.id, view);

        return tile;
    }

    buildCountersSheet(player, view) {
        const sheet = sheetElement(`${player.name} · marqueurs`);
        const list = el('ul', 'grid gap-2 @lg:grid-cols-2');
        for (const counter of COUNTERS) {
            view.counters[counter.key] = el('output', 'counter-value');
            list.append(stepper(counter.icon, counter.label, view.counters[counter.key], { op: 'counter', player: player.id, key: counter.key }, COUNTER_COLORS[counter.key]));
        }
        const designations = el('div', 'mt-3 flex flex-wrap gap-2');
        for (const designation of DESIGNATIONS) {
            view.designations[designation.key] = button(`${designation.icon} ${designation.label}`, { op: 'designation', player: player.id, key: designation.key }, 'toggle-button');
            designations.append(view.designations[designation.key]);
        }
        view.designations.blessing = button('✦ Bénédiction de la cité', { op: 'blessing', player: player.id }, 'toggle-button');
        designations.append(view.designations.blessing);

        const tax = el('ul', 'mt-3 grid gap-2');
        player.commanders.forEach((commander, index) => {
            const output = el('output', 'counter-value w-auto min-w-24 text-sm');
            view.tax[index] = output;
            tax.append(stepper('◈', `Taxe · ${commander}`, output, { op: 'tax', player: player.id, index }, '#d946ef'));
        });

        view.concede = button('Concéder', { op: 'concede', player: player.id }, 'mt-4 block w-full text-center text-xs font-semibold uppercase tracking-widest text-ink-300 transition hover:text-mana-r');
        sheet.body.append(list, designations, tax, view.concede);
        view.sheets.counters = sheet.element;

        return sheet.element;
    }

    buildDamageSheet(player, view) {
        const sheet = sheetElement(`${player.name} · dégâts de commandant reçus`);
        const list = el('ul', 'grid gap-2');
        for (const source of this.state.players) {
            if (source.id === player.id) {
                continue;
            }
            source.commanders.forEach((commander, index) => {
                const output = el('output', 'counter-value');
                view.damage[`${source.id}-${index}`] = output;
                list.append(stepper('⚔', `${commander} · ${source.name}`, output, { op: 'damage', player: player.id, source: source.id, index }, ACCENTS[source.id % ACCENTS.length]));
            });
        }
        sheet.body.append(list, el('p', 'mt-3 text-xs text-ink-300', `Retire aussi les points de vie. ${LETHAL_COMMANDER_DAMAGE} dégâts d'un même commandant éliminent.`));
        view.sheets.damage = sheet.element;

        return sheet.element;
    }

    update() {
        const { state } = this;
        this.boardTarget.dataset.players = String(state.players.length);

        for (const player of state.players) {
            const view = this.views.get(player.id);
            const reason = eliminationReason(player);
            const active = state.active === player.id && reason === null;

            view.tile.classList.toggle('player-low', isLowLife(player));
            view.tile.classList.toggle('player-out', reason !== null);
            view.tile.classList.toggle('player-active', active);
            view.life.textContent = String(player.life);
            view.status.textContent = reason ? `☠ Éliminé · ${reason}` : '';
            view.status.classList.toggle('hidden', reason === null);
            view.concede.textContent = player.conceded ? 'Revenir dans la partie' : 'Concéder';

            for (const counter of COUNTERS) {
                const value = player.counters[counter.key];
                const output = view.counters[counter.key];
                output.textContent = counter.max !== undefined && value === counter.max ? `${value} · max` : String(value);
                output.classList.toggle('counter-lethal', counter.lethal !== undefined && value >= counter.lethal);
                output.classList.toggle('counter-zero', value === 0);
            }
            for (const [sourceId, values] of Object.entries(player.commanderDamage)) {
                values.forEach((value, index) => {
                    const output = view.damage[`${sourceId}-${index}`];
                    output.textContent = String(value);
                    output.classList.toggle('counter-lethal', value >= LETHAL_COMMANDER_DAMAGE);
                    output.classList.toggle('counter-danger', value >= LETHAL_COMMANDER_DAMAGE - 6 && value < LETHAL_COMMANDER_DAMAGE);
                    output.classList.toggle('counter-zero', value === 0);
                });
            }
            player.tax.forEach((casts, index) => {
                view.tax[index].textContent = casts > 0 ? `${casts}× · +${2 * casts} mana` : 'Aucune';
                view.tax[index].classList.toggle('counter-zero', casts === 0);
            });

            const holds = { monarch: state.monarch === player.id, initiative: state.initiative === player.id, blessing: player.cityBlessing };
            for (const [key, pressed] of Object.entries(holds)) {
                view.designations[key].setAttribute('aria-pressed', String(pressed));
            }
            view.badges.replaceChildren(...badges(holds, active));

            // Icônes du bas : pire dégât de commandant reçu, marqueurs actifs
            const worst = Math.max(0, ...Object.values(player.commanderDamage).flat());
            view.damageButton.lastChild.textContent = String(worst);
            view.damageButton.classList.toggle('tile-icon-danger', worst >= LETHAL_COMMANDER_DAMAGE - 6);
            const activeCounters = COUNTERS.filter((c) => player.counters[c.key] > 0);
            view.countersButton.lastChild.textContent = activeCounters.length
                ? activeCounters.map((c) => `${c.icon}${player.counters[c.key]}`).join(' ')
                : '';
            view.countersButton.classList.toggle('tile-icon-danger', player.counters.poison >= LETHAL_POISON - 3);
        }

        const active = state.players[state.active];
        this.turnInfoTarget.textContent = `Tour ${state.round} · à ${active.name}`;
        this.roundBadgeTarget.textContent = String(state.round);
        const champion = winner(state);
        this.winnerTarget.textContent = champion ? `🏆 ${champion.name} gagne !` : '';
        this.winnerTarget.classList.toggle('hidden', !champion);
        this.undoButtonTarget.disabled = this.history.length === 0;
        this.dayNightButtonTarget.textContent = { day: '☀ Jour', night: '☾ Nuit' }[state.dayNight] ?? '☀/☾ Jour ou nuit';
        this.facingButtonTarget.setAttribute('aria-pressed', String(this.facing));
        this.menuButtonTarget.dataset.dayNight = state.dayNight ?? '';
    }

    save() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ state: this.state, history: this.history, facing: this.facing }));
        } catch {
            // Stockage indisponible (navigation privée…) : la partie reste en mémoire le temps de la page
        }
    }
}

function load() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');

        return saved?.state?.players?.length ? saved : null;
    } catch {
        return null;
    }
}

function clear() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // rien à effacer
    }
}

function el(tag, className = '', text = null) {
    const element = document.createElement(tag);
    element.className = className;
    if (text !== null) {
        element.textContent = text;
    }

    return element;
}

function setParams(element, params) {
    for (const [name, value] of Object.entries(params)) {
        element.setAttribute(`data-game-tracker-${name}-param`, String(value));
    }
}

/** Bouton déclenchant game-tracker#act ; les paramètres deviennent des attributs data-game-tracker-*-param. */
function button(text, params, className = '', label = null) {
    const element = el('button', className, text);
    element.type = 'button';
    element.dataset.action = 'game-tracker#act';
    setParams(element, params);
    if (label) {
        element.setAttribute('aria-label', label);
    }

    return element;
}

/** Moitié de tuile qui retire ou ajoute un point de vie : clic simple, ou appui maintenu qui répète l'action. */
function lifeZone(sign, params, className, label) {
    const element = button('', params, `tile-zone ${className}`, label);
    element.append(el('span', 'tile-zone-sign', sign));
    element.dataset.action = [
        'click->game-tracker#act',
        'pointerdown->game-tracker#hold',
        'pointerup->game-tracker#stopHold',
        'pointerleave->game-tracker#stopHold',
        'pointercancel->game-tracker#stopHold',
        'contextmenu->game-tracker#stopHold:prevent',
    ].join(' ');

    return element;
}

function sheetButton(icon, label, playerId, sheet, className) {
    const element = el('button', className);
    element.type = 'button';
    element.dataset.action = 'game-tracker#openSheet';
    setParams(element, { player: playerId, sheet });
    element.setAttribute('aria-label', label);
    element.title = label;
    element.append(el('span', 'tile-icon-glyph', icon), el('span', 'tile-icon-value'));

    return element;
}

/** Fiche qui s'ouvre par-dessus la tuile (et tourne avec elle). */
function sheetElement(title) {
    const element = el('div', 'tile-sheet');
    element.hidden = true;
    const header = el('div', 'flex items-center justify-between gap-2');
    header.append(el('p', 'truncate font-display text-sm font-bold uppercase tracking-widest text-white', title));
    const close = el('button', 'tile-sheet-close', '✕');
    close.type = 'button';
    close.dataset.action = 'game-tracker#closeSheets';
    close.setAttribute('aria-label', 'Fermer');
    header.append(close);
    const body = el('div', 'mt-3');
    element.append(header, body);

    return { element, body };
}

/** Ligne « icône libellé − valeur + ». */
function stepper(icon, label, output, params, color) {
    const row = el('li', 'stepper');
    row.style.setProperty('--counter', color);
    const name = el('span', 'flex min-w-0 flex-1 items-center gap-2 text-sm text-ink-100');
    name.append(el('span', 'stepper-icon', icon), el('span', 'truncate', label));
    row.append(
        name,
        button('−', { ...params, delta: -1 }, 'step-button', `${label} : retirer 1`),
        output,
        button('+', { ...params, delta: 1 }, 'step-button', `${label} : ajouter 1`),
    );

    return row;
}

function badges(holds, active) {
    const list = [];
    if (active) list.push(el('span', 'designation-badge designation-turn', 'À toi'));
    if (holds.monarch) list.push(el('span', 'designation-badge designation-monarch', '♛ Monarque'));
    if (holds.initiative) list.push(el('span', 'designation-badge designation-initiative', '⚑ Initiative'));
    if (holds.blessing) list.push(el('span', 'designation-badge designation-blessing', '✦ Cité'));

    return list;
}

