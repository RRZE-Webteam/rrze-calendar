# RRZE WordPress Package

The RRZE package for WordPress is a general-purpose package designed to simplify and streamline the development of plugins for WordPress.

## Installation

Install the latest version with

```bash
% composer require rrze/wp
```

## Features

### Plugin

The purpose of this PHP library is to streamline the handling of files and directories within WordPress plugins. It offers methods to access various details about the plugin, including its file path, basename, directory, URL, version, and slug.

[Read more](src/Plugin/README.md)

### Settings

This PHP library aims to simplify the creation of WordPress plugin settings pages, reducing reliance on the Settings API or custom code. It addresses the complexity of manual HTML coding for options and the integration of tabs and sections, streamlining the process for straightforward settings page creation.

[Read more](src/Settings/README.md)
