import {editRevisionEventListeners} from "../editRevisionEventListeners";
import {tooltipDataLinks} from "./tooltip";

import { ajaxJsonGet, ajaxJsonPost, ajaxJsonSubmit } from "./ajax";

class AjaxModal {
    constructor(selector) {
        this.selector = selector;
        this.$modal = $(selector);

        this.modal = document.querySelector(this.selector);
        if (this.modal) {
            this.loadingElement = this.modal.querySelector('.modal-loading');
            this.ajaxDataElements = this.modal.querySelectorAll('.ajax-data');
            $(document).on('hide.bs.modal', '.core-modal', (e) => {
                if (e.target.id === this.modal.id) {
                    this.reset();
                }
            });
        }
    }

    close() {
        this.$modal.modal('hide');
    }

    reset() {
        this.loadingElement.style.display = 'block';

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
        var dialog = this.modal.querySelector('.modal-dialog');
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

        if (options.hasOwnProperty('data')) {
            ajaxJsonPost(options.url, options.data, (json, request) => {
                this.ajaxReady(json, request, callback);
                this.stateReady();
            });
        } else {
            ajaxJsonGet(options.url, (json, request) => {
               this.ajaxReady(json, request, callback);
                this.stateReady();
            });
        }
    }

    postRequest(url, data, callback) {
        this.stateLoading();
        ajaxJsonPost(url, data, (json, request) => {
            this.ajaxReady(json, request, callback);
            this.stateReady();
        });
    }

    submitForm(url, callback)
    {
        for (let i in CKEDITOR.instances) {
            if(CKEDITOR.instances.hasOwnProperty(i)) { CKEDITOR.instances[i].updateElement(); }
        }
        var formData = this.$modal.find('form').serialize();

        this.stateLoading();
        ajaxJsonSubmit(url, formData, (json, request) => {
            this.ajaxReady(json, request, callback);
            this.stateReady();
        });
    }

    ajaxReady(json, request, callback) {
        if (request.status === 200) {
            if (json.hasOwnProperty('modalClose') && json.modalClose === true) {
                if (typeof callback === 'function') { callback(json, request, this.modal); }
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
            }
            if (json.hasOwnProperty('modalFooter')) {
                this.$modal.find('.ajax-modal-footer').html(json.modalFooter);
            } else {
                this.$modal.find('.ajax-modal-footer').html('<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>');
            }

            var messages = json.hasOwnProperty('modalMessages') ? json.modalMessages : [];
            messages.forEach((m) => {
                var messageType = Object.keys(m)[0];
                var message = m[messageType];
                this.printMessage(messageType, message);

                if (messageType === 'error') {
                    location.reload();
                }
            });

            var modelForm = this.modal.querySelector('form');
            if (modelForm) {
                editRevisionEventListeners(this.$modal.find('form'));
            }

            var btnAjaxSubmit = this.modal.querySelector('#ajax-modal-submit');
            if (btnAjaxSubmit) {
                btnAjaxSubmit.addEventListener('click', () => {
                    ajaxModal.submitForm( request.responseURL, callback);
                });
            }

            tooltipDataLinks(this.modal);

            if (typeof callback === 'function') { callback(json, request, this.modal); }
        } else {
            this.printMessage('error', 'Error loading ...');
        }
    }

    printMessage(messageType, message) {
        let messageClass;
        switch(messageType) {
            case 'error':
                messageClass = 'alert-danger';
                break;
            default:
                messageClass = 'alert-success';
        }

        this.modal.querySelector('.ajax-modal-body').insertAdjacentHTML(
            'afterbegin',
            '<div class="alert '+ messageClass +'" role="alert">' + message +'</div>'
        );
    }
}

const ajaxModal = new AjaxModal('#ajax-modal');
export default ajaxModal;



