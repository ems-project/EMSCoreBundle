import ajaxModal from "./../helper/ajaxModal";

import { ajaxJsonGet } from "./../helper/ajax";

export default class RevisionTask {
    constructor() {
        this.taskTab = document.querySelector('#tab_tasks');
        this.taskList = document.querySelector('ul#revision-tasks');
        this.tasksEmpty = this.taskTab.querySelector('#revision-tasks-empty');
        this.tasksInfo = this.taskTab.querySelector('#revision-tasks-info');

        if (this.taskList !== null) {
            this.modalCreate();

            if ('true' === this.taskList.dataset.load) {
                this.loadTasks();
            } else {
                this.tasksEmpty.style.display = 'block';
            }
        }
    }

    loadTasks() {
        var url = this.taskList.dataset.url;
        var loading = this.taskList.querySelector('#task-loading');
        loading.style.display = 'block';

        this.tasksEmpty.style.display = 'none';
        this.taskList.querySelectorAll('.task-item').forEach((e) => {
            e.remove();
        });

        ajaxJsonGet(url, (json, request) => {
            if (200 !== request.status) {
                return;
            }

            loading.style.display = 'none';

            var hasTasks = false;
            var tasks = json.hasOwnProperty('tasks') ? json.tasks : [];
            tasks.forEach((task) => {
                this.taskList.insertAdjacentHTML('beforeend', task.html);
                hasTasks = true;
            });

            if (hasTasks) {
                this.tasksInfo.style.display = 'block';
                this.modalEdit();
            } else {
                this.tasksEmpty.style.display = 'block';
                this.tasksInfo.style.display = 'none';
            }
        });
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
}