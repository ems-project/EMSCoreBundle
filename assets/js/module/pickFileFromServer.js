import ajaxModal from "./../helper/ajaxModal";

export default class PickFileFromServer {
    constructor(target) {
        const buttons = target.querySelectorAll('button.file-browse-server');
        const self = this;

        [].forEach.call(buttons, function(button) {
            button.addEventListener('click', function(event) {
                self.onClick(button)
            });
        });
    }

    onClick(button) {
        ajaxModal.load({ url: button.dataset.href, title: button.textContent, size: 'lg' });
    }
}