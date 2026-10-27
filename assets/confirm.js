import * as Turbo from '@hotwired/turbo';
import Swal from 'sweetalert2';

/*
 * Confirmation des formulaires et liens marqués data-turbo-confirm="Question ?" par une fenêtre SweetAlert
 * aux couleurs du site, au lieu du confirm() du navigateur.
 * Attributs facultatifs sur le formulaire : data-confirm-text (détail), data-confirm-button (libellé du bouton).
 */
Turbo.config.forms.confirm = async (message, element) => {
    const { isConfirmed } = await Swal.fire({
        title: message,
        text: element?.dataset.confirmText ?? '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: element?.dataset.confirmButton ?? 'Confirmer',
        cancelButtonText: 'Annuler',
        reverseButtons: true,
        focusCancel: true,
        background: '#141821',
        color: '#d7dbe6',
        iconColor: '#e5484d',
        confirmButtonColor: '#e5484d',
        cancelButtonColor: '#272e42',
    });

    return isConfirmed;
};
