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
        const tagFields = parent.querySelectorAll('div[data-emsco-edit-emslink="' + this.revision +'"]');
        var fields = [];
        [].forEach.call(tagFields, function(tagField) {
            fields.push(tagField.getAttribute('data-emsco-edit-field'));
        });

        ajaxJsonPost(this.url, JSON.stringify({'_data': { emsLink: this.revision, fields: fields}}), (json, request) => {
            if (200 === request.status) {
                console.log(json);
                // @TODO Json if editable = true => replace <div> by corresponding <form>

            }
        });
    }



}