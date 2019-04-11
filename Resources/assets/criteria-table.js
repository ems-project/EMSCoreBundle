'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);



const form = $('#criteria-form');

function formatRepoSelectionForTable (repo) {
    let color = $(repo.element).data('color');
    if(!color) {
        color = repo.color;
    }
    let url = form.data('revision-url');
    url = url.replace('__type__:__ouuid__', repo.id);

    let style = "style=\"color: white;\"";
    let complementary = '#000000';
    if(color){
        complementary = (luma(color.replace("#", "")) >= 165) ? '#000000' : '#ffffff';
        style = " style=\"color: "+complementary+";background-color: "+color+"; padding: 2px;\"";
    }

    return $("<a href=\""+url+"\""+style+">"+repo.text+"</a>");
}

window.onload = (function() {
    const selects = $('#CriteriaUpdateCustomViewTable select');
    selects.select2({
        ajax: {
            url: form.data('api-search'),
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    environment: form.data('environment-name'),
                    type: form.data('type-name'),
                    category: form.data('category'),
                    page: params.page
                };
            },
            processResults: function (data, params) {
                // parse the results into the format expected by Select2
                // since we are using custom formatting functions we do not need to
                // alter the remote JSON data, except to indicate that infinite
                // scrolling can be used
                params.page = params.page || 1;

                return {
                    results: data.items,
                    pagination: {
                        more: (params.page * 30) < data.total_count
                    }
                };
            },
            cache: true
        },
        escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
        minimumInputLength: 1,
        templateResult: formatRepo, // omitted for brevity, see the source of this page
        templateSelection: formatRepoSelectionForTable // omitted for brevity, see the source of this page
    });

    selects.change(function(){
        const filters = $.extend( $(this).closest('td').data('filters'), $(this).closest('table').data('filters') );

        $(this).children("option:selected").each(function() {
            const optionElem = $(this);
            if( optionElem.attr('data-status') !== 'added') {
                const data = {
                    filters: filters,
                    target: $(this).val(),
                    category: form.data('category'),
                    criteriaField: form.data('criteria-field')
                };


                window.ajaxRequest.post( form.data('add-url'), data )
                    .success(function() {
                        optionElem.attr('data-status', 'added');

                    })
                    .fail(function( ) {
                        optionElem.attr('data-status', 'removed');
                        let valuesArray = optionElem.parents("select").val();
                        valuesArray = jQuery.grep(valuesArray, function(value) {
                            return value !== optionElem.val();
                        });
                        optionElem.parents("select").val(valuesArray).trigger("change");
                    });
            }
        });

        $(this).children("option:not(:selected)").each(function() {
            const optionElem = $(this);
            if( optionElem.attr('data-status') !== 'removed') {
                const data = {
                    filters: filters,
                    target: $(this).val(),
                    category: form.data('category'),
                    criteriaField: form.data('criteria-field')
                };


                window.ajaxRequest.post(form.data('remove-url'), data)
                    .success(function(){
                        optionElem.attr('data-status', 'removed');
                    })
                    .fail(function(){
                        let valuesArray = optionElem.parents("select").val();
                        if(valuesArray == null){
                            valuesArray = [optionElem.val()];
                        }
                        else{
                            valuesArray.push(optionElem.val());
                        }
                        optionElem.parents("select").val(valuesArray).trigger("change");
                    });
            }
        });
    });
});