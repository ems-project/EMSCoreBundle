import {observeDom} from "../helper/observeDom";
import {ajaxJsonGet, ajaxJsonPost, ajaxJsonSubmit} from "./../helper/ajax";

export default class LiveEditRevision {
    constructor(target) {
        const buttons = target.querySelectorAll('button[data-emsco-edit-revision]');
        const self = this;
        this.loadOnClick(buttons);
        observeDom(target.documentElement, function(mutations) {
            self.observeDom(mutations);
        });
    }

    loadOnClick(buttons) {
        const self = this;
        [].forEach.call(buttons, function(button) {
            //  @TODO something => EventListener are duplicate
            button.addEventListener('click', function(event) {
                self.onClick(button);
            });
        });
    }

    onClick(button) {
        // @TODO => take TR parent and see data-emsco-edit-field exist ....
    }

    observeDom(mutationList) {
        const self = this;
        [].forEach.call(mutationList, function(mutation) {
            if(mutation.addedNodes.length < 1) {
                return;
            }
            const buttons = mutation.target.querySelectorAll('button[data-emsco-edit-revision]');
            self.loadOnClick(buttons);
        });
    }
}