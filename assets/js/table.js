import dt from 'datatables.net-bs';
import _ from 'lodash';
global.$.DataTable = dt;


export default class Table {

    constructor() {
        $.fn.dataTable.render.html = function (theadTd) {
            const prototype = theadTd.dataset.prototype;

            return function ( data, type, row ) {

               // console.debug(dataset);

                // console.debug(data);
                // console.debug(data);
                // console.debug(data);

                return prototype;
            };
        }
    }

    dataTable(target) {
        const url = $(target).data('url');
        const table = $(target)[0];

        $(target).DataTable({
            ajax: url,
            columns: this.getDataTableColumns(table)
        });
    }

    getDataTableColumns(target) {
        const thead = target.querySelector('thead');
        return _.map(thead.getElementsByTagName("td"), theadTd => this.makeDataTableColumn(theadTd));
    }

    makeDataTableColumn(theadTd) {
        return {
            'data': theadTd.dataset.name,
            'render': this.getDataTableRender(theadTd)
        };
    }

    getDataTableRender(theadTd) {
        switch(_.defaultTo(theadTd.dataset.type, 'text')) {
            case 'html':
                return $.fn.dataTable.render.html(theadTd);
            default:
                return $.fn.dataTable.render.text();
        }
    }
}