import { Controller } from '@hotwired/stimulus';
import { bottomCount, canMulligan, deal, draw, keep, landCount, mulligan } from '../game/goldfish.js';

/*
 * Main de départ d'un deck Commander. La logique (mélange, mulligan de Londres, pioche) est dans game/goldfish.js ;
 * ce contrôleur ne fait qu'afficher l'état et relayer les clics.
 */
export default class extends Controller {
    static values = { deck: Array };
    static targets = ['hand', 'instructions', 'turn', 'mulligans', 'handCount', 'lands', 'library', 'keepButton', 'mulliganButton', 'drawButton'];

    connect() {
        this.selected = new Set();
        this.deal();
    }

    deal() {
        this.state = deal(this.deckValue, random);
        this.selected.clear();
        this.render();
    }

    mulligan() {
        this.state = mulligan(this.state, this.deckValue, random);
        this.selected.clear();
        this.render();
    }

    keep() {
        this.state = keep(this.state, [...this.selected]);
        this.selected.clear();
        this.render();
    }

    draw() {
        this.state = draw(this.state);
        this.render();
    }

    toggle(event) {
        const index = Number(event.currentTarget.dataset.index);
        if (this.selected.has(index)) {
            this.selected.delete(index);
        } else if (this.selected.size < bottomCount(this.state)) {
            this.selected.add(index);
        }
        this.render();
    }

    render() {
        const { state } = this;
        const toBottom = state.kept ? 0 : bottomCount(state);

        this.handTarget.replaceChildren(...state.hand.map((card, index) => this.cardElement(card, index, toBottom > 0)));
        this.turnTarget.textContent = state.kept ? String(state.turn) : '—';
        this.mulligansTarget.textContent = String(state.mulligans);
        this.handCountTarget.textContent = String(state.hand.length);
        this.landsTarget.textContent = String(landCount(state.hand));
        this.libraryTarget.textContent = String(state.library.length);

        this.mulliganButtonTarget.hidden = state.kept;
        this.mulliganButtonTarget.disabled = !canMulligan(state);
        this.keepButtonTarget.hidden = state.kept;
        this.keepButtonTarget.disabled = this.selected.size !== toBottom;
        this.keepButtonTarget.textContent = toBottom > 0 ? `Garder (${this.selected.size}/${toBottom} en dessous)` : 'Garder';
        this.drawButtonTarget.hidden = !state.kept;
        this.drawButtonTarget.disabled = state.library.length === 0;

        this.instructionsTarget.textContent = state.kept
            ? `Main gardée. Pioche une carte pour passer au tour ${state.turn + 1}.`
            : toBottom > 0
                ? `Choisis ${toBottom} carte${toBottom > 1 ? 's' : ''} à mettre en dessous de la bibliothèque, puis garde ta main.`
                : state.mulligans > 0 ? 'Premier mulligan gratuit : garde cette main ou refais un mulligan.' : 'Garde cette main ou fais un mulligan.';
    }

    cardElement(card, index, selectable) {
        const item = document.createElement('li');
        const isSelected = this.selected.has(index);
        const tile = document.createElement(selectable ? 'button' : 'div');
        tile.className = 'card-tile relative block aspect-[63/88] w-full transition';
        if (selectable) {
            tile.type = 'button';
            tile.dataset.index = String(index);
            tile.dataset.action = 'goldfish#toggle';
            tile.setAttribute('aria-pressed', String(isSelected));
            tile.title = isSelected ? 'Garder en main' : 'Mettre en dessous';
            if (isSelected) {
                tile.classList.add('-translate-y-2', 'opacity-50', 'ring-2', 'ring-amber-400');
            }
        }
        if (card.image) {
            const image = document.createElement('img');
            image.src = card.image;
            image.alt = card.name;
            image.className = 'h-full w-full object-cover';
            tile.append(image);
        } else {
            tile.textContent = card.name;
            tile.classList.add('grid', 'place-items-center', 'p-2', 'text-center', 'text-xs', 'text-ink-300');
        }
        if (isSelected) {
            const badge = document.createElement('span');
            badge.className = 'badge absolute inset-x-1 bottom-1 justify-center border-amber-400/60 bg-ink-950/90 text-amber-300';
            badge.textContent = 'En dessous';
            tile.append(badge);
        }
        // Dernière carte piochée mise en avant
        if (this.state.kept && index === this.state.hand.length - 1 && this.state.turn > 1) {
            tile.classList.add('ring-2', 'ring-neon-cyan');
        }
        item.append(tile);

        return item;
    }
}

/** Hasard cryptographique : un mélange de 99 cartes mérite mieux que Math.random(). */
function random() {
    return crypto.getRandomValues(new Uint32Array(1))[0] / 2 ** 32;
}
