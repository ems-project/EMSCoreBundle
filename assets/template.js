'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);

$(window).ready(function() {

    const exportClass = $('.export');
    const notificationClass = $('.notification');
    const embedClass = $('.embed');
    const pdfClass = $('.pdf');
    const renderOption = $('#template_renderOption');

    // Jira - ELASTICMS-41
    exportClass.hide();
    notificationClass.hide();
    embedClass.hide();
    pdfClass.hide();

    if (renderOption.val() === 'notification'){
        notificationClass.show();
    } else if (renderOption.val() === 'export') {
        exportClass.show();
    } else if (renderOption.val() === 'embed') {
        embedClass.show();
    } else if (renderOption.val() === 'pdf') {
        pdfClass.show();
    }

    renderOption.change(function(){
        if ($(this).val() === 'notification'){
            notificationClass.show();
            exportClass.hide();
            embedClass.hide();
            pdfClass.hide();
        } else if ($(this).val() === 'export'){
            exportClass.show();
            notificationClass.hide();
            embedClass.hide();
            pdfClass.hide();
        } else if ($(this).val() === 'embed'){
            embedClass.show();
            exportClass.hide();
            notificationClass.hide();
            pdfClass.hide();
        } else if ($(this).val() === 'pdf'){
            embedClass.hide();
            exportClass.hide();
            notificationClass.hide();
            pdfClass.show();
        } else{
            exportClass.hide();
            notificationClass.hide();
            embedClass.hide();
            pdfClass.hide();
        }
    });
});