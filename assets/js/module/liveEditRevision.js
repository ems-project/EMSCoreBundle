import {ajaxJsonPost} from "./../helper/ajax";

export default class LiveEditRevision {

    constructor(target) {
        const self = this;
        this.target = target;
        this.revision = this.target.getAttribute('data-emsco-edit-revision');
        this.url = this.target.getAttribute('data-emsco-edit-revision-url');

        target.addEventListener('click', function (event) {
            self.onClick(this);
        });
    }

   onClick(button) {
        const self = this;
        var parent = button.closest('html');
        const tagFields = parent.querySelectorAll('div[data-emsco-edit-emsid="' + this.revision +'"]');
        var fields = [];
        [].forEach.call(tagFields, function(tagField) {
            fields.push(tagField.getAttribute('data-emsco-edit-field'));
        });

        ajaxJsonPost(this.url, JSON.stringify({'_data': { emsId: this.revision, fields: fields}}), (json, request) => {
            if (200 === request.status) {
                const obj = JSON.parse(json.data);
                console.log(obj);
                if (obj.isEditable) {
                    [].forEach.call(fields, function(field) {
                        if (obj.forms[field] != undefined) {
                           var items = parent.querySelectorAll('div[data-emsco-edit-emsId="' + obj.emsId +'"][data-emsco-edit-field="' + field +'"]');
                           [].forEach.call(items, function(item) {
                                item.setAttribute('data-emsco-value', item.innerHTML);
                                item.innerHTML = obj.forms[field]
                            });
                        }
                    });
                    button.classList.add('hidden');
                    button.insertAdjacentHTML('afterend', obj.buttons);

                    const cancelButtons = parent.querySelectorAll('button[data-emsco-edit-revision-cancel-url][data-emsco-edit-revision="' + obj.emsId +'"]');
                    [].forEach.call(cancelButtons, function(cancel) {
                        cancel.addEventListener('click', function (event) {
                            self.onClickDiscardDraft(cancel);
                        });
                    });

                    const saveButtons = parent.querySelectorAll('button[data-emsco-edit-revision-save-url][data-emsco-edit-revision="' + obj.emsId +'"]');
                    [].forEach.call(saveButtons, function(cancel) {
                        cancel.addEventListener('click', function (event) {
                            self.onClickSaveDraft(cancel);
                        });
                    });

                } else {
                    alert('No fields are editable for this node');
                }
            }
        });
    }

    onClickDiscardDraft(button) {
        var parent = button.closest('html');
        const tagFields = parent.querySelectorAll('div[data-emsco-edit-emsid="' + button.getAttribute('data-emsco-edit-revision') +'"]');
        [].forEach.call(tagFields, function(tagField) {
            tagField.innerHTML = tagField.getAttribute('data-emsco-value');
        });

        const buttons = parent.querySelectorAll('button[data-emsco-edit-revision="' + button.getAttribute('data-emsco-edit-revision') +'"]');
        [].forEach.call(buttons, function(item) {
            if(item.classList.contains('edit-revision') && item.classList.contains('hidden')) {
                item.classList.remove('hidden');
            }
            if(item.classList.contains('js-save-row') || item.classList.contains('js-cancel-row')) {
                item.remove();
            }
        });

        const httpRequest = new XMLHttpRequest();
        httpRequest.open('POST', button.getAttribute('data-emsco-edit-revision-cancel-url'), true);
        httpRequest.send();
    }

    onClickSaveDraft(button) {
        var parent = button.closest('html');
        const tagFields = parent.querySelectorAll('div[data-emsco-edit-emsid="' + button.getAttribute('data-emsco-edit-revision') +'"][data-emsco-value]');
        var fields = new Map();
        [].forEach.call(tagFields, function(tagField) {
            if (tagField.querySelector('input').type == 'checkbox'){
                fields.set( tagField.getAttribute('data-emsco-edit-field'), tagField.querySelector('input').checked ? true : false);
            } else {
                fields.set( tagField.getAttribute('data-emsco-edit-field'), tagField.querySelector('input').value);
            }
        });
        console.log(fields);
        const buttons = parent.querySelectorAll('button[data-emsco-edit-revision="' + button.getAttribute('data-emsco-edit-revision') +'"]');
     /*   [].forEach.call(buttons, function(item) {
            if(item.classList.contains('edit-revision') && item.classList.contains('hidden')) {
                item.classList.remove('hidden');
            }
            if(item.classList.contains('js-save-row') || item.classList.contains('js-cancel-row')) {
                item.remove();
            }
        });*/

        /*ajaxJsonPost( button.getAttribute('data-emsco-edit-revision-save-url'), JSON.stringify({'_data': { emsId: this.revision, fields: fields}}), (json, request) => {

        });*/
    }
}