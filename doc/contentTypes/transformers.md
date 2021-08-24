# Content Type: Transformers
In the "Migration Options" of contenttype field you can add one or more transformers.
For each transformer you need to define a JSON config.
When running the transform command these transformers will be applied.

Name | description | Field
--- | --- | --- 
[Html Attribute Transformer](#html-attribute-transformer) | Remove html attribute or remove attribute values. | wysiwyg
[Html Empty Transformer](#html-empty-transformer) | Clean empty html content | wysiwyg
[Html Remove Node Transformer](#html-remove-node-transformer) | Clean empty html content | wysiwyg

## Html Attribute Transformer
Only available for WYSIWYG field types.
### Config
* **attribute** : required, which attribute you want to transform
* **element** : default (*), which html element
* **remove** : default (false), remove the attribute
* **remove_value_prefix** : default (null), remove all values starting by from **class** or **style** attributes.

### Examples
> Remove all style attributes for all table elements
```json
{"attribute": "style", "element": "table", "remove": "true"}
```
> Remove all cellpadding attributes for all table elements
```json
{"attribute": "cellpadding", "element": "table", "remove": "true"}
```
> Remove all style values related to font-size
```json
{"attribute": "style", "element": "*", "remove_value_prefix": "font-size"}
```
> Remove all class values starting with 'font' from all divs
```json
{"attribute": "class", "element": "div", "remove_value_prefix": "font-"}
```

## Html Empty Transformer
Only available for WYSIWYG field types.
Clean content without textual content
### Config
> No config required

Example transformer to null
```html
<p style="text-align: justify;"> </p> <div class="example" style="text-align: justify;"> </div> <p> </p>
```
```html
<html><body><h1>            </h1><p>&nbsp;       </p></body>        </html>
```

## Html remove node transformer
> Remove all span elements
```json
{"element": "span"}
```
> Remove all span that have a class attribute containing *delete*
```json
{"element": "span", "attribute": "class", "attribute_contains": "delete"}
```


