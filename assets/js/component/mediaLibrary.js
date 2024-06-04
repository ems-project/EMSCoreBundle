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
    #dragCounter = 0;
    #dragFiles = [];
    #debounceTimer = null;
    #searchValue = null;

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
        this._initInfiniteScrollFiles(this.#elements.files, this.#elements.loadMoreFiles);

        Promise
            .allSettled([this._getFolders(), this._getFiles()])
            .then(() => this.loading(false));
    }
    isLoading() {
        return this.element.classList.contains('loading');
    }
    loading(flag) {
        const buttons = this.element.querySelectorAll('button:not(.close-button)');
        const uploadButton = (this.#elements.inputUpload) ?
            this.#elements.header.querySelector(`label[for="${this.#elements.inputUpload.id}"]`) : false;

        if (flag) {
            this.element.classList.add('loading');
            buttons.forEach(button => button.disabled = true);
            if (uploadButton) uploadButton.setAttribute('disabled', 'disabled');
        } else {
            this.element.classList.remove('loading');
            buttons.forEach(button => button.disabled = false);
            if (uploadButton) uploadButton.removeAttribute('disabled');
        }
    }
    getSearchBox() {
        return this.#elements.header.querySelector('.media-lib-search');
    }
    getFolders() {
        return this.#elements.listFolders.querySelectorAll('.media-lib-folder');
    }
    getSelectionFile() {
        const selection = this.getSelectionFiles();
        return selection.length === 1 ? selection[0] : null;
    }
    getSelectionFiles() {
        return this.#elements.listFiles.querySelectorAll('.active');
    }

    _addEventListeners() {
        this.element.onkeyup = (event) => {
            if (event.shiftKey) this.#selectionLastFile = null;
            if (event.target.classList.contains('media-lib-search')) this._onSearchInput(event.target, 500);
        }

        this.element.onclick = (event) => {
            if (this.isLoading()) return;

            if (event.target.id === 'media_lib_search') return;

            let classList = event.target.classList;

            if (classList.contains('media-lib-file')) this._onClickFile(event.target, event);
            if (classList.contains('media-lib-folder')) this._onClickFolder(event.target);

            if (classList.contains('btn-file-upload')) this.#elements.inputUpload.click();
            if (classList.contains('btn-file-view')) this._onClickButtonFileView(event.target);
            if (classList.contains('btn-file-rename')) this._onClickButtonFileRename(event.target);
            if (classList.contains('btn-file-delete')) this._onClickButtonFileDelete(event.target);
            if (classList.contains('btn-files-delete')) this._onClickButtonFilesDelete(event.target)
            if (classList.contains('btn-files-move')) this._onClickButtonFilesMove(event.target)

            if (classList.contains('btn-folder-add')) this._onClickButtonFolderAdd();
            if (classList.contains('btn-folder-delete')) this._onClickButtonFolderDelete(event.target);
            if (classList.contains('btn-folder-rename')) this._onClickButtonFolderRename(event.target);

            if (classList.contains('btn-home')) this._onClickButtonHome(event.target);
            if (classList.contains('breadcrumb-item')) this._onClickBreadcrumbItem(event.target);

            const keepSelection = ['media-lib-file', 'btn-file-rename', 'btn-file-delete', 'btn-files-delete', 'btn-files-move', 'btn-file-view'];
            if (!keepSelection.some(className => classList.contains(className))) {
                this._selectFilesReset();
            }
        }

        this.#elements.inputUpload.onchange = (event) => {
            if (this.isLoading()) return;
            if (event.target.classList.contains('file-uploader-input')) {
                this._uploadFiles(Array.from(event.target.files));
                event.target.value = '';
            }
        }

        ['dragenter', 'dragover', 'dragleave', 'drop', 'dragend'].forEach((dragEvent) => {
            this.#elements.files.addEventListener(dragEvent, (event) => this._onDragUpload(event));
        });
    }

    _onClickFile(item, event) {
        this.loading(true);
        const selection = this._selectFiles(item, event);
        const fileId = selection.length === 1 ? item.dataset.id : null;
        this._getHeader(fileId).then(() => { this.loading(false); });
    }
    _onClickButtonFileView(button) {
        const getSiblingFile = (fileId, sibling) => {
            const row = this.#elements.listFiles.querySelector(`.media-lib-file[data-id='${fileId}']`);
            const rowSibling = row.closest('li')[sibling];
            return rowSibling ? rowSibling.querySelector('.media-lib-file') : null;
        }

        const navigation = (action, sibling, fileId) => {
            const button = ajaxModal.modal.querySelector(`.btn-preview-${action}`);
            if (!button || null === getSiblingFile(fileId, sibling)) return;

            button.style.display = 'inline-block';
            button.addEventListener('click', () => {
                const file = getSiblingFile(fileId, sibling);
                if (!file) return;

                const header = this.#elements.files.querySelector('.media-lib-file-header');
                const headerHeight = header ? header.getBoundingClientRect().height : 0;

                this._selectFilesReset();
                this._selectFile(file);
                this.#elements.files.scrollTop = (file.offsetTop - this.#elements.files.offsetTop) - headerHeight;
                openModal(file.dataset.id);
            });
        };

        const onKeydown = (e) => {
            const actions = { 'ArrowRight': 'next', 'ArrowLeft': 'prev'};
            const action = actions[e.key] || false;
            if (!action) return;

            const button = ajaxModal.modal.querySelector(`.btn-preview-${action}`);
            if (button) button.click();
        }

        const openModal = (fileId) => {
            const onClose = () => {
                ajaxModal.modal.removeEventListener('ajax-modal-close', onClose);
                document.removeEventListener('keydown', onKeydown);
                const selectionFile = this.getSelectionFile();
                if (selectionFile) selectionFile.click();
            }
            ajaxModal.modal.addEventListener('ajax-modal-close', onClose);

            ajaxModal.load({ url: `${this.#pathPrefix}/file/${fileId}/view`, size: 'lg', noLoading: true }, () => {
                navigation('prev', 'previousSibling', fileId);
                navigation('next','nextSibling', fileId);
                document.addEventListener('keydown', onKeydown);
            });
        };

        openModal(button.dataset.id);
    }
    _onClickButtonFileRename(button) {
        const fileId = button.dataset.id;
        const fileRow = this.#elements.listFiles.querySelector(`.media-lib-file[data-id='${fileId}']`);

        ajaxModal.load({ url: `${this.#pathPrefix}/file/${fileId}/rename`, size: 'sm'}, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (json.hasOwnProperty('fileRow')) fileRow.closest('li').innerHTML = json.fileRow;

            this._getHeader().then(() => {
                ajaxModal.close();
                this.loading(false);
            });
        });
    }
    _onClickButtonFileDelete(button) {
        const fileId = button.dataset.id;
        const fileRow = this.#elements.listFiles.querySelector(`.media-lib-file[data-id='${fileId}']`);

        this._post(`/file/${fileId}/delete`).then((json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;

            fileRow.closest('li').remove();
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

                        fileRow.closest('li').remove();
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
    _onClickButtonFilesMove(button, targetId) {
        const selection = this.getSelectionFiles();
        if (selection.length === 0) return;

        const path = this.#activeFolderId ? `/move-files/${this.#activeFolderId}` : '/move-files';
        const query = new URLSearchParams({ 'selectionFiles': selection.length.toString() });
        if (targetId) query.append('targetId', targetId);
        const modalSize = button.dataset.modalSize ?? 'sm';

        ajaxModal.load({ url: this.#pathPrefix + path + '?' + query.toString(), size: modalSize }, (json) => {
            if (!json.hasOwnProperty('success') || json.success === false) return;
            if (!json.hasOwnProperty('targetFolderId')) return;

            const targetFolderId = json.targetFolderId;

            let processed = 0;
            let errorList = [];
            const progressBar = new ProgressBar('progress-move-files', {
                label: (1 === selection.length ? 'Moving file' : 'Moving files'),
                value: 100,
                showPercentage: true,
            });

            const divAlert = document.createElement('div');
            divAlert.id = 'move-errors';
            divAlert.className = 'alert alert-danger';
            divAlert.style.display = 'none';
            divAlert.attributes.role = 'alert';

            ajaxModal.getBodyElement().append(divAlert);
            ajaxModal.getBodyElement().append(progressBar.element());
            this.loading(true);

            Promise
                .allSettled(Array.from(selection).map(fileRow => {
                    return new Promise((resolve, reject) => {
                        this._post(`/file/${fileRow.dataset.id}/move`, {targetFolderId: targetFolderId})
                            .then((moveOk) => {
                                if (!moveOk.hasOwnProperty('success') || moveOk.success === false) return;
                                fileRow.closest('li').remove();
                                resolve();
                            })
                            .catch((moveError) => moveError.json().then((moveError) => {
                                errorList[moveError.error] = (errorList[moveError.error] || 0) + 1;

                                let content = '';
                                for (let e in errorList) { content += `<p>${e} : for ${errorList[e]} files</p>`;}

                                divAlert.style.display = 'block';
                                divAlert.innerHTML = content;

                                reject();
                            }))
                            .finally(() => {
                                progressBar
                                    .style('success')
                                    .progress(Math.round((++processed / selection.length) * 100))
                                    .status(`${processed} / ${selection.length}`);

                                const currentDivAlert = ajaxModal.getBodyElement().querySelector('div#move-errors');
                                if (currentDivAlert) ajaxModal.getBodyElement().replaceChild(divAlert, currentDivAlert);
                            });
                    });
                }))
                .then(() => this._selectFilesReset())
                .then(() => this.loading(false))
                .then(() => {
                    if (Object.keys(errorList).length === 0) setTimeout(() => { ajaxModal.close() }, 2000);
                })
            ;
        });
    }

    _onClickFolder(folder) {
        this.loading(true);

        this.getFolders().forEach((f) => f.classList.remove('active'));
        folder.classList.add('active');

        let folderItem = folder.closest('li');
        if (folderItem && folderItem.classList.contains('has-children')) {
            folderItem.classList.toggle('open');
        }

        this.#activeFolderId = folder.dataset.id;
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
        this.getFolders().forEach((f) => f.classList.remove('active'));
        this.#activeFolderId = null;
        this._getFiles().then(() => this.loading(false));
    }
    _onClickBreadcrumbItem(item) {
        let id = item.dataset.id;
        if (id) {
            let folder = this.#elements.listFolders.querySelector(`.media-lib-folder[data-id="${id}"]`);
            this._onClickFolder(folder);
        } else {
            this._onClickButtonHome();
        }
    }
    _onSearchInput(input, delay) {
        clearTimeout(this.#debounceTimer);
        this.#debounceTimer = setTimeout( () => {
            this.#searchValue = input.value;
            this._getFiles(0).then(() => this.loading(false));
        }, delay);
    }

    _getHeader(fileId = null) {
        let path = '/header';
        let query = new URLSearchParams();

        if (fileId) query.append('fileId', fileId);
        if (this.getSelectionFiles().length > 0) query.append('selectionFiles', this.getSelectionFiles().length.toString());
        if (this.#activeFolderId) query.append('folderId', this.#activeFolderId);
        if (this.#searchValue) query.append('search', this.#searchValue);

        if (query.size > 0) path = path + '?' + query.toString();

        return this._get(path).then((json) => {
            if (json.hasOwnProperty('header')) this._refreshHeader(json.header);
        });
    }
    _getFiles(from = 0) {
        if (0 === from) {
            this.#loadedFiles = 0;
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
            this.#elements.listFiles.innerHTML = '';
        }

        const query = new URLSearchParams({ from: from.toString() });
        if (this.#searchValue) query.append('search', this.#searchValue);
        const path = this.#activeFolderId ? `/files/${this.#activeFolderId}` : '/files';

        return this._get(`${path}?${query.toString()}`).then((files) => { this._appendFiles(files) });
    }
    _getFolders(openPath) {
        this.#elements.listFolders.innerHTML = '';
        return this._get('/folders').then((json) => {
            this._appendFolderItems(json);
            if (openPath) { this._openPath(openPath); }
        });
    }
    _openPath(path) {
        let currentPath = '';
        path.split('/').filter(f => f !== '').forEach((folderName) => {
            currentPath += `/${folderName}`;
            let parentFolder = document.querySelector(`.media-lib-folder[data-path="${currentPath}"]`);
            let parentLi = parentFolder ? parentFolder.closest('li') : null;

            if (parentLi && parentLi.classList.contains('has-children')) {
                parentLi.classList.add('open');
            }
        });

        if ('' !== currentPath) {
            let folder = document.querySelector(`.media-lib-folder[data-path="${currentPath}"]`);
            if (folder) this._onClickFolder(folder);
        }
    }

    _appendFiles(json) {
        if (json.hasOwnProperty('header')) {
            this._refreshHeader(json.header);
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
    _appendFolderItems(json) {
        this.#elements.listFolders.innerHTML = json.folders;

        this.getFolders().forEach((folder) => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((dragEvent) => {
                folder.addEventListener(dragEvent, (event) => this._onDragFolder(event));
            });
        });
    }
    _refreshHeader(html) {
        const searchBoxHasFocus = document.activeElement === this.getSearchBox();

        this.#elements.header.innerHTML = html;
        if (searchBoxHasFocus) {
            const searchBox = this.getSearchBox();
            searchBox.focus();
            let val = searchBox.value;
            searchBox.value = '';
            searchBox.value = val;
        }
    }

    _onDragUpload(event) {
        if (this.#dragFiles.length > 0) return;

        if ('dragend' === event.type) this.#dragCounter = 0;
        if ('dragover' === event.type) event.preventDefault();
        if ('dragenter' === event.type) {
            this.#dragCounter++;
            this.#elements.files.classList.add('media-lib-drop-area');
            this._selectFilesReset();
        }
        if ('dragleave' === event.type) {
            this.#dragCounter--;
            if (0 === this.#dragCounter) this.#elements.files.classList.remove('media-lib-drop-area');
        }
        if ('drop' === event.type) {
            event.preventDefault();
            this.#dragCounter = 0;
            this.#elements.files.classList.remove('media-lib-drop-area');

            const files = event.target.files || event.dataTransfer.files;
            this._uploadFiles(Array.from(files));
        }
    }
    _onDragFolder(event) {
        if (this.#dragFiles.length === 0) return;
        if (event.target.dataset.id === this.#activeFolderId) return;

        if ('dragover' === event.type) event.preventDefault();
        if ('dragenter' === event.type) {
            this.getFolders().forEach((f) => f.classList.remove('media-lib-drop-area'));
            event.target.classList.add('media-lib-drop-area');
        }
        if ('dragleave' === event.type) {
            event.target.classList.remove('media-lib-drop-area');
        }
        if ('drop' === event.type) {
            event.preventDefault();
            event.target.classList.remove('media-lib-drop-area');
            const folderId = event.target.dataset.id;
            const moveButton = this.#elements.header.querySelector('.btn-files-move');

            this._onClickButtonFilesMove(moveButton, folderId);
        }
    }
    _onDragFile(event) {
        if (event.type === 'dragstart') {
            this.#dragFiles = this.getSelectionFiles();
        }
        if (event.type === 'dragend') {
            this.#dragFiles = [];
            this._selectFilesReset();
        }
    }

    _uploadFiles(files) {
        this.loading(true);

        Promise
            .allSettled(files.map((file) => this._uploadFile(file)))
            .then(() => this._getFiles().then(() => this.loading(false)));
    }
    _uploadFile(file) {
        return new Promise((resolve, reject) => {
            const id = Date.now();
            let liUpload = document.createElement('li');
            liUpload.id = `upload-${id}`;

            const uploadDiv = document.createElement('div');
            uploadDiv.className = 'upload-file';

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'close-button';
            closeButton.addEventListener('click', () => {
                this.#elements.listUploads.removeChild(liUpload);
                liUpload = false;
                reject();
            });

            const closeIcon = document.createElement('i');
            closeIcon.className = 'fa fa-times';
            closeIcon.setAttribute('aria-hidden', 'true');
            closeButton.appendChild(closeIcon);

            const progressBar = new ProgressBar(`progress-${id}`, {'label': file.name, value: 5});

            uploadDiv.append(progressBar.style('success').element());
            uploadDiv.append(closeButton);

            liUpload.appendChild(uploadDiv);
            this.#elements.listUploads.appendChild(liUpload);

            this._getFileHash(file, progressBar)
                .then((fileHash) => this._createFile(file, fileHash))
                .then(() => {
                    progressBar.status('Finished');
                    setTimeout(() => {
                        this.#elements.listUploads.removeChild(liUpload);
                        resolve();
                    }, 1000);
                })
                .catch((error) => {
                    uploadDiv.classList.add('upload-error');
                    progressBar.status(error.message).style('danger').progress(100);
                    setTimeout(() => {
                        if (liUpload === false) return;
                        this.#elements.listUploads.removeChild(liUpload);
                        reject();
                    }, 3000);
                });
        });
    }
    async _createFile(file, fileHash) {
        const formData = new FormData();
        formData.append('name', file.name);
        formData.append('filesize', file.size);
        formData.append('fileMimetype', file.type);
        formData.append('fileHash', fileHash);

        const path = this.#activeFolderId ? `/add-file/${this.#activeFolderId}` : '/add-file';
        await this._post(path, formData, true)
            .catch((response) => response.json().then((json) => {
                throw new Error(json.error);
            }));
    }
    async _getFileHash(file, progressBar) {
        const hash = await new Promise((resolve, reject) => {
            let fileHash = null;
            new FileUploader({
                file: file,
                algo: this.#options.hashAlgo,
                initUrl: this.#options.urlInitUpload,
                onHashAvailable: function (hash) {
                    progressBar.status('Hash available').progress(0);
                    fileHash = hash;
                },
                onProgress: function (status, progress, remaining) {
                    if (status === 'Computing hash') {
                        progressBar.status('Calculating ...').progress(remaining);
                    }
                    if (status === 'Uploading') {
                        progressBar.status('Uploading: ' + remaining).progress(Math.round(progress * 100));
                    }
                },
                onUploaded: function () {
                    progressBar.status('Uploaded').progress(100);
                    resolve(fileHash);
                },
                onError: (message) => reject(message)
            });
        });

        if (typeof hash !== 'string') throw new Error('Invalid hash');

        return hash;
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

    _selectFile(item) {
        item.classList.add('active');
        item.draggable = true;
        ['dragstart', 'dragend'].forEach((dragEvent) => {
            item.addEventListener(dragEvent, (event) => this._onDragFile(event));
        });
    }
    _selectFiles(item, event) {
        if (event.shiftKey && this.#selectionLastFile !== null) {
            let files = this.#elements.listFiles.querySelectorAll('.media-lib-file');
            let start = Array.from(files).indexOf(item);
            let end = Array.from(files).indexOf(this.#selectionLastFile);
            if (start > end) [start, end] = [end, start];

            files.forEach((f, index) => {
                if (index >= start && index <= end) this._selectFile(f);
            });
        } else if (event.ctrlKey) {
            this._selectFile(item);
        } else {
            this._selectFilesReset(false);
            this._selectFile(item);
        }

        this.#selectionLastFile = item;

        return this.getSelectionFiles();
    }
    _selectFilesReset(refreshHeader = true) {
        if (true === refreshHeader) this._refreshHeader(this.#activeFolderHeader);
        this.getSelectionFiles().forEach((file) => {
            file.classList.remove('active');
            file.draggable = false;
            ['dragstart', 'dragend'].forEach((dragEvent) => {
                file.removeEventListener(dragEvent, (event) => this._onDragFile(event));
            });
        });
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
    async _post(path, data = {}, isFormData = false) {
        this.loading(true);
        let options = {};

        if (isFormData) {
            options = { method: "POST", body: data }
        } else {
            options = {
                method: "POST",
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            }
        }

        const response = await fetch(`${this.#pathPrefix}${path}`, options);

        return response.ok ? response.json() : Promise.reject(response);
    }
}
