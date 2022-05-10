const jquery = require('jquery');
require('datatables.net');
require('datatables.net-bs');
import LiveEditRevision from "./liveEditRevision";

export default class datatables {
    constructor(target) {
        const datatables = target.querySelectorAll('[data-datatable]');
        this.loadDatatables(datatables)
    }

    loadDatatables(datatables) {
        [].forEach.call(datatables, function(datatable) {
            var table = jquery(datatable).DataTable(JSON.parse(datatable.dataset.datatable));
            table.on('draw', function () {
                    const buttons = this.querySelectorAll('button[data-emsco-edit-revision]');
                    console.log(buttons);
                    [].forEach.call(buttons, function(button) {
                        new LiveEditRevision(button);
                    });
                }
            );
        });
    }
}
