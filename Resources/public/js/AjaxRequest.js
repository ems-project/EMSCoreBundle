var ajaxRequest = new function() {
    this.counter = 0;

    this.initRequest = function () {
    	
        if(++this.counter == 1){
        	$('#ajax-activity').addClass('fa-spin');
        }
    };
    
    this.private_begin_response = function () {
    	if(--this.counter == 0){
        	$('#ajax-activity').removeClass('fa-spin');
    	}
    };
    
    this.private_add_messages = function(messages, color){
        if(messages) {
        	for (index = 0; index < messages.length; ++index) {
        		var message = $($.parseHTML(messages[index]));
        		$('#system-messages ul.menu').append('<li title="'+message.text()+'">'
                        +'<a href="#" onclick="$(this).parent().remove(); ajaxRequest.updateCounter(); return false;" class="'+color+'">'
                        +messages[index]
                        +'</a>'
                        +'</li>');
        	}
        }    	
    };
    
    this.post = function(url, data, modal){
    	this.initRequest();
    	var self = this;
    	
    	var out = new function() {
    		var successFct;
    		var failFct;	
    		var alwaysFct;
    		
    		this.success = function(callback){
    			this.successFct = callback;
    			return this;
    		}

    		this.fail = function(callback){
    			this.failFct = callback;
    			return this;
    		}
    		
    		this.always = function(callback){
    			this.alwaysFct = callback;
    			return this;
    		}
    		
    		var xhr = $.post( url, data )
    		.done(function(data) {
    			var response = self.treatResponse(data, modal);
    			if(response.success) {
    				if(out.successFct) {
    					out.successFct(response);
    				}
    			}
    			else{
    				if(out.failFct) {
    					out.failFct(response);
    				}
    			}
    			if(out.alwaysFct) {
    				out.alwaysFct(response);
    			}
    		})
    		.fail(function( event, data ) {
    			if(data && data.aborted){
//    				console.log('post aborted');
    			}
    			else{
    				self.requestFailed();    				
    			}
    		});
    		
    		this.abortFct = xhr.abort;
    		
    		this.abort = function(){
    			self.private_begin_response();
    			out.abortFct({aborted:true});
    		}
    		
    	};
    	
    	return out;
    	
    }
    
    this.get = function(url, data){
    	this.initRequest();
    	var self = this;
    	
    	var out = new function() {
    		var successFct;
    		var failFct;	
    		var alwaysFct;
    		
    		this.success = function(callback){
    			this.successFct = callback;
    			return this;
    		}

    		this.fail = function(callback){
    			this.failFct = callback;
    			return this;
    		}
    		
    		this.always = function(callback){
    			this.alwaysFct = callback;
    			return this;
    		}
    		
    		var xhr = $.get( url, data )
    		.done(function(data) {
    			var response = self.treatResponse(data, modal);
    			if(response.success) {
    				if(out.successFct) {
    					out.successFct(response);
    				}
    			}
    			else{
    				if(out.failFct) {
    					out.failFct(response);
    				}
    			}
    			if(out.alwaysFct) {
    				out.alwaysFct(response);
    			}
    		})
    		.fail(function( event, data ) {
    			if(data && data.aborted){
//    				console.log('post aborted');
    			}
    			else{
    				self.requestFailed();    				
    			}
    		});
    		
    		this.abortFct = xhr.abort;
    		
    		this.abort = function(){
    			self.private_begin_response();
    			out.abortFct({aborted:true});
    		}
    		
    	};
    	
    	return out;
    	
    }
    
    this.treatResponse = function (data, modal) {
    	
    	var response = false;
    	this.private_begin_response();
    	try {
    		if(typeof data === 'string'){
    			response = JSON.parse(data);
    			console.log('An AJAX call did not returned a JSON');
    		}
    		else{
    			response = data;
    		}
    		if(modal){
    			$('#'+modal).modal('show') ;
            	this.private_add_modal(modal, response.notice, 'info', 'info', 'Info!');
            	this.private_add_modal(modal, response.warning, 'warning', 'warning', 'Warning!');
            	this.private_add_modal(modal, response.error, 'danger', 'ban', 'Error!');
    		}
    		
            if(response.success){
            	this.private_add_messages(response.notice, 'text-aqua');
            	this.private_add_alerts(response.warning, 'warning', 'warning', 'Warning!');
            	this.private_add_alerts(response.error, 'danger', 'ban', 'Error!');
            	this.updateCounter();            	
            }
            else{
            	this.private_add_alerts(response.notice, 'info', 'info', 'Info!');
            	this.private_add_alerts(response.warning, 'warning', 'warning', 'Warning!');
            	this.private_add_alerts(response.error, 'danger', 'ban', 'Error!');
            }

        } catch (e) {
        	console.log(e);
        	$('#data-out-of-sync').modal('show') ;
        }
    	return response;
    };
    
    this.private_add_alerts = function (alerts, cls, icon, title){
        if(alerts) {
        	var output = '<div class="alert alert-'+cls+' alert-dismissible">'
	                +'<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>'
	                +' <h4><i class="icon fa fa-'+icon+'"></i> '
	                + title
	                +' </h4>';

        	for (index = 0; index < alerts.length; ++index) {
            	output +=  ' <div class="flash-notice">'+alerts[index]+'</div>';
        	}
        	output +=  '</div>';
        	$('#flashbags').append(output);
        }
    }
    
    this.private_add_modal = function (modal, alerts, cls, icon, title){
        if(alerts) {
        	var output = '<div class="alert alert-'+cls+' alert-dismissible">'
	                +'<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>'
	                +' <h4><i class="icon fa fa-'+icon+'"></i> '
	                + title
	                +' </h4>';

        	for (index = 0; index < alerts.length; ++index) {
            	output +=  ' <div class="flash-notice">'+alerts[index]+'</div>';
        	}
        	output +=  '</div>';
        	$('#'+modal+' .modal-body').append(output);
        }
    }
    
    this.updateCounter = function(){
    	var numberOfElem = $('#system-messages ul.menu >li').length;
    	if(numberOfElem) {
    		$('#system-messages >a >span').text(numberOfElem);    		
    	}
    	else {
    		$('#system-messages >a >span').empty();
    	}
    }
    
    
    this.requestFailed = function (e) {
    	console.log(e);
    	this.private_begin_response();
    	$('#data-out-of-sync').modal('show') ;
    };
    
}