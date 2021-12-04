import '../../css/modules/styleset-preview.scss';

export default class IframePreview {
    constructor() {
        const self = this;
        const iframes = document.querySelectorAll('iframe[data-iframe-body]');
        [].forEach.call(iframes, function(iframe) {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            if (iframeDoc.readyState  === 'complete') {
                self.loadBody(iframe);
            } else {
                iframe.addEventListener('load', function () {
                    self.loadBody(iframe);
                });
            }
            window.addEventListener('resize',function () {
                self.adjustHeight(iframe);
            });
        });
    }

    loadBody(iframe) {
        const body = iframe.getAttribute('data-iframe-body');
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.body.innerHTML = body;
        this.adjustHeight(iframe);
    }

    adjustHeight(iframe) {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const height = iframeDoc.documentElement.scrollHeight;
        iframe.style.height = height + 'px';
    }

}