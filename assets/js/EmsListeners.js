
import jquery from 'jquery';
import ace from 'ace-builds/src-noconflict/ace';
require('icheck');
import PickFileFromServer from './module/pickFileFromServer';
import FileUploader from "@elasticms/file-uploader";
import Datatables from "./module/datatables";
import {tooltipDataLinks} from "./helper/tooltip";
import {resizeImage} from "./helper/resizeImage";


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
        new Datatables(target);
    }

    addListeners() {
        this.addCheckBoxListeners();
        this.addSelect2Listeners();
        this.addCollapsibleCollectionListeners();
        this.addSortableListListeners();
        this.addNestedSortableListeners();
        this.addCodeEditorListeners();
        this.addRemoveButtonListeners();
        this.addObjectPickerListeners();
        this.addFieldsToDisplayByValue();
        this.addFileUploaderListerners();
        this.addA2LixLibSfCollection();
        this.addDisabledButtonTreatListeners();
        this.addDateRangeListeners();
        tooltipDataLinks(this.target);
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

            let minLines = 1;
            if(hiddenField.data('min-lines') && hiddenField.data('min-lines') > 0){
                minLines = hiddenField.data('min-lines');
            }

            const editor = ace.edit(pre, {
                mode: language,
                readOnly: disabled,
                maxLines: maxLines,
                minLines: minLines,
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
            let handle = nestedList.data('nested-handle');

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

            if(typeof handle === 'undefined') {
                handle = 'div';
            }

            nestedList.nestedSortable({
                forcePlaceholderSize: true,
                handle: handle,
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

    initFilesUploader(files, context)  {
        const container = $(context).closest(".file-uploader-row");
        const template = container.data('multiple');
        const previewTab = container.find(".tab-pane.asset-preview-tab");
        const uploadTab = container.find(".tab-pane.asset-upload-tab");
        const listTab = container.find(".tab-pane.asset-list-tab > ol");

        if (typeof template !== 'undefined') {
            listTab.removeClass('hidden');
            previewTab.addClass('hidden');
            uploadTab.addClass('hidden');
        }

        let nextId = parseInt(listTab.attr('data-file-list-index'));
        listTab.attr('data-file-list-index', nextId+files.length);

        for (let i = 0; i < files.length; ++i) {
            if (!files.hasOwnProperty(i)) {
                continue;
            }

            if (typeof template !== 'undefined') {

                const subContainer = $(template.replace(/__name__/g, nextId++));
                listTab.append(subContainer);
                new EmsListeners(subContainer.get(0), this.onChangeCallback);
                this.initFileUploader(files[i], subContainer);
            } else {
                this.initFileUploader(files[i], container);
                break;
            }
        }
    }

    _resizeImage(fileHandler, container, previewUrl){
        const self = this;
        const mainDiv = $(container);
        const metaFields = (typeof mainDiv.data('meta-fields') !== 'undefined');
        const contentInput = mainDiv.find(".content");
        const resizedImageHashInput = mainDiv.find(".resized-image-hash");
        const previewLink = mainDiv.find(".img-responsive");

        resizeImage(this.hashAlgo, this.initUpload, fileHandler).then((response) => {
            if (null === response) {
                $(resizedImageHashInput).val('')
                previewLink.attr('src', previewUrl);
            } else {
                $(resizedImageHashInput).val(response.hash)
                previewLink.attr('src', response.url);
            }
        }).catch((errorMessage) => {
            console.error(errorMessage)
            $(resizedImageHashInput).val('')
            previewLink.attr('src', previewUrl);
        }).finally(() => {
            if(metaFields && $(contentInput).length) {
                self.fileDataExtrator(container);
            }
            else if(typeof self.onChangeCallback === "function"){
                self.onChangeCallback();
            }
        })
    }

    initFileUploader(fileHandler, container){
        const mainDiv = $(container);
        const metaFields = (typeof mainDiv.data('meta-fields') !== 'undefined');
        const sha1Input = mainDiv.find(".sha1");
        const resizedImageHashInput = mainDiv.find(".resized-image-hash");
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

        previewTab.addClass('hidden');
        uploadTab.removeClass('hidden');

        const fileUploader = new FileUploader({
            file: fileHandler,
            algo: this.hashAlgo,
            initUrl: this.initUpload,
            emsListener: this,
            onHashAvailable: function(hash, type, name){
                $(sha1Input).val(hash);
                $(resizedImageHashInput).val('');
                $(assetHashSignature).empty().append(hash);
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
                const imageTypes = ['image/png','image/jpeg','image/webp']
                if(imageTypes.includes(fileHandler.type)) {
                    self._resizeImage(fileHandler, container, previewUrl);
                }
                else if(metaFields && $(contentInput).length) {
                    previewLink.attr('src', previewUrl)
                    self.fileDataExtrator(container);
                }
                else if(typeof self.onChangeCallback === "function"){
                    previewLink.attr('src', previewUrl)
                    self.onChangeCallback();
                } else {
                    previewLink.attr('src', previewUrl)
                }
                viewButton.removeClass("disabled");
                clearButton.removeClass("disabled");
                previewTab.removeClass('hidden');
                uploadTab.addClass('hidden');
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
                $(resizedImageHashInput).val('');
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
            .fail(function(response) {
                if (!response || !Array.isArray(response.warning) || 1 !== response.warning.length) {
                    const modal = $('#modal-notifications');
                    $(modal.find('.modal-body')).html('Something went wrong while extracting information from file');
                    modal.modal('show');
                }
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


    onAssetData(row, data){
        const mainDiv = $(row);
        const sha1Input = mainDiv.find(".sha1");
        const resizedImageHashInput = mainDiv.find(".resized-image-hash");
        const metaFields = (typeof mainDiv.data('meta-fields') !== 'undefined');
        const typeInput = mainDiv.find(".type");
        const nameInput = mainDiv.find(".name");
        const assetHashSignature = mainDiv.find(".asset-hash-signature");
        const dateInput = mainDiv.find(".date");
        const authorInput = mainDiv.find(".author");
        const languageInput = mainDiv.find(".language");
        const contentInput = mainDiv.find(".content");
        const titleInput = mainDiv.find(".title");
        const viewButton = mainDiv.find(".view-asset-button");
        const clearButton = mainDiv.find(".clear-asset-button");
        const previewTab = mainDiv.find(".asset-preview-tab");
        const uploadTab = mainDiv.find(".asset-upload-tab");
        const previewLink = mainDiv.find(".img-responsive");
        sha1Input.val(data.sha1);
        resizedImageHashInput.val(data._image_resized_hash ?? '');
        assetHashSignature.empty().append(data.sha1);
        typeInput.val(data.mimetype);
        nameInput.val(data.filename);
        viewButton.attr('href', data.view_url);
        previewLink.attr('src', data.preview_url);
        dateInput.val('');
        authorInput.val('');
        languageInput.val('');
        contentInput.val('');
        titleInput.val('');
        viewButton.removeClass('disabled');
        clearButton.removeClass('disabled');
        previewTab.removeClass('hidden');
        uploadTab.addClass('hidden');

        if(metaFields) {
            this.fileDataExtrator(row);
        } else if(typeof this.onChangeCallback === "function"){
            this.onChangeCallback();
        }
    }

    addFileUploaderListerners() {
        const target = jquery(this.target);
        const self = this;
        new PickFileFromServer(this.target);

        target.find(".file-uploader-row").on('updateAssetData', function(event) {
            self.onAssetData(this, event.originalEvent.detail);
        });

        target.find(".extract-file-info").click(function() {
            const target = $(this).closest('.modal-content');
            self.fileDataExtrator(target, true);
        });

        target.find(".clear-asset-button").click(function() {
            const parent = $(this).closest('.file-uploader-row');
            const sha1Input = $(parent).find(".sha1");
            const resizedImageHashInput = $(parent).find(".resized-image-hash");
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
            resizedImageHashInput.val('');
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
            previewTab.addClass('hidden');
            uploadTab.removeClass('hidden');
            $(parent).find('.view-asset-button').addClass('disabled');
            $(this).addClass('disabled');
            return false
        });

        let fileUploaderInputs = this.target.getElementsByClassName('file-uploader-input');

        [].forEach.call(fileUploaderInputs, function (fileUploaderInput) {
            fileUploaderInput.onchange = () => {
                self.initFilesUploader(fileUploaderInput.files, fileUploaderInput);
            }
        });

        target.find(".file-uploader-row").each(function(){
            // file drop
            this.addEventListener("dragover", self.fileDragHover, false);
            this.addEventListener("dragleave", self.fileDragHover, false);
            this.addEventListener("drop", function(e) {
                self.fileDragHover(e);
                const files = e.target.files || e.dataTransfer.files;
                self.initFilesUploader(files, this);
            }, false);
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
            const querySearch = selectItem.data('query-search');
            const querySearchLabel = selectItem.data('query-search-label');
            const circleOnly = selectItem.data('circleOnly');
            const dynamicLoading = selectItem.data('dynamic-loading');
            const sortable = selectItem.data('sortable');
            const locale = selectItem.data('locale');
            const referrerEmsId = selectItem.data('referrer-ems-id');

            const params = {
                escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
                templateResult: formatRepo, // omitted for brevity, see the source of this page
                templateSelection: formatRepoSelection, // omitted for brevity, see the source of this page
                allowClear: true,
                //https://github.com/select2/select2/issues/3781
                placeholder: querySearchLabel && '' !== querySearchLabel ? querySearchLabel : 'Search',
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
                            searchId: searchId,
                            querySearch: querySearch
                        };

                        if (locale !== undefined) {
                            data.locale = locale;
                        }
                        if (referrerEmsId !== undefined) {
                            data.referrerEmsId = referrerEmsId;
                        }

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
            placeholder: "",
            escapeMarkup: function (markup) { return markup; }
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

    addA2LixLibSfCollection()
    {
        jquery(this.target).find('.a2lix_lib_sf_collection').each(function () {
            a2lix_lib.sfCollection.init({
                collectionsSelector: '#' + $(this).attr('id'),
                manageRemoveEntry: true,
                lang: {
                    add: $(this).data('lang-add'),
                    remove: $(this).data('lang-remove'),
                }
            });
        });
    }

    addDisabledButtonTreatListeners() {
        let treat = document.querySelector('form[name="treat_notifications"] #treat_notifications_publishTo');
        if (treat) {
            treat.addEventListener('change', function () {
                let form = treat.closest('form');
                let isDisabledAccept = this.value.length == 0 ? true : false;
                form.elements.treat_notifications_accept.disabled = isDisabledAccept;
                form.elements.treat_notifications_reject.disabled = !isDisabledAccept;
            });
        }
    }

    addDateRangeListeners() {
        const self = this;
        jquery(this.target).find('.ems_daterangepicker').each(function( ) {

            const options = $(this).data('display-option');
            $(this).unbind('change');

            if ($(this).not('.ignore-ems-update')) {
                if (typeof self.onChangeCallback === "function") {
                    $(this).daterangepicker(options, function() { self.onChangeCallback(); });
                }
            } else {
                $(this).daterangepicker(options);
            }
        });
    }
}
