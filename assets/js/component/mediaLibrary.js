import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonGet, ajaxJsonPost} from "../helper/ajax";
import ProgressBar from "../helper/progressBar";
import FileUploader from "@elasticms/file-uploader";

export default class MediaLibrary {
    #el;
    #url;
    #activeFolder = '';
    #options = {};
    #elements = {};
    #loadedFiles = 0;

    constructor (el, options) {
        this.#options = options;
        this.#url = [options.urlMediaLib, el.dataset.hash].join('/');

        this.#el = el;
        this.#elements = {
            header:  el.querySelector('div.media-nav-bar'),
            inputUpload:  el.querySelector('input.file-uploader-input'),
            files: el.querySelector('div.media-lib-files'),
            loadMoreFiles: el.querySelector('div.media-lib-files > div.media-lib-load-more'),
            listFiles: el.querySelector("ul.media-lib-list-files"),
            listFolders: el.querySelector("ul.media-lib-list-folders"),
            listUploads: el.querySelector('ul.media-lib-list-uploads'),
            listBreadcrumb: el.querySelector('ul.media-lib-list-breadcrumb')
        };

        this._init();
    }

    _init() {
        this._addEventListeners();
        this._initDropArea(this.#elements.files);
        this._initInfiniteScrollFiles(this.#elements.files, this.#elements.loadMoreFiles);

        this._disableButtons();
        Promise
            .allSettled([this._getFolders(), this._getFiles()])
            .then(() => this._enableButtons());
    }

    _disableButtons() {
        this.#el.querySelectorAll('button').forEach(button => button.disabled = true);
        let uploadLabel = this.uploadLabel();
        if (uploadLabel) uploadLabel.setAttribute('disabled', 'disabled');
    }
    _enableButtons() {
        this.#el.querySelectorAll('button').forEach(button => button.disabled = false);
        let uploadLabel = this.uploadLabel();
        if (uploadLabel) uploadLabel.removeAttribute('disabled');
    }

    uploadInput() {
        return this.#elements.header.querySelector('input.file-uploader-input');
    }
    uploadLabel() {
        let uploadInput = this.uploadInput();
        if (!uploadInput) return null;

        return this.#elements.header.querySelector(`label[for="${uploadInput.id}"]`);
    }

    _addEventListeners() {
        this._addEventListenersHeader();
        this.#el.onclick = (event) => {
            let classList = event.target.classList;
            if (classList.contains('media-lib-link-folder')) this._clickButtonFolder(event.target);
        }
    }
    _addEventListenersHeader()
    {
        let elements = {
            btnHome: this.#elements.header.querySelector('button.btn-home'),
            btnAddFolder: this.#elements.header.querySelector('button.btn-add-folder'),
            breadcrumb: this.#elements.header.querySelector('.media-lib-list-breadcrumb'),
            inputUpload: this.uploadInput(),
        };

        if (elements.btnAddFolder) elements.btnAddFolder.onclick = () => this._addFolder();
        if (elements.btnHome) elements.btnHome.onclick = () => this._clickButtonHome();
        if (elements.breadcrumb) elements.breadcrumb.onclick = (event) => this._clickBreadcrumb(event.target);
        if (elements.inputUpload) elements.inputUpload.onchange = (event) => this._addFiles(Array.from(event.target.files));
    }

    _clickButtonHome() {
        this._disableButtons();
        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        this.#activeFolder = ''
        this._getFiles().then(() => this._enableButtons());
    }
    _clickButtonFolder(button) {
        this._disableButtons();
        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        button.classList.add('active');
        let parentLi = button.parentNode;
        if (parentLi && parentLi.classList.contains('media-lib-folder-children')) {
            parentLi.classList.toggle('open');
        }

        this.#activeFolder = button.dataset.id;
        this._getFiles().then(() => this._enableButtons());
    }

    _clickBreadcrumb(target) {
        if (!target.classList.contains('breadcrumb-item')) return;
        let id = target.dataset.id;
        if (id) {
            let folderButton = this.#elements.listFolders.querySelector(`button[data-id="${id}"]`);
            this._clickButtonFolder(folderButton);
        } else {
            this._clickButtonHome();
        }
    }

    _addFiles(files) {
        this._disableButtons();

        Promise
            .allSettled(files.map((file) => this._addFile(file)))
            .then(() => {
                this.#elements.inputUpload.value = "";
                this._getFiles().then(() => this._enableButtons());
            });
    }
    _addFile(file) {
        return new Promise((resolve, reject) => {
            let id = 'upload-' + Date.now();
            let progressBar = new ProgressBar('progress-' + id, {
                'label': file.name
            });

            let fileHash = null;
            let mediaLib = this;
            let liUpload = document.createElement('li');
            liUpload.append(progressBar.element());
            this.#elements.listUploads.appendChild(liUpload);

            new FileUploader({
                file: file,
                algo: this.#options.hashAlgo,
                initUrl: this.#options.urlInitUpload,
                onHashAvailable: function (hash) {
                    progressBar.status('Hash available');
                    progressBar.progress(0);
                    fileHash = hash;
                },
                onProgress: function (status, progress, remaining) {
                    if (status === 'Computing hash') {
                        progressBar.status('Calculating ...');
                        progressBar.progress(remaining);
                    }
                    if (status === 'Uploading') {
                        progressBar.status('Uploading: ' + remaining);
                        progressBar.progress(Math.round(progress * 100));
                    }
                },
                onUploaded: function () {
                    progressBar.status('Uploaded');
                    progressBar.progress(100);
                    progressBar.style('success');

                    let data =  {
                        'filename': file.name,
                        'filesize': file.size,
                        'mimetype': file.type,
                    };
                    data[mediaLib.#options.hashAlgo] = fileHash;

                    ajaxJsonPost(
                        [mediaLib.#url, 'add-file'].join('/') + (mediaLib.#activeFolder ? '/'+ mediaLib.#activeFolder : ''),
                        JSON.stringify({'file': data}),
                        (json, request) => {
                            if (request.status === 201) {
                                resolve();
                                mediaLib.#elements.listUploads.removeChild(liUpload);
                            } else {
                                reject();
                                progressBar.status('Error: ' + message);
                                progressBar.progress(100);
                                progressBar.style('danger');
                            }
                        });
                },
                onError: function (message) {
                    progressBar.status('Error: ' + message);
                    progressBar.progress(100);
                    progressBar.style('danger');
                }
            });

        });
    }
    _addFolder() {
        ajaxModal.load({
            url: [this.#url, 'add-folder'].join('/') + (this.#activeFolder ? '/'+ this.#activeFolder : ''),
            size: 'sm'
        }, (json) => {
            if (json.hasOwnProperty('success') && json.success === true) {
                this._disableButtons();
                this._getFolders(json.path).then(() => this._enableButtons());
            }
        });
    }

    _openPath(path) {
        let currentPath = '';
        path.split('/').filter(f => f !== '').forEach((folderName) => {
            currentPath += `/${folderName}`;

            let parentButton = document.querySelector(`button[data-path="${currentPath}"]`);
            let parentLi = parentButton ? parentButton.parentNode : null;

            if (parentLi && parentLi.classList.contains('media-lib-folder-children')) {
                parentLi.classList.add('open');
            }
        });

        if ('' !== currentPath) {
            let button = document.querySelector(`button[data-path="${currentPath}"]`);
            if (button) button.classList.add('active');
        }
    }

    _getFiles(from = 0) {
        if (0 === from) {
            this.#loadedFiles = 0;
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
            this.#elements.listFiles.innerHTML = '';
          //  this._appendBreadcrumbItems(this.#elements.listBreadcrumb);
        }

        let query = '?' + new URLSearchParams({ from: from.toString() }).toString();

        return fetch([this.#url, 'files'].join('/') + (this.#activeFolder ? '/'+ this.#activeFolder : '') + query, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json'}
        }).then((response) => {
            return response.ok ? response.json().then((json) => this._appendFiles(json)) : Promise.reject(response);
        });
    }
    _getFolders(openPath) {
        return new Promise((resolve) => {
            this.#elements.listFolders.innerHTML = '';
            ajaxJsonGet([this.#url, 'folders'].join('/'), (folders) => {
                this._appendFolderItems(folders, this.#elements.listFolders);
                if (openPath) { this._openPath(openPath); }
                resolve();
            });
        });
    }

    _appendFiles(json) {
        if (json.hasOwnProperty('header')) {
            this.#elements.header.innerHTML = json.header;
            this._addEventListenersHeader();
        }

        if (json.hasOwnProperty('rowHeader')) {
            this.#elements.listFiles.innerHTML += json.rowHeader;
        }

        if (json.hasOwnProperty('rows')) {
            json.rows.forEach((row) => { this.#elements.listFiles.innerHTML += row; });
        }

        if (json.hasOwnProperty('totalRows')) {
            this.#loadedFiles += json.totalRows;
        }

        if (json.hasOwnProperty('remaining') && json.remaining) {
            this.#elements.loadMoreFiles.classList.add('show-load-more');
        } else {
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
        }
    }
    _appendFolderItems(folders, list) {

        Object.values(folders).forEach(folder => {
            let buttonFolder = document.createElement("button");
            buttonFolder.disabled = true;
            buttonFolder.textContent = folder.name;
            buttonFolder.dataset.id = folder.id;
            buttonFolder.dataset.path = folder.path;
            buttonFolder.classList.add('media-lib-link-folder');

            let liFolder = document.createElement("li");
            liFolder.appendChild(buttonFolder);

            if (folder.hasOwnProperty('children')) {
                let ulChildren = document.createElement('ul');
                this._appendFolderItems(folder.children, ulChildren);
                liFolder.appendChild(ulChildren);
                liFolder.classList.add('media-lib-folder-children');
            }

            list.appendChild(liFolder);
        });
    }

    _initDropArea(dropArea)  {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('media-lib-drop-area'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('media-lib-drop-area'), false);
        });

        dropArea.addEventListener('drop', () => {
            const files = event.target.files || event.dataTransfer.files;
            this._addFiles(Array.from(files));
        }, false);
    }

    _initInfiniteScrollFiles(scrollArea, divLoadMore) {
        const options = {
            root: scrollArea,
            rootMargin: "0px",
            threshold: 0.5
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this._disableButtons();
                    this._getFiles(this.#loadedFiles).then(() => this._enableButtons());
                }
            });
        }, options);

        observer.observe(divLoadMore);
    }
}