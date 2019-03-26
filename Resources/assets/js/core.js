import FileUploader from "../FileUploader/FileUploader";

const $ = require('jquery');


// const jQuery = require('jquery');
window.CryptoJS = require("crypto-js");
window.FileUploader = FileUploader;

window.$ = $;
window.jQuery = $;

require('bootstrap');

require('bootstrap-colorpicker');
// require('bootstrap-daterangepicker');
require('bootstrap-slider');
require('bootstrap-select');
require('bootstrap-timepicker');
require('chart.js');
// require('ckeditor');
require('datatables.net');
require('datatables.net-bs');
require('fastclick');
require('flot');
// require('font-awesome'); //pure css
require('fullcalendar');
require('inputmask');
require('ion-rangeslider');
// require('ionicons'); //pure css
require('jquery');
require('jquery-knob');
require('jquery-sparkline');
require('jquery-ui');
require("jquery-lazyload");
require('jvectormap');
require('moment');
// require('morris.js'); //??
require('pace');
require('raphael');
require('select2/dist/js/select2.full');
require('slimscroll');
require('bootstrap-datepicker');
require('admin-lte');
require('bootstrap-fileinput');
require('webpack-jquery-ui/sortable');
require('daterangepicker');

$(document).ready(function() {
    console.log('AdminLTE\'s and elasticms\'s scripts loaded');
});
