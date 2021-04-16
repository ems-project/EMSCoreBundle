# emsco_datatable

This Twig filter generate an Ajax table view for you from elasticsearch queries. 

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
    - `label`: Column's label (string). Default value `'Label'`
    - `template`: Twig template (string) where the following variables are available. Default value `'''`. Available variable in the Twig context:
        - `data`: EMS\CommonBundle\Elasticsearch\Document\DocumentInterface
        - `column`: EMS\CoreBundle\Form\Data\TemplateTableColumn
    - `orderField`: this value (string) will be used in the elasticsearch query, when the table is sorted by this column, in order to sort the result set. If not defined, or set to null, this column won't be sortable. If not defined the column wont't be sortable.
   
## Optional options

### query
It's the elasticsearch query (array or string) used to get the data when a query string is defined in the datatable's search field. I.e.:

```twig
{{ emsco_datatable(['ldap'],['ldap_user'], {
    "query": {
        "multi_match": {
          "query": "%query%",
          "operator": "and",
          "type": "bool_prefix",
          "fields": [
            "live_search",
            "live_search._2gram",
            "live_search._3gram"
          ]
        }
      },
    "columns": [{
        "label": "Name",
        "template": "{{ data.source.name|default('') }}",
        "orderField": "name.keyword"
    }]
}) }}
```

You can use the `%query%` pattern to inject the query string in your query. In this example we are using a [`search_as_you_type`](https://www.elastic.co/guide/en/elasticsearch/reference/7.x/search-as-you-type.html) search field. This kind of field type are particularly suitable for this kind of search. You can define such field type with this mapping's config:

```json
{
   "live_search": {
      "type": "search_as_you_type"
   }
}
```

Default value:

```json
{
   "query_string": {
      "query": "%query%"
   }
}
```

## emptyQuery

It's the elasticsearch query (array or string) used when nothing is defined in the datatable's search field. Default value:

```json
{
}
```
