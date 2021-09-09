# Shortcuts, tips and tricks for elasticms's actions and views

## redirectToUrl parameter

If you call a route that is returning a RedirectionResponse, i.e. the `revision.publish_to` route, you may want to override the redirect target url of the response. By adding an extra redirectToUrl parameter to the route you call control it:

```twig
{{ path("revision.publish_to", {revisionId: revisionId, envId: liveId, redirectToUrl: path("data.customindexview", {viewId: 2})}) }}
```

This will reroute the RedirectResponse to view `2` after having published the revision `revisionId` in the environment `liveId`.