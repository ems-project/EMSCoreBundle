function tooltipDataLinks(target) {
    const links = target.querySelectorAll('[data-toggle="tooltip"]');
    for(let i = 0;i < links.length; i++) $(links[i]).tooltip();
}

export {tooltipDataLinks};