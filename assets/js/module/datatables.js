const jquery = require('jquery');
require('datatables.net');
require('datatables.net-bs');
import frenchLanguage from 'datatables.net-plugins/i18n/fr-FR.mjs';
import dutchLanguage from 'datatables.net-plugins/i18n/nl-NL.mjs';

const languages = {
    fr: frenchLanguage,
    nl: dutchLanguage
};

export default class datatables {
    constructor(target) {
        const datatables = target.querySelectorAll('[data-datatable]');
        this.loadDatatables(datatables)
    }

    loadDatatables(datatables) {
        [].forEach.call(datatables, function(element) {
            const options = JSON.parse(element.dataset.datatable);
            const language = document.documentElement.lang.toLowerCase().split('-')[0];
            if (!options.language && languages[language]) options.language = languages[language];

            const datatable = jquery(element).DataTable(options);
            datatable.on('draw', () => new EmsListeners(element))

            document.querySelectorAll(`[data-datatable-target='${element.id}']`).forEach(
                (btn) => btn.addEventListener('click', () => {
                    if (!btn.dataset.hasOwnProperty('datatableEvent')) return;

                    const checked = element.querySelectorAll(`input[name="${element.id}-select[]"]:checked`);

                    element.dispatchEvent(new CustomEvent(btn.dataset.datatableEvent, {
                        detail: { selection: Array.from(checked).map(checkbox => checkbox.value) }
                    }));
                })
            );

            if (!window.dataTables) window.dataTables = [];
            window.dataTables[element.id] = datatable;
        });
    }
}
