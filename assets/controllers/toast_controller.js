import { Controller } from '@hotwired/stimulus';

/* Toast qui apparaît, puis disparaît seul après quelques secondes (ou au clic sur ×). */
export default class extends Controller {
    static values = { duration: { type: Number, default: 7000 } };

    connect() {
        requestAnimationFrame(() => this.element.classList.add('toast-visible'));
        this.timeout = setTimeout(() => this.dismiss(), this.durationValue);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    dismiss() {
        this.element.classList.remove('toast-visible');
        setTimeout(() => this.element.remove(), 300);
    }
}
