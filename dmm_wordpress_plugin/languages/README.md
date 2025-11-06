# Translation Files

This directory contains translation files for the DMM Delivery Bridge plugin.

## Files

- `dmm-delivery-bridge.pot` - Template file containing all translatable strings
- `dmm-delivery-bridge-el.po` - Greek translation file
- `dmm-delivery-bridge-el.mo` - Compiled Greek translation (binary file)

## Compiling Translation Files

To compile the `.po` files into `.mo` files (required for WordPress to use translations), you can use one of these methods:

### Method 1: Using Poedit (Recommended)
1. Download and install [Poedit](https://poedit.net/)
2. Open the `.po` file in Poedit
3. Click "Save" - Poedit will automatically compile the `.mo` file

### Method 2: Using msgfmt (Command Line)
```bash
# On Linux/Mac
msgfmt -o dmm-delivery-bridge-el.mo dmm-delivery-bridge-el.po

# On Windows (if gettext is installed)
msgfmt.exe -o dmm-delivery-bridge-el.mo dmm-delivery-bridge-el.po
```

### Method 3: Using WordPress Tools
If you have WP-CLI installed:
```bash
wp i18n make-mo languages/
```

## Adding New Translations

1. Copy `dmm-delivery-bridge.pot` to create a new `.po` file (e.g., `dmm-delivery-bridge-fr.po` for French)
2. Translate all strings in the new `.po` file
3. Compile the `.po` file to create the `.mo` file
4. WordPress will automatically load the translation based on the site's language setting

## Supported Languages

- **English (en)** - Default language
- **Greek (el)** - Full translation available

## Text Domain

The plugin uses the text domain: `dmm-delivery-bridge`

Make sure all translation files follow the naming convention:
- `dmm-delivery-bridge-{locale}.po`
- `dmm-delivery-bridge-{locale}.mo`

Where `{locale}` is the language code (e.g., `el` for Greek, `fr` for French, etc.)

