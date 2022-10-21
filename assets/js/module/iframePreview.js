import '../../css/modules/styleset-preview.scss';
import {observeDom} from '../helper/observeDom';

export default class IframePreview {
    constructor(observableSelector) {
        const iframes = document.querySelectorAll('iframe[data-iframe-body]');
        const self = this;
        this.loadIframes(iframes)
        if (observableSelector === undefined) {
            return;
        }
        observeDom(document.querySelector(observableSelector), function(mutations) {
            self.observeDom(mutations);
        });
    }

    loadBody(iframe) {
        let body = iframe.getAttribute('data-iframe-body');
        body = this.#changeSelfTargetLinksToParent(body);
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.body.insertAdjacentHTML('afterbegin', body+iframeDoc.body.innerHTML);
        this.adjustHeight(iframe);
        const emsPreview = iframeDoc.createEvent('Event');
        emsPreview.initEvent('ems-preview', true, true);
        iframeDoc.dispatchEvent(emsPreview);
    }

    adjustHeight(iframe) {
        if(null === iframe.contentWindow) {
            return;
        }
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

        const height = 30 + iframeDoc.body.offsetHeight;
        iframe.style.height = height + 'px';
    }

    loadIframes(iframes) {
        const self = this;
        [].forEach.call(iframes, function(iframe) {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            if (iframeDoc.readyState  === 'complete') {
                self.loadBody(iframe);
            }
            iframe.addEventListener('load', function () {
                self.loadBody(iframe);
            });
            iframe.contentWindow.addEventListener('resize',function () {
                self.adjustHeight(iframe);
            });
            iframe.contentWindow.addEventListener('redraw',function () {
                self.adjustHeight(iframe);
            });
        });
    }

    observeDom(mutationList) {
        const self = this;
        [].forEach.call(mutationList, function(mutation) {
            if(mutation.addedNodes.length < 1) {
                return;
            }
            const iframes = mutation.target.querySelectorAll('iframe[data-iframe-body]');
            self.loadIframes(iframes)
        });
    }

    #changeSelfTargetLinksToParent(body) {
        const parser = new DOMParser()
        const dom = parser.parseFromString(body, 'text/html')
        ;[...dom.getElementsByTagName('a')].forEach((link) => {
            if (!link.getAttribute('target')) {
                link.setAttribute('target', '_parent')
            }
        })

        return dom.documentElement.outerHTML
    }
}