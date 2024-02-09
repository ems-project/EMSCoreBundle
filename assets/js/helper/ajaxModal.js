import {editRevisionEventListeners} from "../editRevisionEventListeners";
import {tooltipDataLinks} from "./tooltip";
import Datatables from "../module/datatables";

class AjaxModal {
    constructor(selector) {
        this.selector = selector;
        this.$modal = $(selector);

        this.modal = document.querySelector(this.selector);
        if (this.modal) {
            this.loadingElement = this.modal.querySelector('.modal-loading');
            $(document).on('hide.bs.modal', '.core-modal', (e) => {
                if (e.target.id === this.modal.id) {
                    this.reset();
                }
                e.target.dispatchEvent(new Event('ajax-modal-close'));
            });
        }

        this.onKeyDown = (event) => {
            if (event.key !== 'Enter' || event.shiftKey) return

            let btnAjaxSubmit = this.modal.querySelector('#ajax-modal-submit');
            let blockTargetElements = ['textarea', 'input', 'select', 'button', 'a'];

            if (btnAjaxSubmit
                && !Array.from(blockTargetElements).includes(event.target.nodeName.toLowerCase())
                && !event.target.classList.contains('select2-selection')
            ) {
                event.preventDefault();
                btnAjaxSubmit.click();
            }
        };
    }

    getBodyElement() {
        return this.modal.querySelector('.ajax-modal-body');
    }

    close() {
        this.$modal.modal('hide');
    }

    reset() {
        this.loadingElement.style.display = 'block';
        document.removeEventListener('keydown', this.onKeyDown);

        this.$modal.find('.ckeditor_ems').each(function () {
            if (CKEDITOR.instances.hasOwnProperty($(this).attr('id'))) {
                CKEDITOR.instances[$(this).attr('id')].destroy();
            }
        });

        this.modal.querySelector('.modal-title').innerHTML = '';
        this.modal.querySelector('.ajax-modal-body').innerHTML = '';
        this.modal.querySelector('.ajax-modal-body').style.display = 'none';
        this.modal.querySelector('.ajax-modal-footer').innerHTML = '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>';
    }
    stateLoading() {
        this.modal
            .querySelectorAll('.ajax-modal-body > div.alert')
            .forEach((e) => {e.remove();});
        this.loadingElement.style.display = 'block';

        this.modal
            .querySelectorAll('input, button, .select2, textarea')
            .forEach((e) => {
                e.classList.add('emsco-modal-has-been-disabled');
                e.setAttribute("disabled","disabled");
            });
    }
    stateReady() {
        this.loadingElement.style.display = 'none';
        this.modal.querySelector('.ajax-modal-body').style.display = 'block';

        this.modal
            .querySelectorAll('input.emsco-modal-has-been-disabled, button.emsco-modal-has-been-disabled, .select2.emsco-modal-has-been-disabled, textarea.emsco-modal-has-been-disabled')
            .forEach((e) => {
                e.removeAttribute("disabled");
                e.classList.remove('emsco-modal-has-been-disabled');
            });
    }

    load(options, callback)
    {
        let dialog = this.modal.querySelector('.modal-dialog');
        dialog.classList.remove('modal-xs', 'modal-sm', 'modal-md', 'modal-lg');
        if (options.hasOwnProperty('size')) {
            dialog.classList.add('modal-'+options.size);
        } else {
            dialog.classList.add('modal-md');
        }

        this.stateLoading();
        if (options.hasOwnProperty('title')) {
            this.modal.querySelector('.modal-title').innerHTML = options.title;
        }
        this.$modal.modal('show');

        let fetchOptions = { method: 'GET', headers: { 'Content-Type': 'application/json'}} ;
        if (options.hasOwnProperty('data')) {
            fetchOptions.method = 'POST';
            fetchOptions.body = options.data;
        }

        fetch(options.url, fetchOptions).then((response) => {
            return response.ok ? response.json().then((json) => {
                this.ajaxReady(json, response.url, callback);
                this.stateReady();
            }) : Promise.reject(response);
        }).catch((e) => {
            this.printMessage('error', 'Error loading ...');
            throw e
        });
    }

    submitForm(url, callback)
    {
        for (let i in CKEDITOR.instances) {
            if(CKEDITOR.instances.hasOwnProperty(i)) { CKEDITOR.instances[i].updateElement(); }
        }

        let formData = new FormData(this.modal.querySelector("form")); //before disabling form
        this.stateLoading();

        fetch(url, { method: 'POST', body: formData}).then((response) => {
            return response.ok ? response.json().then((json) => {
                this.ajaxReady(json, response.url, callback);
                this.stateReady();
            }) : Promise.reject(response);
        }).catch((e) => {
            this.printMessage('error', 'Error loading ...');
            throw e
        });
    }

    ajaxReady(json, url, callback) {
        if (json.hasOwnProperty('modalClose') && json.modalClose === true) {
            if (typeof callback === 'function') { callback(json, this.modal); }
            this.$modal.modal('hide');
            return;
        }

        if (json.hasOwnProperty('modalTitle')) {
            this.$modal.find('.modal-title').html(json.modalTitle);
        }
        if (json.hasOwnProperty('modalBody')) {
            this.$modal.find('.ajax-modal-body').html(json.modalBody);
            this.$modal.find(':input').each(function () {
                $(this).addClass('ignore-ems-update');
            });
            editRevisionEventListeners(this.$modal);
        }
        if (json.hasOwnProperty('modalFooter')) {
            this.$modal.find('.ajax-modal-footer').html(json.modalFooter);
        } else {
            this.$modal.find('.ajax-modal-footer').html('<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>');
        }

        let messages = json.hasOwnProperty('modalMessages') ? json.modalMessages : [];
        messages.forEach((m) => {
            let messageType = Object.keys(m)[0];
            let message = m[messageType];
            this.printMessage(messageType, message);
        });

        let modelForm = this.modal.querySelector('form');
        if (modelForm) {
            modelForm.addEventListener('submit', (event) => {
                ajaxModal.submitForm(url, callback);
                event.preventDefault();
            });
        }

        let btnAjaxSubmit = this.modal.querySelector('#ajax-modal-submit');
        if (btnAjaxSubmit) {
            btnAjaxSubmit.addEventListener('click', () => {
                ajaxModal.submitForm(url, callback);
            });
            document.addEventListener('keydown', this.onKeyDown);
        }

        tooltipDataLinks(this.modal);

        if (typeof callback === 'function') { callback(json, this.modal); }
    }

    printMessage(messageType, message) {
        let messageClass;
        switch(messageType) {
            case 'warning':
                messageClass = 'alert-warning';
                break;
            case 'error':
                messageClass = 'alert-danger';
                break;
            case 'info':
                messageClass = 'alert-info';
                break;
            default:
                messageClass = 'alert-success';
        }

        this.modal.querySelector('.ajax-modal-body').insertAdjacentHTML(
            'afterbegin',
            '<div class="alert '+ messageClass +'" role="alert">' + message.replace(/\n/g, '<br>') +'</div>'
        );
    }
}

const ajaxModal = new AjaxModal('#ajax-modal');
const pickFileModal = new AjaxModal('#pick-file-server-modal');

export default ajaxModal;
export {pickFileModal};



