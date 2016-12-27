function formatRepo (repo) {
    if (repo.loading) return repo.text;

    var markup = "<div class='select2-result-repository clearfix'>" +
      repo.text + "</div>";
      
	return markup;
}

function formatRepoSelection (repo) {
    return repo.text;
}


var QueryString = function () {
	  // This function is anonymous, is executed immediately and 
	  // the return value is assigned to QueryString!
	  var query_string = {};
	  var query = window.location.search.substring(1);
	  var vars = query.split("&");
	  for (var i=0;i<vars.length;i++) {
	    var pair = vars[i].split("=");
	        // If first entry with this name
	    if (typeof query_string[pair[0]] === "undefined") {
	      query_string[pair[0]] = decodeURIComponent(pair[1]);
	        // If second entry with this name
	    } else if (typeof query_string[pair[0]] === "string") {
	      var arr = [ query_string[pair[0]],decodeURIComponent(pair[1]) ];
	      query_string[pair[0]] = arr;
	        // If third or later entry with this name
	    } else {
	      query_string[pair[0]].push(decodeURIComponent(pair[1]));
	    }
	  } 
	  return query_string;
	}();

function objectPickerListeners(objectPicker, maximumSelectionLength){
	var type = objectPicker.data('type'); 
	var dynamicLoading = objectPicker.data('dynamic-loading'); 

	
	var params = {
	  	escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
	  	templateResult: formatRepo, // omitted for brevity, see the source of this page
	  	templateSelection: formatRepoSelection // omitted for brevity, see the source of this page
	};

	if(maximumSelectionLength) {
		params.maximumSelectionLength = maximumSelectionLength;
	}
	else if(objectPicker.attr('multiple')) {
	  	params.allowClear = true;
	  	params.closeOnSelect = false;
	}
	


	if(dynamicLoading){
	  	//params.minimumInputLength = 1,
		params.ajax = {
			url: object_search_url,
	    	dataType: 'json',
	    	delay: 250,
	    	data: function (params) {
	      		return {
		        q: params.term, // search term
		        page: params.page,
		        type: type
		      };
		    },
			processResults: function (data, params) {
				// parse the results into the format expected by Select2
				// since we are using custom formatting functions we do not need to
				// alter the remote JSON data, except to indicate that infinite
				// scrolling can be used
				params.page = params.page || 1;
		
		      	return {
			        results: data.items,
			        pagination: {
			          more: (params.page * 30) < data.total_count
			        }
		      	};
	    	},
	    	cache: true
	  	};
	}
	
	objectPicker.select2(params);
}

function requestNotification (element, tId, envName, ctId, id){
	var data = { templateId : tId, environmentName : envName, contentTypeId : ctId, ouuid : id};
	ajaxRequest.post(element.getAttribute("data-url") , data, 'modal-notifications');
}

$(document).ready(function() {
	
	$('#modal-notification-close-button').click(function(){
		$('#modal-notifications .modal-body').empty();
		$('#modal-notifications').modal('hide');
	});
	

});
