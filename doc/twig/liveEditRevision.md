It' possible to Edit some field for a Revision in overview when use : emsco_datable

Need use option editableField in column where need an edit field. 

Example
```twig

{{ emsco_datatable(['preview'],[contentType.name], {
.....
"columns": [
{
"label": "Highlighted",
"template": '{{ data.source.highlighted|default("") }}',
"editableField" : "[highlighted]"
},
{
"label": "Title FR",
"template": '{{ data.source.fr.title|default("") }}',
"editableField" : "[fr][title]"
},
... ]
}) }}

```
