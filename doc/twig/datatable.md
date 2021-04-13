# emsco_datatable

This Twig filter generate an Ajax table view for you. 

With the following basic example you will have a table vue listing the `identifier` attribute for all `miniature` documents in your `default` environment  :

```twig
{{ emsco_datatable(['default'],['miniature'], {
    "columns": [{
        "label": "ID",
        "template": "{{ data.identifier }}"
    }]
}) }}
```
