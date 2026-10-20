import { Controller } from '@hotwired/stimulus';

/* Menu déroulant basé sur <details> : se ferme au clic à l'extérieur ou avec Échap. */
export default class extends Controller {
    closeOutside(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.close();
        }
    }

    close() {
        this.element.open = false;
    }
}
