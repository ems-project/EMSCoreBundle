


function FileUploader(params) {

	/**
	 * Define all file status values and the size of slice to compute the sha1 and the size of the uploaded chunk
	 */
	this.statics = {
		SHA1SLICESIZE: 2<<17,
		CHUNKSIZE: 2<<17,
		UPLOADED: 1,
		ERROR: 2,
		UPLOADING: 3,
		PAUSE: 4,
		LOADING: 5,
		UPLOADERROR: 6
	};
	
	/**
	 * handler to the file
	 */
	this.file = null,

	/**
	 * Sha1 value of the file
	 */
	this.sha1 = null;
	
	/**
	 * Sha1 value of the file
	 */
	this.size = 0;
	
	/**
	 * current status of the object (LOADING by default)
	 */
	this.status = 5;
	
	//define the right slice function depending the current browser
	//if there is an handler to the file
	if(params.file){
		this.file = params.file;
		this.size = params.file.size;
		this.type = params.file.type;
		this.name = params.file.name;
		this.lastModified = params.file.lastModified;
		if(!this.type || this.type === ''){
			this.type = 'application/octet-stream';
		}
		
		this.uploaded = 0;
		if(typeof this.file.slice === 'function') {
			this.slice = function (start, length) {
				return this.file.slice(start, start+length);
			};
		}
		else if (typeof this.file.mozSlice === 'function') {
			this.slice = function (start, length) {
				return this.file.mozSlice(start, start+length);
			};				
		}
		else if (typeof this.file.webkitSlice === 'function') {
			this.slice = function (start, length) {
				return this.file.webkitSlice(start, start+length);
			};				
		}
	}
		
	
	/**
	 * initiate the sha1 computation
	 */
	this.computeGitSha1 = function(){	
		this.gitPrefix = ""; //"blob " + this.size + "\0";
		this.sliceToLoad = Math.ceil( (this.size+this.gitPrefix.length) / this.statics.SHA1SLICESIZE);
		this.sliceLoaded = 0;
		this.sha1Render = new Sha1();
		this.treatNextSliceGitSha1();
	}
	
	
	/**
	 * regarding the file status extract the right slice and read it
	 * a callback function will continue the sha1 computation later
	 */
	this.treatNextSliceGitSha1 = function(){
		var reader = new FileReader();
		var idx = this.sliceLoaded;
		this.timeStamp = (new Date()).getTime();

		self = this;
		reader.onloadend = function(evt){ self.nakedSha1(evt, self); };

		var start;
		var length;
		if(idx == 0){
			start = 0;
			length = Math.min(this.statics.SHA1SLICESIZE, this.size + this.gitPrefix.length) - this.gitPrefix.length;			
		}
		else{
			start = (idx * this.statics.SHA1SLICESIZE) - this.gitPrefix.length;
			length = Math.min(this.statics.SHA1SLICESIZE, this.size - start);			
		}

		var blob = this.slice(start, length);
		reader.readAsBinaryString(blob);
	};

	/**
	 * Callback function continuing the sha1 computation with the current file slice
	 */
	this.nakedSha1 = function(evt, self){
		// If we use onloadend, we need to check the readyState.
		if (evt.target.readyState == FileReader.DONE && self.status != self.statics.ERROR) { // DONE == 2
			var block;
			var slice = evt.target.result;

			if(self.sliceLoaded == 0){
				block = self.gitPrefix + slice;
			}
			else{
				block = slice;
			}

			var len = block.length;
			self.sha1Render.hash(block);
			++self.sliceLoaded;


			if(self.sliceLoaded == self.sliceToLoad){
				var hex = self.sha1Render.result();
				self.status = self.statics.UPLOADING;
				self.sha1 = hex;
				if(params.onSha1Available){
					params.onSha1Available(self.sha1, self.type, self.name);
				} 
				self.onProgress('Init upload', 0, 'estimating');
				self.initUpload(self);
			}
			else{
				self.onProgress('Computing sha1', 0, Math.round(self.sliceLoaded/self.sliceToLoad)+'%');
				self.treatNextSliceGitSha1();
			}
		}
		else {
			self.setError('An error occured during file preparing');
			self.threadReleased(self);
		}

	};
	
	
	/**
	 * Sha1 setter function
	 * 
	 * @param hex: the sha1
	 */
	this.setSha1 = function(hex){ 
		this.sha1 = hex;
		this.fireEvent('sha1Available', this, hex);
	};

	/**
	 * An error has occurred to the file. The file status is set to ERROR
	 * 
	 * @param description: error message description
	 */
	this.setError = function(description){
		this.status = DFICS.model.FileEx.ERROR;		
		this.errorDescription = description;
		this.fireEvent('fileError', this, description);
	};

	/**
	 * An error has occurred during the upload. the user will be able to retry later.
	 * 
	 * @param description, description of the error
	 * @param errorCode; http status code returned
	 */
	this.setUploadError = function(description, errorCode){
		console.log(description+'/'+errorCode);		
		this.status = this.statics.UPLOADERROR;
		this.errorDescription = description;
		if(params.onError) {
			this.onFileUploadError(description, errorCode);
		}
	};

	/**
	 * callback function, called when the chunk upload has failed (no answer)
	 * 
	 * @param event
	 */
	this.onUploadError = function(event){
		this.setUploadError('The server didn\'t responded as expected', 503);
	};

	
	/**
	 * Convert ms to human readable format
	 *
	 * @param integer time in ms to convert
	 * @param integer precision (number of decimals)
	 * @return string
	 */
	this.msToTime = function(milisec, precision){  
	  var seconds = 1000;
	  var minutes = seconds * 60;
	  var hours = minutes * 60;
	  var days = hours * 24;
	  if(!precision)precision = 0;

	  if ((milisec >= 0) && (milisec < seconds)) {
	    return '> 1 s';

	  } else if ((milisec >= seconds) && (milisec < minutes)) {
	    return (milisec / seconds).toFixed(precision) + ' s';

	  } else if ((milisec >= minutes) && (milisec < hours)) {
	    return (milisec / minutes).toFixed(precision) + ' min';

	  } else if ((milisec >= hours) && (milisec < days)) {
	    return (milisec / hours).toFixed(precision) + ' h';

	  } else if (milisec >= days) {
	    return (milisec / days).toFixed(precision) + ' d';

	  } else {
	    return milisec + ' ms';
	  }
	};
	
	
	/**
	 * callback function, called when the chunk upload has responded
	 * 
	 * @param event
	 */
	this.onChunkUploadSuccess = function(self, event){
		
		//success
		if(event.target.status == '200'){
			self.uploaded += Math.min(self.statics.CHUNKSIZE, self.size-self.uploaded);
			if(self.uploaded == self.size){
				self.status = self.statics.UPLOADED;
				self.onProgress('Uploaded', 1, 'Done');	
			}
			else{
				self.onProgress('Uploading', (self.uploaded / self.size), self.msToTime(((self.size - self.uploaded)/self.statics.CHUNKSIZE)*((new Date()).getTime()-self.timeStamp)));
				self.uploadNextChunk(self);
			}
		}
		//fail
		else{
			self.setUploadError(event.target.responseText, event.target.status);
		}
	};

	this.onProgress = function(statut, progress, estimation){
		if(params.onProgress){
			
			params.onProgress(statut, progress, estimation);
		}
		else {
			console.log('chunk '+self.uploaded+'/'+self.size);
		}		
	}
	
	
	/**
	 * Pause the file upload
	 */
	this.pauseUpload = function(){
		if(this.status == DFICS.model.FileEx.UPLOADING){
			this.fireEvent('fileUploadPause', this, (this.uploaded / this.size));
			this.status = DFICS.model.FileEx.PAUSE;		
		}
	};

	/**
	 * Resume the file upload
	 */
	this.resumeUpload = function(){
		if(this.status == DFICS.model.FileEx.PAUSE){
			this.fireEvent('fileUploadResume', this, (this.uploaded / this.size));
			this.status = DFICS.model.FileEx.UPLOADING;		
		}
	};

	/**
	 * Init the upload. The file description are send to the server. If the file is already know by the server 
	 * the object (this) is updated (bytes already upload, file ready, ...)
	 * @param context
	 */
	this.initUpload = function(self){
		if(this.status == self.statics.UPLOADING 
				|| this.status == self.statics.PAUSE){

			xhttp = new XMLHttpRequest();
			xhttp.onload = function () {
				if(this.status == 200){
					var fileInfo = JSON.parse(this.responseText);

					if(fileInfo && fileInfo.uploaded !== undefined){
						self.uploaded = fileInfo.uploaded;
						if(self.uploaded < self.size){
							self.startUpload(self);
						}
						else if(self.uploaded == self.size) {
							self.status = self.statics.UPLOADED;
							self.onProgress('Uploaded', 1, 'Done');	
						}
						else{
							self.setError('Number bytes of already uploaded is abnormal '+bytesToSize(self.uploaded)+'/'+bytesToSize(self.size));
						}		
					}
					else{
						self.setUploadError('Upload init has failed', 200);
					}
				}
				else {
					self.setUploadError(this.statusText, this.status);
				}
			};
			xhttp.onerror = function (evt) {
				self.setUploadError(this.statusText, this.status);
			}
			
			var url = file_init_upload_url
				.replace('__sha1__', encodeURIComponent(this.sha1))
				.replace('__size__', encodeURIComponent(this.size));
			
			
			xhttp.open("POST", url, true);
			var params = JSON.stringify({ name: this.name, type: this.type });			
			xhttp.setRequestHeader("Content-type", "application/json; charset=utf-8");
			
			xhttp.send(params);
			
//			Ext.Ajax.request({
//				url: 'UploadInit',
//				method: 'GET',
//				scope: this,
//				params: {
//					'context': context,
//					'fileSize': this.size,
//					'contentType': this.type,
//					'fileName': this.name,
//					'fileId':  this.sha1
//				},
//				success: function(response){
//					var fileInfo = Ext.JSON.decode(response.responseText);
//					if(fileInfo && fileInfo.fileSize !== undefined){
//						this.uploaded = fileInfo.fileSize;
//						if(this.uploaded < this.size){
//							this.fireEvent('fileUploadInit', this, (this.uploaded / this.size));
//							this.fireEvent('threadReleased', this);
//						}
//						else if(this.uploaded == this.size) {
//							this.status = DFICS.model.FileEx.UPLOADED;
//							this.fireEvent('fileUploaded', this);		
//							this.fireEvent('threadReleased', this);
//						}
//						else{
//							this.setError('Number bytes of already uploaded is abnormal '+bytesToSize(this.uploaded)+'/'+bytesToSize(this.size));
//							this.fireEvent('threadReleased', this);
//						}		
//					}
//					else{
//						this.setUploadError('Upload init has failed', 200);
//					}
//				},
//				failure: function(response, opts) {
//					this.setUploadError(response.statusText, response.status);
//				}
//			});
		}		
		else{
			this.fireEvent('threadReleased', this);			
		}
	};

	/**
	 * Start or resume the upload
	 */
	this.startUpload = function(self){
		self.status = self.statics.UPLOADING;
		//this.fireEvent('fileUploadStart', this, (this.uploaded / this.size));
		self.uploadNextChunk(self);
	};

	/**
	 * Upload the next chunk, only if the file status is UPLOADING
	 */
	this.uploadNextChunk = function(self){
		if(self.status == self.statics.UPLOADING){
			self.timeStamp = (new Date()).getTime();
			//get the blob corresponding to the current chunk
			//console.log('load from '+self.uploaded+' to '+Math.min(self.statics.CHUNKSIZE, self.size-self.uploaded));
			var blob = self.slice(self.uploaded, Math.min(self.statics.CHUNKSIZE, self.size-self.uploaded));

			
			var xhr = new XMLHttpRequest();

			//add listener to the XHR object in case of success or fail
			//xhr.addEventListener('error', Ext.Function.bind(this.onUploadError, this));
			xhr.addEventListener('load', function(evt){ self.onChunkUploadSuccess(self, evt)});

			

			var url = file_chunk_upload_url
				.replace('__sha1__', encodeURIComponent(self.sha1));
			
			
			//init the XHR request
			xhr.open("POST", url, true);
//			xhr.open('POST', '/data/file/upload-chunk/'+self.sha1, true);

			//send form with the XHR
			xhr.send(blob);
		}
//		else if(this.status == DFICS.model.FileEx.PAUSE){
//			this.fireEvent('threadReleased', this);
//		}
		else{
			self.setUploadError('inconsistent status', 400);			
		}
	};	
	
	this.computeGitSha1();
}