'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import FileUploader from './js/FileUploader';
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
const initUpload = primaryBox.data('init-upload');
const fileExtract = primaryBox.data('file-extract');
const assetPath = document.querySelector("BODY").getAttribute('data-asset-path') ;

$("form[name=revision]").submit(function( ) {
    //disable all pending auto-save
    waitingResponse = true;
    synch = true;
});

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

function initFileUploader(fileHandler, container){


    const sha1Input = $(container).find(".sha1");
    const typeInput = $(container).find(".type");
    const nameInput = $(container).find(".name");
    const progressBar = $(container).find(".progress-bar");
    const progressText = $(container).find(".progress-text");
    const progressNumber = $(container).find(".progress-number");
    const viewButton = $(container).find(".view-asset-button");
    const clearButton = $(container).find(".clear-asset-button");
    const previewTab = $(container).find(".asset-preview-tab");
    const uploadTab = $(container).find(".asset-upload-tab");
    const previewLink = $(container).find(".img-responsive");
    const assetHashSignature = $(container).find(".asset-hash-signature");
    const dateInput = $(container).find(".date");
    const authorInput = $(container).find(".author");
    const languageInput = $(container).find(".language");
    const contentInput = $(container).find(".content");
    const titleInput = $(container).find(".title");


    previewTab.hide();
    uploadTab.show();

    const fileUploader = new FileUploader({
        file: fileHandler,
        algo: $('body').attr('data-hash-algo'),
        initUrl: initUpload,
        onHashAvailable: function(sha1, type, name){
            $(sha1Input).val(sha1);
            $(assetHashSignature).empty().append(sha1);
            $(typeInput).val(type);
            $(nameInput).val(name);
            $(dateInput).val('');
            $(authorInput).val('');
            $(languageInput).val('');
            $(contentInput).val('');
            $(titleInput).val('');
            $(viewButton).addClass('disabled');
            $(clearButton).addClass('disabled');
        },
        onProgress: function(status, progress, remaining){
            if(status !== 'Computing hash' && $(sha1Input).val() !== fileUploader.hash){
                $(sha1Input).val(fileUploader.hash);
                console.log('Sha1 mismatch!');
            }
            const percentage = Math.round(progress*100);
            $(progressBar).css('width', percentage+'%');
            $(progressText).html(status);
            $(progressNumber).html(remaining);
        },
        onUploaded: function(assetUrl, previewUrl){
            viewButton.attr('href', assetUrl);
            previewLink.attr('src', previewUrl);
            viewButton.removeClass("disabled");
            clearButton.removeClass("disabled");
            previewTab.show();
            uploadTab.hide();

            if($(contentInput).length) {
                FileDataExtrator(container);
            }
            else {
                onFormChange();
            }
        },
        onError: function(message, code){
            $(progressBar).css('width', '0%');
            $(progressText).html(message);
            if (code === undefined){
                $(progressNumber).html('');
            }
            else {
                $(progressNumber).html('Error code : '+code);
            }
            $(sha1Input).val('');
            $(assetHashSignature).empty();
            $(typeInput).val('');
            $(nameInput).val('');
            $(dateInput).val('');
            $(authorInput).val('');
            $(languageInput).val('');
            $(contentInput).val('');
            $(titleInput).val('');
            $(viewButton).addClass('disabled');
            $(clearButton).addClass('disabled');
        },
    });
}


//file selection
function FileSelectHandler(e) {

    // cancel event and hover styling
    FileDragHover(e);

    // fetch FileList object
    const files = e.target.files || e.dataTransfer.files;

    // process all File objects
    for (let i = 0; i < files.length; ++i) {
        if(files.hasOwnProperty(i)){
            initFileUploader(files[i], this);
            break;
        }
    }
}

//file data extractor
function FileDataExtrator(container) {

    const sha1Input = $(container).find(".sha1");
    const nameInput = $(container).find(".name");

    const dateInput = $(container).find(".date");
    const authorInput = $(container).find(".author");
    const languageInput = $(container).find(".language");
    const contentInput = $(container).find(".content");
    const titleInput = $(container).find(".title");


    const progressText = $(container).find(".progress-text");
    const progressNumber = $(container).find(".progress-number");
    const previewTab = $(container).find(".asset-preview-tab");
    const uploadTab = $(container).find(".asset-upload-tab");

    const urlPattern = fileExtract
        .replace(/__file_identifier__/g, $(sha1Input).val())
        .replace(/__file_name__/g, $(nameInput).val());



    $(progressText).html('Extracting information from asset...');
    $(progressNumber).html('');
    uploadTab.show();
    previewTab.hide();

    waitingResponse = window.ajaxRequest.get(urlPattern)
        .success(function(response) {
            $(dateInput).val(response.date);
            $(authorInput).val(response.author);
            $(languageInput).val(response.language);
            $(contentInput).val(response.content);
            $(titleInput).val(response.title);
        })
        .fail(function() {
            const modal = $('#modal-notifications');
            $(modal.find('.modal-body')).html('Something went wrong while extrating information from file');
            modal.modal('show');
        })
        .always(function() {
            $(progressText).html('');
            uploadTab.hide();
            previewTab.show();
        });

}

//file drag hover
function FileDragHover(e) {
    e.stopPropagation();
    e.preventDefault();
    //e.target.className = (e.type == "dragover" ? "hover" : "");
}

function addEventListeners(target){

    new EmsListeners(target.get(0));


    target.find(".file-uploader-input").fileinput({
        'showUpload':false,
        'showCaption': false,
        'showPreview': false,
        'showRemove': false,
        'showCancel': false,
        'showClose': false,
        'browseIcon': '<i class="fa fa-upload"></i>&nbsp;',
        'browseLabel': 'Upload file'
    });

    target.find(".extract-file-info").click(function() {
        const target = $(this).closest('.modal-content');
        FileDataExtrator(target);
    });

    target.find(".clear-asset-button").click(function() {
        const parent = $(this).closest('.file-uploader-row');
        const sha1Input = $(parent).find(".sha1");
        const typeInput = $(parent).find(".type");
        const nameInput = $(parent).find(".name");
        const progressBar = $(parent).find(".progress-bar");
        const progressText = $(parent).find(".progress-text");
        const progressNumber = $(parent).find(".progress-number");
        const previewTab = $(parent).find(".asset-preview-tab");
        const uploadTab = $(parent).find(".asset-upload-tab");
        const assetHashSignature = $(parent).find(".asset-hash-signature");
        const dateInput = $(parent).find(".date");
        const authorInput = $(parent).find(".author");
        const languageInput = $(parent).find(".language");
        const contentInput = $(parent).find(".content");
        const titleInput = $(parent).find(".title");

        $(parent).find(".file-uploader-input").val('');
        sha1Input.val('');
        assetHashSignature.empty();
        typeInput.val('');
        nameInput.val('');
        $(dateInput).val('');
        $(authorInput).val('');
        $(languageInput).val('');
        $(contentInput).val('');
        $(titleInput).val('');
        $(progressBar).css('width', '0%');
        $(progressText).html('');
        $(progressNumber).html('');
        previewTab.hide();
        uploadTab.show();
        $(parent).find('.view-asset-button').addClass('disabled');
        $(this).addClass('disabled');
        return false
    });

    target.find(".file-uploader-input").change(function(){
        initFileUploader($(this)[0].files[0], $(this).closest(".file-uploader-row"));
    });


    target.find(".file-uploader-row").each(function(){
        // file drop
        this.addEventListener("dragover", FileDragHover, false);
        this.addEventListener("dragleave", FileDragHover, false);
        this.addEventListener("drop", FileSelectHandler, false);
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


        ckconfig.height = height;
        ckconfig.div_wrapTable = 'true';

        //http://stackoverflow.com/questions/18250404/ckeditor-strips-i-tag
        //TODO: see if we could moved it to the wyysiwyg templates tools
        ckconfig.allowedContent = true;
        ckconfig.extraAllowedContent = 'p(*)[*]{*};div(*)[*]{*};li(*)[*]{*};ul(*)[*]{*}';
        CKEDITOR.dtd.$removeEmpty.i = 0;


        if (!CKEDITOR.instances[$( this ).attr('id')]) {
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
                advCSSClasses['default'] = "table table-bordered";

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

        $( this ).timepicker(settings).on('changeTime.timepicker', onFormChange);


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

        $(this).on('change', onFormChange);
    });

    target.find('.ems_daterangepicker').each(function( ) {

        const options = $(this).data('display-option');
        $(this).unbind('change');

        $(this).daterangepicker(
            options,
            function() {
                onFormChange();
            });
    });
}


$(window).ready(function() {

    updateChoiceFieldTypes();

    for(let i=0; i < stylesSets.length; ++i) {
        CKEDITOR.stylesSet.add(
            stylesSets[i].name,
            stylesSets[i].config
        );
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

