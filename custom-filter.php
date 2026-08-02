<?php
/**
 * Plugin Name: Custom Filter by Oswald
 * Description: A custom WooCommerce filter integrated with ShopEngine.
 * Version: 1.0
 * Author: Oswald
 */

defined('ABSPATH') || exit;

function oswald_get_price_bounds() {
    static $bounds = null;

    if ($bounds !== null) {
        return $bounds;
    }

    global $wpdb;

    $min_db_price = $wpdb->get_var("SELECT MIN(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value > 0");
    $max_db_price = $wpdb->get_var("SELECT MAX(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value > 0");

    $bounds = array(
        'min' => $min_db_price ? floor($min_db_price) : 0,
        'max' => $max_db_price ? ceil($max_db_price) : 1000,
    );

    return $bounds;
}

// Match WooBeeWoo parameter handling
function oswald_add_woobewoo_compat_params() {
    // CRITICAL: Check for category levels with proper parameter format
    $has_category_filter = false;
    foreach ($_GET as $key => $value) {
        if (strpos($key, 'wpf_filter_cat_') === 0 && !empty($value)) {
            $has_category_filter = true;
            break;
        }
    }
    
    // Add default product count when filtering by category
    if (!isset($_GET['wpf_count']) && $has_category_filter) {
        $_GET['wpf_count'] = 60;
    }
    
    // Filter behavior flag (like WooBeeWoo)
    if (!isset($_GET['wpf_fbv'])) {
        // Check for any filter parameter
        $has_filter = $has_category_filter || 
                 (isset($_GET['s']) && trim((string) wp_unslash($_GET['s'])) !== '') || 
                     isset($_GET['wpf_min_price']) || 
                     isset($_GET['wpf_max_price']) || 
                     isset($_GET['pr_onsale']) || 
                     isset($_GET['pr_stock']);
        
        if ($has_filter) {
            $_GET['wpf_fbv'] = 1;
        }
    }
}
add_action('wp', 'oswald_add_woobewoo_compat_params', 5);

function oswald_enqueue_filter_assets() {
    if (!is_admin()) {
        // Add jQuery UI styles for the slider
        wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_style('oswald-filter-style', plugin_dir_url(__FILE__) . 'assets/filter.css', array(), time());
        
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_script('oswald-filter-script', plugin_dir_url(__FILE__) . 'assets/filter.js', array('jquery', 'jquery-ui-slider'), time(), true);
        
        // Pass AJAX URL to JavaScript
        wp_localize_script('oswald-filter-script', 'oswald_filter_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oswald-filter-nonce'),
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
            'price_decimals' => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0,
        ));
    }
}
add_action('wp_enqueue_scripts', 'oswald_enqueue_filter_assets');

function oswald_render_filter_widget() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/filter-widget.php';
    return ob_get_clean();
}
add_shortcode('custom_woo_filter', 'oswald_render_filter_widget');

function oswald_render_filter_widget_auto() {
    if (is_admin()) {
        return;
    }

    if (!function_exists('is_shop') || !function_exists('is_product_taxonomy')) {
        return;
    }

    if (!is_shop() && !is_product_taxonomy()) {
        return;
    }

    echo oswald_render_filter_widget();
}
add_action('woocommerce_before_shop_loop', 'oswald_render_filter_widget_auto', 5);

// Hook into pre_get_posts to filter all product queries
function oswald_filter_products_query($query) {
    if (is_admin()) {
        return;
    }
    
    // Only apply to main query or product queries
    if ($query->is_main_query() || 
        (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == 'product')) {

        if (isset($_GET['s']) && trim((string) wp_unslash($_GET['s'])) !== '') {
            $query->set('s', sanitize_text_field(wp_unslash($_GET['s'])));
        }
        
        // Create meta_query array if it doesn't exist
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        
        // Create tax_query array if it doesn't exist
        $tax_query = $query->get('tax_query');
        if (!is_array($tax_query)) {
            $tax_query = array();
        }
        
        // On sale filter - pr_onsale
        if (isset($_GET['pr_onsale']) && $_GET['pr_onsale'] == '1') {
            $on_sale_products = wc_get_product_ids_on_sale();
            if (!empty($on_sale_products)) {
                // Get existing post__in
                $post__in = $query->get('post__in');
                
                // If post__in is already set, get intersection
                if (!empty($post__in)) {
                    $post__in = array_intersect($post__in, $on_sale_products);
                    if (empty($post__in)) {
                        $post__in = array(0);
                    }
                } else {
                    $post__in = $on_sale_products;
                }
                
                $query->set('post__in', $post__in);
            }
        }
        
        // Stock status filter - pr_stock
        if (isset($_GET['pr_stock']) && !empty($_GET['pr_stock'])) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => sanitize_text_field($_GET['pr_stock']),
                'compare' => '=',
            );
        }
        
        // Price range filter - wpf_min_price and wpf_max_price
        // FIXED: Only apply price filter if values have been changed from defaults
        $price_bounds = oswald_get_price_bounds();
        $min_default = $price_bounds['min'];
        $max_default = $price_bounds['max'];
        
        if (isset($_GET['wpf_min_price']) && isset($_GET['wpf_max_price'])) {
            $min_price = floatval($_GET['wpf_min_price']);
            $max_price = floatval($_GET['wpf_max_price']);
            
            // Only add price filter if values differ from defaults
            if ($min_price != $min_default || $max_price != $max_default) {
                $meta_query[] = array(
                    'key'     => '_price',
                    'value'   => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                );
            }
        }
        
        // Category filters - FIXED: Use proper category level IDs from WooBeeWoo
        $category_ids = [];
        $cat_params = [];
        
        // First, collect all the category parameters
        foreach ($_GET as $key => $value) {
            if (preg_match('/^wpf_filter_cat_(\d+)$/', $key, $matches) && !empty($value)) {
                $level = $matches[1];
                $cat_params[$level] = $value;
            }
        }
        
        // WooBeeWoo stores the actual category term_id in these parameters
        // No need to convert the level to term_id - the value is already the term_id
        foreach ($cat_params as $level => $term_id) {
            if (is_array($term_id)) {
                foreach ($term_id as $id) {
                    if (!empty($id)) {
                        $category_ids[] = intval($id);
                    }
                }
            } else {
                if (!empty($term_id)) {
                    $category_ids[] = intval($term_id);
                }
            }
        }
        
        if (!empty($category_ids)) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_ids,
                'operator' => 'IN',
            );
        }
        
        // WooBeeWoo compatibility - handle their parameters
        if (isset($_GET['wpf_count']) && is_numeric($_GET['wpf_count'])) {
            // Set posts per page based on wpf_count parameter
            $posts_per_page = intval($_GET['wpf_count']);
            $query->set('posts_per_page', $posts_per_page);
        }
        
        // Handle filter behavior based on wpf_fbv parameter
        if (isset($_GET['wpf_fbv']) && $_GET['wpf_fbv'] == '1') {
            $query->set('suppress_filters', false);
        }
        
        // Set the modified queries back
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
        
        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }
    }
}
// Use high priority (999) to make sure our filter runs after other plugins
add_action('pre_get_posts', 'oswald_filter_products_query', 999);
add_action('woocommerce_product_query', 'oswald_filter_products_query', 999);

// Handle AJAX filtering
function oswald_ajax_filter_products() {
    // Verify nonce
    check_ajax_referer('oswald-filter-nonce', 'nonce');
    
    // Set up query args
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => isset($_POST['wpf_count']) ? intval($_POST['wpf_count']) : 60,
        'post_status'    => 'publish',
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            ),
        ),
        'meta_query'     => array(),
    );

    if (isset($_POST['s']) && trim((string) wp_unslash($_POST['s'])) !== '') {
        $args['s'] = sanitize_text_field(wp_unslash($_POST['s']));
    }
    
    // On sale filter - pr_onsale
    if (isset($_POST['pr_onsale']) && $_POST['pr_onsale'] == '1') {
        $product_ids_on_sale = wc_get_product_ids_on_sale();
        if (!empty($product_ids_on_sale)) {
            $args['post__in'] = $product_ids_on_sale;
        }
    }
    
    // Stock status filter - pr_stock
    if (isset($_POST['pr_stock']) && !empty($_POST['pr_stock'])) {
        $args['meta_query'][] = array(
            'key'     => '_stock_status',
            'value'   => sanitize_text_field($_POST['pr_stock']),
            'compare' => '=',
        );
    }
    
    // Price range filter - wpf_min_price and wpf_max_price
    // FIXED: Only apply price filter if values have been changed from defaults
    $price_bounds = oswald_get_price_bounds();
    $min_default = $price_bounds['min'];
    $max_default = $price_bounds['max'];
    
    if (isset($_POST['wpf_min_price']) && isset($_POST['wpf_max_price'])) {
        $min_price = floatval($_POST['wpf_min_price']);
        $max_price = floatval($_POST['wpf_max_price']);
        
        // Only add price filter if values differ from defaults
        if ($min_price != $min_default || $max_price != $max_default) {
            $args['meta_query'][] = array(
                'key'     => '_price',
                'value'   => array($min_price, $max_price),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            );
        }
    }
    
    // Category filters - FIXED: Use proper category level IDs from WooBeeWoo
    $category_ids = [];
    $cat_params = [];
    
    // First, collect all the category parameters
    foreach ($_POST as $key => $value) {
        if (preg_match('/^wpf_filter_cat_(\d+)$/', $key, $matches) && !empty($value)) {
            $level = $matches[1]; 
            $cat_params[$level] = $value;
        }
    }
    
    // WooBeeWoo stores the actual category term_id in these parameters
    foreach ($cat_params as $level => $term_id) {
        if (is_array($term_id)) {
            foreach ($term_id as $id) {
                if (!empty($id)) {
                    $category_ids[] = intval($id);
                }
            }
        } else {
            if (!empty($term_id)) {
                $category_ids[] = intval($term_id);
            }
        }
    }
    
    if (!empty($category_ids)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_ids,
            'operator' => 'IN',
        );
    }
    
    // Run the query
    $products_query = new WP_Query($args);
    
    ob_start();
    
    if ($products_query->have_posts()) {
        // Output product grid
        echo '<ul class="products columns-4">';
        
        while ($products_query->have_posts()) {
            $products_query->the_post();
            wc_get_template_part('content', 'product');
        }
        
        echo '</ul>';
    } else {
        echo '<p class="woocommerce-info">No products found matching your criteria.</p>';
    }
    
    wp_reset_postdata();
    
    // Get the HTML output
    $html = ob_get_clean();
    
    // Send the response
    wp_send_json_success(array('html' => $html));
}
add_action('wp_ajax_oswald_ajax_filter', 'oswald_ajax_filter_products');
add_action('wp_ajax_nopriv_oswald_ajax_filter', 'oswald_ajax_filter_products');

// Filter products in ShopEngine and page builders
function oswald_filter_shopengine_products($query_args) {
    // On sale filter - pr_onsale
    if (isset($_GET['pr_onsale']) && $_GET['pr_onsale'] == '1') {
        $on_sale_products = wc_get_product_ids_on_sale();
        if (!empty($on_sale_products)) {
            $query_args['post__in'] = isset($query_args['post__in']) ? 
                array_intersect($query_args['post__in'], $on_sale_products) : 
                $on_sale_products;
                
            if (empty($query_args['post__in'])) {
                $query_args['post__in'] = array(0);
            }
        }
    }
    
    // Stock status filter - pr_stock
    if (isset($_GET['pr_stock']) && !empty($_GET['pr_stock'])) {
        if (!isset($query_args['meta_query']) || !is_array($query_args['meta_query'])) {
            $query_args['meta_query'] = array();
        }
        
        $query_args['meta_query'][] = array(
            'key'     => '_stock_status',
            'value'   => sanitize_text_field($_GET['pr_stock']),
            'compare' => '=',
        );
    }
    
    // Price range filter - wpf_min_price and wpf_max_price
    // FIXED: Only apply if values have been changed from defaults
    global $wpdb;
    $min_db_price = $wpdb->get_var("SELECT MIN(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value > 0");
    $max_db_price = $wpdb->get_var("SELECT MAX(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value > 0");
    $min_default = $min_db_price ? floor($min_db_price) : 0;
    $max_default = $max_db_price ? ceil($max_db_price) : 1000;
    
    if (isset($_GET['wpf_min_price']) && isset($_GET['wpf_max_price'])) {
        $min_price = floatval($_GET['wpf_min_price']);
        $max_price = floatval($_GET['wpf_max_price']);
        
        // Only add price filter if values differ from defaults
        if ($min_price != $min_default || $max_price != $max_default) {
            if (!isset($query_args['meta_query']) || !is_array($query_args['meta_query'])) {
                $query_args['meta_query'] = array();
            }
            
            $query_args['meta_query'][] = array(
                'key'     => '_price',
                'value'   => array($min_price, $max_price),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            );
        }
    }
    
    // Category filters - FIXED: Use proper category level IDs from WooBeeWoo
    $category_ids = [];
    $cat_params = [];
    
    // First, collect all the category parameters
    foreach ($_GET as $key => $value) {
        if (preg_match('/^wpf_filter_cat_(\d+)$/', $key, $matches) && !empty($value)) {
            $level = $matches[1];
            $cat_params[$level] = $value;
        }
    }
    
    // WooBeeWoo stores the actual category term_id in these parameters
    foreach ($cat_params as $level => $term_id) {
        if (is_array($term_id)) {
            foreach ($term_id as $id) {
                if (!empty($id)) {
                    $category_ids[] = intval($id);
                }
            }
        } else {
            if (!empty($term_id)) {
                $category_ids[] = intval($term_id);
            }
        }
    }
    
    if (!empty($category_ids)) {
        if (!isset($query_args['tax_query']) || !is_array($query_args['tax_query'])) {
            $query_args['tax_query'] = array();
        }
        
        $query_args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_ids,
            'operator' => 'IN',
        );
    }

    if (isset($_GET['s']) && trim((string) wp_unslash($_GET['s'])) !== '') {
        $query_args['s'] = sanitize_text_field(wp_unslash($_GET['s']));
    }
    
    // WooBeeWoo compatibility - handle their parameters
    if (isset($_GET['wpf_count']) && is_numeric($_GET['wpf_count'])) {
        $query_args['posts_per_page'] = intval($_GET['wpf_count']);
    }
    
    return $query_args;
}

// Add filters with high priority (999) to override other plugins
add_filter('shopengine_product_list_query_args', 'oswald_filter_shopengine_products', 999);
add_filter('woocommerce_shortcode_products_query', 'oswald_filter_shopengine_products', 999);
add_filter('woocommerce_related_products_args', 'oswald_filter_shopengine_products', 999);
add_filter('elementor/query/query_args', 'oswald_filter_shopengine_products', 999);

// Integrate with WooCommerce filters like WooBeeWoo
function oswald_integrate_with_woocommerce() {
    // Only run on the products page or when filtering
    if (!is_page('products') && !isset($_GET['wpf_fbv'])) {
        return;
    }
    
    // Apply filter to ShopEngine modules too
    add_filter('shopengine/module/woocommerce/query_args', 'oswald_filter_shopengine_products', 999);
    
    // Add WooBeeWoo-style filtering to WooCommerce's built-in filtering
    add_action('woocommerce_before_shop_loop', function() {
        if (isset($_GET['wpf_fbv'])) {
            wc_enqueue_js("
                jQuery(document).trigger('woobewoo_filter_applied');
            ");
        }
    });
}
add_action('wp', 'oswald_integrate_with_woocommerce');