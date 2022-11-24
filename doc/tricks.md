# Shortcuts, tips and tricks for elasticms's actions and views

## redirectToUrl parameter

If you call a route that is returning a RedirectionResponse, i.e. the `revision.publish_to` route, you may want to override the redirect target url of the response. By adding an extra redirectToUrl parameter to the route you call control it:

```twig
{{ path("revision.publish_to", {revisionId: revisionId, envId: liveId, redirectToUrl: path("data.customindexview", {viewId: 2})}) }}
```

This will reroute the RedirectResponse to view `2` after having published the revision `revisionId` in the environment `liveId`.


## Catch the iframe load event in the StyleSet JS appplication

When you activate the iframe preview the JS script (if specified) is loaded before that the DOM is injected. If you want of execute some actions after that the DOM is updated you can listen to the `ems-preview` event.

```javascript
document.addEventListener('ems-preview', function (event){
    console.log('ems-preview loaded!');
    drawioPreviewer();
});
```

Also, if the frontend application alter the iframe in a way where the iframe's height change, you can fire a redraw event on the preview's window:

```javascript
const script = document.createElement('script');
script.setAttribute('type', 'text/javascript');
script.addEventListener("load", function() {
    const redrawEvent = new Event('redraw');
    window.dispatchEvent(redrawEvent);
});
script.setAttribute('src', '//viewer.diagrams.net/js/viewer-static.min.js');
document.body.appendChild(script);
```

# Content type encoding form

## Avoid nested tabs

It's very useful to use multiplex fields in order to avoid maintaining forms per locale. But the multiplex fields are displayed as tabs. To avoid nested tabs, multiplex fields in tabs fields are integrated directly to it's parent.    

