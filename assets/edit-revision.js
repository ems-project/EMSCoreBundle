'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';
import IframePreview from "./js/module/iframePreview";


import EmsListeners from './js/EmsListeners';
import {editRevisionEventListeners} from "./js/editRevisionEventListeners";
window.EmsListeners = EmsListeners;

new IframePreview('#ajax-modal');


let waitingResponse = false;
let synch = true;

const primaryBox = $('#revision-primary-box');
const updateMode = primaryBox.data('update-mode');

function onFormChange(event, allowAutoPublish){
    if (updateMode === 'disabled') {
        // console.log('No way to save a finalized revision!');
        return;
    }
    else if (updateMode === 'autoPublish' && !allowAutoPublish) {
        // console.log('The auto-save is disabled in auto-publish mode!');
        return;
    }

    synch = false;

    updateChoiceFieldTypes();
    updateCollectionLabel();


    if(waitingResponse) {
        return;
        //abort the request might be an option, but it overloads the server
        // waitingResponse.abort();
    }

    synch = true;
    //update ckeditor's text areas
    for (let i in CKEDITOR.instances) {
        if(CKEDITOR.instances.hasOwnProperty(i)) {
            CKEDITOR.instances[i].updateElement();
        }
    }


    waitingResponse = window.ajaxRequest.post( primaryBox.data('ajax-update'), $("form[name=revision]").serialize())
        .success(function(response) {
            $('.has-error').removeClass('has-error');
            $('span.help-block').remove();

            /**
             * @param {{formErrors:array}} response
             */
            $(response.formErrors).each(function(index, item){

                /**
                 * @param {{propertyPath:string}} item
                 */
                let target = item.propertyPath;
                const targetLabel = $('#' + target + '__label');
                const targetError = $('#' + target + '__error');

                let propPath = $('#'+item.propertyPath+'_value');
                if(propPath.length && propPath.prop('nodeName') === 'TEXTAREA'){
                    target = item.propertyPath+'_value';
                }

                const targetParent = $('#' + target);
                if (targetLabel.length) {
                    targetLabel.closest('div.form-group').addClass('has-error');
                    if (item.message && targetError.length > 0) {
                        targetError.addClass('has-error');
                        if($('#'+target+'__error span.help-block').length === 0){
                            targetError.append('<span class="help-block"><ul class="list-unstyled"></ul></span>');
                        }
                        $('#'+target+'__error'+' span.help-block ul.list-unstyled').append('<li><span class="glyphicon glyphicon-exclamation-sign"></span> '+item.message+'</li>');
                    }
                }
                else {
                    $('#' + target).closest('div.form-group').addClass('has-error');
                    targetParent.parents('.form-group').addClass('has-error');
                    if(item.message) {
                        if(targetParent.parents('.form-group').find(' span.help-block').length === 0){
                            targetParent.parent('.form-group').append('<span class="help-block"><ul class="list-unstyled"><li><span class="glyphicon glyphicon-exclamation-sign"></span> '+item.message+'</li></ul></span>');
                        }
                        else {
                            targetParent.parents('.form-group').find(' span.help-block ul.list-unstyled').append('<li><span class="glyphicon glyphicon-exclamation-sign"></span> '+item.message+'</li>');
                        }
                    }
                }

            });
        })
        .always(function() {
            waitingResponse = false;
            if(!synch){
                onFormChange();
            }
        });
}

$("form[name=revision]").submit(function( ) {
    //disable all pending auto-save
    waitingResponse = true;
    synch = true;
    $('#data-out-of-sync').remove();
});

function updateCollectionLabel()
{
    $('.collection-panel').each(function() {
        const collectionPanel = $(this);
        const fieldLabel = collectionPanel.data('label-field');
        if (fieldLabel) {
            $(this).children(':first').children(':first').children().each(function(){
                let val = $(this).find('input[name*='+fieldLabel+']').val();
                if (typeof val !== 'undefined') {
                    $(this).find('.collection-label-field').html(' | ' + val)
                }
            });
        }
    });
}

function updateChoiceFieldTypes()
{
    $('.ems-choice-field-type').each(function(){
        const choice = $(this);
        const collectionName = choice.data('linked-collection');
        if(collectionName)
        {

            $('.collection-panel').each(function()
            {
                const collectionPanel = $(this);
                if(collectionPanel.data('name') === collectionName)
                {
                    const collectionLabelField = choice.data('collection-label-field');

                    collectionPanel.children('.panel-body').children('.collection-panel-container').children('.collection-item-panel').each(function(){

                        const collectionItem = $(this);
                        const index = collectionItem.data('index');
                        const id = collectionItem.data('id');
                        let label = ' #'+index;

                        if(collectionLabelField)
                        {
                            label += ': '+$('#'+id+'_'+collectionLabelField).val();
                        }

                        const multiple = choice.data('multiple');
                        const expanded = choice.data('expanded');

                        if(expanded)
                        {
                            const option = choice.find('input[value="'+index+'"]');
                            if(option.length)
                            {
                                const parent = option.closest('.checkbox,.radio');
                                if($('#'+id+'__ems_internal_deleted').val() === 'deleted'){
                                    parent.hide();
                                    option.addClass('input-to-hide');
                                    if(multiple)
                                    {
                                        option.attr('checked', false);
                                    }
                                    else
                                    {
                                        option.removeAttr("checked");
                                    }
                                }
                                else{
                                    option.removeClass('input-to-hide');
                                    parent.find('.checkbox-radio-label-text').text(label);
                                    parent.show();
                                }
                            }
                        }
                        else
                        {
                            const option = choice.find('option[value="'+index+'"]');
                            if(option.length)
                            {
                                if($('#'+id+'__ems_internal_deleted').val() === 'deleted')
                                {
                                    option.addClass('input-to-hide');
                                }
                                else
                                {
                                    option.removeClass('input-to-hide');
                                    option.show();
                                    option.text(label);
                                }

                            }
                        }

                    })
                }

            });

        }

        $(this).find('option.input-to-hide').hide();
        $(this).find('.input-to-hide').each(function(){
            $(this).closest('.checkbox,.radio').hide();
        })
    });
}

$(window).ready(function() {
    updateChoiceFieldTypes();
    updateCollectionLabel();
    editRevisionEventListeners($('form[name=revision]'), onFormChange);
});

if (null !== document.querySelector('form[name="revision"]')) {
    $(document).keydown(function (e) {
        let key = undefined;
        /**
         * @param {{keyIdentifier:string}} e
         */
        const possible = [e.key, e.keyIdentifier, e.keyCode, e.which];

        while (key === undefined && possible.length > 0) {
            key = possible.pop();
        }

        if (typeof key === "number" && (115 === key || 83 === key) && (e.ctrlKey || e.metaKey) && !(e.altKey)) {
            e.preventDefault();
            onFormChange(e, true);
            return false;
        }
        return true;

    });
}

