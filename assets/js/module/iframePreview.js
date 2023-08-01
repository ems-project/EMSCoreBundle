import '../../css/modules/styleset-preview.scss';

export default class IframePreview {
    constructor() {
        const self = this;
        window.addEventListener('message', function(event) {
            self.onMessage(event);
        });
    }

    onMessage(event) {
        let iframe = null;
        const iframes = document.querySelectorAll("iframe");
        for (let i = 0; i < iframes.length; i++) {
            if (event.source === iframes[i].contentWindow) {
                iframe = iframes[i];
                break;
            }
        }
        if (null === iframe) {
            console.log('Received message from a unknown iframe');
            return;
        }
        if ('ready' === event.data) {
            this.loadBody(iframe);
        } else if ('resize' === event.data) {
            this.adjustHeight(iframe);
        } else {
            console.log('Unknown event type: ' + event.data);
        }

    }

    loadBody(iframe) {
        let body = iframe.getAttribute('data-iframe-body');
        body = this.#changeSelfTargetLinksToParent(body);
        const window = iframe.contentWindow || iframe.contentDocument.defaultView;
        window.postMessage(body, '*');
    }

    adjustHeight(iframe) {
        const window = iframe.contentWindow || iframe.contentDocument.defaultView;

        let height = window.document.documentElement.scrollHeight;

        ['border-top-width', 'border-bottom-width', 'padding-top', 'padding-bottom'].forEach((v) => {
            height += parseInt(window.getComputedStyle(iframe, null).getPropertyValue(v).replace('px',''), 10);
        });

        iframe.height = height;
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
