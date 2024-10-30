# User Guide
This plugin is for extracting the products of WordPress stores that use the WooCommerce plugin, through accessing a web service to receive all products and update the specifications of each product.

## Requirements
* WordPress version 5.2.0 and above
* WooCommerce version 4.0.0 and above
* Changing the mode of unique links to an option other than Plain (this is done by default in new versions of WordPress)

## How to use
Assuming that your WooCommerce store is active, installing this plugin is no different from the usual WordPress plugins and you can easily install it.
After installing the plugin, be sure to click on the activation option to activate the plugin.
Please notify Isee support at any stage of the installation if you have any problems or if you encounter an error related to the plugin.

## Installation
1. Just copy the plugin into `/wp-content/plugins/` folder or install it directly from the WordPress plugins repository.
2. Then activate the plugin from the plugins section of your dashboard.

### Compile sass
```bash
sass --watch admin/static/sass/main.scss:admin/static/css/styles.css --style compressed
```