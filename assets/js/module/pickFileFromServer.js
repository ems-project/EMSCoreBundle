import ajaxModal from "./../helper/ajaxModal";

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
        ajaxModal.load({ url: button.dataset.href, title: button.textContent, size: 'lg' }, function(json, request, modal) {

            const linkList = modal.querySelectorAll(['div[data-json] > a']);
            for (let i = 0; i < linkList.length; i++) {
                linkList[i].addEventListener('click', function(event) {
                    if (event.target.parentNode === undefined || event.target.parentNode.dataset.json === undefined) {
                        return;
                    }
                    event.preventDefault();
                    const data =  JSON.parse(event.target.parentNode.dataset.json)
                    const row = button.closest('.file-uploader-row');
                    row.dispatchEvent(new CustomEvent('updateAssetData', {detail: data}));
                    ajaxModal.close();
                });
            }
        });
    }
}