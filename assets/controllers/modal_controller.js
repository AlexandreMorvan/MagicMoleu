import { Controller } from '@hotwired/stimulus';

/*
 * Modale <dialog> alimentée par une Turbo Frame : s'ouvre quand la frame a chargé son contenu
 * et se vide à la fermeture (Échap, bouton, clic sur le fond).
 */
export default class extends Controller {
    static targets = ['frame', 'closeButton'];
    static values = { version: String };

    /*
     * Les fiches de la modale sont gardées 24 h par le navigateur : la version du site dans l'URL
     * fait qu'un déploiement ne ressert jamais une fiche produite par l'ancien code.
     */
    addVersion(event) {
        if (this.versionValue) {
            event.detail.url.searchParams.set('v', this.versionValue);
        }
    }

    open() {
        // Appelé à chaque chargement de la frame : changer d'impression ne doit pas faire défiler la modale
        if (!this.element.open) {
            this.element.showModal();
            this.element.scrollTop = 0;
            this.closeButtonTarget.focus({ preventScroll: true });
        }
    }

    close() {
        this.element.close();
    }

    closeOnBackdrop(event) {
        // Le clic sur le fond a pour cible le <dialog> lui-même, pas son contenu
        if (event.target === this.element) {
            this.close();
        }
    }

    clear() {
        this.frameTarget.removeAttribute('src');
        this.frameTarget.replaceChildren();
    }
}
