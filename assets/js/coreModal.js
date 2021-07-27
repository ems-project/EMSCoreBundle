import {addEventListeners as editRevisionAddEventListeners} from "../edit-revision";

class CoreModal {
    constructor(selector) {
        this.$modal = $(selector);

        this.element = document.querySelector(selector);
        this.loadingElement = this.element.querySelector('.modal-loading');
        this.ajaxDataElements = this.element.querySelectorAll('.ajax-data');

        this.setXhrCallback = (xhrCallback) => { this.xhrCallback = xhrCallback; };

        $(document).on('hide.bs.modal', '.core-modal', () => { this.stateReset(); });
    }

    stateReset() {
        this.xhrCallback = null;
        this.loadingElement.style.display = 'block';

        this.$modal.find('.ckeditor_ems').each(function () {
            if (CKEDITOR.instances.hasOwnProperty($(this).attr('id'))) {
                CKEDITOR.instances[$(this).attr('id')].destroy();
            }
        });

        this.ajaxDataElements.forEach((e) => { e.innerHTML = ''; e.style.display = 'none'; });
    }
    stateLoading() {
        this.loadingElement.style.display = 'block';

        this.element
            .querySelectorAll('input, button')
            .forEach((e) => { e.setAttribute("disabled","disabled"); });

    }
    stateReady() {
        this.loadingElement.style.display = 'none';
        this.ajaxDataElements.forEach((e) => { e.style.display = 'block'; });

        this.element
            .querySelectorAll('input, button')
            .forEach((e) => { e.removeAttribute("disabled"); });
    }

    load(options, xhrCallback)
    {
        this.element.querySelector('.modal-title').innerHTML = options.title;
        this.$modal.modal('show');

        if (typeof xhrCallback !== 'undefined') {
            this.setXhrCallback(xhrCallback);
        }

        var defaultLoadOptions = {
            method: 'GET',
            contentType: 'application/json',
            data: null,
        };
        var options = $.extend({}, defaultLoadOptions, options || {});
        this.xhr(options);
    }

    submitForm(options)
    {
        const formData = this.$modal.find('form').serialize();
        var defaultSubmitOptions = {
            method: 'POST',
            contentType: 'application/x-www-form-urlencoded',
            data: formData,
        };
        var options = $.extend({}, defaultSubmitOptions, options || {});
        this.xhr(options);
    }

    xhr(options)
    {
        this.stateLoading();

        let httpRequest = new XMLHttpRequest();
        httpRequest.open(options.method, options.url, true);
        httpRequest.setRequestHeader('Content-Type', options.contentType);
        httpRequest.onreadystatechange = () => {
            if (httpRequest.readyState === XMLHttpRequest.DONE) {
                this.xhrDone(httpRequest);
                this.stateReady();
            }
        }
        httpRequest.send(options.data);
    }

    printMessage(messageType, message) {
        switch(messageType) {
            case 'error':
                var messageClass = 'alert-danger';
                break;
            default:
                var messageClass = 'alert-success';
        }

        this.element.querySelector('.core-modal-body').insertAdjacentHTML(
            'afterbegin',
            '<div class="alert '+ messageClass +'" role="alert">'+ message +'</div>'
        );
    }

    xhrDone(httpRequest) {
        if (httpRequest.status === 200) {
            let response = JSON.parse(httpRequest.responseText);

            if (response.hasOwnProperty('body')) {
                this.$modal.find('.core-modal-body').html(response.body);
                this.$modal.find(':input').each(function () {
                    $(this).addClass('ignore-ems-update');
                });
            }
            if (response.hasOwnProperty('buttons')) {
                this.$modal.find('.core-modal-buttons').html(response.buttons);
            }

            var messages = response.hasOwnProperty('messages') ? response.messages : [];
            messages.forEach((m) => {

                var messageType = Object.keys(m)[0];
                var message = m[messageType];
                console.debug(m, messageType, message);
                this.printMessage(messageType, message);
            })


            if (this.xhrCallback !== null) {
                this.xhrCallback(response);
            }
        } else {
            this.printMessage('error', 'Error loading ...');
        }
    }
}

const coreModal = new CoreModal('#core-modal');
export default coreModal;



