import './../../css/modules/dashboard-browser.scss';

document.addEventListener('DOMContentLoaded', function (e) {
    let params = (new URL(window.location.href)).searchParams

    let browserDiv = document.getElementById('dashboard-browser')
    if (null === browserDiv) return

    let dashboardType = browserDiv.dataset.type

    document.addEventListener('click', (event) => {
        if(event.target.tagName.toLowerCase() !== 'a') return

        event.preventDefault();

        let url = new URL(event.target.href)
        let text = event.target.innerText;
        let emsId = event.target.dataset.emsId;

        window.opener.CKEDITOR.tools.callFunction(params.get('CKEditorFuncNum'), url, function () {
            let dialog = this.getDialog();

            switch (dashboardType) {
                case 'browser_image':
                    dialog.getContentElement('info', 'src').setValue(url.pathname + url.search);
                    break
                case 'browser_object':
                    dialog.getContentElement('info', 'localPage').setValue({
                        'id': emsId ? emsId.replace('ems://object:', '') : url.toString(),
                        'text': text
                    })
                    break
                case 'browser_file':
                    let fileLink = dialog.getContentElement( 'info', 'fileLink' )
                    fileLink.setValue(text)
                    fileLink.getInputElement().$.setAttribute('data-link', emsId ?? url.toString())
                    break
            }
        })

        window.close()
    })
});