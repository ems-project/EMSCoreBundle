const $ = require('jquery');
const jQuery = require('jquery');
window.$ = $;
window.jQuery = $;

require('webpack-jquery-ui');
require('bootstrap');
require('bootstrap-fileinput');
require('select2');
require('jquery.dataTables');
// var dt      = require( 'datatables.net' )( window, $ );
// require('datatables');
// require('admin-lte');
// require('ckeditor');
// require('bootstrap-colorpicker');
require('moment');
require('daterangepicker');
require('bootstrap-datepicker');
require('bootstrap-select');
require('bootstrap-timepicker');

$(document).ready(function() {
    console.log('ems_core requirements have been defined');
});

jQuery(document).ready(function() {
    console.log('ems_core requirements have been defined 2');
});