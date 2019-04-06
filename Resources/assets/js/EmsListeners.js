
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
        this.addCodeEditorListeners();
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

            const pre = codeDiv.find('pre').get(0);
            const hiddenField = codeDiv.find('input');

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

            if(hiddenField.data('disabled')){
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