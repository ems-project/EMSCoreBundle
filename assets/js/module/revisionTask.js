import ajaxModal from "./../helper/ajaxModal";

import { ajaxJsonGet } from "./../helper/ajax";

export default class RevisionTask {
    constructor() {
        this.taskList = document.querySelector('ul#revision-tasks');

        if (this.taskList !== null) {
            this.modalCreate();

            if (true === Boolean(this.taskList.dataset.load)) {
                this.loadTasks();
            }
        }
    }

    loadTasks() {
        var url = this.taskList.dataset.url;

        ajaxJsonGet(url, (json, request) => {
            if (200 !== request.status) {
                return;
            }

            var tasks = json.hasOwnProperty('tasks') ? json.tasks : [];
            tasks.forEach((task) => {
                this.taskList.insertAdjacentHTML('beforeend', task.html);
            });

            this.modalEdit();
        });
    }

    modalCreate() {
        var modalCreateCallback = (json, request) => {
            // var btnCreate = ajaxModal.modal.querySelector('#btn-create-task');
            // if (btnCreate) {
            //     btnCreate.addEventListener('click', () => {
            //         ajaxModal.submitForm({ url: request.responseURL });
            //     });
            // }
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
            // var btnUpdate = ajaxModal.modal.querySelector('#btn-update-task');
            // if (btnUpdate) {
            //     btnCreate.addEventListener('click', () => {
            //         ajaxModal.submitForm({ url: request.responseURL });
            //     });
            // }
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