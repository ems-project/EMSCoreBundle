import ajaxModal from "./../helper/ajaxModal";
import ProgressBar from "../helper/progressBar";
import FileUploader from "@elasticms/file-uploader";

export default class MediaLibrary {
    id;
    element;
    #pathPrefix;
    #options = {};
    #elements = '';
    #activeFolder = '';
    #loadedFiles = 0;

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

    _addEventListeners() {
        this.element.onclick = (event) => {
            let classList = event.target.classList;

            if (classList.contains('media-lib-folder')) this._onClickFolder(event.target);
            if (classList.contains('media-lib-item')) this._onClickFile(event.target);
            if (classList.contains('btn-file-delete')) this._onClickButtonFileDelete();
            if (classList.contains('btn-file-rename')) this._onClickButtonFileRename(event.target);
            if (classList.contains('btn-home')) this._onClickButtonHome(event.target);
            if (classList.contains('btn-folder-add')) this._onClickButtonFolderAdd();
            if (classList.contains('btn-folder-delete')) this._onClickButtonFolderDelete(event.target);
            if (classList.contains('btn-folder-rename')) this._onClickButtonFolderRename(event.target);
            if (classList.contains('breadcrumb-item')) this._onClickBreadcrumbItem(event.target);
        }

        this.element.onchange = (event) => {
            if (event.target.classList.contains('file-uploader-input')) {
                this._uploadFiles(Array.from(event.target.files));
            }
        }
    }

    _onClickFile(item) {
        this.loading(true);

        const activeItem = this.#elements.listFiles.querySelectorAll('.active');
        activeItem.forEach((li) => li.classList.remove('active'))
        item.classList.add('active');

        this._getHeader([item.dataset.id]).then(() => { this.loading(false); });
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
    _onClickButtonFileDelete() {
        const activeItems = this.#elements.listFiles.querySelectorAll('.active');
        const activeIds =  [];
        activeItems.forEach((element) => activeIds.push(element.dataset.id));

        this._post(`/files/delete`, { 'files': activeIds }).then((json) => {
            if (json.hasOwnProperty('success'))  activeItems.forEach((element) => element.remove());
            this._getHeader().then(() => this.loading(false));
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

        this.#activeFolder = `/${button.dataset.id}`;
        this._getFiles().then(() => this.loading(false));
    }
    _onClickButtonFolderAdd() {
        ajaxModal.load({
            url: `${this.#pathPrefix}/add-folder${this.#activeFolder}`,
            size: 'sm'
        }, (json) => {
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
                .then(() => setTimeout(() => {}, 1000))
                .then(() => ajaxModal.close())
            ;
        });
    }
    _onClickButtonFolderRename(button) {
        const folderId = button.dataset.id;

        ajaxModal.load({ url: `${this.#pathPrefix}/folder/${folderId}/rename`, size: 'sm'}, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (!json.hasOwnProperty('jobId')) return;

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
                .then(() => this._getFolders())
                .then(() => setTimeout(() => {}, 1000))
                .then(() => ajaxModal.close())
            ;
        });
    }
    _onClickButtonHome() {
        this.loading(true);
        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        this.#activeFolder = ''
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

    _getHeader(files = null) {
        let path = `/header${this.#activeFolder}`;

        if (files) {
            let query = new URLSearchParams([['files[]', files]]);
            path = path + `?${query.toString()}`
        }

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
        return this._get(`/files${this.#activeFolder}?${query}`).then((files) => { this._appendFiles(files) });
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
        if (json.hasOwnProperty('header')) this.#elements.header.innerHTML = json.header;
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

                    const data =  {
                        'filename': file.name,
                        'filesize': file.size,
                        'mimetype': file.type,
                    };
                    data[mediaLib.#options.hashAlgo] = fileHash;

                    mediaLib._post(
                        `/add-file${mediaLib.#activeFolder}`,
                        { file: data }
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
            dropArea.addEventListener(eventName, () => dropArea.classList.add('media-lib-drop-area'), false);
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
