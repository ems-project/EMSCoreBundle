import {addEventListeners as editRevisionAddEventListeners} from "../edit-revision";
import coreModal from "./coreModal";

export default class RevisionTask {
    constructor() {
        var addBtn = document.querySelector('#btn-new-task');

        var modalXhrCallback = () => {
            coreModal.$modal.find('#btn-create').on('click', () => {
                coreModal.submitForm({ url: addBtn.dataset.url });
            });
        };

        addBtn.onclick = (event) => {
            event.preventDefault();
            coreModal.load(
                { url: addBtn.dataset.url, title: addBtn.dataset.title},
                modalXhrCallback
            );
        }


    }
}