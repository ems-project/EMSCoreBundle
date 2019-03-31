
const $ = require('jquery');
window.$ = $;
window.jQuery = $;

const assetPath = document.querySelector("BODY").getAttribute('data-asset-path') ;
window.CryptoJS = require("crypto-js");


import FileUploader from "./FileUploader";
window.FileUploader = FileUploader;
import AjaxRequest from "./AjaxRequest";
window.AjaxRequest = AjaxRequest;
window.ajaxRequest = new AjaxRequest();

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
require('bootstrap-timepicker');
require('chart.js');
require('ckeditor');
require('datatables.net');
require('datatables.net-bs');
require('fastclick');
require('flot');
require('fullcalendar');
require('inputmask');
require('ion-rangeslider');
require('jquery');
require('jquery-knob');
require('jquery-sparkline');
require('jquery-ui');
require('jquery-ui/ui/widgets/sortable');
require("jquery-lazyload");
require('jvectormap');
require('moment');
require('pace');
require('raphael');
require('select2/dist/js/select2.full');
require('slimscroll');
require('bootstrap-datepicker');
require('admin-lte');
require('bootstrap-fileinput');
require('daterangepicker');
require('./nestedSortable');
window.moment = require('moment');
require('fullcalendar');
require('icheck');
require('jquery-match-height');

require('./initEms');

