# Translation Files

This directory contains translation files for the CPT Table Engine plugin.

## Text Domain

`slk-cpt-table-engine`

## Generating POT File

To generate a POT file for translation:

```bash
wp i18n make-pot . languages/slk-cpt-table-engine.pot
```

## Adding Translations

1. Create a `.po` file for your language (e.g., `slk-cpt-table-engine-de_DE.po`)
2. Translate the strings
3. Compile to `.mo` file:
   ```bash
   msgfmt slk-cpt-table-engine-de_DE.po -o slk-cpt-table-engine-de_DE.mo
   ```

## Translatable Strings

All user-facing strings use the `slk-cpt-table-engine` text domain:

```php
__( 'Text to translate', 'slk-cpt-table-engine' )
esc_html__( 'Text to translate', 'slk-cpt-table-engine' )
esc_attr__( 'Text to translate', 'slk-cpt-table-engine' )
```
