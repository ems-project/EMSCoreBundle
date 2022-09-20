#emsco_asset_meta

This filter returns you a [ExtractedData](../../src/Helper/AssetExtractor/ExtractedData.php) object from an asset's hash:

```twig
{% set meta = source.file.sha1|emsco_asset_meta %}
{{ meta.locale }}
```

You can also specify a filename and bypass the 3MB protection:


```twig
{% set meta = source.file.sha1|emsco_asset_meta('raport.pdf', true) %}
{{ meta.locale }}
```

#emsco_guess_locale

This filter returns you a text's locale guessed by Tika:

```twig
{{ 'Hello comment allez-vous?'|emsco_guess_locale }}
{# Displays a 'fr' #}
```