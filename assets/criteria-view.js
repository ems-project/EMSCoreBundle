'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);


window.onload = (function() {

    const columnCriteria = $('.criteria-filter-columnrow');

    columnCriteria.change(function(){

        if($('#criteria_filter_columnCriteria option:selected').val() === $('#criteria_filter_rowCriteria  option:selected').val()){
            if($(this).attr('id') === 'criteria_filter_columnCriteria'){
                $('#criteria_filter_rowCriteria').val($('#criteria_filter_rowCriteria option:not(:selected)').first().val());
            }
            else {
                $('#criteria_filter_columnCriteria').val($('#criteria_filter_columnCriteria option:not(:selected)').first().val());
            }
        }

        $('div#criterion select').each(function(){
            const criterionName = $( this ).closest('div[data-name]').data('name');
            const colCriteria = $('#criteria_filter_columnCriteria').val();
            const rowCriteria = $('#criteria_filter_rowCriteria').val();

            //TODO: multiple not supported?
            // const attr = $(this).attr('multiple');


            if(criterionName === colCriteria || criterionName === rowCriteria) {
                objectPickerListeners($( this ));
            }
            else{
                if($(this).val() && $(this).val().length > 1){
                    $(this).val('');
                }
                objectPickerListeners($( this ), 1);
            }

        });
    });

    columnCriteria.trigger('change');

});
