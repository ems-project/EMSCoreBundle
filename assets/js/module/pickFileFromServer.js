import ajaxModal, {pickFileModal} from "../helper/ajaxModal";
import {observeDom} from '../helper/observeDom';
import {resizeImage} from "../helper/resizeImage";
import ProgressBar from "../helper/progressBar";

export default class PickFileFromServer {
    constructor(target) {
        const buttons = target.querySelectorAll('button.file-browse-server');
        const self = this;

        [].forEach.call(buttons, function(button) {
            button.addEventListener('click', function(event) {
                self.onClick(button);
            });
        });
    }

    onClick(button) {
        const wysiwygInfo = JSON.parse(document.querySelector('body').dataset.wysiwygInfo);
        if (wysiwygInfo && wysiwygInfo.config && wysiwygInfo.config.emsBrowsers && wysiwygInfo.config.emsBrowsers.browser_file && wysiwygInfo.config.emsBrowsers.browser_file.url) {
          const query = new URLSearchParams({ 'format': 'json' });
          ajaxModal.load({ url: wysiwygInfo.config.emsBrowsers.browser_file.url + '?' + query.toString(), size: 'lg' }, (json) => {
              console.log(json);
              ajaxModal.getBodyElement().append(progressBar.element());
              // if (!json.hasOwnProperty('success') || json.success === false) return;
              //
              // let processed = 0;
              // const progressBar = new ProgressBar('progress-delete-files', {
              //   label: 'Deleting files',
              //   value: 100,
              //   showPercentage: true,
              // });
              //
              // ajaxModal.getBodyElement().append(progressBar.element());
              // this.loading(true);
              //
              // Promise
              //   .allSettled(Array.from(selection).map(fileRow => {
              //     return this._post(`/file/${fileRow.dataset.id}/delete`).then(() => {
              //       if (!json.hasOwnProperty('success') || json.success === false) return;
              //
              //       fileRow.closest('li').remove();
              //       progressBar
              //         .progress(Math.round((++processed / selection.length) * 100))
              //         .style('success');
              //     });
              //   }))
              //   .then(() => this._getFiles())
              //   .then(() => this.loading(false))
              //   .then(() => new Promise(resolve => setTimeout(resolve, 2000)))
              //   .then(() => ajaxModal.close())
              // ;
            });

            return;
        }

        pickFileModal.load({ url: button.dataset.href, title: button.textContent, size: 'lg' },
            (json, modal) => {

            const addClickCallbacks = function(linkList){
                for (let i = 0; i < linkList.length; i++) {
                    linkList[i].onclick = (event) => {
                        const primaryBox = $('body')
                        const initUpload = primaryBox.data('init-upload')
                        const hashAlgo = primaryBox.data('hash-algo');
                        if (event.target.parentNode === undefined || event.target.parentNode.dataset.json === undefined) {
                            return;
                        }
                        event.preventDefault();
                        const data =  JSON.parse(event.target.parentNode.dataset.json)
                        fetch(data.view_url, {mode: 'cors'})
                            .then(res => res.blob())
                            .then(blob => {
                                blob.name = data.filename
                                return resizeImage(hashAlgo, initUpload, blob)
                            })
                            .then((response) => {
                                if (null === response) {
                                    return
                                }
                                data._image_resized_hash = response.hash
                                data.preview_url = response.url
                            })
                            .catch((errorMessage) => {
                                console.error(errorMessage)
                            })
                            .finally(() => {
                                const row = button.closest('.file-uploader-row');
                                row.dispatchEvent(new CustomEvent('updateAssetData', {detail: data}));
                                pickFileModal.close();
                                observer.disconnect();
                            })
                    };
                }
            }

            const linkList = modal.querySelectorAll('div[data-json] > a');
            addClickCallbacks(linkList);
            const observer = observeDom(modal, function(mutationList) {
                [].forEach.call(mutationList, function(mutation) {
                    if(mutation.addedNodes.length < 1) {
                        return;
                    }
                    [].forEach.call(mutation.addedNodes, function (node) {
                        if (node.nodeType !== Node.ELEMENT_NODE) {
                            return;
                        }

                        if (node.matches('div[data-json] > a')) {
                            addClickCallbacks([node]);
                        }

                        addClickCallbacks(node.querySelectorAll('div[data-json] > a'));
                    });
                });
            });
        });
    }
}
