#emsco_asset_meta

This filter returns you a [ExtractedData](../../src/Helper/AssetExtractor/ExtractedData.php) object from an asset's hash:

```twig
{% set meta = source.file.sha1|emsco_asset_meta %}
{{ meta.locale }}
```

#emsco_guess_locale

This filter returns you the text's locale guessed by Tika:

```twig
{{ 'Hello comment allez-vous?'|emsco_guess_locale }}
{# Displays a 'fr' #}
```