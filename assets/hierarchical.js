'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);



$(document).ready(function () {

    const prototype = $('#hierarchical-row').data('hierarchical-item-url');
    const contentType = $('#hierarchical-row').data('content-type');

    $('#reorganize_addItem_value').on("select2:select", function () {


        if ($(this).val().startsWith(contentType+':') && $('li#' + $(this).val().replace(':', '\\:')).length > 0) {

            $('#modal-notifications .modal-body').append('<p>This item is already presents in this structure/menu</p>'.replace('%ouuid%', $(this).val()));
            $('#modal-notifications').modal('show');
        }
        else {
            $.get(prototype.replace('__key__', $(this).val()), function (data) {
                const item = $(data);

                $('#root-list').append(item);
                item.each(function () {
                    new EmsListeners(this);
                });
            });
        }

        $(this).val(null).trigger("change");
    });
});