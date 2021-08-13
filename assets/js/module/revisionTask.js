import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonGet, ajaxJsonSubmit} from "./../helper/ajax";

export default class RevisionTask {
    constructor() {
        this.dashboard();

        this.taskTab = document.querySelector('#tab_task');
        if (this.taskTab !== null) {
            this.loadTask();
        }

        this.tasksTab = document.querySelector('#tab_tasks');
        if (this.tasksTab !== null) {
            this.tasksList = document.querySelector('ul#revision-tasks');
            this.tasksListApproved = document.querySelector('ul#revision-tasks-approved');
            this.tasksEmpty = this.tasksTab.querySelector('#revision-tasks-empty');
            this.tasksInfo = this.tasksTab.querySelector('#revision-tasks-info');
            this.tasksApprovedLink = this.tasksTab.querySelector('#revision-tasks-approved-link');

            if ('true' === this.tasksList.dataset.load) {
                this.loadTasks();
            } else {
                this.tasksEmpty.style.display = 'block';
                this.btnTaskCreateModal();
            }
        }
    }

    dashboard() {
        document.addEventListener('click', (e) => {
            if (!e.target.classList.contains('task-modal')) { return; }
            ajaxModal.load({ url: e.target.dataset.url, title: e.target.dataset.title});
        });
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
    loadTasks() {
        this.tasksClear();
        ajaxJsonGet(this.tasksList.dataset.url, this.callbackGetTasks());
    }
    loadTasksApproved() {
        var loading = this.tasksListApproved.querySelector('.task-loading');
        loading.style.display = 'block'

        ajaxJsonGet(this.tasksListApproved.dataset.url, (json, request) => {
            if (200 !== request.status) { return; }
            loading.style.display = 'none';

            var tasks = json.hasOwnProperty('tasks') ? json.tasks : [];
            tasks.forEach((task) => {
                this.tasksListApproved.insertAdjacentHTML('beforeend', task.html);
            });
            this.tasksListApproved.querySelectorAll('.tasks-item').forEach((item) => {
                item.onclick = () => { ajaxModal.load({ url: item.dataset.url, title: item.dataset.title}); }
            })
        });
    }
    callbackGetTasks() {
        return (json, request) => {
            if (200 !== request.status) { return; }

            this.tasksList.querySelector('.task-loading').style.display = 'none';

            var hasTasks = false;
            var tasks = json.hasOwnProperty('tasks') ? json.tasks : [];
            tasks.forEach((task) => {
                this.tasksList.insertAdjacentHTML('beforeend', task.html);
                hasTasks = true;
            });

            if (hasTasks) {
                this.tasksInfo.style.display = 'block';
                this.btnTaskUpdateModal();
                this.btnTaskValidation();
            } else {
                this.tasksEmpty.style.display = 'block';
                this.tasksInfo.style.display = 'none';
            }

            this.btnTaskCreateModal();
            if (json.hasOwnProperty('tasks_approved_link')) {
                this.tasksApprovedLink.innerHTML = json.tasks_approved_link;
                this.btnTasksApproved();
            }
        }
    }
    tasksClear() {
        this.tasksList.querySelector('.task-loading').style.display = 'block';
        this.tasksTab.querySelector('#btn-task-create-modal').setAttribute('disabled','disabled');
        this.tasksEmpty.style.display = 'none';
        this.tasksApprovedLink.innerHTML = '';
        this.tasksList.querySelectorAll('.tasks-item').forEach((e) => { e.remove();});
        this.tasksListApproved.querySelectorAll('.tasks-item').forEach((e) => { e.remove();});
    }
    btnTaskCreateModal() {
        var button = document.querySelector('#btn-task-create-modal');
        button.onclick = (event) => {
            event.preventDefault();
            ajaxModal.load(
                { url: button.dataset.url, title: button.dataset.title},
                modalCreateCallback
            );
        }
        button.style.display = 'block';
        button.removeAttribute('disabled');

        var modalCreateCallback = (json, request) => {
            var success = json.hasOwnProperty('modalSuccess') ? json.modalSuccess : false;
            if (success) {
                this.loadTasks();
            }
        };
    }
    btnTaskUpdateModal() {
        var buttons = document.getElementsByClassName("btn-task-update-modal");
        Array.from(buttons).forEach((buttonModalEdit) => {
            buttonModalEdit.addEventListener('click', () => {
                ajaxModal.load(
                    { url: buttonModalEdit.dataset.url, title: buttonModalEdit.dataset.title  },
                    modalEditCallback
                )
            });
        });

        var modalEditCallback = (json, request) => {
            this.btnTaskDelete();
            var success = json.hasOwnProperty('modalSuccess') ? json.modalSuccess : false;
            if (success) {
                this.loadTasks();
            }
        }
    }
    btnTaskDelete() {
        var button = ajaxModal.modal.querySelector('#btn-task-delete');
        if (button) {
            button.addEventListener('click', () => { ajaxModal.postRequest(button.dataset.url); });
        }
    }
    btnTaskValidation() {
        var sendValidation = (action) => {
            return (event) => {
                event.preventDefault();
                var formElement = this.tasksTab.querySelector('form[name="validation"]');
                var formData = new FormData(formElement);
                formData.append('action', action);
                var submitData = Array.from(formData, e => e.map(encodeURIComponent).join('=')).join('&');

                this.tasksClear();
                ajaxJsonSubmit(this.tasksList.dataset.url, submitData, this.callbackGetTasks());
            }
        };

        var btnApprove = this.tasksTab.querySelector('#btn-task-validation-approve');
        if (btnApprove) { btnApprove.onclick = sendValidation('approve'); }
        var btnReject = this.tasksTab.querySelector('#btn-task-validation-reject');
        if (btnReject) { btnReject.onclick = sendValidation('reject'); }
    }
    btnTasksApproved() {
        var button = document.querySelector('#btn-tasks-approved');
        if (!button) { return; }

        button.onclick = (event) => {
            event.preventDefault();
            var btnText = button.textContent;
            var toggleText = button.dataset.toggleText;
            button.dataset.toggle = button.dataset.toggle == 'false';
            button.dataset.toggleText = btnText;
            button.innerHTML = toggleText;

            if (button.dataset.toggle == 'true') {
                this.loadTasksApproved();
            } else {
                this.tasksListApproved.querySelectorAll('.tasks-item').forEach((e) => { e.remove();});
            }
        }
    }
}