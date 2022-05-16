import {observeDom} from "../helper/observeDom";
import {ajaxJsonGet, ajaxJsonPost, ajaxJsonSubmit} from "./../helper/ajax";

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
                    // @TODO Json if editable = true => replace <button> by corresponding buttons
                } else {
                    alert('No fields are editable for this node');
                }


            }
        });
    }



}