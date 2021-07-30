import {addEventListeners as editRevisionAddEventListeners} from "./../../edit-revision";

import { ajaxJsonGet, ajaxJsonSubmit } from "./ajax";

class AjaxModal {

    constructor(selector) {
        this.selector = selector;
        this.$modal = $(selector);

        this.modal = document.querySelector(this.selector);
        this.loadingElement = this.modal.querySelector('.modal-loading');
        this.ajaxDataElements = this.modal.querySelectorAll('.ajax-data');

        this.setAjaxCallback = (ajaxCallback) => { this.ajaxCallback = ajaxCallback; };

        $(document).on('hide.bs.modal', '.core-modal', () => { this.reset(); });
    }

    reset() {
        this.ajaxCallback = null;
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
        this.loadingElement.style.display = 'block';

        this.modal
            .querySelectorAll('input, button, .select2, textarea')
            .forEach((e) => { e.setAttribute("disabled","disabled"); });

    }
    stateReady() {
        this.loadingElement.style.display = 'none';
        this.modal.querySelector('.ajax-modal-body').style.display = 'block';

        this.modal
            .querySelectorAll('input, button')
            .forEach((e) => { e.removeAttribute("disabled"); });
    }

    load(options, ajaxCallback)
    {
        this.modal.querySelector('.modal-title').innerHTML = options.title;
        this.$modal.modal('show');

        if (typeof ajaxCallback !== 'undefined') {
            this.setAjaxCallback(ajaxCallback);
        }

        ajaxJsonGet(options.url, (json, request) => {
            this.ajaxReady(json, request);
            this.stateReady();
        });
    }

    submitForm(options)
    {
        var formData = this.$modal.find('form').serialize();

        this.stateLoading();
        ajaxJsonSubmit(options.url, formData, (json, request) => {
            this.ajaxReady(json, request);
            this.stateReady();
        });
    }

    ajaxReady(json, request) {
        if (request.status === 200) {
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
            });

            var modelForm = this.modal.querySelector('form');
            if (modelForm) {
                editRevisionAddEventListeners(this.$modal.find('form'));
            }

            var btnAjaxSubmit = this.modal.querySelector('#ajax-modal-submit');
            if (btnAjaxSubmit) {
                btnAjaxSubmit.addEventListener('click', () => {
                    ajaxModal.submitForm({ url: request.responseURL });
                });
            }

            if (this.ajaxCallback !== null) {
                this.ajaxCallback(json, request);
            }
        } else {
            this.printMessage('error', 'Error loading ...');
        }
    }

    printMessage(messageType, message) {
        switch(messageType) {
            case 'error':
                var messageClass = 'alert-danger';
                break;
            default:
                var messageClass = 'alert-success';
        }

        this.modal.querySelector('.ajax-modal-body').insertAdjacentHTML(
            'afterbegin',
            '<div class="alert '+ messageClass +'" role="alert">'
                    + '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                    + message
                    +'</div>'
        );
    }
}

const ajaxModal = new AjaxModal('#ajax-modal');
export default ajaxModal;



