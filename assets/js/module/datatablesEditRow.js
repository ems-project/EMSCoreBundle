const jquery = require('jquery');

export default class datatablesEditRow {
    constructor(target) {
        //console.log(target);
        const fields = target.querySelectorAll('div[data-datatable-edit-field]');
        //console.log(fields);
        if (fields.length > 0) {
            this.loadEditRow(fields, target)
        }
    }

    loadEditRow(fields, target) {
        [].forEach.call(fields, function(field) {
            console.log(field);
        });

    }
}
