
import jquery from 'jquery';
import ace from 'ace-builds/src-noconflict/ace';
require('icheck');


export default class EmsListeners {

    constructor(target) {
        if(target === undefined) {
            console.log('Impossible to add ems listeners as no target is defined');
            return;
        }

        this.target = target;
        this.addListeners();
    }

    addListeners() {
        this.addCheckBoxListeners();
        this.addSelect2Listeners();
        this.addCollapsibleCollectionListeners();
        this.addSortableListListeners();
        this.addNestedSortableListeners();
        this.addCodeEditorListeners();
        this.addRemoveButtonListeners();
    }

    static getAceConfig() {
        if(!EmsListeners.aceConfig) {
            EmsListeners.aceConfig = ace.require("ace/config");
            EmsListeners.aceConfig.init();
        }
        return EmsListeners.aceConfig;
    }

    addCodeEditorListeners() {

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

            const editor = ace.edit(pre);
            editor.setTheme(theme);
            editor.session.setMode(language);

            let maxLines = 15;
            if(hiddenField.data('max-lines') && hiddenField.data('max-lines') > 0){
                maxLines = hiddenField.data('max-lines');
            }

            if(disabled){
                editor.setOptions({
                    readOnly: true,
                    highlightActiveLine: false,
                    highlightGutterLine: false,
                    maxLines: maxLines
                });
                editor.renderer.$cursorLayer.element.style.opacity=0;
                editor.textInput.getElement().tabIndex=-1;
                editor.commands.commmandKeyBinding={};
            }
            else {
                editor.setOption("maxLines", maxLines);
            }

            editor.on("change", function(e){
                hiddenField.val(editor.getValue());
                if(e.action === 'remove' && typeof onFormChange === "function"){
                    onFormChange();
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
        const nestedList =jquery(this.target).find('.nested-sortable');
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

        jquery(this.target).find('.reorder-button').on('click', function(e){
            const form = jquery(this).closest('form');
            const hierarchy = form.find('.nested-sortable').nestedSortable('toHierarchy', {startDepthCount: 0});
            form.find('input.reorder-items').val(JSON.stringify(hierarchy)).trigger("change");
        });

        jquery(this.target).find('.mjs-nestedSortable .button-collapse').click(function (event) {
            event.preventDefault();
            const $isExpanded = ($(this).attr('aria-expanded') === 'true');
            $(this).parent().find('> button').attr('aria-expanded', !$isExpanded);
            let $panel = $(this).closest('li');
            $panel.find('ol').first().collapse('toggle');
        });

        jquery(this.target).find('.mjs-nestedSortable .button-collapse-all').click(function (event) {
            event.preventDefault();
            const $isExpanded = ($(this).attr('aria-expanded') === 'true');
            let $panel = $(this).closest('li');
            $panel.find('.button-collapse').attr('aria-expanded', !$isExpanded);
            $panel.find('.button-collapse-all').attr('aria-expanded', !$isExpanded);
            $panel.find('ol').collapse('toggle');
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


    addSelect2Listeners() {
        //Initialize Select2 Elements
        jquery(this.target).find(".select2").select2({
            escapeMarkup: function (markup) { return markup; }
        });
    }

    addRemoveButtonListeners() {
        jquery(this.target).find('.remove-item')
            .on('click', function(event) {
                event.preventDefault();
                $(this).closest('li').remove();
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