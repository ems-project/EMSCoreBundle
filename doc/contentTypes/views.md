# Content Type: Views

Name | description 
--- | ---
[CalendarViewType](#calendar-view) | A view where you can schedule your object  
[CriteriaViewType](#criteria-view) | A view where we can massively edit content types having criteria 
[DataLinkViewType](#datalink-view) | Manipulate the choices in a data link of this content type
[ExportViewType](#export-view) | Perform an elasticsearch query and generate a export with a twig template 
[GalleryViewType](#gallery-view) | A view where you can browse images 
[HierarchicalViewType](#hierarchical-view) | Manage a menu structure (based on a ES query) 
[ImporterViewType](#importer-view) | Form to import a zip file containing JSON files 
[KeywordsViewType](#keywords-view) | A view where all properties of kind (such as keyword) are listed on a single page 
[ReportViewType](#report-view) | Perform an elasticsearch query and generate a report with a twig template 
[SorterViewType](#sorter-view) | Order a sub set (based on a ES query) 
 

## Calendar view
A view where you can schedule your object

## Criteria view
A view where we can massively edit content types having criteria

## DataLink view
> Manipulate the choices in a data link of this content type.

It is used by the searchApi when creating an internal link inside a WYSIWYG.
The view template does not need to return anything, it needs to add data to the passed **dataLinks** object.
This view will be excluded from the elasticms menu navigation.

### Twig content template

name | instance 
--- | ---
view | [Entity\View](https://github.com/ems-project/EMSCoreBundle/blob/master/src/Entity/View.php) 
contentType | [Entity\contentType](https://github.com/ems-project/EMSCoreBundle/blob/master/src/Entity/ContentType.php)
environment | [Entity\environment](https://github.com/ems-project/EMSCoreBundle/blob/master/src/Entity/Environment.php)
dataLinks | [Core\Document\DataLinks](https://github.com/ems-project/EMSCoreBundle/blob/master/src/Core/Document/DataLinks.php)


### Example
> A document contains a json menu nested structure, and you want to select a node (id) inside this structure. 
> The WYSIWYG has a language defined and is also passed to the twig context.

```twig
{% set searchStructures = { 
    "index": environment.alias,
    "size": 50,
    "body": {
        "query": { "bool": { "must":[ {"term": { "_contenttype": {"value":"my_structure"} } } ] } },
        "sort": [ { "order": { "order": "asc" } } ]
    }
}|search.hits.hits %}

{% set structures = [] %}
{% for h in searchStructures %}
    {% set structures = structures|merge([{
        'id': h._id,
        'type': 'structure',
        'label': (h._source.label),
        'object': { "label": (h._source.name) },
        'children': (h._source.structure|default('{}')|ems_json_decode)   
    }]) %}
{% endfor %}
{%- set structureMenu = structures|json_encode|ems_json_menu_nested_decode -%}

{% set locale = dataLinks.locale|default('fr') %}

{% set patterns = dataLinks.pattern|split('>')|map(v => v|trim) %}
{% set pattern = patterns|join('.*') %} {# searching for "Example > link" will patch "This Example > test > test2 > link" #}
{% set matchRegex = "/.*#{pattern}.*/i"  %}

{% for item in structureMenu %}
    {% set path = [] %}
    {%- for p in item.path -%}{%- set path = path|merge([p.object.label]) -%}{%- endfor -%}
    
    {% set text = path|join(' > ') %}
    {% if text matches matchRegex %}{% do dataLinks.add( ("my_node:#{item.id}"), text ) %}{% endif %}
{% endfor %}
```

## Export view
Perform an elasticsearch query and generate a export with a twig template

## Gallery view
A view where you can browse images

## Hierarchical view
Manage a menu structure (based on a ES query)

## Importer view
Form to import a zip file containing JSON files

## Keywords view
A view where all properties of kind (such as keyword) are listed on a single page

## Report view
Perform an elasticsearch query and generate a report with a twig template

## Sorter view
Order a sub set (based on a ES query)