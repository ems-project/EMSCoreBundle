export default function collapse() {
    document.querySelectorAll('.collapsible').forEach((wrapper) => {
        let button = wrapper.querySelector('.btn-collapse');
        if (null === button) {
            return;
        }

        let collapse = wrapper.querySelectorAll(":scope > .collapse");
        let hasContent = false;
        let defaultExpanded = false;

        collapse.forEach((element) => {
            hasContent = element.firstElementChild ? true : false;
            defaultExpanded = (element.style.display === 'block' && defaultExpanded == false ? true : false);
        });

        button.setAttribute('aria-expanded', defaultExpanded);

        if (!hasContent) {
            button.style.display = 'none';
            button.onclick = (event) => {};
        } else {
            button.style.display = 'inline-block';
            button.onclick = (event) => {
                event.preventDefault();
                let expanded = button.getAttribute('aria-expanded');
                button.setAttribute('aria-expanded', expanded == 'true' ? 'false' : 'true');
                collapse.forEach((c) => { c.style.display = expanded == 'true' ? 'none' : 'block'; });
            }
            button.addEventListener('show', (evt) => {
                evt.preventDefault();
                evt.target.setAttribute('aria-expanded', 'true');
                collapse.forEach((c) => { c.style.display = 'block'; });
            });
            button.addEventListener('hide', (evt) => {
                evt.preventDefault();
                evt.target.setAttribute('aria-expanded', 'false');
                collapse.forEach((c) => { c.style.display = 'none'; });
            });
        }
    });
}


