<?php

/*
Plugin Name: WC Attach Images
Description: Bulk attached images to products, assuming that the images are already in the media library and have the product SKU in the filename.
Version: 1.0.0
Author: Engage24
Author URI: https://engage24.com
Text Domain: wc-attach-images
Domain Path: /languages
License: GPL v2 or later
Requires at least: 5.0
Requires PHP: 7.0
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Add the admin menu item
function wc_attach_images_menu()
{
    add_submenu_page(
        'woocommerce',
        'Bulk Attach Images',
        'Bulk Attach Images',
        'manage_options',
        'wc-attach-images',
        'wc_attach_images_page'
    );
}

add_action('admin_menu', 'wc_attach_images_menu');

// Add the admin page
function wc_attach_images_page()
{

    if (isset($_POST['attach-images'])) {

        // if action has been scheduled display success message
        echo '<div class="notice notice-success is-dismissible"><p>Images will be attached to products in the background. You can view a log of the process under WooCommerce -> Status -> Logs once the process has run.</p></div>';
    }

?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Click the button below to bulk attach images to products, assuming that the images are already in the media library and have the product SKU (or part of it) in the filename.</p>
        <p>You can view a log of the process under WooCommerce -> Status -> Logs once the process has run.</p>

        <form action="#" method="post">
            <input type="submit" value="Attach Images" class="button button-primary button-large" name="attach-images" id="attach-images" />
        </form>

    </div>
<?php
}

add_action('init', function () {
    if (isset($_POST['attach-images']) && false === as_has_scheduled_action('bulk_attach_product_images')) :
        as_schedule_single_action(time(), 'bulk_attach_product_images', array());
    endif;
});

add_action('bulk_attach_product_images', function () {
    wc_attach_images_process_products();
});

/**
 * Function to process attachment of images to products
 *
 * @param  int  $page
 *
 * @return void
 */
function wc_attach_images_process_products($page = 1)
{

    // log separator
    wc_attach_images_log('======================================================================================================');

    // log start time
    wc_attach_images_log('Starting process at: ' . date('Y-m-d H:i:s'));

    // log querying products message
    wc_attach_images_log('Querying products...');

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 100,
        'paged'          => $page,
        // 'meta_query'     => array(
        //     array(
        //         'key'     => '_thumbnail_id',
        //         'compare' => 'NOT EXISTS',
        //     ),
        // ),
    );

    $products = new WP_Query($args);

    // counter
    $i = 0;

    if ($products->have_posts()) :

        // log products found message
        wc_attach_images_log('Products found, starting product loop...');

        while ($products->have_posts()) :
            $products->the_post();

            $product_id  = get_the_ID();

            // log retrieving product object message
            wc_attach_images_log('Retrieving product object for product: ' . $product_id);

            $product_object = wc_get_product($product_id);

            $product_sku = html_entity_decode(trim($product_object->get_sku()));

            // if no sku, or sku is empty, skip product
            if (!$product_sku) :
                // log product has no sku message
                wc_attach_images_log('Product has no SKU, skipping product: ' . $product_id);
                continue;
            endif;

            // log retrieving product sku message
            wc_attach_images_log('Retrieving product SKU for product: ' . $product_sku);

            // $last_dash_pos = strrpos($product_sku, '-');

            // $search_term = str_replace('-', ' ', substr($product_sku, 0, $last_dash_pos));
            $search_term = str_replace(['-', '/', '\\', '_', ',', '.'], ' ', $product_sku);

            // remove any integers from the end of the search term IF there is a space before the integer
            $search_term = preg_replace('/\s\d+$/', '', $search_term);

            // log searching media library message
            wc_attach_images_log('Searching media library for following keyword: ' . $search_term);

            $media_id = query_media_items($search_term);

            // log media id found message
            wc_attach_images_log('Media ID found: ' . $media_id);

            if ($media_id) :

                // log attaching image to product message
                wc_attach_images_log('Attaching image to product: ' . $product_id);

                $product_object->set_image_id($media_id);

                $product_object->save();

                // log image attached to product message
                wc_attach_images_log('Image attached to product: ' . $product_id);

                // of product has variations, attach image to each variation
                if ($product_object->has_child()) :

                    // log product has variations message
                    wc_attach_images_log('Product has variations, starting variation loop...');

                    $variations = $product_object->get_children();

                    if (is_array($variations) && !empty($variations)) :

                        foreach ($variations as $variation) :

                            // log retrieving variation object message
                            wc_attach_images_log('Retrieving variation object for variation: ' . $variation);

                            $variation_object = wc_get_product($variation);

                            // log attaching image to variation message
                            wc_attach_images_log('Attaching image to variation: ' . $variation);

                            $variation_object->set_image_id($media_id);

                            $variation_object->save();

                        endforeach;

                    endif;

                endif;

            else :

                // log no media id found message
                wc_attach_images_log('No media ID found for product: ' . get_the_title($product_id)) . ' - [ID ' . $product_id . ']';

            endif;

            // Increment counter
            $i++;

            // if counter reaches 100, rest, increment page and process next batch of products
            if ($i == 100) :

                // log rest message
                wc_attach_images_log('RESTING...');

                // Rest between batches
                sleep(5);

                // log rest complete message
                wc_attach_images_log('REST COMPLETE, MOVING TO NEXT BATCH...');

                // Increment page
                $page++;

                // Process next batch of products
                wc_attach_images_process_products($page);
            endif;

        endwhile;

    endif;

    // log end time
    wc_attach_images_log('Ending process at: ' . date('Y-m-d H:i:s'));

    // log memory usage
    wc_attach_images_log('Memory usage: ' . memory_get_usage());

    // log memory peak usage
    wc_attach_images_log('Memory peak usage: ' . memory_get_peak_usage());

    // log memory limit
    wc_attach_images_log('Memory limit: ' . ini_get('memory_limit'));

    // log separator
    wc_attach_images_log('======================================================================================================');
}

/**
 * Log messages to WooCommerce log
 *
 * @param  string $message
 *
 * @return void
 */
function wc_attach_images_log($message)
{
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->log('wc-attach-images:', $message);
    }
}

/**
 * Query and return media items which are not attached to a product
 *
 * @return array - media items
 */
function query_media_items($search_term)
{

    // query and return media items which are not attached to a product
    $args = array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'inherit',
        'post_parent'    => 0,
        's'              => $search_term,
    );

    $media_query = new WP_Query($args);

    if ($media_query->have_posts()) :

        while ($media_query->have_posts()) :
            $media_query->the_post();

            $media_id = get_the_ID();

            return $media_id;

        endwhile;

    else :

        return false;

    endif;
}


?>