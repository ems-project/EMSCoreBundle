# Routes

Here some useful routes that you may want to use in your actions or view

## A a revision to a release

This piece of code add a link to a datatable view:

```twig
{%- set revisionId = object._id|get_revision_id(contentType.name) -%}

<a href="{{ path('emsco_pick_a_release', {revision: revisionId}) }}"><i class="glyphicon glyphicon-pushpin"></i> Add to release</a>
```
It can be used as is in a `Raw HTML` action`