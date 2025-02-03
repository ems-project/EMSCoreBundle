import './css/_ckeditor.scss';
const $ = require('jquery');
window.$ = $;
window.jQuery = $;
const assetPath = document.body.dataset.assetPath
window.CKEDITOR_BASEPATH = assetPath + 'bundles/emscore/js/ckeditor/'
window.ems_wysiwyg_type_filters = JSON.parse(document.body.dataset.wysiwygTypeFilters ?? '{}')
window.object_search_url = document.body.dataset.searchApi
require('ckeditor4')
require('./js/helpers')
require('select2/dist/js/select2.full')