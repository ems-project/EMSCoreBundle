
function luma(color) // color can be a hx string or an array of RGB values 0-255
{
    var rgb = (typeof color === 'string') ? hexToRGBArray(color) : color;
    return (0.2126 * rgb[0]) + (0.7152 * rgb[1]) + (0.0722 * rgb[2]); // SMPTE C, Rec. 709 weightings
}

function hexToRGBArray(color)
{
    if (color.length === 3)
        color = color.charAt(0) + color.charAt(0) + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2);
    else if (color.length !== 6)
        throw('Invalid hex color: ' + color);
    var rgb = [];
    for (var i = 0; i <= 2; i++)
        rgb[i] = parseInt(color.substr(i * 2, 2), 16);
    return rgb;
}


function getAssocArray(input) {
	var output = [];
	for (var item in input){
// 		console.log(input[item]);
		output.push({
			ouuid: input[item].id,
			children: getAssocArray(input[item].children),
		});
	}
	return output;
}

$(document).ready(function() {
    //Initialize Select2 Elements
    $(".select2").select2({
    	escapeMarkup: function (markup) { return markup; }
    });
    
    //Function to update the cluster status
	var updateStatusFct = function(){
		$.getJSON( elasticsearch_status_url )
		  .done(function( json ) {
			  $('#status-overview').html(json.body);
		  })
		  .fail(function( jqxhr, textStatus, error ) {
			var err = textStatus + ", " + error;
			$('#status-overview').html('<i class="fa fa-circle text-red"></i> ' + err);
		  });
	};	

 	//cron to update the cluster status
	window.setInterval(function(){
		updateStatusFct();
	}, 180000);
	//60000 every minute
	
	
	//try to find wich sidemenu elements to activate
	var currentMenuLink = $('section.sidebar ul.sidebar-menu a[href="'+window.location.pathname + window.location.search+'"]');
	
	if(currentMenuLink.length == 1){
		currentMenuLink.parents('li').addClass('active');
	}
	else if (typeof side_menu_target !== 'undefined') { 
    		$('#'+side_menu_target).parents('li').addClass('active');
	} 
	

		
//	$("#sidebar-toggle-button").click(function(event){
//		var url = collapse_sidebar_url.replace("__collapsed__", $('body.sidebar-collapse').length == 0?1:0);
//		ajaxRequest.post( url );
//	});
	
	
	$('.toggle-button').click(function(){ 
	    var toggleTex = $(this).data('toggle-contain');
	    var text=$(this).html();
	    $(this).html(toggleTex);
		$(this).data('toggle-contain', text);
	});
	
});

$(window).load(function() {
	$("img.lazy").show().lazyload({
	    effect : "fadeIn",
	    threshold : 200
	});
	

});



