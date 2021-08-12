import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonGet, ajaxJsonSubmit} from "./../helper/ajax";

export default class RevisionTask {
    constructor() {
        this.taskTab = document.querySelector('#tab_task');
        if (this.taskTab !== null) {
            this.loadTask();
        }

        this.tasksTab = document.querySelector('#tab_tasks');
        if (this.tasksTab !== null) {
            this.tasksList = document.querySelector('ul#revision-tasks');
            this.tasksUrl = this.tasksList.dataset.url;
            this.tasksListLoading = this.tasksList.querySelector('li.task-loading');
            this.tasksEmpty = this.tasksTab.querySelector('#revision-tasks-empty');
            this.tasksInfo = this.tasksTab.querySelector('#revision-tasks-info');

            this.modalCreate();

            if ('true' === this.tasksList.dataset.load) {
                this.loadTasks();
            } else {
                this.tasksEmpty.style.display = 'block';
            }
        }
    }

    loadTask() {
        var revisionTask = this.taskTab.querySelector('#revision-task');

        var url = revisionTask.dataset.url;
        var loading = this.taskTab.querySelector('.task-loading');
        loading.style.display = 'block';

        var callbackRequest = (json, request) => {
            if (200 !== request.status) { return; }

            loading.style.display = 'none';
            if (json.hasOwnProperty('html')) { revisionTask.innerHTML = json.html; }

            var buttonRequestValidation = this.taskTab.querySelector('#btn-validation-request');
            if (buttonRequestValidation) {
                buttonRequestValidation.onclick = (event) => {
                    event.preventDefault();
                    var formData = $('form[name="validation-request"]').serialize();
                    revisionTask.innerHTML = '';
                    loading.style.display = 'block';
                    ajaxJsonSubmit(url, formData, callbackRequest);
                }
            }
            this.taskTab.querySelectorAll('.btn-modal-history-task').forEach((btn) => {
                btn.onclick = (event) => {
                    event.preventDefault();
                    ajaxModal.load({ url: btn.dataset.url, title: btn.dataset.title});
                }
            });
        };

        ajaxJsonGet(url, callbackRequest);
    }

    clearTasks() {
        this.tasksListLoading.style.display = 'block';
        this.tasksEmpty.style.display = 'none';
        this.tasksList.querySelectorAll('.task-item').forEach((e) => {
            e.remove();
        });
    }

    loadTasks() {
        this.clearTasks();
        ajaxJsonGet(this.tasksUrl, this.callbackGetTasks());
    }

    callbackGetTasks() {
        return (json, request) => {
            if (200 !== request.status) { return; }

            this.tasksListLoading.style.display = 'none';

            var hasTasks = false;
            var tasks = json.hasOwnProperty('tasks') ? json.tasks : [];
            tasks.forEach((task) => {
                this.tasksList.insertAdjacentHTML('beforeend', task.html);
                hasTasks = true;
            });

            if (hasTasks) {
                this.tasksInfo.style.display = 'block';
                this.modalEdit();
                this.validationForm();
            } else {
                this.tasksEmpty.style.display = 'block';
                this.tasksInfo.style.display = 'none';
            }
        }
    }

    modalCreate() {
        var modalCreateCallback = (json, request) => {
            var success = json.hasOwnProperty('modalSuccess') ? json.modalSuccess : false;
            if (success) {
                this.loadTasks();
            }
        };

        var buttonModalCreate = document.querySelector('#btn-modal-create-task');
        buttonModalCreate.onclick = (event) => {
            event.preventDefault();
            ajaxModal.load(
                { url: buttonModalCreate.dataset.url, title: buttonModalCreate.dataset.title},
                modalCreateCallback
            );
        }
    }
    modalEdit() {
        var modalEditCallback = (json, request) => {
            var btnDelete = ajaxModal.modal.querySelector('#btn-delete-task');
            if (btnDelete) {
                btnDelete.addEventListener('click', () => {
                    ajaxModal.postRequest(btnDelete.dataset.url);
                });
            }

            var success = json.hasOwnProperty('modalSuccess') ? json.modalSuccess : false;
            if (success) {
                this.loadTasks();
            }
        }

        var modalEditButtons = document.getElementsByClassName("btn-modal-edit-task");
        Array.from(modalEditButtons).forEach((buttonModalEdit) => {
            buttonModalEdit.addEventListener('click', () => {
                ajaxModal.load(
                    { url: buttonModalEdit.dataset.url, title: buttonModalEdit.dataset.title  },
                    modalEditCallback
                )
            });
        });
    }
    validationForm() {
        var sendValidation = (action) => {
            return (event) => {
                event.preventDefault();
                var formElement = this.tasksTab.querySelector('form[name="validation"]');
                var formData = new FormData(formElement);
                formData.append('action', action);
                var submitData = Array.from(formData, e => e.map(encodeURIComponent).join('=')).join('&');
                this.clearTasks();
                ajaxJsonSubmit(this.tasksUrl, submitData, this.callbackGetTasks());
            }
        };

        var buttonApproveValidation = this.tasksTab.querySelector('#btn-validation-approve');
        if (buttonApproveValidation) {
            buttonApproveValidation.onclick = sendValidation('approve');
        }
        var buttonRejectValidation = this.tasksTab.querySelector('#btn-validation-reject');
        if (buttonRejectValidation) {
            buttonRejectValidation.onclick = sendValidation('reject');
        }
    }
}