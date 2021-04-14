# emsco_datatable

This Twig filter generate an Ajax table view for you. 

With the following basic example you will have a table vue listing the `identifier` attribute for all `miniature` documents in your `default` environment  :

```twig
{{ emsco_datatable(['default'],['miniature'], {
    "columns": [{
        "label": "ID",
        "template": "{{ data.source.identifier }}",
        "orderField": "identifier"
    }]
}) }}
```

The first parameter is an array of environments.

The second parameter is an array of content types.

The third parameter is an options array:

 - `columns`: Definition of columns (array)
    - `label`: Column's label (string)
    - `template`: Twig template (string) where the following variables are available:
        - `data`: EMS\CommonBundle\Elasticsearch\Document\DocumentInterface
        - `column`: EMS\CoreBundle\Form\Data\TemplateTableColumn
    - `orderField`: this value will be used in the elasticsearch query, when the table is sorted by this column, in order to sort the result set. If not defined, or set to null, this column won't be sortable. (string)