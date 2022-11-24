'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);


$(function() {
    $('#managed_alias_align_indexes').on('change', function() {
        const data = $(this).find("option:selected").data();

        $('.align-index').iCheck('uncheck');

        if (data.indexes !== undefined) {
            data.indexes.map(function (index) {
                $('input[type="checkbox"][value="'+ index +'"]').iCheck('check');
            });
        }
        $(this).val(null);
    });
});
