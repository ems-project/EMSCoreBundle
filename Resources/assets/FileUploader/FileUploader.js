'use strict';

import HashFile from './HashFile';

/**
 * Upload chunk by chunk a file after having check that is not already on the server (the file is identified by an hash, computed client side)
 */
export default class FileUploader {

    constructor(params) {

        // Define all file status values and the size of slice to compute the sha1 and the size of the uploaded chunk
        this.statics = {
            CHUNK_SIZE: 1024 * 1024 /* bytes */,
            UPLOADED: 1,
            ERROR: 2,
            UPLOADING: 3,
            PAUSE: 4,
            LOADING: 5,
            UPLOADERROR: 6
        };

        if(params.file) {

            this.file = params.file;
            this.size = params.file.size;
            this.type = params.file.type;
            this.name = params.file.name;
            this.lastModified = params.file.lastModified;

            this.initUrl = params.initUrl;
            this.algo = params.algo;
            this.onError = params.onError;
            this.onHashAvailable = params.onHashAvailable;
            this.onUploaded = params.onUploaded;
            this.onProgress = params.onProgress;

            this.errorDescription = 'N/A';
            this.hash = null;
            this.chunkUrl = null;
            this.uploaded = 0;

            if (!this.type || this.type === '') {
                this.type = 'application/octet-stream';
            }

            new HashFile(this.file, this.callbackHashFinal, this.callbackHashProgress, this, this.algo === undefined?'sha1':this.algo);
        }
    }


    /**
     * Callback function called when the file's hash has been computed
     * @param hash
     */
    callbackHashFinal(hash) {
        if(typeof this.onHashAvailable === "function") {
            this.onHashAvailable(hash, this.type, this.name);
        }
        else {
            console.log('Hash: '+hash);
        }
        this.hash = hash;
        this.status = this.statics.UPLOADING;
        this.initUpload();
    }


    /**
     * Callback function called each time that a file's chunk has hashed
     * @param percentage
     */
    callbackHashProgress(percentage) {
        if(typeof this.onProgress === "function") {
            this.onProgress('Computing hash', 0, percentage+'%');
        }
        else {
            console.log('Hash treated at '+percentage+'%');
        }
    }

    /**
     * Init the upload. The file description are send to the server. If the file is already know by the server
     * the object (this) is updated (bytes already upload, file ready, ...)
     */
    initUpload() {
        const self = this;

        if (this.status === this.statics.UPLOADING
            || this.status === this.statics.PAUSE) {

            const xmlHttp = new XMLHttpRequest();

            xmlHttp.onload = function () {
                if (this.status === 200) {
                    const fileInfo = JSON.parse(this.responseText);

                    if (fileInfo && fileInfo.uploaded !== undefined) {
                        self.uploaded = fileInfo.uploaded;
                        self.chunkUrl = fileInfo.chunkUrl;

                        if (self.uploaded < self.size) {
                            self.startUpload(self);
                        }
                        else if (self.uploaded === self.size) {
                            self.finalizeUpload(fileInfo);
                        }
                        else {
                            self.setUploadError('Number bytes of already uploaded is abnormal ' + HashFile.humanFileSize(self.uploaded) + '/' + HashFile.humanFileSize(self.size));
                        }
                    }
                    else {
                        if (fileInfo.error && fileInfo.error[0]) {
                            self.setUploadError(fileInfo.error[0], 200);
                        }
                        else {
                            self.setUploadError('Upload init has failed', 200);
                        }
                    }
                }
                else {
                    self.setUploadError(this.statusText, this.status);
                }
            };

            xmlHttp.onerror = function () {
                self.setUploadError(this.statusText, this.status);
            };

            xmlHttp.open("POST", this.initUrl, true);
            const params = JSON.stringify({name: this.name, type: this.type, size: this.size, hash: this.hash});
            xmlHttp.setRequestHeader("Content-type", "application/json; charset=utf-8");

            xmlHttp.send(params);
        }
    }


    /**
     * An error has occurred to the file. The file status is set to ERROR
     *
     * @param description, description of the error
     * @param errorCode; http status code returned
     */
    setUploadError(description, errorCode){
        this.status = this.statics.ERROR;
        this.errorDescription = description;
        if (this.onError) {
            this.onError(description, errorCode);
        }
        else {
            console.log(description + '/' + errorCode);
        }
    }



    /**
     * Start or resume the upload
     */
    startUpload() {
        this.status = this.statics.UPLOADING;
        this.uploadNextChunk();
    }


    /**
     * Finalize the upload
     * @param response
     */
    finalizeUpload(response) {
        if (response.sha1 !== this.hash) {
            console.log('hash mismatch');
            this.hash = response.hash;
        }
        this.status = this.statics.UPLOADED;

        if(typeof this.onUploaded === "function") {
            this.onUploaded(response.url, response.previewUrl);
        }
        else {
            console.log('Upload done: '+this.hash);
        }
    }

    /**
     * Upload the next chunk, only if the file status is UPLOADING
     */
    uploadNextChunk() {
        if (this.status === this.statics.UPLOADING) {
            this.timeStamp = (new Date()).getTime();

            //get the blob corresponding to the current chunk
            // console.log('load from '+this.uploaded+' to '+Math.min(this.statics.CHUNK_SIZE, this.size-this.uploaded));
            const blob = this.file.slice(this.uploaded, this.uploaded + Math.min(this.statics.CHUNK_SIZE, this.size - this.uploaded));
            const xhr = new XMLHttpRequest();

            const self = this;
            //add listener to the XHR object in case of success or fail

            xhr.onerror = function () {
                self.setUploadError(this.statusText, this.status);
            };

            xhr.onload = function (evt) {
                self.onChunkUploadSuccess(evt)
            };

            //init the XHR request
            xhr.open("POST", this.chunkUrl, true);

            //send form with the XHR
            xhr.send(blob);
        }
        else {
            this.setUploadError('inconsistent status', 400);
        }
    }

    /**
     * callback function, called when the chunk upload has responded
     *
     * @param event
     */
    onChunkUploadSuccess(event) {
        if (event.target.status === 200) {

            this.uploaded += Math.min(this.statics.CHUNK_SIZE, this.size - this.uploaded);
            if (this.uploaded === this.size) {
                const response = JSON.parse(event.target.responseText);

                if (!response.error) {
                    this.finalizeUpload(response);
                }
                else {
                    this.setUploadError(response.error[0], 200);
                }

            }
            else {
                if(typeof this.onProgress === "function") {
                    this.onProgress('Uploading', (this.uploaded / this.size), FileUploader.msToHumanDuration(((this.size - this.uploaded) / this.statics.CHUNK_SIZE) * ((new Date()).getTime() - this.timeStamp)));
                }
                else {
                    console.log('Uploading '+((this.uploaded / this.size) * 100).toFixed(0));
                }
                this.uploadNextChunk();
            }
        }
        else {
            this.setUploadError(event.target.responseText, event.target.status);
        }
    };

    /**
     * Convert ms to human readable format
     *
     * @param milliseconds to convert
     * @param precision (number of decimals)
     * @return string
     */
    static msToHumanDuration(milliseconds, precision) {
        const seconds = 1000;
        const minutes = seconds * 60;
        const hours = minutes * 60;
        const days = hours * 24;
        if (!precision) precision = 0;

        if ((milliseconds >= 0) && (milliseconds < seconds)) {
            return '> 1 s';

        } else if ((milliseconds >= seconds) && (milliseconds < minutes)) {
            return (milliseconds / seconds).toFixed(precision) + ' s';

        } else if ((milliseconds >= minutes) && (milliseconds < hours)) {
            return (milliseconds / minutes).toFixed(precision) + ' min';

        } else if ((milliseconds >= hours) && (milliseconds < days)) {
            return (milliseconds / hours).toFixed(precision) + ' h';

        } else if (milliseconds >= days) {
            return (milliseconds / days).toFixed(precision) + ' d';

        } else {
            return milliseconds + ' ms';
        }
    };
}