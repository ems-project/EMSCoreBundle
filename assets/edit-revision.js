'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;


let waitingResponse = false;
let synch = true;

const primaryBox = $('#revision-primary-box');
const updateMode = primaryBox.data('update-mode');
const wysiwygConfig = primaryBox.data('wysiwyg-config');
const uploadUrl = primaryBox.data('upload-url');
const imageUrl = primaryBox.data('image-url');
const stylesSets = primaryBox.data('styles-sets');
const assetPath = document.querySelector("BODY").getAttribute('data-asset-path') ;

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

function addEventListeners(target){

    new EmsListeners(target.get(0), onFormChange);

    target.find('button#btn-publish-version').on('click', function(e) {
        e.preventDefault();
        $('#publish-version-modal').modal('show');
    });

    target.find('.remove-content-button').on('click', function(e) {
        // prevent the link from creating a "#" on the URL
        e.preventDefault();

        const panel = $(this).closest('.collection-item-panel');
        panel.find('input._ems_internal_deleted').val('deleted');
        panel.hide();
        onFormChange();
    });

    target.find("input").not(".ignore-ems-update").on('input', onFormChange);
    target.find("select").not(".ignore-ems-update").on('change', onFormChange);
    target.find("textarea").not(".ignore-ems-update").on('input', onFormChange);

    target.find('.add-content-button').on('click', function(e) {
        // prevent the link from creating a "#" on the URL
        e.preventDefault();

        const panel = $(this).closest('.collection-panel');
        const index = panel.data('index');
        const prototype = panel.data('prototype');
        const prototypeName = new RegExp(panel.data('prototype-name'), "g");
        const prototypeLabel = new RegExp(panel.data('prototype-label'), "g");

        // Replace '__label__name__$fieldId__' in the prototype's HTML to
        // Replace '__name__$fieldId__' in the prototype's HTML to
        // instead be a number based on how many items we have
        const newForm = $(prototype.replace(prototypeLabel, (index+1)).replace(prototypeName, index));
        // increase the index with one for the next item
        panel.data('index', (index + 1));

        addEventListeners(newForm);

        panel.children('.panel-body').children('.collection-panel-container').append(newForm);
        onFormChange();

    });

    target.find('.ems-sortable > div').sortable({
        handle: ".ems-handle"
    });

    target.find('.selectpicker').selectpicker();

    target.find(".ckeditor_ems").each(function(){

        const ckconfig = wysiwygConfig;

        ckconfig.imageUploadUrl = uploadUrl;
        ckconfig.imageBrowser_listUrl = imageUrl;

        let height = $( this ).attr('data-height');
        if(!height){
            height = 400;
        }

        const format_tags = $( this ).attr('data-format-tags');
        if(format_tags){
            ckconfig.format_tags = format_tags;
        }

        const styles_set = $( this ).attr('data-styles-set');
        if(styles_set){
            ckconfig.stylesSet = styles_set;
        }

        const content_css = $( this ).attr('data-content-css');
        if(content_css){
            ckconfig.contentsCss = content_css;
        }

        const language = $( this ).attr('data-lang');
        if(language){
            ckconfig.language = language;
        }



        let tableDefaultCss = $( this ).attr('data-table-default-css');
        if(typeof tableDefaultCss == 'undefined'){
            tableDefaultCss = 'table table-bordered';
        }


        ckconfig.height = height;
        ckconfig.div_wrapTable = 'true';

        //http://stackoverflow.com/questions/18250404/ckeditor-strips-i-tag
        //TODO: see if we could moved it to the wysiwyg templates tools
        ckconfig.allowedContent = true;
        ckconfig.extraAllowedContent = 'p(*)[*]{*};div(*)[*]{*};li(*)[*]{*};ul(*)[*]{*}';
        CKEDITOR.dtd.$removeEmpty.i = 0;


        if (!CKEDITOR.instances[$( this ).attr('id')] && $(this).hasClass('ignore-ems-update') === false) {
            CKEDITOR.replace(this, ckconfig).on('key', onFormChange );
        }
        else {
            CKEDITOR.replace( $( this ).attr('id'), ckconfig);
        }


        //Set defaults that are compatible with bootstrap for html generated by CKEDITOR (e.g. tables)
        CKEDITOR.on( 'dialogDefinition', function( ev )
        {
            // Take the dialog name and its definition from the event data.
            const dialogName = ev.data.name;
            const dialogDefinition = ev.data.definition;

            // Check if the definition is from the dialog we're interested in (the "Table" dialog).
            if ( dialogName === 'table' )
            {
                // Get a reference to the "Table Info" tab.
                const infoTab = dialogDefinition.getContents( 'info' );

                const txtBorder = infoTab.get( 'txtBorder');
                txtBorder['default'] = 0;
                const txtCellPad = infoTab.get( 'txtCellPad');
                txtCellPad['default'] = "";
                const txtCellSpace = infoTab.get( 'txtCellSpace');
                txtCellSpace['default'] = "";
                const txtWidth = infoTab.get( 'txtWidth' );
                txtWidth['default'] = "";

                // Get a reference to the "Table Advanced" tab.
                const advancedTab = dialogDefinition.getContents( 'advanced' );

                const advCSSClasses = advancedTab.get( 'advCSSClasses' );
                advCSSClasses['default'] = tableDefaultCss;

            }
        });


    });

    target.find(".colorpicker-component").colorpicker();

    target.find(".colorpicker-component").bind('changeColor', onFormChange);

    target.find(".timepicker").each(function(){

        const settings = {
            showMeridian: 	$( this ).data('show-meridian'),
            explicitMode: 	$( this ).data('explicit-mode'),
            minuteStep: 	$( this ).data('minute-step'),
            disableMousewheel: true,
            defaultTime: false
        };

        $( this ).unbind( "change" );

        if ($(this).not('.ignore-ems-update')) {
            $( this ).timepicker(settings).on('changeTime.timepicker', onFormChange);
        } else {
            $( this ).timepicker(settings);
        }
    });


    target.find('.datepicker').each(function( ) {

        $(this).unbind('change');
        const params = {
            format: $(this).attr('data-date-format'),
            todayBtn: true,
            weekStart: $(this).attr('data-week-start'),
            daysOfWeekHighlighted: $(this).attr('data-days-of-week-highlighted'),
            daysOfWeekDisabled: $(this).attr('data-days-of-week-disabled'),
            todayHighlight: $(this).attr('data-today-highlight')
        };

        if($(this).attr('data-multidate') && $(this).attr('data-multidate') !== 'false'){
            params.multidate = true;
        }

        $(this).datepicker(params);

        $(this).not(".ignore-ems-update").on('change', onFormChange);
    });

    target.find('.datetime-picker').each(function( ) {
        let $element = $(this);
        $element.unbind('change');
        $element.datetimepicker({
            keepInvalid: true, //otherwise daysOfWeekDisabled or disabledHours will not work!
            extraFormats: [moment.ISO_8601]
        });
        $element.not(".ignore-ems-update").on('change', onFormChange);
    });

    target.find('.ems_daterangepicker').each(function( ) {

        const options = $(this).data('display-option');
        $(this).unbind('change');

        if ($(this).not('.ignore-ems-update')) {
            $(this).daterangepicker(options, function() { onFormChange(); });
        } else {
            $(this).daterangepicker(options);
        }
    });
}


$(window).ready(function() {

    updateChoiceFieldTypes();
    updateCollectionLabel();

    if (typeof stylesSets !== 'undefined') {
        for(let i=0; i < stylesSets.length; ++i) {
            CKEDITOR.stylesSet.add(
                stylesSets[i].name,
                stylesSets[i].config
            );
        }
    }

    CKEDITOR.plugins.addExternal('adv_link', assetPath+'bundles/emscore/js/cke-plugins/adv_link/plugin.js', '' );
    CKEDITOR.plugins.addExternal('div', assetPath+'bundles/emscore/js/cke-plugins/div/plugin.js', '' );
    CKEDITOR.plugins.addExternal('imagebrowser', assetPath+'bundles/emscore/js/cke-plugins/imagebrowser/plugin.js', '' );
    addEventListeners($('form[name=revision]'));
});

$(document).keydown(function(e) {

    let key = undefined;

    /**
     * @param {{keyIdentifier:string}} e
     */
    const possible = [ e.key, e.keyIdentifier, e.keyCode, e.which ];

    while (key === undefined && possible.length > 0)
    {
        key = possible.pop();
    }

    if (typeof key === "number" && ( 115 === key || 83 === key ) && (e.ctrlKey || e.metaKey) && !(e.altKey))
    {
        e.preventDefault();
        onFormChange(e, true);
        return false;
    }
    return true;

});

export {
    addEventListeners
}

