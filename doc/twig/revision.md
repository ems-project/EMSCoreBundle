# Twig core extension about Revision

# Functions

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
