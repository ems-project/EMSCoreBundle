import ajaxModal from "./../helper/ajaxModal";
import Sortable from 'sortablejs';

export default class RevisionTask {
    constructor() {
        this.dashboard();

        this.tasksTab = document.querySelector('#tab_tasks');
        if (this.tasksTab !== null) {
            this.revisionTasks = document.querySelector('div#revision-tasks');
            this.revisionTaskLoading = this.revisionTasks.querySelector('div#revision-tasks-loading');
            this.revisionTasksContent = this.revisionTasks.querySelector('div#revision-tasks-content');
            this._addClickListeners();
            this.loadTasks();
        }
    }
    dashboard() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('task-modal')) {
                e.preventDefault();
                ajaxModal.load({ url: e.target.dataset.url, title: e.target.dataset.title});
            }
        });
    }
    loadTasks() {
        fetch(this.revisionTasks.dataset.url, {
            method: "GET",
            headers: { 'Content-Type': 'application/json'},
        })
            .then((response) => { return response.json(); })
            .then((json) => {
                if (json.hasOwnProperty('tab')) this._updateTab(json.tab)
            });
    }

    _updateTab(tab) {
        this.revisionTasksContent.outerHTML = tab;
        this.revisionTasksContent = this.revisionTasks.querySelector('div#revision-tasks-content');
        this.revisionTaskLoading.style.display = 'none';
        this.revisionTasksContent.style.display = 'block';
    }
    _addClickListeners() {
        this.tasksTab.addEventListener('click', (event) => {
            const target = event.target;
            const classList = target.classList;

            if (classList.contains('btn-task-modal')) this._onClickButtonTaskCreateOrUpdate(target);
            if (classList.contains('btn-task-handle')) this._onClickButtonHandle(target);
            if (classList.contains('tasks-item-view')) this._onClickTaskItem(event, target);
            if (target.id === 'btn-tasks-reorder') this._onClickButtonTaskReorder(target);
            if (target.id === 'btn-tasks-approved') this._onClickButtonTasksApproved(event, target);

        }, true);
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.id === 'btn-task-delete') this._onClickButtonTaskDelete(target);
        });
    }
    _onClickButtonTaskCreateOrUpdate(button) {
        ajaxModal.load({ url: button.dataset.url, title: button.dataset.title}, (json) => {
            if (json.hasOwnProperty('modalSuccess') && json.modalSuccess) {
                this.loadTasks();
            }
        }, () => location.reload());
    }
    _onClickButtonTaskDelete(button) {
        ajaxModal.load({ url: button.dataset.url, title: button.dataset.title}, (json) => {
            if (json.hasOwnProperty('modalSuccess') && json.modalSuccess) {
                this.loadTasks();
            }
        }, () => location.reload());
    }
    _onClickButtonHandle(button) {
        let formData = new FormData(this.tasksTab.querySelector("form"));
        formData.set('handle', button.dataset.type);

        fetch(this.revisionTasks.dataset.url, {method: "POST", body: formData})
            .then((response) => response.ok ? response.json() : Promise.reject(response))
            .then((json) => {
                if (json.hasOwnProperty('success') && json.success) this.loadTasks();
                if (json.hasOwnProperty('tab')) this._updateTab(json.tab);
            })
            .catch(() => location.reload());
    }
    _onClickButtonTaskReorder(button) {
        this.tasksTab.classList.add('reorder');
        button.style.display = 'none';

        const btnReorderCancel = this.tasksTab.querySelector('#btn-tasks-reorder-cancel');
        const btnReorderSave = this.tasksTab.querySelector('#btn-tasks-reorder-save');

        btnReorderSave.style.display = 'inline-block';
        btnReorderCancel.style.display = 'inline-block';
        btnReorderCancel.removeAttribute('disabled');

        const tasksPlannedList = this.tasksTab.querySelector('ul#revision-tasks-planned-list');
        tasksPlannedList.querySelectorAll('.tasks-item').item(0).classList.remove('tasks-item-current');

        Sortable.create(tasksPlannedList, {
            fallbackTolerance: 3,
            animation: 150,
            ghostClass: 'dragging',
        });

        const finishReorder = () => {
            this.loadTasks();
            this.tasksTab.classList.remove('reorder');
        }

        btnReorderCancel.onclick = () => finishReorder();
        btnReorderSave.onclick = () => {
            let taskIds = [];
            tasksPlannedList.querySelectorAll('.tasks-item').forEach((item) => {
                taskIds.push(item.dataset.id);
            });
            fetch(btnReorderSave.dataset.url, {
                method: "POST",
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({taskIds: taskIds})
            })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then(() => finishReorder())
                .catch(() => location.reload());
        }
    }
    _onClickButtonTasksApproved(event, button) {
        event.preventDefault();
        const btnText = button.textContent;
        const toggleText = button.dataset.toggleText;
        const list = this.tasksTab.querySelector('ul#revision-tasks-approved');

        button.dataset.toggleText = btnText;
        button.innerHTML = toggleText;
        if (button.dataset.toggle === 'true') {
            list.style.display = 'block';
            button.dataset.toggle = 'false';
        } else {
            list.style.display = 'none';
            button.dataset.toggle = 'true';
        }
    }
    _onClickTaskItem(event, target) {
        event.preventDefault();
        ajaxModal.load({ url: target.dataset.url, title: target.dataset.title});
    }
}
