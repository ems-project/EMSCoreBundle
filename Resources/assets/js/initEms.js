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

    $(document).ready(function() {
        activeMenu();
        loadLazyImages();
        matchHeight();
    });


}));