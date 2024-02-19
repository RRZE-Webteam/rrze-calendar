# RRZE\WP\Plugin

This PHP library is designed to simplify the management of WordPress plugin files and directories. It provides methods for retrieving information about the plugin, such as its file path, basename, directory, URL, version, and slug.

## Usage

To use this class, simply instantiate it with the full path and filename of the plugin:
```php
$plugin = new RRZE\WP\Plugin(__FILE__);
```

Then call the loaded() method to initialize the plugin properties:
```php
$plugin->loaded();
```

You can now access various information about the plugin:
```php
$plugin_file = $plugin->getFile();
$basename = $plugin->getBasename();
$directory = $plugin->getDirectory();
$url = $plugin->getUrl();
$slug = $plugin->getSlug();
$version = $plugin->getVersion();
```

## Methods

- `getFile()`: Get the full path and filename of the plugin.
- `getBasename()`: Get the basename of the plugin.
- `getDirectory()`: Get the filesystem directory path for the plugin.
- `getPath(string $path)`: Get the filesystem directory path for a specific file or directory within the plugin.
- `getUrl(string $path)`: Get the URL directory path for a specific file or directory within the plugin.
- `getSlug()`: Get the slug of the plugin.
- `getVersion()`: Get the version of the plugin.
