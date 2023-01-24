import {pickFileModal} from "../helper/ajaxModal";
import {observeDom} from '../helper/observeDom';

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
        pickFileModal.load({ url: button.dataset.href, title: button.textContent, size: 'lg' },
            (json, modal) => {

            const addClickCallbacks = function(linkList){
                for (let i = 0; i < linkList.length; i++) {
                    linkList[i].onclick = (event) => {
                        if (event.target.parentNode === undefined || event.target.parentNode.dataset.json === undefined) {
                            return;
                        }
                        event.preventDefault();
                        const data =  JSON.parse(event.target.parentNode.dataset.json)
                        const row = button.closest('.file-uploader-row');
                        row.dispatchEvent(new CustomEvent('updateAssetData', {detail: data}));
                        pickFileModal.close();
                        observer.disconnect();
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
                    [].forEach.call(mutation.addedNodes, function(node) {
                        addClickCallbacks(node.querySelectorAll('div[data-json] > a'));
                    });
                });
            });
        });
    }
}