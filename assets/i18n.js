'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);


$(window).ready(function() {

    const $listTranslations = $('#i18n_content');
    $listTranslations.data('index', $('#i18n_content > div').length);

    $('.btn-add').on('click', function(e) {
        // prevent the link from creating a "#" on the URL
        e.preventDefault();

        const prototype = $listTranslations.data('prototype');
        const index = $listTranslations.data('index');
        // Replace '__name__' in the prototype's HTML to
        // instead be a number based on how many items we have
        const newForm = $(prototype.replace(/__name__/g, index));

        // increase the index with one for the next item
        $listTranslations.data('index', index + 1);

        newForm.find('.btn-remove').on('click', function(e) {
            // prevent the link from creating a "#" on the URL
            e.preventDefault();

            $(this).parents('.filter-container').remove();

        });

        $listTranslations.append(newForm);

    });

    $('.btn-remove').on('click', function(e) {
        // prevent the link from creating a "#" on the URL
        e.preventDefault();

        $(this).parents('.filter-container').remove();

    });
});