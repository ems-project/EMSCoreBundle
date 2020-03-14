
import jquery from 'jquery';
import ace from 'ace-builds/src-noconflict/ace';
require('icheck');
import JsonMenuEditor from './JsonMenuEditor';
import FileUploader from "./FileUploader";


export default class EmsListeners {

    constructor(target, onChangeCallback=null) {
        if(target === undefined) {
            console.log('Impossible to add ems listeners as no target is defined');
            return;
        }

        this.target = target;
        this.onChangeCallback = onChangeCallback;
        const primaryBox = $('body');
        this.initUpload = primaryBox.data('init-upload');
        this.fileExtract = primaryBox.data('file-extract');
        this.fileExtractForced = primaryBox.data('file-extract-forced');
        this.hashAlgo = primaryBox.data('hash-algo');
        this.addListeners();
    }

    addListeners() {
        this.addCheckBoxListeners();
        this.addJsonMenuEditorListeners();
        this.addSelect2Listeners();
        this.addCollapsibleCollectionListeners();
        this.addSortableListListeners();
        this.addNestedSortableListeners();
        this.addCodeEditorListeners();
        this.addRemoveButtonListeners();
        this.addObjectPickerListeners();
        this.addFieldsToDisplayByValue();
        this.addFileUploaderListerners();
    }

    addFieldsToDisplayByValue() {
        const elements = this.target.getElementsByClassName('fields-to-display-by-input-value');
        for(let i = 0;i < elements.length; i++) {
            const fieldsToDisplay = elements[i].closest('.fields-to-display-by-value').getElementsByClassName('fields-to-display-for');
            elements[i].onchange = function (){
                const value = elements[i].value;
                for(let j = 0;j < fieldsToDisplay.length; j++) {
                    fieldsToDisplay[j].closest('.form-group').style.display = (fieldsToDisplay[j].classList.contains('fields-to-display-for-'+value)?'block':'none');
                }
            }
            elements[i].onchange();
        }
    }

    static getAceConfig() {
        if(!EmsListeners.aceConfig) {
            EmsListeners.aceConfig = ace.require("ace/config");
            EmsListeners.aceConfig.init();
        }
        return EmsListeners.aceConfig;
    }

    addCodeEditorListeners() {
        const self = this;
        const codeEditors = this.target.getElementsByClassName('ems-code-editor');
        for(let i = 0;i < codeEditors.length; i++) {

            const codeDiv = jquery(codeEditors[i]);
            let pre = codeEditors[i];
            let hiddenField = codeDiv;
            let disabled = true;

            if(pre.tagName === 'DIV') {
                pre = codeDiv.find('pre').get(0);
                hiddenField = codeDiv.find('input');
                disabled = hiddenField.data('disabled');
            }

            let language = hiddenField.data('language');
            language = language?language:'ace/mode/twig';

            let theme = hiddenField.data('theme');
            theme = theme?theme:'ace/theme/chrome';


            let maxLines = 15;
            if(hiddenField.data('max-lines') && hiddenField.data('max-lines') > 0){
                maxLines = hiddenField.data('max-lines');
            }

            const editor = ace.edit(pre, {
                mode: language,
                readOnly: disabled,
                maxLines: maxLines,
                theme: theme
            });

            editor.on("change", function(e){
                hiddenField.val(editor.getValue());
                if(typeof self.onChangeCallback === "function") {
                    self.onChangeCallback();
                }
            });

            editor.commands.addCommands([{
                name: "fullscreen",
                bindKey: {win: "F11", mac: "Esc"},
                exec: function(editor) {
                    if (codeDiv.hasClass('panel-fullscreen')) {
                        editor.setOption("maxLines", maxLines);
                        codeDiv.removeClass('panel-fullscreen');
                        editor.setAutoScrollEditorIntoView(false);
                    }
                    else {
                        editor.setOption("maxLines", Infinity);
                        codeDiv.addClass('panel-fullscreen');
                        editor.setAutoScrollEditorIntoView(true);
                    }

                    editor.resize();

                }
            }, {
                name: "showKeyboardShortcuts",
                bindKey: {win: "Ctrl-Alt-h", mac: "Command-Alt-h"},
                exec: function(editor) {
                    EmsListeners.getAceConfig().loadModule("ace/ext/keybinding_menu", function(module) {
                        module.init(editor);
                        editor.showKeyboardShortcuts();
                    });
                }
            }]);
        }
    }


    addNestedSortableListeners() {
        const nestedList = jquery(this.target).find('.nested-sortable');
        nestedList.each(function() {
            const nestedList = jquery(this);

            let maxLevels = nestedList.data('nested-max-level');
            let isTree = nestedList.data('nested-is-tree');

            if(typeof maxLevels === 'undefined') {
                maxLevels = 1;
            }
            else {
                maxLevels = Number(maxLevels);
            }

            if(typeof isTree === 'undefined') {
                isTree = false;
            }
            else {
                isTree = ( isTree === 'true' );
            }

            nestedList.nestedSortable({
                forcePlaceholderSize: true,
                handle: 'div',
                helper: 'clone',
                items: 'li',
                opacity: .6,
                placeholder: 'placeholder',
                revert: 250,
                tabSize: 25,
                tolerance: 'pointer',
                toleranceElement: '> div',
                maxLevels: maxLevels,
                expression: /()(.+)/,

                isTree: isTree,
                expandOnHover: 700,
                startCollapsed: true
            });
        });

        jquery(this.target).find('.reorder-button').on('click', function(){
            const form = jquery(this).closest('form');
            const hierarchy = form.find('.nested-sortable').nestedSortable('toHierarchy', {startDepthCount: 0});
            form.find('input.reorder-items').val(JSON.stringify(hierarchy)).trigger("change");
        });

        let findCollapseButtonPrefix = '.json_menu_editor_fieldtype_widget ';

        if (jquery(this.target).find(findCollapseButtonPrefix).length === 0) {
            findCollapseButtonPrefix = '.mjs-nestedSortable ';
        }

        if (jquery(this.target).hasClass('mjs-nestedSortable')) {
            findCollapseButtonPrefix = '';
        }

        jquery(this.target).find(findCollapseButtonPrefix+'.button-collapse').click(function (event) {
            event.preventDefault();
            const $isExpanded = ($(this).attr('aria-expanded') === 'true');
            $(this).parent().find('> button').attr('aria-expanded', !$isExpanded);
            let $panel = $(this).closest('.collapsible-container');
            if ($isExpanded) {
                $panel.find('ol').first().show();
            }
            else {
                $panel.find('ol').first().hide();
            }
        });

        jquery(this.target).find(findCollapseButtonPrefix+'.button-collapse-all').click(function (event) {
            event.preventDefault();
            const $isExpanded = ($(this).attr('aria-expanded') === 'true');
            let $panel = $(this).closest('.collapsible-container');
            $panel.find('.button-collapse').attr('aria-expanded', !$isExpanded);
            $panel.find('.button-collapse-all').attr('aria-expanded', !$isExpanded);
            if ($isExpanded) {
                $panel.find('ol').not('.not-collapsible').show();
            }
            else {
                $panel.find('ol').not('.not-collapsible').hide();
            }
        });


    }


    initFileUploader(fileHandler, container){
        const mainDiv = $(container);
        const metaFields = (typeof mainDiv.data('meta-fields') !== 'undefined');
        const sha1Input = mainDiv.find(".sha1");
        const typeInput = mainDiv.find(".type");
        const nameInput = mainDiv.find(".name");
        const progressBar = mainDiv.find(".progress-bar");
        const progressText = mainDiv.find(".progress-text");
        const progressNumber = mainDiv.find(".progress-number");
        const viewButton = mainDiv.find(".view-asset-button");
        const clearButton = mainDiv.find(".clear-asset-button");
        const previewTab = mainDiv.find(".asset-preview-tab");
        const uploadTab = mainDiv.find(".asset-upload-tab");
        const previewLink = mainDiv.find(".img-responsive");
        const assetHashSignature = mainDiv.find(".asset-hash-signature");
        const dateInput = mainDiv.find(".date");
        const authorInput = mainDiv.find(".author");
        const languageInput = mainDiv.find(".language");
        const contentInput = mainDiv.find(".content");
        const titleInput = mainDiv.find(".title");
        const self = this;


        previewTab.hide();
        uploadTab.show();

        const fileUploader = new FileUploader({
            file: fileHandler,
            algo: this.hashAlgo,
            initUrl: this.initUpload,
            emsListener: this,
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

                console.log(self.onChangeCallback);

                if(metaFields && $(contentInput).length) {
                    self.fileDataExtrator(container);
                }
                else if(typeof self.onChangeCallback === "function"){
                    self.onChangeCallback();
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


    fileSelectHandler(e) {

        // cancel event and hover styling
        this.fileDragHover(e);

        // fetch FileList object
        const files = e.target.files || e.dataTransfer.files;

        // process all File objects
        for (let i = 0; i < files.length; ++i) {
            if(files.hasOwnProperty(i)){
                this.initFileUploader(files[i], this);
                break;
            }
        }
    }


    fileDataExtrator(container, forced=false) {
        const self = this;

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

        const urlPattern = (forced?this.fileExtractForced:this.fileExtract)
            .replace(/__file_identifier__/g, $(sha1Input).val())
            .replace(/__file_name__/g, $(nameInput).val());



        $(progressText).html('Extracting information from asset...');
        $(progressNumber).html('');
        uploadTab.show();
        previewTab.hide();

        const waitingResponse = window.ajaxRequest.get(urlPattern)
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
                if(typeof self.onChangeCallback === "function") {
                    self.onChangeCallback();
                }
            });

    }

    fileDragHover(e) {
        e.stopPropagation();
        e.preventDefault();
    }


    addFileUploaderListerners() {
        const target = jquery(this.target);
        const self = this;

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
            self.fileDataExtrator(target, true);
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
            self.initFileUploader($(this)[0].files[0], $(this).closest(".file-uploader-row"));
        });


        target.find(".file-uploader-row").each(function(){
            // file drop
            this.addEventListener("dragover", self.fileDragHover, false);
            this.addEventListener("dragleave", self.fileDragHover, false);
            this.addEventListener("drop", self.fileSelectHandler, false);
        });
    }

    addSortableListListeners() {
        jquery(this.target).find('ul.sortable').sortable();
    }

    addCheckBoxListeners() {
        const self = this;
        jquery(this.target).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%'
        }).on('ifChecked', function(){
            if( jquery(this).attr('data-grouped-checkbox-target') ) {
                jquery(self.target).find(jquery(this).attr('data-grouped-checkbox-target')).iCheck('check');
            }
        }).on('ifUnchecked', function(){
            if( jquery(this).attr('data-grouped-checkbox-target') ) {
                jquery(self.target).find(jquery(this).attr('data-grouped-checkbox-target')).iCheck('uncheck');
            }
        });
    }


    addObjectPickerListeners() {
        const searchApiUrl = $('body').data('search-api');

        jquery(this.target).find(".objectpicker").each(function(){
            const selectItem = jquery(this);

            const type = selectItem.data('type');
            const searchId = selectItem.data('search-id');
            const circleOnly = selectItem.data('circleOnly');
            const dynamicLoading = selectItem.data('dynamic-loading');
            const sortable = selectItem.data('sortable');

            const params = {
                escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
                templateResult: formatRepo, // omitted for brevity, see the source of this page
                templateSelection: formatRepoSelection, // omitted for brevity, see the source of this page
                allowClear: true,
                //https://github.com/select2/select2/issues/3781
                placeholder: 'Select a document'
            };

            if(selectItem.attr('multiple')) {
                params.closeOnSelect = false;
            }

            if(dynamicLoading){
                params.minimumInputLength = 1;
                params.ajax = {
                    url: searchApiUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        let data = {
                            q: params.term, // search term
                            page: params.page,
                            type: type,
                            searchId: searchId
                        };

                        if (circleOnly !== undefined) {
                            data.circle = circleOnly;
                        }

                        return data;
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
                };
            }

            selectItem.select2(params);

            if(sortable){
                selectItem.parent().find("ul.select2-selection__rendered").sortable({
                    stop: function( ) {

                        //http://stackoverflow.com/questions/45888/what-is-the-most-efficient-way-to-sort-an-html-selects-options-by-value-while
                        const selected = selectItem.val();
                        const my_options = selectItem.find("option");

                        const ul = $(this);

                        my_options.sort(function(a,b) {
                            const indexA = ul.find("li[title='"+a.title.replace(/\'/g, "\\\'")+"']").index();
                            const indexB = ul.find("li[title='"+b.title.replace(/\'/g, "\\\'")+"']").index();

                            if (indexA > indexB) return 1;
                            if (indexA < indexB) return -1;
                            return 0
                        });
                        selectItem.empty().append( my_options );
                        selectItem.val(selected);
                    }
                });
            }
        });
    }

    addSelect2Listeners() {
        //Initialize Select2 Elements
        jquery(this.target).find(".select2").select2({
            allowClear: true,
            escapeMarkup: function (markup) { return markup; }
        });
    }

    addJsonMenuEditorListeners() {
        jquery(this.target).find(".json_menu_editor_fieldtype").each(function(){
            new JsonMenuEditor(this);
        });
    }

    addRemoveButtonListeners() {
        jquery(this.target).find('.remove-item')
            .on('click', function(event) {
                event.preventDefault();
                $(this).closest('li').remove();
            });

        jquery(this.target).find('.remove-filter')
            .on('click', function(event) {
                event.preventDefault();
                $(this).closest('.filter-container').remove();
            });

    }

    addCollapsibleCollectionListeners() {
        jquery(this.target).find('.collapsible-collection')
            .on('click', '.button-collapse', function() {
                const $isExpanded = ($(this).attr('aria-expanded') === 'true');
                $(this).parent().find('button').attr('aria-expanded', !$isExpanded);

                const panel = $(this).closest('.panel');
                panel.find('.collapse').first().collapse('toggle');
            })
            .on('click', '.button-collapse-all', function() {
                const $isExpanded = ($(this).attr('aria-expanded') === 'true');
                $(this).parent().find('button').attr('aria-expanded', !$isExpanded);

                const panel = $(this).closest('.panel');
                panel.find('.button-collapse').attr('aria-expanded', !$isExpanded);
                panel.find('.button-collapse-all').attr('aria-expanded', !$isExpanded);

                if (!$isExpanded) {
                    panel.find('.collapse').collapse('show');
                } else {
                    panel.find('.collapse').collapse('hide');
                }
            });
    }

}