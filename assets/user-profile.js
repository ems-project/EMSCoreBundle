'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);

$(window).ready(function() {

    const sampleConfig = {
        "uiColor": "#66AB16",
        "plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,flash,floatingspace,font,format,horizontalrule,htmlwriter,image2,iframe,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pagebreak,pastefromword,pastetext,preview,print,removeformat,resize,save,scayt,selectall,showblocks,showborders,smiley,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
        "pasteFromWordRemoveFontStyles": true,
        "pasteFromWordRemoveStyles": true,
        "language": "en",
        "toolbarGroups": [
            {
                "name": "document",
                "groups": [
                    "mode",
                    "document",
                    "doctools"
                ]
            },
            {
                "name": "clipboard",
                "groups": [
                    "clipboard",
                    "undo"
                ]
            },
            {
                "name": "editing",
                "groups": [
                    "find",
                    "spellchecker"
                ]
            },
            {
                "name": "forms"
            },
            "/",
            {
                "name": "basicstyles",
                "groups": [
                    "basicstyles",
                    "cleanup"
                ]
            },
            {
                "name": "paragraph",
                "groups": [
                    "list",
                    "indent",
                    "blocks",
                    "align",
                    "bidi"
                ]
            },
            {
                "name": "links"
            },
            {
                "name": "insert"
            },
            "/",
            {
                "name": "styles"
            },
            {
                "name": "colors"
            },
            {
                "name": "tools"
            },
            {
                "name": "others"
            },
            {
                "name": "about"
            }
        ]
    }

    const self = $('.wysiwyg-profile-picker');
    const editor = ace.edit($('.wysiwyg-profile-options').parent().find('pre').get(0));

    const onChange = function(){
        if(self.val()) {
            editor.setOption('readOnly', true);
            $('.wysiwyg-options-sample').addClass('disabled');
        }
        else {
            editor.setOption('readOnly', false);
            $('.wysiwyg-options-sample').removeClass('disabled');
        }
    };
    self.on('change', onChange);
    onChange();

    $('.wysiwyg-options-sample').click(function(e){
        if(! self.val()) {
            const options = JSON.stringify(sampleConfig, undefined, 4);
            editor.setValue(options);
        }
    });
});