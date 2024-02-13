import ajaxModal from "./../helper/ajaxModal";
import ProgressBar from "../helper/progressBar";
import FileUploader from "@elasticms/file-uploader";

export default class MediaLibrary {
    id;
    element;
    #pathPrefix;
    #options = {};
    #elements = '';
    #activeFolderId = null;
    #activeFolderHeader = '';
    #loadedFiles = 0;
    #selectionLastFile = null;

    constructor (element, options) {
        this.id = element.id;
        this.element = element;
        this.#pathPrefix = `${options.urlMediaLib}/${element.dataset.hash}`;
        this.#options = options;

        this.#elements = {
            header:  element.querySelector('div.media-nav-bar'),
            inputUpload:  element.querySelector('input.file-uploader-input'),
            files: element.querySelector('div.media-lib-files'),
            loadMoreFiles: element.querySelector('div.media-lib-files > div.media-lib-load-more'),
            listFiles: element.querySelector("ul.media-lib-list-files"),
            listFolders: element.querySelector("ul.media-lib-list-folders"),
            listUploads: element.querySelector('ul.media-lib-list-uploads'),
        };

        this._init();
    }

    _init() {
        this.loading(true);
        this._addEventListeners();
        this._initDropArea(this.#elements.files);
        this._initInfiniteScrollFiles(this.#elements.files, this.#elements.loadMoreFiles);

        Promise
            .allSettled([this._getFolders(), this._getFiles()])
            .then(() => this.loading(false));
    }

    loading(flag) {
        const buttons = this.element.querySelectorAll('button');
        const uploadButton = (this.#elements.inputUpload) ?
            this.#elements.header.querySelector(`label[for="${this.#elements.inputUpload.id}"]`) : false;

        if (flag) {
            buttons.forEach(button => button.disabled = true);
            if (uploadButton) uploadButton.setAttribute('disabled', 'disabled');
        } else {
            buttons.forEach(button => button.disabled = false);
            if (uploadButton) uploadButton.removeAttribute('disabled');
        }
    }
    getSelectionFiles() {
        return this.#elements.listFiles.querySelectorAll('.active');
    }

    _addEventListeners() {
        document.onkeyup = (event) => { if (event.shiftKey) this.#selectionLastFile = null; }

        this.element.onclick = (event) => {
            let classList = event.target.classList;

            if (classList.contains('media-lib-item')) this._onClickFile(event.target, event);
            if (classList.contains('media-lib-folder')) this._onClickFolder(event.target);

            if (classList.contains('btn-file-upload')) this.#elements.inputUpload.click();
            if (classList.contains('btn-file-rename')) this._onClickButtonFileRename(event.target);
            if (classList.contains('btn-file-delete')) this._onClickButtonFileDelete(event.target);
            if (classList.contains('btn-files-delete')) this._onClickButtonFilesDelete(event.target)

            if (classList.contains('btn-folder-add')) this._onClickButtonFolderAdd();
            if (classList.contains('btn-folder-delete')) this._onClickButtonFolderDelete(event.target);
            if (classList.contains('btn-folder-rename')) this._onClickButtonFolderRename(event.target);

            if (classList.contains('btn-home')) this._onClickButtonHome(event.target);
            if (classList.contains('breadcrumb-item')) this._onClickBreadcrumbItem(event.target);

            const keepSelection = ['media-lib-item', 'btn-file-rename', 'btn-file-delete', 'btn-files-delete'];
            if (!keepSelection.some(className => classList.contains(className))) {
                this._selectFilesReset();
            }
        }

        this.element.onchange = (event) => {
            if (event.target.classList.contains('file-uploader-input')) {
                this._uploadFiles(Array.from(event.target.files));
            }
        }
    }

    _onClickFile(item, event) {
        this.loading(true);
        const selection = this._selectFiles(item, event);
        const fileId = selection.length === 1 ? item.dataset.id : null;
        this._getHeader(fileId).then(() => { this.loading(false); });
    }

    _onClickButtonFileRename(button) {
        const fileId = button.dataset.id;
        const fileRow = this.#elements.listFiles.querySelector(`[data-id='${fileId}']`);

        ajaxModal.load({ url: `${this.#pathPrefix}/file/${fileId}/rename`, size: 'sm'}, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (json.hasOwnProperty('fileRow')) fileRow.outerHTML = json.fileRow;

            this._getHeader().then(() => {
                ajaxModal.close();
                this.loading(false);
            });
        });
    }
    _onClickButtonFileDelete(button) {
        const fileId = button.dataset.id;
        const fileRow = this.#elements.listFiles.querySelector(`[data-id='${fileId}']`);

        this._post(`/file/${fileId}/delete`).then((json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;

            fileRow.remove();
            this._selectFilesReset();
            this.loading(false);
        });
    }
    _onClickButtonFilesDelete(button) {
        const selection = this.getSelectionFiles();
        if (selection.length < 1) return;

        const path = this.#activeFolderId ? `/delete-files/${this.#activeFolderId}` : '/delete-files';
        const query = new URLSearchParams({ 'selectionFiles': selection.length.toString() });
        const modalSize = button.dataset.modalSize ?? 'sm';

        ajaxModal.load({ url: this.#pathPrefix + path + '?' + query.toString(), size: modalSize }, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;

            let processed = 0;
            const progressBar = new ProgressBar('progress-delete-files', {
                label: 'Deleting files',
                value: 100,
                showPercentage: true,
            });

            ajaxModal.getBodyElement().append(progressBar.element());
            this.loading(true);

            Promise
                .allSettled(Array.from(selection).map(fileRow => {
                    return this._post(`/file/${fileRow.dataset.id}/delete`).then(() => {
                        if (!json.hasOwnProperty('success') || json.success === false) return;

                        fileRow.remove();
                        progressBar
                            .progress(Math.round((++processed / selection.length) * 100))
                            .style('success');
                    });
                }))
                .then(() => this._selectFilesReset())
                .then(() => this.loading(false))
                .then(() => new Promise(resolve => setTimeout(resolve, 2000)))
                .then(() => ajaxModal.close())
            ;
        });
    }

    _onClickFolder(button) {
        this.loading(true);
        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        button.classList.add('active');
        let parentLi = button.parentNode;
        if (parentLi && parentLi.classList.contains('media-lib-folder-children')) {
            parentLi.classList.toggle('open');
        }

        this.#activeFolderId = button.dataset.id;
        this._getFiles().then(() => this.loading(false));
    }
    _onClickButtonFolderAdd() {
        const path = this.#activeFolderId ? `/add-folder/${this.#activeFolderId}` : '/add-folder';

        ajaxModal.load({ url: this.#pathPrefix + path, size: 'sm'}, (json) => {
            if (json.hasOwnProperty('success') && json.success === true) {
                this.loading(true);
                this._getFolders(json.path).then(() => this.loading(false));
            }
        });
    }
    _onClickButtonFolderDelete(button) {
        const folderId = button.dataset.id;
        const modalSize = button.dataset.modalSize ?? 'sm';

        ajaxModal.load({ url: `${this.#pathPrefix}/folder/${folderId}/delete`, size: modalSize }, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (!json.hasOwnProperty('jobId')) return;

            let jobProgressBar = new ProgressBar('progress-' + json.jobId, {
                label: 'Deleting folder',
                value: 100,
                showPercentage: false,
            });

            ajaxModal.getBodyElement().append(jobProgressBar.element());
            this.loading(true);

            Promise.allSettled([
                this._startJob(json.jobId),
                this._jobPolling(json.jobId, jobProgressBar)
            ])
                .then(() => this._onClickButtonHome())
                .then(() => this._getFolders())
                .then(() => new Promise(resolve => setTimeout(resolve, 2000)))
                .then(() => ajaxModal.close())
            ;
        });
    }
    _onClickButtonFolderRename(button) {
        const folderId = button.dataset.id;

        ajaxModal.load({ url: `${this.#pathPrefix}/folder/${folderId}/rename`, size: 'sm'}, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (!json.hasOwnProperty('jobId') || !json.hasOwnProperty('path')) return;

            let jobProgressBar = new ProgressBar('progress-' + json.jobId, {
                label: 'Renaming',
                value: 100,
                showPercentage: false,
            });

            ajaxModal.getBodyElement().append(jobProgressBar.element());
            this.loading(true);

            Promise.allSettled([
                this._startJob(json.jobId),
                this._jobPolling(json.jobId, jobProgressBar)
            ])
                .then(() => this._getFolders(json.path))
                .then(() => new Promise(resolve => setTimeout(resolve, 2000)))
                .then(() => ajaxModal.close())
            ;
        });
    }
    _onClickButtonHome() {
        this.loading(true);
        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        this.#activeFolderId = null;
        this._getFiles().then(() => this.loading(false));
    }
    _onClickBreadcrumbItem(item) {
        let id = item.dataset.id;
        if (id) {
            let folderButton = this.#elements.listFolders.querySelector(`button[data-id="${id}"]`);
            this._onClickFolder(folderButton);
        } else {
            this._onClickButtonHome();
        }
    }

    _getHeader(fileId = null) {
        let path = '/header';
        let query = new URLSearchParams();

        if (fileId) query.append('fileId', fileId);
        if (this.getSelectionFiles().length > 0) query.append('selectionFiles', this.getSelectionFiles().length.toString());
        if (this.#activeFolderId) query.append('folderId', this.#activeFolderId);

        if (query.size > 0) path = path + '?' + query.toString();

        return this._get(path).then((json) => {
            if (json.hasOwnProperty('header')) this.#elements.header.innerHTML = json.header;
        });
    }
    _getFiles(from = 0) {
        if (0 === from) {
            this.#loadedFiles = 0;
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
            this.#elements.listFiles.innerHTML = '';
        }

        const query = new URLSearchParams({ from: from.toString() }).toString();
        const path = this.#activeFolderId ? `/files/${this.#activeFolderId}` : '/files';

        return this._get(`${path}?${query}`).then((files) => { this._appendFiles(files) });
    }
    _getFolders(openPath) {
        this.#elements.listFolders.innerHTML = '';
        return this._get('/folders').then((folders) => {
            this._appendFolderItems(folders, this.#elements.listFolders);
            if (openPath) { this._openPath(openPath); }
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
            if (button) this._onClickFolder(button);
        }
    }

    _appendFiles(json) {
        if (json.hasOwnProperty('header')) {
            this.#elements.header.innerHTML = json.header;
            this.#activeFolderHeader = json.header;
        }
        if (json.hasOwnProperty('rowHeader'))  this.#elements.listFiles.innerHTML += json.rowHeader;
        if (json.hasOwnProperty('totalRows'))  this.#loadedFiles += json.totalRows;
        if (json.hasOwnProperty('rows'))  this.#elements.listFiles.innerHTML += json.rows;

        if (json.hasOwnProperty('remaining') && json.remaining) {
            this.#elements.loadMoreFiles.classList.add('show-load-more');
        } else {
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
        }
    }
    _appendFolderItems(folders, list) {
        Object.values(folders).forEach(folder => {
            let buttonFolder = document.createElement("button");
            buttonFolder.textContent = folder.name;
            buttonFolder.dataset.id = folder.id;
            buttonFolder.dataset.path = folder.path;
            buttonFolder.classList.add('media-lib-folder');

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

    _uploadFiles(files) {
        this.loading(true);

        Promise
            .allSettled(files.map((file) => this._uploadFile(file)))
            .then(() => {
                this._getFiles().then(() => this.loading(false));
            });
    }
    _uploadFile(file) {
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

                    const path = mediaLib.#activeFolderId ? `/add-file/${mediaLib.#activeFolderId}` : '/add-file';
                    const data =  {
                        'filename': file.name,
                        'filesize': file.size,
                        'mimetype': file.type,
                    };
                    data[mediaLib.#options.hashAlgo] = fileHash;

                    mediaLib._post(path, { file: data }
                    ).then(() => {
                        mediaLib.#elements.listUploads.removeChild(liUpload);
                        resolve();
                    }).catch(() => reject());
                },
                onError: function (message) {
                    progressBar.status('Error: ' + message);
                    progressBar.progress(100);
                    progressBar.style('danger');
                }
            });
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
            dropArea.addEventListener(eventName, () => {
                this._selectFilesReset();
                dropArea.classList.add('media-lib-drop-area')
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('media-lib-drop-area'), false);
        });

        dropArea.addEventListener('drop', () => {
            const files = event.target.files || event.dataTransfer.files;
            this._uploadFiles(Array.from(files));
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
                    this.loading(true);
                    this._getFiles(this.#loadedFiles).then(() => this.loading(false));
                }
            });
        }, options);

        observer.observe(divLoadMore);
    }

    _selectFiles(item, event) {
        let files = this.#elements.listFiles.querySelectorAll('.media-lib-item');

        if (event.shiftKey && this.#selectionLastFile !== null) {
            let start = Array.from(files).indexOf(item);
            let end = Array.from(files).indexOf(this.#selectionLastFile);
            if (start > end) [start, end] = [end, start];

            files.forEach((f, index) => {
                if (index >= start && index <= end) f.classList.add('active')
            });
        } else {
            files.forEach((f) => f.classList.remove('active'));
            item.classList.add('active')
        }

        this.#selectionLastFile = item;

        return this.getSelectionFiles();
    }
    _selectFilesReset() {
        this.#elements.header.innerHTML = this.#activeFolderHeader;
        this.#elements.listFiles.querySelectorAll('.media-lib-item').forEach((f) => f.classList.remove('active'));
    }

    async _jobPolling(jobId, jobProgressBar) {
        const jobStatus = await this._getJobStatus(jobId);

        if (jobStatus.started === true && jobStatus.progress > 0) {
            jobProgressBar.status('Running ...').progress(jobStatus.progress).style('success');
        }
        if (jobStatus.done === true) {
            jobProgressBar.status('Finished').progress(100);
            return jobStatus;
        }

        await new Promise((r) => setTimeout(r, 1500));
        return await this._jobPolling(jobId, jobProgressBar);
    }
    async _startJob(jobId) {
        const response = await fetch(`/job/start/${jobId}`, {
            method: "POST",
            headers: { 'Content-Type': 'application/json'},
        });
        return response.json();
    }

    async _getJobStatus(jobId) {
        const response = await fetch(`/job/status/${jobId}`, {
            method: "GET",
            headers: { 'Content-Type': 'application/json'},
        });
        return response.json();
    }
    async _get(path) {
        this.loading(true);
        const response = await fetch(`${this.#pathPrefix}${path}`, {
            method: "GET",
            headers: { 'Content-Type': 'application/json'},
        });
        return response.json();
    }
    async _post(path, data = {}) {
        this.loading(true);
        const response = await fetch(`${this.#pathPrefix}${path}`, {
            method: "POST",
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return response.json();
    }
}
