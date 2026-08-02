# Custom Filter By Oswald

## Overview
"Custom Filter By Oswald" is a WooCommerce plugin that provides a customizable product filtering system. This plugin allows users to filter products based on search terms, stock status, price range, and product categories.

## Installation
1. Download the plugin files.
2. Upload the `custom-filter-by-oswald` folder to the `/wp-content/plugins/` directory of your WordPress installation.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage
1. ***With Plugin:*** To display the custom filter widget on your WooCommerce product archive pages, use the following shortcode in your desired page or post:

```
[custom_woo_filter]
```

2. ***Without Plugin:*** You can also copy and paste the ```index.html``` code in your WooCommerce html editor
## Features
- **Category Filtering**: Displays product categories in a hierarchical format with checkboxes for selection.
- **Search Filtering**: Adds a keyword search field for quick product lookup.
- **Stock Status Filtering**: Provides options to filter products based on their stock status (In Stock or Out of Stock).
- **Price Range Filtering**: Includes a price range slider for users to specify minimum and maximum price limits.

The repository also includes a standalone HTML demo in `new filter.html` that mirrors the same modern UI and live filtering behavior.

## File Structure
```
custom-filter-by-oswald
├── assets
│   ├── filter.css
│   └── filter.js
├── templates
│   └── filter-widget.php
├── custom-filter.php
└── README.md
```

## File Descriptions
- **custom-filter.php**: The main plugin file that registers the custom filter functionality, enqueues necessary CSS and JavaScript files, defines the shortcode for rendering the filter widget, and modifies the WooCommerce product query based on filter inputs.
- **templates/filter-widget.php**: Contains the HTML structure and PHP logic for the filter widget, retrieves product categories, and includes options for filtering.
- **assets/filter.js**: Handles the interactive elements of the filter widget, including toggling visibility and managing the price range slider.
- **assets/filter.css**: Styles the filter widget to ensure it is visually appealing and user-friendly.

## Support
For any issues or feature requests, please contact the author at [Oswald's contact information].