
$(document).ready(function() {
	var updateStatusFct = function(){
		$.getJSON( "/elasticsearch/status.json" )
		  .done(function( json ) {
			  $('#status-overview').html(json.body);
		  })
		  .fail(function( jqxhr, textStatus, error ) {
			var err = textStatus + ", " + error;
			$('#status-overview').html('<i class="fa fa-circle text-red"></i> ' + err);
		  });
	};	

	updateStatusFct();
	window.setInterval(function(){
		updateStatusFct();
	}, 5000);
	

});

/**
 * Update the cluster status every 5 seconds
 */



