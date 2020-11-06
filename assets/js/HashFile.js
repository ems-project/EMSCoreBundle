'use strict';

import CryptoJS from 'crypto-js';

/**
 * Compute a file's hash file based on the Luca Vaccaro's approch https://medium.com/@0xVaccaro/hashing-big-file-with-filereader-js-e0a5c898fc98
 */
export default class HashFile {

    static defaultCallbackFinal(hash, duration, fileSize, chunkTotal, chunkReorder) {
        console.log('Computed hash: ' + hash + ' in ' + duration + 'seconds (#chunks: ' + chunkTotal + ', #reorder: ' + chunkReorder + '). File size: ' + HashFile.humanFileSize(fileSize));
    }

    static defaultCallbackProgress(percentage, duration, treatedSize, fileSize, chunkTotal, chunkReorder) {
        console.log('File hash in progress ' + percentage + '% after ' + duration + ' seconds (' + HashFile.humanFileSize(treatedSize) + '/' + HashFile.humanFileSize(fileSize) + ') (#chunks: ' + chunkTotal + ', #reorder: ' + chunkReorder + ')');
    }

    static humanFileSize(bytes, si = true) {
        const thresh = si ? 1000 : 1024;
        if (Math.abs(bytes) < thresh) {
            return bytes + ' B';
        }
        const units = si
            ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
            : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        let u = -1;
        do {
            bytes /= thresh;
            ++u;
        } while (Math.abs(bytes) >= thresh && u < units.length - 1);
        return bytes.toFixed(1) + ' ' + units[u];
    }


    constructor(fileHandler, callbackFinal = null, callbackProgress = null, callbackContext = null, algo = 'sha1', chunkSize = 1024 * 1024 /* bytes */, timeout = 10 /*milli-seconds*/) {

        if (fileHandler === undefined) {
            return;
        }

        this.algo = algo;
        this.fileHandler = fileHandler;
        this.HASH = CryptoJS.algo[algo.toUpperCase()].create();
        this.counter = 0;
        this.chunkSize = chunkSize;
        this.timeout = timeout;
        this.timeStart = new Date().getTime();
        this.timeEnd = 0;
        this.lastOffset = 0;
        this.chunkReorder = 0;
        this.chunkTotal = 0;

        this.callbackContext = callbackContext ? callbackContext : this;

        if (callbackFinal) {
            this.callbackFinal = callbackFinal;
        }
        else {
            this.callbackFinal = HashFile.defaultCallbackFinal;
        }

        if (callbackProgress) {
            this.callbackProgress = callbackProgress;
        }
        else {
            this.callbackProgress = HashFile.defaultCallbackProgress;
        }

        this.loading();
    }

    loading() {

        let offset = 0;
        let partial;
        let index = 0;
        const self = this;

        if (this.fileHandler.size === 0) {
            this.callbackFinal(this.callbackContext, this.HASH.finalize().toString());
        }

        while (offset < this.fileHandler.size) {
            partial = this.fileHandler.slice(offset, offset + this.chunkSize);
            const reader = new FileReader;
            reader.size = this.chunkSize;
            reader.offset = offset;
            reader.index = index;
            reader.onload = function (evt) {
                self.callbackRead.call(self, this, evt);
            };
            reader.readAsArrayBuffer(partial);
            offset += this.chunkSize;
            index += 1;
        }

    }

    callbackRead(reader, evt) {
        if (this.lastOffset === reader.offset) {
            // console.log("[",reader.size,"]",reader.offset,'->', reader.offset+reader.size,"");
            this.lastOffset = reader.offset + reader.size;
            this.progressHash(evt.target.result);
            if (reader.offset + reader.size >= this.fileHandler.size) {
                this.lastOffset = 0;
                this.finalHash();
            }
        } else {

            // console.log("[",reader.size,"]",reader.offset,'->', reader.offset+reader.size,"wait");
            const self = this;
            setTimeout(function () {
                self.callbackRead(reader, evt);
            }, this.timeout);
            this.chunkReorder++;
        }
    }

    progressHash(data) {
        const wordBuffer = CryptoJS.lib.WordArray.create(data);
        this.HASH.update(wordBuffer);
        this.counter += data.byteLength;
        const duration = ((new Date()) - this.timeStart) / 1000;
        this.chunkTotal++;

        this.callbackProgress.call(this.callbackContext, ((this.counter / this.fileHandler.size) * 100).toFixed(0), duration, this.counter, this.fileHandler.size, this.chunkTotal, this.chunkReorder);
    }

    finalHash() {
        const encrypted = this.HASH.finalize().toString();
        const duration = ((new Date()) - this.timeStart) / 1000;
        this.callbackFinal.call(this.callbackContext, encrypted, duration, this.fileHandler.size, this.chunkTotal, this.chunkReorder);
    }

}