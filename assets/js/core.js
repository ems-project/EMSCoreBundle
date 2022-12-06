
const $ = require('jquery');
window.$ = $;
window.jQuery = $;

const assetPath = document.querySelector("BODY").getAttribute('data-asset-path') ;
window.CryptoJS = require("crypto-js");


import FileUploader from "@elasticms/file-uploader";
window.FileUploader = FileUploader;
import AjaxRequest from "./AjaxRequest";
window.AjaxRequest = AjaxRequest;
window.ajaxRequest = new AjaxRequest();
import sfCollection from '@a2lix/symfony-collection/src/a2lix_sf_collection';
window.a2lix_lib = sfCollection;

window.object_search_url = document.querySelector("BODY").getAttribute('data-search-api');
window.ems_wysiwyg_type_filters = JSON.parse(document.querySelector("BODY").getAttribute('data-wysiwyg-type-filters'));

window.CKEDITOR_BASEPATH = assetPath + 'bundles/emscore/js/ckeditor/';

const ace = require('ace-builds/src-noconflict/ace');
require('ace-builds/src-noconflict/ext-modelist');
require('ace-builds/src-noconflict/ext-themelist');
window.ace = ace;
ace.config.set('basePath', assetPath + 'bundles/emscore/js/ace' );

require('bootstrap');

require('bootstrap-colorpicker');
// require('bootstrap-daterangepicker');
require('bootstrap-slider');
require('bootstrap-select');
require('admin-lte/plugins/timepicker/bootstrap-timepicker');
require('chart.js');
require('ckeditor4');
require('fastclick');
require('flot');
require('fullcalendar');
require('inputmask');
require('ion-rangeslider');
require('jquery-knob');
require('jquery-sparkline');
require('jquery-ui');
require('./sortable'); //forked jquery-ui/ui/widgets/sortable
require('jquery-ui/ui/widgets/draggable');
require("jquery-lazyload");
require('jvectormap');
require('moment');
require('pace');
require('raphael');
require('select2/dist/js/select2.full');
require('slimscroll');
require('bootstrap-datepicker');
require('eonasdan-bootstrap-datetimepicker/src/js/bootstrap-datetimepicker');
require('admin-lte');
require('daterangepicker');
require('./nestedSortable');
window.moment = require('moment');
require('fullcalendar');
require('icheck');
require('jquery-match-height');

//Fix issue CK editor in bootstrap model
//https://ckeditor.com/old/forums/Support/Issue-with-Twitter-Bootstrap#comment-127719
$.fn.modal.Constructor.prototype.enforceFocus = function() {
    const modal_this = this
    $(document).on('focusin.modal', function (e) {
        if (modal_this.$element[0] !== e.target && !modal_this.$element.has(e.target).length
            && !$(e.target.parentNode).hasClass('cke_dialog_ui_input_select')
            && !$(e.target.parentNode).hasClass('cke_dialog_ui_input_text')) {
            modal_this.$element.focus()
        }
    })
};

require('./helpers');
require('./initEms');

