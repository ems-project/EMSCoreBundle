'use strict';

/*
 * This function initialized the elasticms admin interface
 *
 */
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
        $('#modal-notification-close-button').click(function(){
            $('#modal-notifications .modal-body').empty();
            $('#modal-notifications').modal('hide');
        });
    }

    function requestJob() {
        $("a.request_job").click(function(e){
            e.preventDefault();
            ajaxRequest.post($(e.target).data('url'));
        });
    }

    function queryString () {
        // This function is anonymous, is executed immediately and
        // the return value is assigned to QueryString!
        let query_string = {};
        const query = window.location.search.substring(1);
        const vars = query.split("&");
        for (var i=0;i<vars.length;i++) {
            var pair = vars[i].split("=");
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
        $.getJSON( elasticsearch_status_url )
            .done(function( json ) {
                $('#status-overview').html(json.body);
            })
            .fail(function( jqxhr, textStatus, error ) {
                const err = textStatus + ", " + error;
                $('#status-overview').html('<i class="fa fa-circle text-red"></i> ' + err);
            });
    }

    function toggleMenu() {
        $('.toggle-button').click(function(){
            const toggleTex = $(this).data('toggle-contain');
            const text=$(this).html();
            $(this).html(toggleTex);
            $(this).data('toggle-contain', text);
        });
    }

    $(document).ready(function() {
        activeMenu();
        loadLazyImages();
        matchHeight();
        closeModalNotification();
        requestJob();
        toggleMenu();
        window.QueryString = queryString();


        //cron to update the cluster status
        window.setInterval(function(){
            updateStatusFct();
        }, 10000);
        //60000 every minute
    });


}));