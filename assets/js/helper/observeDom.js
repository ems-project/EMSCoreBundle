function observeDom(obj, callback) {
    const MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
    if( !obj || obj.nodeType !== 1 ) return;

    if( MutationObserver ){
        const mutationObserver = new MutationObserver(callback)
        mutationObserver.observe( obj, { childList:true, subtree:true })
        return mutationObserver
    }

    else if( window.addEventListener ){
        obj.addEventListener('DOMNodeInserted', callback, false)
        obj.addEventListener('DOMNodeRemoved', callback, false)
    }
}

export {observeDom};