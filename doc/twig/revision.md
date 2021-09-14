# Twig core extension about Revision

# Functions

## emsco_revision_create
Create a new revision from twig

```twig
{% set contentType = 'page'|get_content_type %}
{% set newRevision = emsco_revision_create(contentType, emsco_uuid(), {
    'title': 'Test',
    'locale': 'de'
}) %}
```

## emsco_revision_update
Will overwrite the rawData, you can also use [emsco_revision_merge](#emsco_revision_merge).
Use case example: update an other document on post processing.
It will return the new revision, if you do not needed you can just use twig ``do``

```twig
{% do emsco_revision_update("ems://object:page:1", {
    title: 'Update test',
    body: '<h1>TEST<\/h1>'  
}) %}
```

## emsco_revision_merge
Will merge the passed rawData into the current rawData.
It will return the new revision, if you do not needed you can just use twig ``do``

```twig
{% do emsco_revision_merge("ems://object:page:1", {
    body: '<h1>Merge<\/h1>'  
}) %}
```

## emsco_revisions_draft

Returns a iteratable result of revisions for the given contentTypeName

```twig
{% set ouuidsPagesInDraft = emsco_revisions_draft('page')|map(r => r.ouuid) %}
```

# Filters

## emsco_document_info

The `emsco_document_info` filter on a EMSLink object or on a EMSId string (i.e. `contentTypeName:ouuid`) returns a [`DocumentInfo`](../../src/Common/DocumentInfo.php) object with the following method:
 - revision(string environmentName): returning null of a Revision. False otherwise.
 - draft/hasDraft: returning true if there is a draft in progress. False otherwise.
 - aligned(string environmentName): returning true if the revision linked to the environment is the current one. False otherwise.
 - published(string environmentName): returning true if a revision is published in that environment. False otherwise.
