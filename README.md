# PLUGIN: WooCommerce Bulk Attach Product Images

A simple WooCommerce plugin to bulk attach main product images to products and their children via Action Scheduler.

Using this plugin is a good way to get started with attaching images to your products post CSV import.

### The plugin searches the Media Library for matching images, assuming the following:

- Your products have SKUs assigned to them
- Your product images have titles which contain at least part of the aforementioned SKU

### Caveats:

- Successful matching will increase the closer your image title matches the product's SKU
- Images are overwritten with each run
- Matching rate with good SKU correlation between products and SKU is 90 to 100%
