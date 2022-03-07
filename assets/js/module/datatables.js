import {observeDom} from "../helper/observeDom";
import DatatablesEditRow from "./datatablesEditRow";

const jquery = require('jquery');
require('datatables.net');
require('datatables.net-bs');

export default class datatables {
    constructor(target) {
        const datatables = target.querySelectorAll('[data-datatable]');
        this.loadDatatables(datatables)
    }

    loadDatatables(datatables) {
        [].forEach.call(datatables, function(datatable) {
            jquery(datatable).DataTable(JSON.parse(datatable.dataset.datatable));
        });
    }
}
