
import dt from 'datatables.net-bs';
global.$.DataTable = dt;

export default class Table {

    constructor() {
        $.fn.dataTable.render.test = function () {
            return function ( data, type, row ) {
                // console.debug(data);
                // console.debug(data);
                // console.debug(data);

                return 'coool';
            };
        }
    }

    dataTable(target) {
        let url = $(target).data('url');

        $(target).DataTable({
            ajax: url,
            columns: [
                {
                    data: '#'
                },
                {
                    data: 'name'
                },
                {
                    data: 'indexes'
                },
                {
                    data: 'total'
                },
                {
                    data: 'action',
                    render: $.fn.dataTable.render.test()
                }
            ]
        });
    }

}