import $ from 'jquery';

export default class AjaxRequest {

    constructor() {
        this.counter = 0;
    }

    initRequest() {
        if(++this.counter > 0){
        	$('#ajax-activity').addClass('fa-spin');
        }
    }

    private_begin_response() {
    	if(--this.counter === 0){
        	$('#ajax-activity').removeClass('fa-spin');
    	}
    }

    static private_add_messages(messages, color){
        if(messages && messages.length > 0) {
        	for (let index = 0; index < messages.length; ++index) {
        		const message = $($.parseHTML(messages[index]));
        		$('ul#activity-log').append('<li title="'+message.text()+'">'
                        +'<a href="#" onclick="$(this).parent().remove(); ajaxRequest.updateCounter(); return false;" class="'+color+'">'
                        +messages[index]
                        +'</a>'
                        +'</li>');
        	}
        }
    }

    post(url, data, modal){
    	this.initRequest();
    	const self = this;

        const out = new function() {

    		this.success = function(callback){
    			this.successFct = callback;
    			return this;
    		};

    		this.fail = function(callback){
    			this.failFct = callback;
    			return this;
    		};

    		this.always = function(callback){
    			this.alwaysFct = callback;
    			return this;
    		};

    		const xhr = $.post( url, data )
    		.done(function(data) {
                const response = self.treatResponse(data, modal);
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

    get(url, data, modal){
    	this.initRequest();
    	const self = this;

        const out = new function() {

    		this.success = function(callback){
    			this.successFct = callback;
    			return this;
    		};

    		this.fail = function(callback){
    			this.failFct = callback;
    			return this;
    		};

    		this.always = function(callback){
    			this.alwaysFct = callback;
    			return this;
    		};

            const xhr = $.get( url, data )
    		.done(function(data) {
                const response = self.treatResponse(data, modal);
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

    treatResponse(data, modal) {

    	this.private_begin_response();
    	try {
            let response = data;
    		if(typeof data === 'string'){
    			response = JSON.parse(data);
    			console.log('An AJAX call did not returned a JSON');
    		}

    		if(modal){
    			$('#'+modal).modal('show') ;
                AjaxRequest.private_add_modal(modal, response.notice, 'info', 'info', 'Info!');
                AjaxRequest.private_add_modal(modal, response.warning, 'warning', 'warning', 'Warning!');
                AjaxRequest.private_add_modal(modal, response.error, 'danger', 'ban', 'Error!');
    		}

            if(response.success){
            	AjaxRequest.private_add_messages(response.notice, 'text-aqua');
                AjaxRequest.private_add_alerts(response.warning, 'warning', 'warning', 'Warning!');
                AjaxRequest.private_add_alerts(response.error, 'danger', 'ban', 'Error!');
            	AjaxRequest.updateCounter();
            }
            else{
                AjaxRequest.private_add_alerts(response.notice, 'info', 'info', 'Info!');
                AjaxRequest.private_add_alerts(response.warning, 'warning', 'warning', 'Warning!');
                AjaxRequest.private_add_alerts(response.error, 'danger', 'ban', 'Error!');
            }
            return response;

        } catch (e) {
        	console.log(e);
        }
        $('#data-out-of-sync').modal('show') ;
    	return null;
    }

    static private_add_alerts(alerts, cls, icon, title){
        if(alerts && alerts.length > 0) {
        	let output = '<div class="alert alert-'+cls+' alert-dismissible">'
	                +'<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>'
	                +' <h4><i class="icon fa fa-'+icon+'"></i> '
	                + title
	                +' </h4>';

        	for (let index = 0; index < alerts.length; ++index) {
            	output +=  ' <div class="flash-notice">'+alerts[index]+'</div>';
        	}
        	output +=  '</div>';
        	$('#flashbags').append(output);
        }
    }

    static private_add_modal(modal, alerts, cls, icon, title){
        if(alerts && alerts.length > 0) {
        	let output = '<div class="alert alert-'+cls+' alert-dismissible">'
	                +'<button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>'
	                +' <h4><i class="icon fa fa-'+icon+'"></i> '
	                + title
	                +' </h4>';

        	for (let index = 0; index < alerts.length; ++index) {
            	output +=  ' <div class="flash-notice">'+alerts[index]+'</div>';
        	}
        	output +=  '</div>';
        	$('#'+modal+' .modal-body').append(output);
        }
    }

    static updateCounter(){
    	const numberOfElem = $('ul#activity-log >li').length;
    	if(numberOfElem) {
    		$('#system-messages >a >span').text(numberOfElem);
    	}
    	else {
    		$('#system-messages >a >span').empty();
    	}
    }


    requestFailed(e) {
    	console.log(e);
    	this.private_begin_response();
    	$('#data-out-of-sync').modal('show') ;
    }

}