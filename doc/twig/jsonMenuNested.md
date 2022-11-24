# emsco_json_menu_nested

Twig function for rendering a json menu structure. It is also used on the revision detail and edit page.
You only need to pass an options object.

Every rendering allways checks the **premissions**. On the json_menu_nested field type and the child containers.

Loading the page with the request parameter **item** and value a item id, will select and focus on the requested item.



## Options

### Required
- **id** : unique id for the rendering
- **document** : elasticsearch hit array
- **field** : field name defined in the contentType
  
### Optional
- **field_document** : supporting multiplex
- **silent_publish** : default **true**, every action will be silently trigger a safe
- **structure** : pass a json string of a part of the structure. Silent publish will now do partial updates
- **actions** : allow or deny actions by default everything is enabled
- **blocks** : overwrite item actions, append item information
- **context** : extra context for the blocks rendering

### Simple render
```twig
{{ emsco_json_menu_nested({
     'id': (doc._id),
     'document': (doc),
     'field': 'structure'
 }) }}
```

### Actions

Possible actions: **add** | **edit** | **delete** | **move** | **copy** | **paste** | **preview**.
> Foreach action you can define an array with **allow** or **deny** types.
> Disabling or enabling **root** add, copy, paste you can use **root** as type

### Deny all actions
```twig
{{ emsco_json_menu_nested({
     'id': (doc._id),
     'document': (doc),
     'field': 'structure',
     'actions': {
        'add': { 'deny': ['all'] },
        'edit': { 'deny': ['all'] },
        'delete': { 'deny': ['all'] },
        'move': { 'deny': ['all'] },
        'copy': { 'deny': ['all'] },
        'paste': { 'deny': ['all'] },
        'preview': { 'deny': ['all'] },
     }
 }) }}
```

### Only allow copy/move/view for items with type page

```twig
{{ emsco_json_menu_nested({
     'id': (doc._id),
     'document': (doc),
     'field': 'structure',
     'actions': {
        'copy': { 'allow': ['page'] },
        'move': { 'allow': ['page'] },
        'preview': { 'allow': ['page'] },
     }
 }) }}
```

### Default data

If the current request contains a param **defaultData** you can prefill the add modal.
The **defaultData** should be a valid json and base64 decoded.

Example create a link to the dashboard that contains a jsonMenuNested structure.
If on the page you add a new item that has the field 'page', it will be prefilled with 'page:#{source._version_uuid}'.

```twig
{% set defaultData = {'page': "page:#{source._version_uuid}"}|json_encode|ems_base64_encode %}
<a href="{{ path('emsco_dashboard', { 'name': 'page', 'defaultData': defaultData })  }} ">Page structure</a>
```

### Blocks

Define an array of object blocks. Foreach object need to define a type, item_type and html.
Multiple blocks of the same type will be rendered first come first served.

In the html string you can access the option **context** and:
- **item**: JsonMenuNested instance of the item.
- **buttons**: Object with the render result for edit,preview,add,more,move and delete.   
- **node**: node information used by the renderer.

```twig
    {% set context = { 'title': 'Example' } %}
    {% set pageButtons %}
        {% verbatim %}  
            {{ '<button class="btn-test btn btn-sm btn-primary" data-item-id="{{ item.id }}">{{ item }}</button>' }}
            {{ '{{ buttons.edit|raw }}' }}
            {{ '{{ buttons.delete|raw }}' }}
        {% endverbatim %}  
    {% endset %}
    {% set pageExtra %}
        {% verbatim %} 
            {{ '<div class="well p-2 m-2">{{ buttons.view|raw }} {{ title }} : {{ item.object.label }}</div>' }}
        {% endverbatim %}
    {% endset %}
    {% set rootButtons %}
         {% verbatim %}       
            {% if is_granted('ROLE_PUBLISHER') %}
                {{ buttons.add|raw }}
                {{ buttons.more|raw }}
            {% endif %}
            <div class="pull-right">
                <button class="btn btn-sm btn-default">Extra button</button>
            </div>
        {% endverbatim %}
    {% endset %}
    
    {{ emsco_json_menu_nested({
        'id': (doc._id),
        'document': (doc),
        'field': 'structure',
        'context': context,
        'blocks': [
            {
                'type': 'item_after',
                'item_type': 'page',
                'html': (pageExtra)
            },
            {
                'type': 'item_action',
                'item_type': 'page',
                'html': (pageButtons)
            },
            {
                'type': 'item_action',
                'item_type': '_root',
                'html': (rootButtons)
            }
        ],
    }) }}
```

### Partial structure

Render the first node of the type folder_pages.

```twig
    {% set structureDoc = {
        "index": "preview",
        "size": 1,
        "body": { "query": { "bool": { "must":[
            {"term": { "_contenttype": {"value": "structure" } } },
            {"term": { "_id": {"value": "4e4e1e11-98b3-4915-99fe-2904d8d42b2a" } } }
        ] } } }
    }|search.hits.hits|first %}

    {% if structureDoc %}
        {% set jsonMenuNested = structureDoc._source.structure|ems_json_menu_nested_decode %}
        {% set folderPages = jsonMenuNested.children|filter(c => c.type == 'folder_pages')|first %}
    
        {{ emsco_json_menu_nested({
            'id': structureDoc._id,
            'document': structureDoc,
            'field': 'structure',
            'structure': (folderPages.toArrayStructure(true)|json_encode),
        }) }}
    {% endif %}
```
