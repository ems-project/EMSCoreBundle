'use strict';

/*
 * This function initialized the elasticms admin interface
 *
 */
import EmsListeners from "./EmsListeners";
import MediaLibrary from "./component/mediaLibrary";
import JsonMenu from "./module/jsonMenu";
import JsonMenuNested from "./module/jsonMenuNested";
import ajaxModal from "./helper/ajaxModal";
import JsonMenuNestedComponent from "./component/jsonMenuNestedComponent";

(function(factory) {
    "use strict";

    if ( typeof define === "function" && define.amd ) {
        // AMD. Register as an anonymous module.
        define([
            "jquery",
        ], factory );
    } else {
        // Browser globals
        factory( window.jQuery );
    }

}(function($) {

    function activeMenu() {
        //try to find which side menu elements to activate
        const currentMenuLink = $('section.sidebar ul.sidebar-menu a[href="' + window.location.pathname + window.location.search + '"]');

        if ( currentMenuLink.length > 0 ) {
            currentMenuLink.last().parents('li').addClass('active');
        }
        else {
            $('#side-menu-id').each(function(){
                $('#'+$(this).data('target')).parents('li').addClass('active');
            });
        }
    }

    function loadLazyImages() {
        $("img.lazy").show().lazyload({
            effect : "fadeIn",
            threshold : 200
        });
    }

    function matchHeight() {
        $('.match-height').matchHeight();
    }


    function closeModalNotification() {
        $('#modal-notification-close-button').on('click', function(){
            $('#modal-notifications .modal-body').empty();
            $('#modal-notifications').modal('hide');
        });
    }

    function requestJob() {
        $("a.request_job").on('click', function(e){
            e.preventDefault();
            window.ajaxRequest.post($(e.target).data('url'))
                .success(function(message) {
                    window.ajaxRequest.post(message.jobUrl);
                    $('ul#commands-log').prepend('<li title="Job '+message.jobId+'">'
                        +'<a href="'+message.url+'" >'
                        +'Job #'+message.jobId
                        +'</a>'
                        +'</li>');
                });
        });
    }

    function queryString () {
        // This function is anonymous, is executed immediately and
        // the return value is assigned to QueryString!
        let query_string = {};
        const query = window.location.search.substring(1);
        const vars = query.split("&");
        for (let i=0;i<vars.length;i++) {
            const pair = vars[i].split("=");
            // If first entry with this name
            if (typeof query_string[pair[0]] === "undefined") {
                query_string[pair[0]] = decodeURIComponent(pair[1]);
                // If second entry with this name
            } else if (typeof query_string[pair[0]] === "string") {
                query_string[pair[0]] = [ query_string[pair[0]],decodeURIComponent(pair[1]) ];
                // If third or later entry with this name
            } else {
                query_string[pair[0]].push(decodeURIComponent(pair[1]));
            }
        }
        return query_string;
    }

    //Function to update the cluster status
    function updateStatusFct(){
        $.getJSON( $('body').attr('data-status-url') )
            .done(function( json ) {
                $('#status-overview').html(json.body);
            })
            .fail(function( jqxhr, textStatus, error ) {
                const err = textStatus + ", " + error;
                $('#status-overview').html('<i class="fa fa-circle text-red"></i> ' + err);
            });
    }


    function initCodeEditorThemeAngLanguage(){

        const codeEditorModeField = $('.code_editor_mode_ems');
        if( codeEditorModeField ){
            const modeList = ace.require("ace/ext/modelist");
            let modeListVar = [];
            for (let index = 0; index < modeList.modes.length; ++index) {
                modeListVar.push({
                    id: modeList.modes[index].mode,
                    text: modeList.modes[index].caption,
                });
            }
            codeEditorModeField.select2({
                data: modeListVar,
                placeholder: 'Select a language'
            });

            const themeList = ace.require("ace/ext/themelist");
            let themeList_var = [];
            for (let index = 0; index < themeList.themes.length; ++index) {
                themeList_var.push({
                    id: themeList.themes[index].theme,
                    text: themeList.themes[index].caption+' ('+(themeList.themes[index].isDark?'Dark':'Bright')+')',
                });
            }
            $('.code_editor_theme_ems').select2({
                data: themeList_var,
                placeholder: 'Select a theme'
            });
        }
    }

    function toggleMenu() {
        $('.toggle-button').on('click', function(){
            const toggleTex = $(this).data('toggle-contain');
            const text=$(this).html();
            $(this).html(toggleTex);
            $(this).data('toggle-contain', text);
        });
    }

    function autoOpenModal(queryString) {
        if(queryString.open) {
            $('#content_type_structure_fieldType'+queryString.open).modal('show');
        }
    }

    function initSearchForm() {

        $('#add-search-filter-button').on('click', function(e) {
            // prevent the link to scroll to the top ("#" anchor)
            e.preventDefault();

            const $listFilters = $('#list-of-search-filters');
            const prototype = $listFilters.data('prototype');
            const index = $listFilters.data('index');
            // Replace '__name__' in the prototype's HTML to
            // instead be a number based on how many items we have
            const newForm = $(prototype.replace(/__name__/g, index));

            // increase the index with one for the next item
            $listFilters.data('index', index + 1);

            //attach listeners to the new DOM element
            new EmsListeners(newForm.get(0));
            $listFilters.append(newForm);

        });
    }

    function startPendingJob() {
        $('[data-start-job-url]').each(function(){
            $.ajax({
                type: "POST",
                url: this.getAttribute('data-start-job-url')
            }).always(function() {
                location.reload();
            });
        });
    }

    function initJsonMenu() {
        $('.json_menu_editor_fieldtype').each(function(){ new JsonMenu(this); });

        let jsonMenuNestedList = [];
        $('.json-menu-nested').each(function () {
            let menu = new JsonMenuNested(this);
            jsonMenuNestedList[menu.getId()] = menu;
        });
        window.jsonMenuNested = jsonMenuNestedList;
    }

    function initAjaxFormSave() {
        $('button[data-ajax-save-url]').each(function(){
            const button = $(this);
            const form = button.closest('form');

            const ajaxSave = function(event){
                event.preventDefault();

                const formContent = form.serialize();
                window.ajaxRequest.post(button.data('ajax-save-url'), formContent)
                    .success(function(message) {
                        let response = message;
                        if ( ! response instanceof Object ) {
                            response = jQuery.parseJSON( message );
                        }

                        $('.has-error').removeClass('has-error');

                        $(response.errors).each(function(index, item){
                            $('#'+item.propertyPath).parent().addClass('has-error');
                        });
                    });
            };

            button.on('click', ajaxSave);

            $(document).keydown(function(e) {
                let key = undefined;
                const possible = [ e.key, e.keyIdentifier, e.keyCode, e.which ];

                while (key === undefined && possible.length > 0)
                {
                    key = possible.pop();
                }

                if (typeof key === "number" && ( 115 === key || 83 === key ) && (e.ctrlKey || e.metaKey) && !(e.altKey))
                {
                    ajaxSave(e);
                    return false;
                }
                return true;

            });

        });
    }

    function initJsonMenuNestedComponent() {
        const elements = document.getElementsByClassName('json-menu-nested-component');

        let jsonMenuNestedComponents = [];
        [].forEach.call(elements, function (element) {
            const component = new JsonMenuNestedComponent(element)
            if (component.id in jsonMenuNestedComponents) throw new Error(`duplicate id : ${component.id}`)
            jsonMenuNestedComponents[component.id] = component
        });

        document.addEventListener('jmn.copy', (e) => {
            Object.values(jsonMenuNestedComponents).forEach((component) => component.onCopy(e.detail))
        })

        window.jsonMenuNestedComponents = jsonMenuNestedComponents
    }

    function initMediaLibrary() {
        let elements = document.getElementsByClassName('media-lib');
        let bodyData = document.querySelector('body').dataset;

        [].forEach.call(elements, function (el) {
            new MediaLibrary(el, {
                urlMediaLib: '/component/media-lib',
                urlInitUpload: bodyData.initUpload,
                hashAlgo: bodyData.hashAlgo,
            });
        });
    }

    function intAjaxModalLinks() {
        let ajaxModalLinks = document.querySelectorAll('a[data-ajax-modal-url]');
        [].forEach.call(ajaxModalLinks, function (link) {
            link.onclick = (event) => {
                ajaxModal.load({
                    url: event.target.dataset.ajaxModalUrl,
                    size: event.target.dataset.ajaxModalSize
                }, (json) => {
                    if (json.hasOwnProperty('success') && json.success === true) {
                        location.reload();
                    }
                });
            }
        });
    }

    function initPostButtons() {
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('core-post-button')) {
                e.preventDefault();

                let button = e.target;
                let postSettings = JSON.parse(button.dataset.postSettings)
                let url = button.href;

                let f = postSettings.hasOwnProperty('form') ? document.getElementById(postSettings.form) :  document.createElement('form');

                if (postSettings.hasOwnProperty('form')) {
                    let my_tb=document.createElement('INPUT');
                    my_tb.style.display='none';
                    my_tb.type='TEXT';
                    my_tb.name='source_url';
                    my_tb.value= url;
                    f.appendChild(my_tb);

                    if (postSettings.action) {
                        f.action=JSON.parse(postSettings.action);
                    }
                } else {
                    f.style.display='none';
                    f.method='post';
                    f.action=url;
                    button.parentNode.appendChild(f);
                }

                if (postSettings.hasOwnProperty('value') && postSettings.hasOwnProperty('name')) {
                    let my_tb=document.createElement('INPUT');
                    my_tb.style.display='none';
                    my_tb.type='TEXT';
                    my_tb.name=JSON.parse(postSettings.name);
                    my_tb.value=JSON.parse(postSettings.value);
                    f.appendChild(my_tb);
                }

                f.submit();
            }
        });
    }


    $(document).ready(function() {
        activeMenu();
        loadLazyImages();
        matchHeight();
        closeModalNotification();
        requestJob();
        toggleMenu();
        initSearchForm();
        initCodeEditorThemeAngLanguage();
        autoOpenModal(queryString());
        startPendingJob();
        initAjaxFormSave();
        initJsonMenu();
        initMediaLibrary();
        initJsonMenuNestedComponent()
        intAjaxModalLinks();
        initPostButtons();

        //cron to update the cluster status
        window.setInterval(function(){
            updateStatusFct();
        }, 180000);

        window.dispatchEvent(new CustomEvent('emsReady'));
    });

}));