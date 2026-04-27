<?php
/**
 * Plugin Name: Plastic Wallet Shop - Custom Pricing System
 * Plugin URI: https://plasticwalletshop.co.uk
 * Description: A powerful, enterprise-grade WooCommerce extension for dynamic product pricing, custom sizing, and seamless cart integration. Features include real-time price calculations, bulk order suggestions, A-size configurations, and a beautiful, conversion-optimized checkout experience.
 * Version: 12.2.0
 * Author: Shaan - Full Stack Developer
 * Author URI: https://plasticwalletshop.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plastic-wallet-shop
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'PWS_VERSION', '12.2.0' );
define( 'PWS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PWS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare WooCommerce HPOS (High-Performance Order Storage) compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Declare WooCommerce Cart/Checkout Blocks compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

class PWS_Pricing_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Shortcodes
        add_shortcode('pws_product_selector', array($this, 'render_product_selector'));
        add_shortcode('pws_product_options', array($this, 'render_product_options'));
        add_shortcode('pws_checkbox', array($this, 'render_single_checkbox'));
        add_shortcode('pws_cart', array($this, 'render_custom_cart'));
        add_shortcode('pws_custom_sizes', array($this, 'render_custom_sizes'));
        add_shortcode('pws_a_sizes', array($this, 'render_a_sizes'));
        add_shortcode('pws_category_page', array($this, 'render_category_page'));
        
        // Dynamic product shortcodes for Elementor
        add_shortcode('pws_product_card', array($this, 'render_product_card'));
        add_shortcode('pws_product_image', array($this, 'render_product_image'));
        add_shortcode('pws_product_title', array($this, 'render_product_title'));
        add_shortcode('pws_product_description', array($this, 'render_product_description'));
        add_shortcode('pws_category_products', array($this, 'render_category_products'));
        add_shortcode('pws_main_image', array($this, 'render_main_image')); // Dynamic main image
        
        // AJAX - Product
        add_action('wp_ajax_pws_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_pws_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_pws_get_product_data', array($this, 'ajax_get_product_data'));
        add_action('wp_ajax_nopriv_pws_get_product_data', array($this, 'ajax_get_product_data'));
        add_action('wp_ajax_pws_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_pws_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // AJAX - Custom Sizes
        add_action('wp_ajax_pws_calculate_custom_price', array($this, 'ajax_calculate_custom_price'));
        add_action('wp_ajax_nopriv_pws_calculate_custom_price', array($this, 'ajax_calculate_custom_price'));
        add_action('wp_ajax_pws_add_custom_to_cart', array($this, 'ajax_add_custom_to_cart'));
        add_action('wp_ajax_nopriv_pws_add_custom_to_cart', array($this, 'ajax_add_custom_to_cart'));
        
        // AJAX - A Sizes
        add_action('wp_ajax_pws_calculate_a_size_price', array($this, 'ajax_calculate_a_size_price'));
        add_action('wp_ajax_nopriv_pws_calculate_a_size_price', array($this, 'ajax_calculate_a_size_price'));
        add_action('wp_ajax_pws_add_a_size_to_cart', array($this, 'ajax_add_a_size_to_cart'));
        add_action('wp_ajax_nopriv_pws_add_a_size_to_cart', array($this, 'ajax_add_a_size_to_cart'));
        add_action('wp_ajax_pws_save_a_sizes_config', array($this, 'ajax_save_a_sizes_config'));
        add_action('wp_ajax_pws_delete_a_size_pricing', array($this, 'ajax_delete_a_size_pricing'));
        
        // AJAX - Cart
        add_action('wp_ajax_pws_remove_cart_item', array($this, 'ajax_remove_cart_item'));
        add_action('wp_ajax_nopriv_pws_remove_cart_item', array($this, 'ajax_remove_cart_item'));
        add_action('wp_ajax_pws_update_cart_qty', array($this, 'ajax_update_cart_qty'));
        add_action('wp_ajax_nopriv_pws_update_cart_qty', array($this, 'ajax_update_cart_qty'));
        add_action('wp_ajax_pws_empty_cart', array($this, 'ajax_empty_cart'));
        add_action('wp_ajax_nopriv_pws_empty_cart', array($this, 'ajax_empty_cart'));
        
        // AJAX - Category Mappings (updated for unlimited products)
        add_action('wp_ajax_pws_save_category_mapping', array($this, 'ajax_save_category_mapping'));
        add_action('wp_ajax_pws_delete_category_mapping', array($this, 'ajax_delete_category_mapping'));
        add_action('wp_ajax_pws_reset_all_defaults', array($this, 'ajax_reset_all_defaults'));
        
        // AJAX - Product Sizes (NEW in v10)
        add_action('wp_ajax_pws_save_product_sizes', array($this, 'ajax_save_product_sizes'));
        add_action('wp_ajax_pws_add_product_size', array($this, 'ajax_add_product_size'));
        add_action('wp_ajax_pws_delete_product_size', array($this, 'ajax_delete_product_size'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_pws_save_featured_products', array($this, 'ajax_save_featured_products'));
        add_action('wp_ajax_pws_save_pricing', array($this, 'ajax_save_pricing'));
        add_action('wp_ajax_pws_delete_pricing', array($this, 'ajax_delete_pricing'));
        add_action('wp_ajax_pws_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_pws_export_csv', array($this, 'export_csv'));
        
        // Category Template Override (v10 - takes over WooCommerce category pages)
        add_action('template_redirect', array($this, 'maybe_override_category_template'));
        
        // Cart Integration
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);
        add_action('woocommerce_before_calculate_totals', array($this, 'set_cart_item_price'), 20, 1);
        
        // WC Session + Fragment (T7)
        add_action('woocommerce_init', array($this, 'maybe_start_wc_session'));
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'pws_cart_count_fragment'));
        
        // Cart page ID shim so WC treats [pws_cart] page as cart (T9)
        add_filter('woocommerce_cart_page_id', array($this, 'get_pws_cart_page_id'));
        // Also make is_cart() return true on the [pws_cart] page (ensures WC loads
        // cart scripts incl. wc-cart-fragments, so fragment refresh works natively)
        add_filter('woocommerce_is_cart', array($this, 'pws_is_cart_page'));
        
        // Basket badge on all pages (T8)
        add_action('wp_head', array($this, 'output_basket_badge_styles'));
        add_action('wp_footer', array($this, 'output_basket_badge_init'));
        
        // AJAX: Update cart (T8)
        add_action('wp_ajax_pws_update_cart', array($this, 'ajax_update_cart'));
        add_action('wp_ajax_nopriv_pws_update_cart', array($this, 'ajax_update_cart'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    /**
     * Override WooCommerce category template for mapped categories
     */
    public function maybe_override_category_template() {
        if (!is_product_category()) return;
        
        $category = get_queried_object();
        if (!$category) return;
        
        $mappings = $this->get_category_mappings();
        if (!isset($mappings[$category->slug])) return;
        
        // We have a mapping - override the template
        add_filter('template_include', array($this, 'load_category_template'));
    }
    
    /**
     * Load our custom category template
     */
    public function load_category_template($template) {
        // Check if our template exists
        $custom_template = PWS_PLUGIN_PATH . 'templates/category-page.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
        return $template;
    }
    
    private function get_featured_product_ids() {
        return array_filter(array_map('intval', (array)get_option('pws_featured_products', array(3917, 3918, 3919, 3920))));
    }
    
    public function get_pricing_slug($product_id) {
        if (!$product_id) return '';
        
        // First: Check if product ID is in our Product Sizes config
        $product_sizes = $this->get_product_sizes_config();
        foreach ($product_sizes as $slug => $data) {
            if (isset($data['wc_product_id']) && intval($data['wc_product_id']) === intval($product_id)) {
                return $slug;
            }
        }
        
        // Second: Try product mappings option
        $mappings = get_option('pws_product_mappings', array());
        if (isset($mappings[$product_id])) return $mappings[$product_id];
        
        // Third: Generate slug from WC product name
        if (!function_exists('wc_get_product')) return '';
        $product = wc_get_product($product_id);
        if (!$product) return '';
        $name = strtolower(trim($product->get_name()));
        $slug = sanitize_title($name);
        
        return $slug;
    }
    
    /**
     * Get category mappings from database
     */
    public function get_category_mappings() {
        $mappings = get_option('pws_category_mappings', array());
        $defaults = $this->get_default_category_mappings_v2();
        
        // If empty, use defaults
        if (empty($mappings)) {
            update_option('pws_category_mappings', $defaults);
            return $defaults;
        }
        
        // VALIDATION: Check if we have the new format with correct categories
        // Old format had: 'travel', 'home-filing', etc.
        // New format has: 'organise', 'keep-safe', 'for-business'
        $has_organise = isset($mappings['organise']);
        $has_keep_safe = isset($mappings['keep-safe']);
        $has_for_business = isset($mappings['for-business']);
        
        // If any of the main categories are missing, reset to defaults
        if (!$has_organise || !$has_keep_safe || !$has_for_business) {
            update_option('pws_category_mappings', $defaults);
            return $defaults;
        }
        
        // Also check if 'organise' has the new structure with 'products' key
        if (!isset($mappings['organise']['products']) || !is_array($mappings['organise']['products'])) {
            // Old format - reset
            update_option('pws_category_mappings', $defaults);
            return $defaults;
        }
        
        return $mappings;
    }
    
    /**
     * Default category mappings (v2 - with ordering)
     */
    private function get_default_category_mappings_v2() {
        return array(
            'organise' => array(
                'products' => array(3917, 3918, 3919, 3920, 3923, 3925, 5019, 5020),
                'order' => array(3917 => 1, 3918 => 2, 3919 => 3, 3920 => 4, 3923 => 5, 3925 => 6, 5019 => 7, 5020 => 8)
            ),
            'keep-safe' => array(
                'products' => array(3921, 3922, 3933, 3935, 3937, 3940, 3941, 5120),
                'order' => array(3921 => 1, 3922 => 2, 3933 => 3, 3935 => 4, 3937 => 5, 3940 => 6, 3941 => 7, 5120 => 8)
            ),
            'for-business' => array(
                'products' => array(3927, 3929, 3931, 3932, 5024, 5025, 5026, 5027, 5028),
                'order' => array(3927 => 1, 3929 => 2, 3931 => 3, 3932 => 4, 5024 => 5, 5025 => 6, 5026 => 7, 5027 => 8, 5028 => 9)
            )
        );
    }
    
    /**
     * Legacy default mappings (kept for backwards compatibility)
     */
    private function get_default_category_mappings() {
        return $this->get_default_category_mappings_v2();
    }
    
    /**
     * Get products for a category slug (sorted by order)
     */
    public function get_category_products($category_slug) {
        $mappings = $this->get_category_mappings();
        $category_slug = sanitize_title($category_slug);
        
        if (!isset($mappings[$category_slug])) {
            return array();
        }
        
        $mapping = $mappings[$category_slug];
        
        // Handle new structure (v2) with 'products' and 'order' arrays
        if (isset($mapping['products']) && is_array($mapping['products'])) {
            $products = array_filter(array_map('intval', $mapping['products']));
            
            // Sort by order if available
            if (isset($mapping['order']) && is_array($mapping['order'])) {
                usort($products, function($a, $b) use ($mapping) {
                    $order_a = isset($mapping['order'][$a]) ? $mapping['order'][$a] : 999;
                    $order_b = isset($mapping['order'][$b]) ? $mapping['order'][$b] : 999;
                    return $order_a - $order_b;
                });
            }
            
            // Filter out products that no longer exist in WooCommerce
            $products = array_filter($products, function($product_id) {
                return wc_get_product($product_id) !== false;
            });
            
            return array_values($products);
        }
        
        // Handle legacy structure (simple array of product IDs)
        if (is_array($mapping)) {
            $products = array_filter(array_map('intval', $mapping));
            return array_values(array_filter($products, function($product_id) {
                return wc_get_product($product_id) !== false;
            }));
        }
        
        return array();
    }
    
    /**
     * Check if current category has mapping
     */
    public function has_category_mapping() {
        if (!is_product_category()) return false;
        $category = get_queried_object();
        if (!$category) return false;
        $products = $this->get_category_products($category->slug);
        return !empty($products);
    }
    
    /**
     * Override category template to show our custom page
     */
    public function category_template_override($template) {
        if ($this->has_category_mapping()) {
            $category = get_queried_object();
            $products = $this->get_category_products($category->slug);
            $this->enqueue_category_assets($products);
            
            // Remove ALL WooCommerce shop content
            remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
            remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
            remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
            remove_action('woocommerce_archive_description', 'woocommerce_product_archive_description', 10);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
            remove_action('woocommerce_shop_loop', 'woocommerce_shop_loop', 10);
            remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);
            remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
            remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
            
            // Remove product loop
            add_filter('woocommerce_product_loop_start', '__return_empty_string');
            add_filter('woocommerce_product_loop_end', '__return_empty_string');
            add_filter('woocommerce_before_shop_loop', array($this, 'output_category_page_start'), 5);
            add_filter('woocommerce_no_products_found', array($this, 'output_category_page_content'));
            
            // This is key - prevent product loop from running
            add_filter('woocommerce_product_query', array($this, 'empty_product_query'));
        }
        return $template;
    }
    
    /**
     * Empty the product query to prevent shop loop
     */
    public function empty_product_query($query) {
        if ($this->has_category_mapping()) {
            $query->set('post__in', array(0)); // Return no products
        }
    }
    
    /**
     * Output category page start
     */
    public function output_category_page_start() {
        if ($this->has_category_mapping()) {
            $category = get_queried_object();
            echo $this->render_category_page(array('category' => $category->slug));
        }
    }
    
    /**
     * Output category page content (fallback)
     */
    public function output_category_page_content() {
        if ($this->has_category_mapping()) {
            $category = get_queried_object();
            echo $this->render_category_page(array('category' => $category->slug));
        }
    }
    
    /**
     * Enqueue assets for category pages
     */
    public function enqueue_category_assets($product_ids = array()) {
        // Always use filemtime for cache busting
        $css_version = file_exists(PWS_PLUGIN_PATH . 'assets/css/pws-styles.css') 
            ? filemtime(PWS_PLUGIN_PATH . 'assets/css/pws-styles.css') 
            : PWS_VERSION;
        $js_version = file_exists(PWS_PLUGIN_PATH . 'assets/js/pws-scripts.js') 
            ? filemtime(PWS_PLUGIN_PATH . 'assets/js/pws-scripts.js') 
            : PWS_VERSION;
        
        wp_enqueue_style('pws-styles', PWS_PLUGIN_URL . 'assets/css/pws-styles.css', array(), $css_version);
        wp_enqueue_script('pws-scripts', PWS_PLUGIN_URL . 'assets/js/pws-scripts.js', array('jquery'), $js_version, true);
        
        $first_product = !empty($product_ids) ? $product_ids[0] : 0;
        
        wp_localize_script('pws-scripts', 'pws_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pws_nonce'),
            'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
            'shop_url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '/',
            'current_product_id' => $first_product,
            'current_pricing_slug' => $this->get_pricing_slug($first_product),
            'featured_ids' => $product_ids,
            'cart_count' => (function_exists('WC') && WC()->cart) ? count(WC()->cart->get_cart()) : 0,
        ));
    }
    
    public function enqueue_assets() {
        // Load on product pages, cart page, category pages, and shortcode pages
        $load_assets = false;
        if (function_exists('is_product') && is_product()) $load_assets = true;
        if (function_exists('is_cart') && is_cart()) $load_assets = true;
        if (function_exists('is_product_category') && is_product_category()) $load_assets = true;
        
        global $post;
        $post_content = isset($post->post_content) ? $post->post_content : '';
        if (has_shortcode($post_content, 'pws_cart')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_custom_sizes')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_a_sizes')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_product_selector')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_product_options')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_category_page')) $load_assets = true;
        if (has_shortcode($post_content, 'pws_category_products')) $load_assets = true;
        
        if (!$load_assets) return;
        
        // Cache busting version - use file modification time
        $css_version = filemtime(PWS_PLUGIN_PATH . 'assets/css/pws-styles.css');
        $js_version = filemtime(PWS_PLUGIN_PATH . 'assets/js/pws-scripts.js');
        
        wp_enqueue_style('pws-styles', PWS_PLUGIN_URL . 'assets/css/pws-styles.css', array(), $css_version);
        wp_enqueue_script('pws-scripts', PWS_PLUGIN_URL . 'assets/js/pws-scripts.js', array('jquery'), $js_version, true);
        
        $current_product_id = $post ? $post->ID : 0;
        
        wp_localize_script('pws-scripts', 'pws_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pws_nonce'),
            'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
            'shop_url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '/',
            'current_product_id' => $current_product_id,
            'current_pricing_slug' => $this->get_pricing_slug($current_product_id),
            'featured_ids' => $this->get_featured_product_ids(),
            'cart_count' => (function_exists('WC') && WC()->cart) ? count(WC()->cart->get_cart()) : 0,
        ));
    }
    
    /**
     * Enqueue assets specifically for A Sizes shortcode
     * This ensures assets load even when called via Elementor
     */
    public function enqueue_a_sizes_assets() {
        // Prevent double loading
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;
        
        // Always use filemtime for cache busting
        $css_version = file_exists(PWS_PLUGIN_PATH . 'assets/css/pws-styles.css') 
            ? filemtime(PWS_PLUGIN_PATH . 'assets/css/pws-styles.css') 
            : PWS_VERSION;
        $js_version = file_exists(PWS_PLUGIN_PATH . 'assets/js/pws-scripts.js') 
            ? filemtime(PWS_PLUGIN_PATH . 'assets/js/pws-scripts.js') 
            : PWS_VERSION;
        
        wp_enqueue_style('pws-styles', PWS_PLUGIN_URL . 'assets/css/pws-styles.css', array(), $css_version);
        wp_enqueue_script('pws-scripts', PWS_PLUGIN_URL . 'assets/js/pws-scripts.js', array('jquery'), $js_version, true);
        
        wp_localize_script('pws-scripts', 'pws_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pws_nonce'),
            'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
            'shop_url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '/',
            'current_product_id' => 0,
            'current_pricing_slug' => '',
            'featured_ids' => array(),
            'cart_count' => (function_exists('WC') && WC()->cart) ? count(WC()->cart->get_cart()) : 0,
        ));
    }
    
    /**
     * SHORTCODE: Dynamic Category Page [pws_category_page]
     */
    public function render_category_page($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
        ), $atts, 'pws_category_page');
        
        // Get category from attribute or current category page
        $category_slug = $atts['category'];
        if (empty($category_slug) && is_product_category()) {
            $category = get_queried_object();
            if ($category) {
                $category_slug = $category->slug;
            }
        }
        
        if (empty($category_slug)) {
            return '<p>No category specified.</p>';
        }
        
        // Get products for this category
        $product_ids = $this->get_category_products($category_slug);
        
        if (empty($product_ids)) {
            return '<p>No products found for this category.</p>';
        }
        
        // Build product data
        $products = array();
        foreach ($product_ids as $pid) {
            if (!function_exists('wc_get_product')) continue;
            $wc_product = wc_get_product($pid);
            if ($wc_product) {
                $description = $wc_product->get_short_description();
                if (empty($description)) {
                    $description = $wc_product->get_description();
                }
                $description = wp_trim_words(wp_strip_all_tags($description), 15, '...');
                
                $products[] = array(
                    'id' => $pid,
                    'name' => $wc_product->get_name(),
                    'image' => wp_get_attachment_image_url($wc_product->get_image_id(), 'medium'),
                    'image_large' => wp_get_attachment_image_url($wc_product->get_image_id(), 'large'),
                    'slug' => $this->get_pricing_slug($pid),
                    'desc' => $description,
                );
            }
        }
        
        if (empty($products)) {
            return '<p>No valid products found.</p>';
        }
        
        // First product is default selected
        $first_product = $products[0];
        $sizes = $this->get_product_sizes($first_product['slug']);
        
        ob_start();
        ?>
        <div id="pws-category-page" class="pws-category-page" data-category="<?php echo esc_attr($category_slug); ?>">
            
            <?php if (count($products) > 1) : ?>
            <!-- Product Selector - Only show if more than 1 product -->
            <div class="pws-category-selector">
                <div class="pws-selector-grid pws-grid-<?php echo count($products); ?>">
                    <?php foreach ($products as $index => $prod) : $is_first = ($index === 0); ?>
                    <div class="pws-selector-item <?php echo $is_first ? 'active' : ''; ?>" 
                         data-product-id="<?php echo esc_attr($prod['id']); ?>"
                         data-pricing-slug="<?php echo esc_attr($prod['slug']); ?>">
                        <div class="pws-selector-image">
                            <?php if ($prod['image']) : ?>
                            <img src="<?php echo esc_url($prod['image']); ?>" alt="<?php echo esc_attr($prod['name']); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="pws-selector-info">
                            <h4><?php echo esc_html($prod['name']); ?></h4>
                            <p><?php echo esc_html($prod['desc']); ?></p>
                        </div>
                        <div class="pws-selector-checkbox">
                            <span class="pws-checkbox <?php echo $is_first ? 'checked' : ''; ?>"></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Product Display Area -->
            <div class="pws-category-product">
                <div class="pws-product-layout">
                    <!-- Left: Product Image -->
                    <div class="pws-product-image-col">
                        <img src="<?php echo esc_url($first_product['image_large'] ?: $first_product['image']); ?>" 
                             alt="<?php echo esc_attr($first_product['name']); ?>" 
                             id="pws-main-product-image" 
                             class="pws-main-image">
                    </div>
                    
                    <!-- Right: Options Form -->
                    <div class="pws-product-options-col">
                        <div id="pws-options" class="pws-options" 
                             data-product-id="<?php echo esc_attr($first_product['id']); ?>" 
                             data-pricing-slug="<?php echo esc_attr($first_product['slug']); ?>">
                            
                            <h3 class="pws-product-title">
                                <span id="pws-product-name"><?php echo esc_html($first_product['name']); ?></span>
                            </h3>
                            
                            <div class="pws-field">
                                <label for="pws-size">Size:</label>
                                <select id="pws-size" name="pws_size">
                                    <?php foreach ($sizes as $size) : ?>
                                    <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="pws-field">
                                <label for="pws-thumbcut">Thumbcuts:</label>
                                <select id="pws-thumbcut" name="pws_thumbcut">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                            </div>
                            
                            <div class="pws-field">
                                <label for="pws-holepunch">Hole Punch:</label>
                                <select id="pws-holepunch" name="pws_holepunch">
                                    <option value="none">None</option>
                                    <option value="left-top">Left top</option>
                                    <option value="right-top">Right top</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            
                            <div class="pws-field">
                                <label for="pws-openside">Open Side Wallet Position:</label>
                                <select id="pws-openside" name="pws_openside">
                                    <option value="short">Short Side</option>
                                    <option value="long">Long Side</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            
                            <div class="pws-qty-section">
                                <div class="pws-qty-field">
                                    <label for="pws-quantity">QTY Pack:</label>
                                    <select id="pws-quantity" name="pws_quantity">
                                        <?php foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $qty) : ?>
                                        <option value="<?php echo $qty; ?>" <?php selected($qty, 100); ?>><?php echo $qty; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="pws-bulk-suggestions" class="pws-bulk-suggestions"></div>
                            </div>
                            
                            <div class="pws-price-display">
                                <span id="pws-total-price" class="pws-total">£0.00</span>
                                <span id="pws-unit-price" class="pws-unit">(£0.00 per unit)</span>
                            </div>
                            
                            <button type="button" id="pws-add-to-cart" class="pws-add-btn">Add to basket</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Elementor Templates: Bespoke & A Sizes Sections -->
            <div class="pws-category-footer-sections">
                <?php echo do_shortcode('[elementor-template id="5209"]'); ?>
                <?php echo do_shortcode('[elementor-template id="5212"]'); ?>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: Custom Cart Page [pws_cart]
     */
    public function render_custom_cart($atts) {
        if (!function_exists('WC') || !WC()->cart) return '<p>Cart not available.</p>';
        
        $cart = WC()->cart;
        $cart_items = $cart->get_cart();
        $cart_total = 0;
        $total_items = 0;
        
        ob_start();
        ?>
        <div class="pws-cart-page">
            <!-- Top Actions -->
            <div class="pws-cart-actions-top">
                <a href="#" class="pws-empty-cart-link">Empty basket</a>
                <a href="#" class="pws-update-cart-link">Update cart</a>
            </div>
            
            <!-- Page Title -->
            <h1 class="pws-cart-title">Shopping Basket</h1>
            
            <?php if (empty($cart_items)) : ?>
            <div class="pws-cart-empty">
                <p>Your basket is empty.</p>
                <a href="<?php echo esc_url(home_url('/our-product/')); ?>" class="pws-btn pws-btn-continue">Continue shopping</a>
            </div>
            <?php else : ?>
            
            <!-- Cart Table -->
            <table class="pws-cart-table">
                <!-- Header -->
                <thead>
                    <tr class="pws-cart-header">
                        <th class="pws-col-image">Product image</th>
                        <th class="pws-col-details">Product Details:</th>
                        <th class="pws-col-qty">Qty</th>
                        <th class="pws-col-total">Total</th>
                        <th class="pws-col-remove">Remove</th>
                    </tr>
                </thead>
                <tbody>
                <!-- Cart Items -->
                <?php foreach ($cart_items as $cart_item_key => $cart_item) : 
                    $is_pws = (isset($cart_item['pws_custom']) && $cart_item['pws_custom'])
                            || (isset($cart_item['pws_custom_item']) && $cart_item['pws_custom_item']);
                    
                    if ($is_pws) {
                        $product_name = $cart_item['pws_product_name'] ?? 'Product';
                        $size = $cart_item['pws_size'] ?? '';
                        $thumbcut = $cart_item['pws_thumbcut'] ?? 'no';
                        $holepunch = $cart_item['pws_holepunch'] ?? 'none';
                        $openside = $cart_item['pws_openside'] ?? 'both';
                        $quantity = $cart_item['quantity'];
                        $original_product_id = $cart_item['pws_original_product_id'] ?? 0;
                        
                        // Use pws_total_price for precision, fall back to unit*qty
                        $stored_total = floatval($cart_item['pws_total_price'] ?? 0);
                        $unit_price = floatval($cart_item['pws_unit_price'] ?? 0);
                        if ($stored_total > 0) {
                            $line_total = $stored_total;
                            $unit_price = $quantity > 0 ? ($stored_total / $quantity) : $unit_price;
                        } else {
                            $line_total = $unit_price * $quantity;
                        }
                        
                        // Get product image from original WC product
                        $image_url = '';
                        if ($original_product_id) {
                            $wc_product = wc_get_product($original_product_id);
                            if ($wc_product && $wc_product->get_image_id()) {
                                $image_url = wp_get_attachment_image_url($wc_product->get_image_id(), 'medium');
                            }
                        }
                        
                        // For custom/A-size items: check the page that contains the shortcode for a featured image
                        if (!$image_url && !empty($cart_item['pws_is_custom_size'])) {
                            $image_url = $this->get_custom_size_thumbnail();
                        }
                        if (!$image_url && !empty($cart_item['pws_size_type']) && $cart_item['pws_size_type'] === 'A Size') {
                            $image_url = $this->get_a_size_thumbnail();
                        }
                        
                        if (!$image_url) {
                            $image_url = wc_placeholder_img_src('medium');
                        }
                        
                        // Parse size for width/height display
                        $size_parts = preg_split('/[xX×\s]+/', $size);
                        $width = isset($size_parts[0]) ? trim($size_parts[0]) : '';
                        $height = isset($size_parts[1]) ? trim($size_parts[1]) : '';
                    } else {
                        // Standard WooCommerce item
                        $product = $cart_item['data'];
                        $product_name = $product->get_name();
                        $unit_price = floatval($product->get_price());
                        $quantity = $cart_item['quantity'];
                        $line_total = $unit_price * $quantity;
                        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src('medium');
                        $size = $width = $height = $thumbcut = $holepunch = $openside = '';
                    }
                    
                    $cart_total += $line_total;
                    $total_items += $quantity;
                ?>
                <tr class="pws-cart-row" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
                    <!-- Product Image -->
                    <td class="pws-col-image">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product_name); ?>" class="pws-cart-thumbnail">
                    </td>
                    
                    <!-- Product Details -->
                    <td class="pws-col-details">
                        <div class="pws-details-inner">
                            <h3 class="pws-cart-product-title"><?php echo esc_html($product_name); ?></h3>
                            <?php if ($is_pws) : ?>
                            <div class="pws-cart-product-meta">
                                <?php if ($size) : ?><span>Size: <?php echo esc_html($size); ?></span><?php endif; ?>
                                <?php if ($width) : ?><span>Width: <?php echo esc_html($width); ?></span><?php endif; ?>
                                <?php if ($height) : ?><span>Height: <?php echo esc_html($height); ?></span><?php endif; ?>
                                <span>Thumbcuts: <?php echo esc_html(ucfirst($thumbcut)); ?></span>
                                <span>Hole Punch: <?php echo esc_html(ucfirst(str_replace('-', ' ', $holepunch))); ?></span>
                                <span>Side Position: <?php echo esc_html(ucfirst($openside)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <!-- Quantity (editable input triggers updateCartQty via JS) -->
                    <td class="pws-col-qty">
                        <input type="number" class="pws-qty-input pws-cart-qty-value"
                               value="<?php echo esc_attr($quantity); ?>"
                               min="1" step="1"
                               style="width:60px;text-align:center;border:1px solid #ccc;padding:4px;"
                               data-testid="input-qty-<?php echo esc_attr($cart_item_key); ?>">
                    </td>
                    
                    <!-- Total -->
                    <td class="pws-col-total">
                        <span class="pws-cart-line-total pws-item-total">£<?php echo number_format($line_total, 2); ?></span>
                    </td>
                    
                    <!-- Remove -->
                    <td class="pws-col-remove">
                        <button type="button" class="pws-remove-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>" title="Remove item">×</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Cart Footer -->
            <?php
                $vat_rate = 0.20;
                $vat_amount = round($cart_total * $vat_rate, 2);
                $total_with_vat = round($cart_total + $vat_amount, 2);
            ?>
            <div class="pws-cart-footer">
                <div class="pws-cart-totals">
                    <div class="pws-totals-row" style="display:flex;justify-content:flex-end;align-items:baseline;gap:10px;margin-bottom:5px;">
                        <span class="pws-totals-label" style="font-size:14px;color:#555;">Subtotal:</span>
                        <span id="pws-cart-subtotal-amount" class="pws-total-amount" style="font-size:22px;font-weight:800;">£<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    <div class="pws-totals-row" style="display:flex;justify-content:flex-end;align-items:baseline;gap:10px;margin-bottom:5px;">
                        <span class="pws-totals-label" style="font-size:14px;color:#555;">VAT (20%):</span>
                        <span id="pws-cart-vat" style="font-size:18px;font-weight:700;">£<?php echo number_format($vat_amount, 2); ?></span>
                    </div>
                    <div class="pws-totals-row" style="display:flex;justify-content:flex-end;align-items:baseline;gap:10px;margin-bottom:5px;padding-top:8px;border-top:2px solid #333;">
                        <span class="pws-totals-label" style="font-size:14px;color:#555;">Total:</span>
                        <span id="pws-cart-total" class="pws-total-amount" style="font-size:28px;font-weight:800;">£<?php echo number_format($total_with_vat, 2); ?></span>
                    </div>
                    <span id="pws-cart-subtotal" class="pws-total-unit" style="display:block;text-align:right;font-size:12px;color:#666;">(£<?php echo number_format($total_items > 0 ? $cart_total / $total_items : 0, 2); ?> per unit excl. VAT)</span>
                </div>
                <div class="pws-cart-buttons">
                    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="pws-btn pws-btn-continue">Continue shopping</a>
                    <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="pws-btn pws-btn-checkout">Checkout</a>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Remove cart item
     */
    public function ajax_remove_cart_item() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available')); return;
        }
        $cart_key = sanitize_text_field($_POST['cart_key'] ?? '');
        if (WC()->cart->remove_cart_item($cart_key)) {
            WC()->cart->calculate_totals();
            
            // Recalculate totals from PWS items accurately
            $cart_total = 0;
            $total_items = 0;
            foreach (WC()->cart->get_cart() as $ci) {
                $ci_is_pws = (isset($ci['pws_custom']) && $ci['pws_custom'])
                           || (isset($ci['pws_custom_item']) && $ci['pws_custom_item']);
                if ($ci_is_pws) {
                    $ci_total = floatval($ci['pws_total_price'] ?? 0);
                    if ($ci_total <= 0) {
                        $ci_total = floatval($ci['pws_unit_price'] ?? 0) * $ci['quantity'];
                    }
                    $cart_total += $ci_total;
                } else {
                    $cart_total += floatval($ci['data']->get_price()) * $ci['quantity'];
                }
                $total_items += $ci['quantity'];
            }
            
            wp_send_json_success(array(
                'message'    => 'Item removed',
                'cart_count' => count(WC()->cart->get_cart()),
                'subtotal'   => number_format($cart_total, 2),
                'total'      => number_format($cart_total, 2),
                'per_unit'   => $total_items > 0 ? number_format($cart_total / $total_items, 2) : '0.00',
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not remove item'));
        }
    }
    
    /**
     * AJAX: Empty cart
     */
    public function ajax_empty_cart() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available')); return;
        }
        WC()->cart->empty_cart();
        wp_send_json_success(array('message' => 'Cart emptied'));
    }
    
    /**
     * AJAX: Update cart quantity
     * Recalculates pricing using the formula so base_cost is applied correctly
     */
    public function ajax_update_cart_qty() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available')); return;
        }
        $cart_key = sanitize_text_field($_POST['cart_key'] ?? '');
        $quantity  = max(1, intval($_POST['quantity'] ?? 1));
        
        $cart = WC()->cart;
        $cart_items = $cart->get_cart();
        
        if (!isset($cart_items[$cart_key])) {
            wp_send_json_error(array('message' => 'Item not found in cart'));
            return;
        }
        
        $item = $cart_items[$cart_key];
        
        // For PWS items, recalculate price with new quantity using the formula
        $is_pws_item = (isset($item['pws_custom']) && $item['pws_custom'])
                     || (isset($item['pws_custom_item']) && $item['pws_custom_item']);
        if ($is_pws_item) {
            $width = 0;
            $height = 0;
            $thumbcut = $item['pws_thumbcut'] ?? 'no';
            $holepunch = $item['pws_holepunch'] ?? 'none';
            
            // Determine dimensions from the item data
            if (isset($item['pws_width']) && isset($item['pws_height'])) {
                $width = intval($item['pws_width']);
                $height = intval($item['pws_height']);
            } elseif (isset($item['pws_original_product_id']) && $item['pws_original_product_id']) {
                $product_sizes = $this->get_product_sizes_config();
                $original_id = intval($item['pws_original_product_id']);
                foreach ($product_sizes as $slug => $data) {
                    if (isset($data['wc_product_id']) && intval($data['wc_product_id']) === $original_id) {
                        $width = intval($data['width']);
                        $height = intval($data['height']);
                        break;
                    }
                }
            }
            
            // Parse dimensions from size string as fallback
            if (($width <= 0 || $height <= 0) && !empty($item['pws_size'])) {
                $size_str = $item['pws_size'];
                if (preg_match('/([\d]+)\s*x\s*([\d]+)/i', $size_str, $matches)) {
                    $width = intval($matches[1]);
                    $height = intval($matches[2]);
                }
            }
            
            if ($width > 0 && $height > 0) {
                $recalc = $this->calculate_price_from_dimensions($width, $height, $quantity, $thumbcut, $holepunch);
                $new_unit_price = $recalc['unit'];
                $new_total = $recalc['total'];
                
                // Update cart item data with recalculated prices
                WC()->cart->cart_contents[$cart_key]['pws_unit_price'] = $new_unit_price;
                WC()->cart->cart_contents[$cart_key]['pws_total_price'] = $new_total;
            }
        }
        
        if ($cart->set_quantity($cart_key, $quantity)) {
            $cart->calculate_totals();
            
            $item_total = 0;
            $item_unit = 0;
            foreach ($cart->get_cart() as $key => $ci) {
                if ($key === $cart_key) {
                    $ci_is_pws = (isset($ci['pws_custom']) && $ci['pws_custom'])
                              || (isset($ci['pws_custom_item']) && $ci['pws_custom_item']);
                    if ($ci_is_pws) {
                        $item_total = floatval($ci['pws_total_price'] ?? 0);
                        if ($item_total <= 0) {
                            $item_total = floatval($ci['pws_unit_price'] ?? 0) * $ci['quantity'];
                        }
                        $item_unit = $ci['quantity'] > 0 ? ($item_total / $ci['quantity']) : 0;
                    } else {
                        $item_total = isset($ci['line_total']) ? $ci['line_total'] : (floatval($ci['data']->get_price()) * $ci['quantity']);
                    }
                    break;
                }
            }
            
            // Recalculate full cart total from all items
            $cart_total = 0;
            $total_items = 0;
            foreach ($cart->get_cart() as $ci) {
                $ci_is_pws = (isset($ci['pws_custom']) && $ci['pws_custom'])
                          || (isset($ci['pws_custom_item']) && $ci['pws_custom_item']);
                if ($ci_is_pws) {
                    $ci_total = floatval($ci['pws_total_price'] ?? 0);
                    if ($ci_total <= 0) {
                        $ci_total = floatval($ci['pws_unit_price'] ?? 0) * $ci['quantity'];
                    }
                    $cart_total += $ci_total;
                } else {
                    $cart_total += floatval($ci['data']->get_price()) * $ci['quantity'];
                }
                $total_items += $ci['quantity'];
            }
            
            wp_send_json_success(array(
                'message'    => 'Updated',
                'cart_count' => count($cart->get_cart()),
                'item_total' => number_format($item_total, 2),
                'item_unit'  => number_format($item_unit, 2),
                'subtotal'   => number_format($cart_total, 2),
                'total'      => number_format($cart_total, 2),
                'per_unit'   => $total_items > 0 ? number_format($cart_total / $total_items, 2) : '0.00',
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not update'));
        }
    }
    
    /**
     * SHORTCODE: Single Checkbox
     */
    public function render_single_checkbox($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'pws_checkbox');
        $product_id = intval($atts['id']);
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        global $post;
        $current_id = isset($post->ID) ? $post->ID : 0;
        $is_current = ($product_id == $current_id);
        $pricing_slug = $this->get_pricing_slug($product_id);
        
        ob_start();
        ?>
        <div class="pws-single-checkbox <?php echo $is_current ? 'active' : ''; ?>" 
             data-product-id="<?php echo esc_attr($product_id); ?>"
             data-pricing-slug="<?php echo esc_attr($pricing_slug); ?>">
            <span class="pws-checkbox <?php echo $is_current ? 'checked' : ''; ?>"></span>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: Product Card - Full card with image, title, description, checkbox
     * Usage: [pws_product_card id="3917"] or [pws_product_card position="1" category="travel"]
     */
    public function render_product_card($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'position' => 0,      // 1, 2, 3, or 4 - which product in category
            'category' => '',     // Category slug
        ), $atts, 'pws_product_card');
        
        $product_id = intval($atts['id']);
        
        // If no ID but position and category provided, get product from category mapping
        if (!$product_id && $atts['position'] && $atts['category']) {
            $products = $this->get_category_products($atts['category']);
            $position = intval($atts['position']) - 1; // Convert to 0-based index
            if (isset($products[$position])) {
                $product_id = $products[$position];
            }
        }
        
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        global $post;
        $current_id = isset($post->ID) ? $post->ID : 0;
        $is_current = ($product_id == $current_id);
        $pricing_slug = $this->get_pricing_slug($product_id);
        
        // Get product data
        $image_url = wp_get_attachment_image_url($wc_product->get_image_id(), 'medium');
        $title = $wc_product->get_name();
        $description = $wc_product->get_short_description();
        if (empty($description)) {
            $description = $wc_product->get_description();
        }
        $description = wp_trim_words(wp_strip_all_tags($description), 15, '...');
        
        ob_start();
        ?>
        <div class="pws-product-card <?php echo $is_current ? 'active' : ''; ?>" 
             data-product-id="<?php echo esc_attr($product_id); ?>"
             data-pricing-slug="<?php echo esc_attr($pricing_slug); ?>">
            <div class="pws-card-image">
                <?php if ($image_url) : ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php endif; ?>
            </div>
            <div class="pws-card-content">
                <h4 class="pws-card-title"><?php echo esc_html($title); ?></h4>
                <p class="pws-card-description"><?php echo esc_html($description); ?></p>
            </div>
            <div class="pws-card-checkbox">
                <span class="pws-checkbox <?php echo $is_current ? 'checked' : ''; ?>"></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: Main Product Image (Dynamic)
     * Usage: [pws_main_image] - Auto-detects from current product page
     * This image updates when user clicks different product checkboxes
     */
    public function render_main_image($atts) {
        $atts = shortcode_atts(array(
            'size' => 'large',
        ), $atts, 'pws_main_image');
        
        global $post;
        $product_id = 0;
        
        // Get current product ID from page
        if (is_product() && isset($post->ID)) {
            $product_id = $post->ID;
        } elseif (isset($post->ID)) {
            $product_id = $post->ID;
        }
        
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        $image_url = wp_get_attachment_image_url($wc_product->get_image_id(), $atts['size']);
        $title = $wc_product->get_name();
        
        if (!$image_url) return '';
        
        // Output image with ID for JavaScript to target
        return '<div id="pws-main-image-wrapper" data-product-id="' . esc_attr($product_id) . '"><img id="pws-main-image" src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '"></div>';
    }
    
    /**
     * SHORTCODE: Product Image Only
     * Usage: [pws_product_image id="3917"] or [pws_product_image position="1" category="travel"]
     */
    public function render_product_image($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'position' => 0,
            'category' => '',
            'size' => 'medium',
        ), $atts, 'pws_product_image');
        
        $product_id = intval($atts['id']);
        
        if (!$product_id && $atts['position'] && $atts['category']) {
            $products = $this->get_category_products($atts['category']);
            $position = intval($atts['position']) - 1;
            if (isset($products[$position])) {
                $product_id = $products[$position];
            }
        }
        
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        $image_url = wp_get_attachment_image_url($wc_product->get_image_id(), $atts['size']);
        $title = $wc_product->get_name();
        
        if (!$image_url) return '';
        
        return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" class="pws-dynamic-image">';
    }
    
    /**
     * SHORTCODE: Product Title Only
     * Usage: [pws_product_title id="3917"] or [pws_product_title position="1" category="travel"]
     */
    public function render_product_title($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'position' => 0,
            'category' => '',
        ), $atts, 'pws_product_title');
        
        $product_id = intval($atts['id']);
        
        if (!$product_id && $atts['position'] && $atts['category']) {
            $products = $this->get_category_products($atts['category']);
            $position = intval($atts['position']) - 1;
            if (isset($products[$position])) {
                $product_id = $products[$position];
            }
        }
        
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        return '<span class="pws-dynamic-title">' . esc_html($wc_product->get_name()) . '</span>';
    }
    
    /**
     * SHORTCODE: Product Description Only
     * Usage: [pws_product_description id="3917"] or [pws_product_description position="1" category="travel"]
     */
    public function render_product_description($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'position' => 0,
            'category' => '',
            'words' => 20,
        ), $atts, 'pws_product_description');
        
        $product_id = intval($atts['id']);
        
        if (!$product_id && $atts['position'] && $atts['category']) {
            $products = $this->get_category_products($atts['category']);
            $position = intval($atts['position']) - 1;
            if (isset($products[$position])) {
                $product_id = $products[$position];
            }
        }
        
        if (!$product_id || !function_exists('wc_get_product')) return '';
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) return '';
        
        $description = $wc_product->get_short_description();
        if (empty($description)) {
            $description = $wc_product->get_description();
        }
        $description = wp_trim_words(wp_strip_all_tags($description), intval($atts['words']), '...');
        
        return '<span class="pws-dynamic-description">' . esc_html($description) . '</span>';
    }
    
    /**
     * Get category slug for a product based on our mappings
     */
    private function get_product_category_slug($product_id) {
        $product_id = intval($product_id);
        $mappings = $this->get_category_mappings();
        foreach ($mappings as $category_slug => $mapping_data) {
            $product_list = array();
            if (isset($mapping_data['products']) && is_array($mapping_data['products'])) {
                $product_list = $mapping_data['products'];
            } elseif (is_array($mapping_data) && !isset($mapping_data['products'])) {
                $product_list = $mapping_data;
            }
            if (in_array($product_id, array_map('intval', $product_list))) {
                return $category_slug;
            }
        }
        return '';
    }
    
    /**
     * SHORTCODE: All Category Products in Row
     * Usage: [pws_category_products] - Auto-detects category from current product
     * Usage: [pws_category_products category="travel"] - Specific category
     * Shows all products (1-4) in a category as clickable cards
     */
    public function render_category_products($atts) {
        // Safety check
        if (!function_exists('wc_get_product')) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'category' => '', // If empty, auto-detect from current product
        ), $atts, 'pws_category_products');
        
        global $post;
        $current_id = 0;
        
        // Get current product ID safely
        if (is_product() && isset($post->ID)) {
            $current_id = $post->ID;
        } elseif (isset($post->ID)) {
            $current_id = $post->ID;
        }
        
        // Auto-detect category if not specified
        $category_slug = $atts['category'];
        if (empty($category_slug) && $current_id) {
            $category_slug = $this->get_product_category_slug($current_id);
        }
        
        if (empty($category_slug)) {
            return ''; // Return empty, don't break page
        }
        
        $product_ids = $this->get_category_products($category_slug);
        if (empty($product_ids)) {
            return ''; // Return empty, don't break page
        }
        
        // Determine which product is the default (current page product or first one)
        $default_id = $product_ids[0];
        if (in_array($current_id, $product_ids)) {
            $default_id = $current_id;
        }
        
        // Build output
        $output = '<div class="pws-category-products pws-grid-' . count($product_ids) . '" data-category="' . esc_attr($category_slug) . '">';
        
        foreach ($product_ids as $index => $pid) {
            $wc_product = wc_get_product($pid);
            if (!$wc_product) continue;
            
            $is_default = ($pid == $default_id);
            $pricing_slug = $this->get_pricing_slug($pid);
            $image_url = wp_get_attachment_image_url($wc_product->get_image_id(), 'medium');
            $title = $wc_product->get_name();
            $description = $wc_product->get_short_description();
            if (empty($description)) {
                $description = $wc_product->get_description();
            }
            $description = wp_trim_words(wp_strip_all_tags($description), 15, '...');
            
            $output .= '<div class="pws-product-card ' . ($is_default ? 'active' : '') . '" ';
            $output .= 'data-product-id="' . esc_attr($pid) . '" ';
            $output .= 'data-pricing-slug="' . esc_attr($pricing_slug) . '">';
            
            $output .= '<div class="pws-card-image">';
            if ($image_url) {
                $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '">';
            }
            $output .= '</div>';
            
            $output .= '<div class="pws-card-content">';
            $output .= '<h4 class="pws-card-title">' . esc_html($title) . '</h4>';
            $output .= '<p class="pws-card-description">' . esc_html($description) . '</p>';
            $output .= '</div>';
            
            $output .= '<div class="pws-card-checkbox">';
            $output .= '<span class="pws-checkbox ' . ($is_default ? 'checked' : '') . '"></span>';
            $output .= '</div>';
            
            $output .= '</div>'; // Close product-card
        }
        
        $output .= '</div>'; // Close category-products
        
        return $output;
    }
    
    /**
     * SHORTCODE: Full Product Selector
     */
    public function render_product_selector($atts) {
        if (!function_exists('wc_get_product')) return '';
        global $post;
        $current_id = isset($post->ID) ? $post->ID : 0;
        $featured_ids = $this->get_featured_product_ids();
        
        $products = array();
        foreach ($featured_ids as $pid) {
            $wc_product = wc_get_product($pid);
            if ($wc_product) {
                $description = $wc_product->get_description();
                if (empty($description)) $description = $wc_product->get_short_description();
                $products[] = array(
                    'id' => $pid,
                    'name' => $wc_product->get_name(),
                    'image' => wp_get_attachment_image_url($wc_product->get_image_id(), 'medium'),
                    'slug' => $this->get_pricing_slug($pid),
                    'desc' => wp_trim_words(wp_strip_all_tags($description), 12, ''),
                );
            }
        }
        if (empty($products)) return '';
        
        ob_start();
        ?>
        <div id="pws-selector" class="pws-selector">
            <div class="pws-selector-grid">
                <?php foreach ($products as $prod) : $is_current = ($prod['id'] == $current_id); ?>
                <div class="pws-selector-item <?php echo $is_current ? 'active' : ''; ?>" 
                     data-product-id="<?php echo esc_attr($prod['id']); ?>"
                     data-pricing-slug="<?php echo esc_attr($prod['slug']); ?>">
                    <div class="pws-selector-image">
                        <?php if ($prod['image']) : ?><img src="<?php echo esc_url($prod['image']); ?>" alt="<?php echo esc_attr($prod['name']); ?>"><?php endif; ?>
                    </div>
                    <div class="pws-selector-info">
                        <h4><?php echo esc_html($prod['name']); ?></h4>
                        <p><?php echo esc_html($prod['desc']); ?></p>
                    </div>
                    <div class="pws-selector-checkbox">
                        <span class="pws-checkbox <?php echo $is_current ? 'checked' : ''; ?>"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: Product Options
     */
    public function render_product_options($atts) {
        if (!function_exists('wc_get_product')) return '';
        global $post;
        $current_id = isset($post->ID) ? $post->ID : 0;
        $wc_product = $current_id ? wc_get_product($current_id) : null;
        $pricing_slug = $this->get_pricing_slug($current_id);
        $sizes = $this->get_product_sizes($pricing_slug);
        $product_name = $wc_product ? $wc_product->get_name() : 'Product';
        
        ob_start();
        ?>
        <div class="pws-shortcode-wrapper" style="display:block;clear:both;">
        <div id="pws-options" class="pws-options" data-product-id="<?php echo esc_attr($current_id); ?>" data-pricing-slug="<?php echo esc_attr($pricing_slug); ?>">
            <!-- Top Section: Beige Background -->
            <div class="pws-options-top">
                <h3 class="pws-product-title"><span id="pws-product-name"><?php echo esc_html($product_name); ?></span></h3>
                
                <div class="pws-field">
                    <label for="pws-size">Size:</label>
                    <select id="pws-size" name="pws_size">
                        <?php foreach ($sizes as $size) : ?><option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pws-field">
                    <label for="pws-thumbcut">Thumbcuts:</label>
                    <select id="pws-thumbcut" name="pws_thumbcut">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
                
                <div class="pws-field">
                    <label for="pws-holepunch">Hole Punch:</label>
                    <select id="pws-holepunch" name="pws_holepunch">
                        <option value="none">None</option>
                        <option value="left-top">Left top</option>
                        <option value="right-top">Right top</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                
                <div class="pws-field">
                    <label for="pws-openside">Open Side Wallet Position:</label>
                    <select id="pws-openside" name="pws_openside">
                        <option value="short">Short Side</option>
                        <option value="long">Long Side</option>
                        <option value="both">Both</option>
                    </select>
                </div>
            </div>
            
            <!-- Bottom Section: White Background -->
            <div class="pws-options-bottom">
                <div class="pws-qty-section">
                    <div class="pws-qty-field">
                        <label for="pws-quantity">QTY Pack:</label>
                        <select id="pws-quantity" name="pws_quantity">
                            <?php foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $qty) : ?>
                            <option value="<?php echo $qty; ?>" <?php selected($qty, 100); ?>><?php echo $qty; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="pws-bulk-suggestions" class="pws-bulk-suggestions"></div>
                </div>
                
                <div class="pws-price-display">
                    <span id="pws-total-price" class="pws-total">£0.00</span>
                    <span id="pws-unit-price" class="pws-unit">(£0.00 per unit)</span>
                </div>
                
                <button type="button" id="pws-add-to-cart" class="pws-add-btn">Add to basket</button>
            </div>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: Custom Size Wallets [pws_custom_sizes]
     * For Custom Orders page - Width/Height DROPDOWNS on same row
     */
    public function render_custom_sizes($atts) {
        $atts = shortcode_atts(array(
            'product_id' => 4453,
            'title' => 'Custom Size Wallets',
        ), $atts, 'pws_custom_sizes');
        
        $product_id = intval($atts['product_id']);
        $product_name = $atts['title'];
        
        ob_start();
        ?>
        <div id="pws-custom-sizes" class="pws-options pws-custom-options" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="pws-options-top">
                <h3 class="pws-product-title"><?php echo esc_html($product_name); ?></h3>
                
                <div class="pws-field pws-field-inline">
                    <div class="pws-field-half">
                        <label for="pws-custom-width">Width (mm):</label>
                        <select id="pws-custom-width" name="pws_custom_width">
                            <?php for ($w = 50; $w <= 307; $w++) : ?>
                            <option value="<?php echo $w; ?>"><?php echo $w; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="pws-field-half">
                        <label for="pws-custom-height">Height (mm):</label>
                        <select id="pws-custom-height" name="pws_custom_height">
                            <?php for ($h = 50; $h <= 425; $h++) : ?>
                            <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="pws-field">
                    <label for="pws-custom-thumbcut">Thumbcuts:</label>
                    <select id="pws-custom-thumbcut" name="pws_custom_thumbcut">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
                
                <div class="pws-field">
                    <label for="pws-custom-holepunch">Hole Punch:</label>
                    <select id="pws-custom-holepunch" name="pws_custom_holepunch">
                        <option value="none">None</option>
                        <option value="left-top">Left Top</option>
                        <option value="right-top">Right Top</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                
                <div class="pws-field">
                    <label for="pws-custom-openside">Open Side Wallet Position:</label>
                    <select id="pws-custom-openside" name="pws_custom_openside">
                        <option value="short">Short Side</option>
                        <option value="long">Long Side</option>
                        <option value="both">Both</option>
                    </select>
                </div>
            </div>
            
            <div class="pws-options-bottom">
                <div class="pws-qty-section">
                    <div class="pws-qty-field">
                        <label for="pws-custom-quantity">QTY Pack:</label>
                        <select id="pws-custom-quantity" name="pws_custom_quantity">
                            <?php foreach (array(10, 15, 20, 25, 30, 50, 100, 200, 400, 500, 1000, 2000) as $qty) : ?>
                            <option value="<?php echo $qty; ?>" <?php selected($qty, 100); ?>><?php echo $qty; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="pws-custom-bulk-suggestions" class="pws-bulk-suggestions"></div>
                </div>
                
                <div class="pws-price-display">
                    <span id="pws-custom-total-price" class="pws-total">£0.00</span>
                    <span id="pws-custom-unit-price" class="pws-unit">(£0.00 per unit)</span>
                </div>
                
                <button type="button" id="pws-custom-add-to-cart" class="pws-add-btn">Add to basket</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * SHORTCODE: A Sizes [pws_a_sizes]
     * For A Sizes page - Size dropdown (A3-A8) with admin-configurable pricing
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_a_sizes( $atts ) {
        // Ensure assets are loaded (important for Elementor)
        $this->enqueue_a_sizes_assets();
        
        $atts = shortcode_atts( array(
            'product_id' => 4453,
            'title'      => 'A Size Wallets',
        ), $atts, 'pws_a_sizes' );
        
        $product_id   = absint( $atts['product_id'] );
        $product_name = sanitize_text_field( $atts['title'] );
        
        // Get A sizes configuration from database
        $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
        $quantities     = get_option( 'pws_a_sizes_quantities', array( 10, 15, 20, 25, 30, 50, 100, 200, 400, 500, 1000, 2000 ) );
        
        // Dropdown arrow SVG
        $dropdown_arrow = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23707070' d='M5 6L0 0h10z'/%3E%3C/svg%3E";
        
        // Common select styles
        $select_style = "width: 158px; height: 35px; padding: 0 30px 0 10px; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; color: #000; background: #FFFFFF url(\"{$dropdown_arrow}\") no-repeat right 10px center; background-size: 10px 6px; border: 1px solid #707070; border-radius: 0; cursor: pointer; -webkit-appearance: none; -moz-appearance: none; appearance: none;";
        
        $qty_select_style = "width: 100px; height: 35px; padding: 0 30px 0 10px; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; color: #000; background: #FFFFFF url(\"{$dropdown_arrow}\") no-repeat right 10px center; background-size: 10px 6px; border: 1px solid #707070; border-radius: 0; cursor: pointer; -webkit-appearance: none; -moz-appearance: none; appearance: none;";
        
        ob_start();
        ?>
        <div id="pws-a-sizes" class="pws-options pws-a-sizes-options" data-product-id="<?php echo esc_attr( $product_id ); ?>" style="max-width: 592px; box-sizing: border-box; background: #F6F1ED;">
            <div class="pws-options-top" style="background: #F6F1ED; padding: 25px 30px 25px 30px;">
                <h3 class="pws-product-title" style="font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 30px; font-weight: 800; line-height: 1.2; color: #000; margin: 0 0 18px 0;"><?php echo esc_html( $product_name ); ?></h3>
                
                <div class="pws-field" style="margin-bottom: 12px;">
                    <label for="pws-a-size" style="display: block; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 400; color: #000; margin-bottom: 5px;">Size:</label>
                    <select id="pws-a-size" name="pws_a_size" style="<?php echo esc_attr( $select_style ); ?>">
                        <?php foreach ( $a_sizes_config as $size_key => $size_data ) : ?>
                        <option value="<?php echo esc_attr( $size_key ); ?>"><?php echo esc_html( $size_key . ' (' . $size_data['dimensions'] . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pws-field" style="margin-bottom: 12px;">
                    <label for="pws-a-thumbcut" style="display: block; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 400; color: #000; margin-bottom: 5px;">Thumbcuts:</label>
                    <select id="pws-a-thumbcut" name="pws_a_thumbcut" style="<?php echo esc_attr( $select_style ); ?>">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
                
                <div class="pws-field" style="margin-bottom: 12px;">
                    <label for="pws-a-holepunch" style="display: block; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 400; color: #000; margin-bottom: 5px;">Hole Punch:</label>
                    <select id="pws-a-holepunch" name="pws_a_holepunch" style="<?php echo esc_attr( $select_style ); ?>">
                        <option value="none">None</option>
                        <option value="left-top">Left Top</option>
                        <option value="right-top">Right Top</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                
                <div class="pws-field" style="margin-bottom: 12px;">
                    <label for="pws-a-openside" style="display: block; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 400; color: #000; margin-bottom: 5px;">Open Side Wallet Position:</label>
                    <select id="pws-a-openside" name="pws_a_openside" style="<?php echo esc_attr( $select_style ); ?>">
                        <option value="short">Short Side</option>
                        <option value="long">Long Side</option>
                        <option value="both">Both</option>
                    </select>
                </div>
            </div>
            
            <div class="pws-options-bottom" style="background: #F6F1ED; padding: 20px 30px 25px 30px;">
                <div class="pws-qty-section" style="display: flex; align-items: flex-start; gap: 20px; margin-top: 0;">
                    <div class="pws-qty-field" style="display: flex; align-items: center; gap: 10px;">
                        <label for="pws-a-quantity" style="font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 600; color: #000; margin: 0; white-space: nowrap;">QTY Pack:</label>
                        <select id="pws-a-quantity" name="pws_a_quantity" style="<?php echo esc_attr( $qty_select_style ); ?>">
                            <?php foreach ( $quantities as $qty ) : ?>
                            <option value="<?php echo absint( $qty ); ?>" <?php selected( $qty, 100 ); ?>><?php echo absint( $qty ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="pws-a-bulk-suggestions" class="pws-bulk-suggestions" style="display: flex; flex-direction: column; gap: 2px;"></div>
                </div>
                
                <div class="pws-price-display" style="margin: 18px 0 15px 0;">
                    <span id="pws-a-total-price" class="pws-total" style="font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 28px; font-weight: 800; color: #000;">£0.00</span>
                    <span id="pws-a-unit-price" class="pws-unit" style="font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 12px; font-weight: 600; color: #000; margin-left: 6px;">(£0.00 per unit)</span>
                </div>
                
                <button type="button" id="pws-a-add-to-cart" class="pws-add-btn" style="display: inline-block; background: #40A4D2; color: #fff; font-family: 'Area Normal', 'Area Regular', sans-serif; font-size: 14px; font-weight: 600; border: none; padding: 10px 22px; cursor: pointer; border-radius: 0; line-height: 1.3;">Add to basket</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get default A sizes configuration
     * 
     * @return array Default A sizes with dimensions and base pricing
     */
    private function get_default_a_sizes_config() {
        return array(
            'A3' => array( 'dimensions' => '297 x 420', 'width' => 297, 'height' => 420 ),
            'A4' => array( 'dimensions' => '210 x 297', 'width' => 210, 'height' => 297 ),
            'A5' => array( 'dimensions' => '148 x 210', 'width' => 148, 'height' => 210 ),
            'A6' => array( 'dimensions' => '105 x 148', 'width' => 105, 'height' => 148 ),
            'A7' => array( 'dimensions' => '74 x 105', 'width' => 74, 'height' => 105 ),
            'A8' => array( 'dimensions' => '50 x 80', 'width' => 50, 'height' => 80 ),
        );
    }
    
    private function get_product_sizes($pricing_slug) {
        // Get dimensions from Product Sizes config
        $product_sizes = $this->get_product_sizes_config();
        
        if (isset($product_sizes[$pricing_slug])) {
            $data = $product_sizes[$pricing_slug];
            $w = isset($data['width']) ? intval($data['width']) : 0;
            $h = isset($data['height']) ? intval($data['height']) : 0;
            if ($w > 0 && $h > 0) {
                // Return size in mm format
                return array($w . ' x ' . $h . 'mm');
            }
        }
        
        // Try normalized slug match
        $normalized_slug = strtolower(str_replace(array('-', '_', ' '), '', $pricing_slug));
        foreach ($product_sizes as $slug => $data) {
            $normalized_key = strtolower(str_replace(array('-', '_', ' '), '', $slug));
            if ($normalized_key === $normalized_slug) {
                $w = isset($data['width']) ? intval($data['width']) : 0;
                $h = isset($data['height']) ? intval($data['height']) : 0;
                if ($w > 0 && $h > 0) {
                    return array($w . ' x ' . $h . 'mm');
                }
            }
        }
        
        return array('Standard');
    }
    
    // AJAX: Calculate Price
    public function ajax_calculate_price() {
        check_ajax_referer('pws_nonce', 'nonce');
        
        $pricing_slug = sanitize_text_field($_POST['pricing_slug'] ?? '');
        $size         = sanitize_text_field($_POST['size']         ?? '');
        $quantity     = max(1, intval($_POST['quantity']  ?? 1));
        $thumbcut     = sanitize_text_field($_POST['thumbcut']  ?? 'no');
        $holepunch    = sanitize_text_field($_POST['holepunch'] ?? 'none');
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        // Get product dimensions from Product Sizes config
        $product_sizes = $this->get_product_sizes_config();
        
        // Try to find product by slug
        $width = 0;
        $height = 0;
        $found = false;
        
        // First: Try lookup by WC product ID (most reliable)
        if ($product_id > 0) {
            foreach ($product_sizes as $slug => $data) {
                if (isset($data['wc_product_id']) && intval($data['wc_product_id']) === $product_id) {
                    $width = intval($data['width']);
                    $height = intval($data['height']);
                    $found = true;
                    break;
                }
            }
        }
        
        // Second: Direct slug match
        if (!$found && isset($product_sizes[$pricing_slug])) {
            $width = intval($product_sizes[$pricing_slug]['width']);
            $height = intval($product_sizes[$pricing_slug]['height']);
            $found = true;
        }
        
        // Third: Try normalized slug match (remove hyphens, lowercase)
        if (!$found) {
            $normalized_slug = strtolower(str_replace(array('-', '_', ' '), '', $pricing_slug));
            foreach ($product_sizes as $slug => $data) {
                $normalized_key = strtolower(str_replace(array('-', '_', ' '), '', $slug));
                if ($normalized_key === $normalized_slug) {
                    $width = intval($data['width']);
                    $height = intval($data['height']);
                    $found = true;
                    break;
                }
            }
        }
        
        // Fourth: Try matching by product name
        if (!$found) {
            $normalized_slug = strtolower(str_replace(array('-', '_', ' '), '', $pricing_slug));
            foreach ($product_sizes as $slug => $data) {
                $normalized_name = strtolower(str_replace(array('-', '_', ' '), '', $data['name']));
                if ($normalized_name === $normalized_slug || strpos($normalized_name, $normalized_slug) !== false) {
                    $width = intval($data['width']);
                    $height = intval($data['height']);
                    $found = true;
                    break;
                }
            }
        }
        
        // Fifth: Parse size string as fallback (e.g., "8.8 x 5.5cm" or "88x55mm")
        if (!$found && !empty($size) && $size !== 'Standard') {
            // Try cm format: "8.8 x 5.5cm"
            if (preg_match('/([\d.]+)\s*x\s*([\d.]+)\s*cm/i', $size, $matches)) {
                $width = intval(round(floatval($matches[1]) * 10)); // Convert cm to mm
                $height = intval(round(floatval($matches[2]) * 10));
                $found = true;
            }
            // Try mm format: "88x55mm"
            elseif (preg_match('/(\d+)\s*x\s*(\d+)\s*mm/i', $size, $matches)) {
                $width = intval($matches[1]);
                $height = intval($matches[2]);
                $found = true;
            }
            // Try plain format: "88 x 55" or "8.8 x 5.5"
            elseif (preg_match('/([\d.]+)\s*x\s*([\d.]+)/i', $size, $matches)) {
                $w = floatval($matches[1]);
                $h = floatval($matches[2]);
                // If values are small (< 50), assume cm and convert to mm
                if ($w < 50 && $h < 50) {
                    $width = intval(round($w * 10));
                    $height = intval(round($h * 10));
                } else {
                    $width = intval($w);
                    $height = intval($h);
                }
                $found = true;
            }
        }
        
        if (!$found || $width <= 0 || $height <= 0) {
            wp_send_json_error(array('message' => 'Product dimensions not found for "' . $pricing_slug . '" (ID: ' . $product_id . '). Please add this product in PWS Pricing → Product Sizes.'));
            return;
        }
        
        // Use the standard pricing formula
        $pricing_params = get_option('pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        
        $multiplier = floatval($pricing_params['multiplier']);
        $base_cost = floatval($pricing_params['base_cost']);
        
        // Ensure multiplier is correct (safety check)
        if ($multiplier > 0.001 || $multiplier <= 0) {
            $multiplier = 0.00072; // Force correct default
        }
        
        /**
         * Formula: =ROUND((((Width_mm * Height_mm) * 0.00072) * Quantity / 100) + 13.5, 2)
         */
        $base_price = round(((($width * $height) * $multiplier) * $quantity / 100) + $base_cost, 2);
        
        // Add-ons: £0.01 per unit each
        $thumbcut_addon = ($thumbcut === 'yes') ? 0.01 : 0;
        $holepunch_addon = ($holepunch !== 'none' && $holepunch !== '') ? 0.01 : 0;
        
        $addon_total = ($thumbcut_addon + $holepunch_addon) * $quantity;
        $final_total = round($base_price + $addon_total, 2);
        $final_unit = round($final_total / max(1, $quantity), 4);
        
        // Bulk suggestions
        $bulk = array();
        foreach (array(100, 200, 400) as $bq) {
            if ($bq > $quantity) {
                $bq_base = round(((($width * $height) * $multiplier) * $bq / 100) + $base_cost, 2);
                $bq_addon = ($thumbcut_addon + $holepunch_addon) * $bq;
                $bq_total = round($bq_base + $bq_addon, 2);
                $bq_unit = round($bq_total / $bq, 4);
                $bulk[] = array(
                    'qty' => $bq,
                    'total' => number_format($bq_total, 2),
                    'unit' => number_format($bq_unit, 2)
                );
            }
        }
        
        wp_send_json_success(array(
            'total' => number_format($final_total, 2),
            'unit_price' => number_format($final_unit, 2),
            'bulk_suggestions' => $bulk,
            'raw_total' => $final_total,
            'raw_unit' => $final_unit,
        ));
    }
    
    // AJAX: Calculate Custom Size Price
    public function ajax_calculate_custom_price() {
        check_ajax_referer('pws_nonce', 'nonce');
        
        $width     = max(1, intval($_POST['width']    ?? 50));
        $height    = max(1, intval($_POST['height']   ?? 50));
        $quantity  = max(1, intval($_POST['quantity'] ?? 1));
        $thumbcut  = sanitize_text_field($_POST['thumbcut']  ?? 'no');
        $holepunch = sanitize_text_field($_POST['holepunch'] ?? 'none');
        
        $result = $this->calculate_price_from_dimensions($width, $height, $quantity, $thumbcut, $holepunch);
        $final_total = $result['total'];
        $final_unit = $result['unit'];
        
        $thumbcut_addon = ($thumbcut === 'yes') ? 0.01 : 0;
        $holepunch_addon = ($holepunch !== 'none' && $holepunch !== '') ? 0.01 : 0;
        
        // Bulk suggestions
        $bulk = array();
        foreach (array(200, 400, 500, 1000) as $bq) {
            if ($bq > $quantity) {
                $bq_result = $this->calculate_price_from_dimensions($width, $height, $bq, $thumbcut, $holepunch);
                $bulk[] = array(
                    'qty' => $bq, 
                    'total' => number_format($bq_result['total'], 2), 
                    'unit' => number_format($bq_result['unit'], 2)
                );
                if (count($bulk) >= 2) break;
            }
        }
        
        wp_send_json_success(array(
            'total' => number_format($final_total, 2),
            'unit_price' => number_format($final_unit, 2),
            'bulk_suggestions' => $bulk,
            'raw_total' => $final_total,
            'raw_unit' => $final_unit
        ));
    }
    
    // AJAX: Add Custom Size to Cart
    public function ajax_add_custom_to_cart() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) { 
            wp_send_json_error(array('message' => 'Cart not available')); 
            return; 
        }
        
        $width      = intval($_POST['width']     ?? 0);
        $height     = intval($_POST['height']    ?? 0);
        $size_type  = sanitize_text_field($_POST['size_type'] ?? 'Custom');
        $product_id = intval($_POST['product_id'] ?? 0);
        
        // Build size string
        if ($size_type === 'Custom') {
            $size_display = $width . ' x ' . $height . 'mm (Custom)';
        } else {
            $size_display = $width . ' x ' . $height . 'mm (' . $size_type . ')';
        }
        
        // Resolve product name with server-side fallback
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        if ('' === $product_name && $product_id) {
            $wc_prod = wc_get_product($product_id);
            if ($wc_prod) $product_name = $wc_prod->get_name();
        }
        
        $cart_item_data = array(
            'pws_custom'               => true,
            'pws_is_custom_size'       => true,
            'pws_original_product_id'  => $product_id,
            'pws_product_name'         => $product_name,
            'pws_size'                 => $size_display,
            'pws_width'                => $width,
            'pws_height'               => $height,
            'pws_size_type'            => $size_type,
            'pws_thumbcut'             => sanitize_text_field($_POST['thumbcut']  ?? ''),
            'pws_holepunch'            => sanitize_text_field($_POST['holepunch'] ?? ''),
            'pws_openside'             => sanitize_text_field($_POST['openside']  ?? ''),
            'pws_unit_price'           => floatval($_POST['unit_price']  ?? 0),
            'pws_total_price'          => floatval($_POST['total_price'] ?? 0),
            'pws_unique_key'           => md5(microtime() . rand()),
        );
        
        $cart_item_key = WC()->cart->add_to_cart(
            $this->get_cart_product_id(),
            max(1, intval($_POST['quantity'] ?? 1)),
            0,
            array(),
            $cart_item_data
        );
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'message' => 'Added to basket', 
                'cart_url' => wc_get_cart_url(), 
                'cart_count' => count(WC()->cart->get_cart())
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not add to cart'));
        }
    }
    
    // AJAX: Get Product Data
    public function ajax_get_product_data() {
        check_ajax_referer('pws_nonce', 'nonce');
        $product_id = intval($_POST['product_id'] ?? 0);
        $wc_product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        if (!$wc_product) { wp_send_json_error(array('message' => 'Product not found')); return; }
        
        wp_send_json_success(array(
            'id' => $product_id,
            'name' => $wc_product->get_name(),
            'image' => wp_get_attachment_image_url($wc_product->get_image_id(), 'large'),
            'pricing_slug' => $this->get_pricing_slug($product_id),
            'sizes' => $this->get_product_sizes($this->get_pricing_slug($product_id)),
            'permalink' => get_permalink($product_id)
        ));
    }
    
    // AJAX: Add to Cart
    public function ajax_add_to_cart() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) { wp_send_json_error(array('message' => 'Cart not available')); return; }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        
        // Resolve product name: prefer what JS sends, fall back to WC product name
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        if ('' === $product_name && $product_id) {
            $wc_prod = wc_get_product($product_id);
            if ($wc_prod) {
                $product_name = $wc_prod->get_name();
            }
        }
        
        // Look up dimensions for cart qty recalculation
        $prod_width = 0;
        $prod_height = 0;
        if ($product_id) {
            $product_sizes = $this->get_product_sizes_config();
            foreach ($product_sizes as $slug => $pdata) {
                if (isset($pdata['wc_product_id']) && intval($pdata['wc_product_id']) === $product_id) {
                    $prod_width = intval($pdata['width']);
                    $prod_height = intval($pdata['height']);
                    break;
                }
            }
        }
        
        $cart_item_data = array(
            'pws_custom'               => true,
            'pws_original_product_id'  => $product_id,
            'pws_product_name'         => $product_name,
            'pws_size'                 => sanitize_text_field($_POST['size']      ?? ''),
            'pws_width'                => $prod_width,
            'pws_height'               => $prod_height,
            'pws_thumbcut'             => sanitize_text_field($_POST['thumbcut']  ?? ''),
            'pws_holepunch'            => sanitize_text_field($_POST['holepunch'] ?? ''),
            'pws_openside'             => sanitize_text_field($_POST['openside']  ?? ''),
            'pws_unit_price'           => floatval($_POST['unit_price']  ?? 0),
            'pws_total_price'          => floatval($_POST['total_price'] ?? 0),
            'pws_unique_key'           => md5(microtime() . rand()),
        );
        
        $cart_item_key = WC()->cart->add_to_cart(
            $this->get_cart_product_id(),
            max(1, intval($_POST['quantity'] ?? 1)),
            0,
            array(),
            $cart_item_data
        );
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'message'    => 'Added to basket',
                'cart_url'   => wc_get_cart_url(),
                'cart_count' => count(WC()->cart->get_cart()),
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not add to cart'));
        }
    }
    
    public function display_cart_item_data($item_data, $cart_item) {
        $is_pws = (isset($cart_item['pws_custom']) && $cart_item['pws_custom'])
                || (isset($cart_item['pws_custom_item']) && $cart_item['pws_custom_item']);
        if (!$is_pws) return $item_data;
        
        if (!empty($cart_item['pws_product_name'])) {
            $item_data[] = array('key' => 'Product', 'value' => $cart_item['pws_product_name']);
        }
        if (!empty($cart_item['pws_size'])) {
            $item_data[] = array('key' => 'Size', 'value' => $cart_item['pws_size']);
        }
        if (!empty($cart_item['pws_thumbcut'])) {
            $item_data[] = array('key' => 'Thumbcuts', 'value' => ucfirst($cart_item['pws_thumbcut']));
        }
        if (!empty($cart_item['pws_holepunch'])) {
            $item_data[] = array('key' => 'Hole Punch', 'value' => ucfirst(str_replace('-', ' ', $cart_item['pws_holepunch'])));
        }
        if (!empty($cart_item['pws_openside'])) {
            $item_data[] = array('key' => 'Open Side', 'value' => ucfirst($cart_item['pws_openside']));
        }
        return $item_data;
    }
    
    public function set_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;
        foreach ($cart->get_cart() as $cart_item) {
            $is_pws = (isset($cart_item['pws_custom']) && $cart_item['pws_custom'])
                    || (isset($cart_item['pws_custom_item']) && $cart_item['pws_custom_item']);
            if ($is_pws) {
                $qty = max(1, $cart_item['quantity']);
                $total = isset($cart_item['pws_total_price']) ? floatval($cart_item['pws_total_price']) : 0;
                $unit  = isset($cart_item['pws_unit_price']) ? floatval($cart_item['pws_unit_price']) : 0;
                
                // Use total/qty for full precision to prevent rounding drift
                // e.g. £20.70/400 = £0.05175 rather than rounded £0.05
                if ($total > 0 && $qty > 0) {
                    $cart_item['data']->set_price($total / $qty);
                } elseif ($unit > 0) {
                    $cart_item['data']->set_price($unit);
                }
            }
        }
    }
    
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        $is_pws = (isset($values['pws_custom']) && $values['pws_custom'])
                || (isset($values['pws_custom_item']) && $values['pws_custom_item']);
        if (!$is_pws) return;
        
        if (!empty($values['pws_product_name'])) {
            $item->add_meta_data('Product Type', $values['pws_product_name'], true);
        }
        if (!empty($values['pws_size'])) {
            $item->add_meta_data('Size', $values['pws_size'], true);
        }
        if (!empty($values['pws_thumbcut'])) {
            $item->add_meta_data('Thumbcuts', ucfirst($values['pws_thumbcut']), true);
        }
        if (!empty($values['pws_holepunch'])) {
            $item->add_meta_data('Hole Punch', ucfirst(str_replace('-', ' ', $values['pws_holepunch'])), true);
        }
        if (!empty($values['pws_openside'])) {
            $item->add_meta_data('Open Side', ucfirst($values['pws_openside']), true);
        }
    }
    
    /**
     * Get thumbnail for Custom Size items from the page containing [pws_custom_sizes]
     */
    private function get_custom_size_thumbnail() {
        static $url = null;
        if ($url !== null) return $url;
        
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
             AND post_content LIKE '%[pws_custom_sizes%'
             ORDER BY ID ASC LIMIT 1"
        );
        if ($page_id && has_post_thumbnail($page_id)) {
            $url = get_the_post_thumbnail_url($page_id, 'medium');
        } else {
            $url = '';
        }
        return $url;
    }
    
    /**
     * Get thumbnail for A Size items from the page containing [pws_a_sizes]
     */
    private function get_a_size_thumbnail() {
        static $url = null;
        if ($url !== null) return $url;
        
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
             AND post_content LIKE '%[pws_a_sizes%'
             ORDER BY ID ASC LIMIT 1"
        );
        if ($page_id && has_post_thumbnail($page_id)) {
            $url = get_the_post_thumbnail_url($page_id, 'medium');
        } else {
            $url = '';
        }
        return $url;
    }
    
    private function get_cart_product_id() {
        $product_id = get_option('pws_cart_product_id');
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_purchasable()) return $product_id;
        }
        if (!class_exists('WC_Product_Simple')) return 0;
        
        $product = new WC_Product_Simple();
        $product->set_name('Custom Plastic Sleeve');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_virtual(true);
        $product->save();
        update_option('pws_cart_product_id', $product->get_id());
        return $product->get_id();
    }
    
    // Admin
    public function add_admin_menu() {
        add_menu_page('PWS Pricing', 'PWS Pricing', 'manage_options', 'pws-pricing', array($this, 'render_admin_page'), 'dashicons-cart', 56);
        add_submenu_page('pws-pricing', 'Product Sizes', 'Product Sizes', 'manage_options', 'pws-product-sizes', array($this, 'render_product_sizes_page'));
        add_submenu_page('pws-pricing', 'Category Mappings', 'Category Mappings', 'manage_options', 'pws-categories', array($this, 'render_categories_page'));
        add_submenu_page('pws-pricing', 'A Sizes Config', 'A Sizes Config', 'manage_options', 'pws-a-sizes', array($this, 'render_a_sizes_config_page'));
        // Legacy pages removed - using formula-based pricing only
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'pws-') === false && strpos($hook, 'pws_') === false) return;
        wp_enqueue_style('pws-admin', PWS_PLUGIN_URL . 'assets/css/pws-admin.css', array(), PWS_VERSION);
        wp_enqueue_script('pws-admin', PWS_PLUGIN_URL . 'assets/js/pws-admin.js', array('jquery', 'jquery-ui-sortable'), PWS_VERSION, true);
        wp_localize_script('pws-admin', 'pws_admin', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('pws_admin_nonce')));
    }
    
    public function render_admin_page() {
        // Get current pricing params for display
        $pricing_params = get_option('pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        $product_sizes = $this->get_product_sizes_config();
        $category_mappings = $this->get_category_mappings();
        ?>
        <div class="wrap pws-admin">
            <h1>🛒 Plastic Wallet Shop - Pricing System</h1>
            <p style="font-size: 14px; color: #666;">Version <?php echo esc_html( PWS_VERSION ); ?> | Developed by <strong>Shaan - Full Stack Developer</strong></p>
            
            <!-- System Status -->
            <div style="background: #fff; padding: 15px 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px; border-left: 4px solid <?php echo ($pricing_params['multiplier'] > 0.001) ? '#d63638' : '#46b450'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <strong>Formula:</strong> 
                        ROUND((((W × H) × <?php echo esc_html($pricing_params['multiplier']); ?>) × Qty ÷ 100) + £<?php echo esc_html($pricing_params['base_cost']); ?>, 2)
                        <?php if ($pricing_params['multiplier'] > 0.001): ?>
                            <span style="color: #d63638; margin-left: 10px;">⚠️ Wrong multiplier!</span>
                        <?php else: ?>
                            <span style="color: #46b450; margin-left: 10px;">✓</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <span style="background: #f0f0f1; padding: 5px 12px; border-radius: 3px;">
                            📦 <strong><?php echo count($product_sizes); ?></strong> Products
                        </span>
                        <span style="background: #f0f0f1; padding: 5px 12px; border-radius: 3px;">
                            🗂️ <strong><?php echo count($category_mappings); ?></strong> Categories
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="pws-admin-cards">
                <div class="pws-admin-card">
                    <h2>📦 Product Sizes</h2>
                    <p>Add products and set mm dimensions. Prices calculate automatically.</p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=pws-product-sizes' ) ); ?>" class="button button-primary">Manage Products</a>
                </div>
                <div class="pws-admin-card">
                    <h2>🗂️ Category Mappings</h2>
                    <p>Assign products to categories and set display order.</p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=pws-categories' ) ); ?>" class="button button-primary">Manage Categories</a>
                </div>
                <div class="pws-admin-card">
                    <h2>💰 Pricing Formula</h2>
                    <p>Change multiplier & base cost to adjust ALL prices.</p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=pws-a-sizes' ) ); ?>" class="button button-primary">Edit Formula</a>
                </div>
            </div>
            
            <!-- Reset Section -->
            <div style="background: #fff3cd; padding: 15px 20px; margin: 20px 0; border: 1px solid #ffc107; border-radius: 4px;">
                <h3 style="margin-top: 0; color: #856404;">🔄 Reset to Defaults</h3>
                <p style="margin-bottom: 10px; color: #856404;">This will reset all pricing settings, product sizes, and category mappings to the pre-configured defaults.</p>
                <button type="button" id="pws-reset-all-defaults" class="button" style="background: #ffc107; border-color: #ffc107; color: #000;">
                    🔄 Reset ALL to Defaults
                </button>
                <span id="pws-reset-message" style="margin-left: 15px;"></span>
            </div>
            
            <div class="pws-admin-info">
                <h2>📋 How It Works</h2>
                <ol style="font-size: 14px; line-height: 1.8;">
                    <li><strong>Product Sizes</strong> - Products with mm dimensions (linked to WooCommerce)</li>
                    <li><strong>Category Mappings</strong> - Products assigned to: Organise, Keep Safe, For Business</li>
                    <li><strong>Display Order</strong> - Drag to reorder products within each category</li>
                    <li><strong>Auto-Pricing</strong> - All prices calculated using the formula</li>
                </ol>
                
                <h3>💡 Example: Driving License (88mm × 55mm), Qty 100</h3>
                <p style="background: #f0f0f1; padding: 10px; font-family: monospace;">
                    = ROUND((((88 × 55) × 0.00072) × 100 ÷ 100) + 13.5, 2) = <strong>£16.98</strong>
                </p>
                
                <h2 style="margin-top: 30px;">🎯 Quick Reference</h2>
                <table class="widefat" style="max-width: 600px;">
                    <tr><th>Category Page</th><th>URL</th></tr>
                    <tr><td>Organise</td><td><code>/product-category/organise/</code></td></tr>
                    <tr><td>Keep Safe</td><td><code>/product-category/keep-safe/</code></td></tr>
                    <tr><td>For Business</td><td><code>/product-category/for-business/</code></td></tr>
                    <tr><td>Custom Sizes</td><td><code>/custom-wallets/</code> (shortcode page)</td></tr>
                    <tr><td>A Sizes</td><td><code>/a-sizes/</code> (shortcode page)</td></tr>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#pws-reset-all-defaults').on('click', function() {
                if (!confirm('⚠️ RESET ALL TO DEFAULTS?\n\nThis will:\n• Set pricing to 0.00072 / £13.50\n• Reset all 25 products with WC IDs\n• Reset category mappings (Organise, Keep Safe, For Business)\n\nContinue?')) {
                    return;
                }
                
                var $btn = $(this);
                var $msg = $('#pws-reset-message');
                $btn.prop('disabled', true).text('Resetting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pws_reset_all_defaults',
                        nonce: '<?php echo wp_create_nonce('pws_admin_nonce'); ?>'
                    },
                    success: function(r) {
                        if (r.success) {
                            $msg.html('<span style="color: #46b450;">✓ ' + r.data.message + '</span>');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $msg.html('<span style="color: #dc3232;">✗ ' + (r.data.message || 'Error') + '</span>');
                            $btn.prop('disabled', false).text('🔄 Reset ALL to Defaults');
                        }
                    },
                    error: function() {
                        $msg.html('<span style="color: #dc3232;">✗ Error resetting</span>');
                        $btn.prop('disabled', false).text('🔄 Reset ALL to Defaults');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Category Mappings Admin Page (v11 - With Product Ordering)
     */
    public function render_categories_page() {
        $mappings = $this->get_category_mappings();
        $products = wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        ?>
        <div class="wrap pws-admin">
            <h1>🗂️ Category → Product Mappings</h1>
            <p>Assign products to categories and set their display order. When visitors access a category URL (e.g., /product-category/organise/), they'll see your custom product grid.</p>
            
            <!-- Add New Mapping -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>➕ Add/Edit Category Mapping</h2>
                <form id="pws-category-mapping-form">
                    <table class="form-table">
                        <tr>
                            <th>Category *</th>
                            <td>
                                <select name="category_slug" id="pws-cat-slug" class="regular-text" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?> (<?php echo $cat->slug; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select: organise, keep-safe, or for-business</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Products (drag to reorder)</th>
                            <td>
                                <div style="display: flex; gap: 20px;">
                                    <!-- Available Products -->
                                    <div style="flex: 1;">
                                        <h4 style="margin-top: 0;">Available Products</h4>
                                        <div id="pws-available-products" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; min-height: 200px;">
                                            <?php foreach ($products as $p) : ?>
                                            <div class="pws-product-item" data-id="<?php echo $p->get_id(); ?>" style="padding: 8px; margin-bottom: 5px; background: #fff; border: 1px solid #ddd; cursor: move; display: flex; align-items: center;">
                                                <span style="flex: 1;"><strong><?php echo esc_html($p->get_name()); ?></strong> <small style="color: #666;">(ID: <?php echo $p->get_id(); ?>)</small></span>
                                                <button type="button" class="button button-small pws-add-product" title="Add">→</button>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Selected Products (Ordered) -->
                                    <div style="flex: 1;">
                                        <h4 style="margin-top: 0;">Selected Products (in display order)</h4>
                                        <div id="pws-selected-products" style="max-height: 400px; overflow-y: auto; border: 2px dashed #0073aa; padding: 10px; background: #e6f3ff; min-height: 200px;">
                                            <p class="pws-empty-msg" style="color: #666; text-align: center; padding: 20px;">Drag products here or click → to add</p>
                                        </div>
                                        <input type="hidden" name="product_ids" id="pws-product-ids-input" value="">
                                        <p class="description" style="margin-top: 10px;">
                                            <strong>Selected:</strong> <span id="pws-selected-count">0</span> products | Drag to reorder
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">💾 Save Mapping</button>
                        <span class="pws-save-status" style="margin-left: 15px;"></span>
                    </p>
                </form>
            </div>
            
            <!-- Current Mappings -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>📋 Current Mappings (<?php echo count($mappings); ?>)</h2>
                <?php if (empty($mappings)) : ?>
                <p style="color: #666;">No category mappings configured yet. Add one above!</p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Category</th>
                            <th>Assigned Products (in order)</th>
                            <th style="width: 80px;">Count</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mappings as $slug => $mapping_data) : 
                            // Handle both new structure (with 'products' key) and legacy (simple array)
                            $product_ids = isset($mapping_data['products']) ? $mapping_data['products'] : (is_array($mapping_data) ? $mapping_data : array());
                            $order = isset($mapping_data['order']) ? $mapping_data['order'] : array();
                            
                            // Sort by order if available
                            if (!empty($order)) {
                                usort($product_ids, function($a, $b) use ($order) {
                                    $oa = isset($order[$a]) ? $order[$a] : 999;
                                    $ob = isset($order[$b]) ? $order[$b] : 999;
                                    return $oa - $ob;
                                });
                            }
                            
                            $product_names = array();
                            foreach ($product_ids as $pid) {
                                $p = wc_get_product($pid);
                                if ($p) $product_names[] = $p->get_name();
                            }
                            $cat_obj = get_term_by('slug', $slug, 'product_cat');
                            $cat_name = $cat_obj ? $cat_obj->name : ucfirst(str_replace('-', ' ', $slug));
                        ?>
                        <tr data-slug="<?php echo esc_attr($slug); ?>">
                            <td>
                                <strong><?php echo esc_html($cat_name); ?></strong><br>
                                <code style="font-size: 11px;">/product-category/<?php echo esc_html($slug); ?>/</code>
                            </td>
                            <td>
                                <?php 
                                $i = 1;
                                foreach ($product_names as $name) {
                                    echo '<span style="display: inline-block; background: #f0f0f1; padding: 2px 8px; margin: 2px; border-radius: 3px; font-size: 12px;">' . $i . '. ' . esc_html($name) . '</span>';
                                    $i++;
                                }
                                ?>
                            </td>
                            <td style="text-align: center;"><span style="background: #0073aa; color: #fff; padding: 3px 10px; border-radius: 3px;"><?php echo count($product_ids); ?></span></td>
                            <td>
                                <button class="button pws-edit-mapping" data-slug="<?php echo esc_attr($slug); ?>" data-products="<?php echo esc_attr(implode(',', $product_ids)); ?>">✏️ Edit</button>
                                <button class="button pws-delete-mapping" data-slug="<?php echo esc_attr($slug); ?>" style="color: #a00;">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- How It Works -->
            <div class="pws-admin-section" style="background: #f0f6fc; padding: 20px; margin-top: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
                <h3>ℹ️ How It Works</h3>
                <ol style="margin-left: 20px;">
                    <li>Select a WooCommerce product category from the dropdown</li>
                    <li>Check the products you want to display on that category page</li>
                    <li>Products will appear in a grid (4 per row) when customers visit the category</li>
                    <li>Customers can click products to see options and add to cart</li>
                </ol>
                <p><strong>Category URLs:</strong> <code>/product-category/organise/</code>, <code>/product-category/keep-safe/</code>, <code>/product-category/for-business/</code></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save Category Mapping (v10 - Unlimited Products)
     */
    public function ajax_save_category_mapping() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $slug = sanitize_title($_POST['category_slug'] ?? '');
        if (empty($slug)) {
            wp_send_json_error(array('message' => 'Category slug is required'));
            return;
        }
        
        // Get product IDs from POST - supports ordered array
        $product_ids = array();
        if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            foreach ($_POST['product_ids'] as $pid) {
                $pid = intval($pid);
                if ($pid > 0) {
                    $product_ids[] = $pid;
                }
            }
        }
        
        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'At least one product is required'));
            return;
        }
        
        // Build order array (1, 2, 3, etc based on position)
        $order = array();
        $position = 1;
        foreach ($product_ids as $pid) {
            $order[$pid] = $position;
            $position++;
        }
        
        // Save with new structure
        $mappings = get_option('pws_category_mappings', array());
        $mappings[$slug] = array(
            'products' => $product_ids,
            'order' => $order
        );
        update_option('pws_category_mappings', $mappings);
        
        wp_send_json_success(array('message' => 'Mapping saved successfully! ' . count($product_ids) . ' products assigned.', 'count' => count($product_ids)));
    }
    
    /**
     * AJAX: Delete Category Mapping
     */
    public function ajax_delete_category_mapping() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $slug = sanitize_title($_POST['category_slug'] ?? '');
        if (empty($slug)) {
            wp_send_json_error(array('message' => 'Category slug is required'));
            return;
        }
        
        $mappings = $this->get_category_mappings();
        if (isset($mappings[$slug])) {
            unset($mappings[$slug]);
            update_option('pws_category_mappings', $mappings);
        }
        
        wp_send_json_success(array('message' => 'Mapping deleted'));
    }
    
    public function render_featured_page() {
        $featured_ids = $this->get_featured_product_ids();
        $products = wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <div class="wrap pws-admin">
            <h1>Featured Products</h1>
            <form id="pws-featured-form">
                <?php for ($i = 0; $i < 4; $i++) : $current_id = isset($featured_ids[$i]) ? $featured_ids[$i] : ''; ?>
                <div class="pws-featured-row">
                    <label>Product <?php echo $i + 1; ?>:</label>
                    <select name="featured_products[]" class="pws-product-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($products as $p) : ?><option value="<?php echo $p->get_id(); ?>" <?php selected($current_id, $p->get_id()); ?>><?php echo esc_html($p->get_name()); ?> (<?php echo $p->get_id(); ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <?php endfor; ?>
                <p class="submit"><button type="submit" class="button button-primary">Save</button><span class="pws-save-status"></span></p>
            </form>
        </div>
        <?php
    }
    
    public function render_pricing_page() {
        global $wpdb;
        $pricing_data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pws_pricing ORDER BY product_name ASC");
        ?>
        <div class="wrap pws-admin">
            <h1>Pricing Table</h1>
            <div class="pws-pricing-actions"><button type="button" id="pws-add-new-pricing" class="button button-primary">+ Add New</button></div>
            <div id="pws-pricing-form-container" style="display:none;">
                <h2 id="pws-form-title">Add New Pricing</h2>
                <form id="pws-pricing-form">
                    <input type="hidden" name="pricing_id" id="pricing_id" value="">
                    <table class="form-table">
                        <tr><th>Product Name *</th><td><input type="text" name="product_name" id="product_name" required class="regular-text"></td></tr>
                        <tr><th>Product Slug *</th><td><input type="text" name="product_slug" id="product_slug" required class="regular-text"></td></tr>
                        <tr><th>Size *</th><td><input type="text" name="size" id="size" required class="regular-text"></td></tr>
                        <tr><th>Category</th><td><input type="text" name="main_category" id="main_category" class="regular-text"></td></tr>
                        <tr><th>Sub Category</th><td><input type="text" name="sub_category" id="sub_category" class="regular-text"></td></tr>
                        <tr><th>Thumbcut Cost</th><td><input type="number" step="0.00001" name="thumbcut_cost" id="thumbcut_cost" value="0.01" class="small-text"></td></tr>
                        <tr><th>Hole Punch Cost</th><td><input type="number" step="0.00001" name="holepunch_cost" id="holepunch_cost" value="0.01" class="small-text"></td></tr>
                    </table>
                    <h3>Quantity Tier Pricing (Unit Price)</h3>
                    <table class="widefat pws-tier-table"><thead><tr><?php foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $q) : ?><th>Qty <?php echo $q; ?></th><?php endforeach; ?></tr></thead><tbody><tr><?php foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $q) : ?><td><input type="number" step="0.00001" name="qty_<?php echo $q; ?>_unit" id="qty_<?php echo $q; ?>_unit" class="small-text"></td><?php endforeach; ?></tr></tbody></table>
                    <p class="submit"><button type="submit" class="button button-primary">Save</button><button type="button" id="pws-cancel-form" class="button">Cancel</button><span class="pws-save-status"></span></p>
                </form>
            </div>
            <table class="widefat pws-pricing-list">
                <thead><tr><th>Product</th><th>Slug</th><th>Size</th><th>Qty 100</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($pricing_data)) : ?><tr><td colspan="5">No data.</td></tr>
                    <?php else : foreach ($pricing_data as $row) : ?>
                    <tr><td><?php echo esc_html($row->product_name); ?></td><td><code><?php echo esc_html($row->product_slug); ?></code></td><td><?php echo esc_html($row->size); ?></td><td>£<?php echo number_format($row->qty_100_unit, 4); ?></td><td><button class="button pws-edit-pricing" data-id="<?php echo $row->id; ?>">Edit</button> <button class="button pws-delete-pricing" data-id="<?php echo $row->id; ?>">Delete</button></td></tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <script>var pwsPricingData = <?php echo json_encode($pricing_data); ?>;</script>
        <?php
    }
    
    public function render_import_export_page() {
        ?>
        <div class="wrap pws-admin">
            <h1>Import / Export</h1>
            <div class="pws-admin-cards">
                <div class="pws-admin-card"><h2>📥 Import CSV</h2><form id="pws-import-form" enctype="multipart/form-data"><p><input type="file" name="csv_file" accept=".csv" required></p><p><label><input type="checkbox" name="replace_existing" value="1"> Replace existing</label></p><p><button type="submit" class="button button-primary">Import</button><span class="pws-import-status"></span></p></form></div>
                <div class="pws-admin-card"><h2>📤 Export CSV</h2><p><a href="<?php echo admin_url('admin-ajax.php?action=pws_export_csv&nonce=' . wp_create_nonce('pws_admin_nonce')); ?>" class="button button-primary">Download</a></p></div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_save_featured_products() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permission denied'));
        update_option('pws_featured_products', array_filter(array_map('intval', $_POST['products'] ?? array())));
        wp_send_json_success(array('message' => 'Saved'));
    }
    
    public function ajax_save_pricing() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permission denied'));
        global $wpdb;
        $table = $wpdb->prefix . 'pws_pricing';
        $id = intval($_POST['pricing_id'] ?? 0);
        $data = array(
            'product_name' => sanitize_text_field($_POST['product_name']  ?? ''),
            'product_slug' => sanitize_title($_POST['product_slug']       ?? ''),
            'size'         => sanitize_text_field($_POST['size']          ?? ''),
            'main_category'=> sanitize_text_field($_POST['main_category'] ?? ''),
            'sub_category' => sanitize_text_field($_POST['sub_category']  ?? ''),
            'thumbcut_cost' => floatval($_POST['thumbcut_cost']  ?? 0),
            'holepunch_cost'=> floatval($_POST['holepunch_cost'] ?? 0),
        );
        foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $q) {
            $data['qty_' . $q . '_unit'] = floatval($_POST['qty_' . $q . '_unit'] ?? 0);
            $data['qty_' . $q . '_total'] = $data['qty_' . $q . '_unit'] * $q;
        }
        if ($id > 0) $wpdb->update($table, $data, array('id' => $id));
        else { $wpdb->insert($table, $data); $id = $wpdb->insert_id; }
        wp_send_json_success(array('message' => 'Saved', 'id' => $id));
    }
    
    public function ajax_delete_pricing() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permission denied'));
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'pws_pricing', array('id' => intval($_POST['pricing_id'] ?? 0)));
        wp_send_json_success(array('message' => 'Deleted'));
    }
    
    public function ajax_import_csv() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permission denied'));
        if (!isset($_FILES['csv_file'])) wp_send_json_error(array('message' => 'No file'));
        global $wpdb;
        $table = $wpdb->prefix . 'pws_pricing';
        $replace = !empty($_POST['replace_existing'] ?? null);
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $d = array_combine($header, $row);
            $pd = array('product_name' => sanitize_text_field($d['product_name']), 'product_slug' => sanitize_title($d['product_slug']), 'size' => sanitize_text_field($d['size']), 'main_category' => sanitize_text_field($d['main_category'] ?? ''), 'sub_category' => sanitize_text_field($d['sub_category'] ?? ''), 'thumbcut_cost' => floatval($d['thumbcut_cost'] ?? 0.01), 'holepunch_cost' => floatval($d['holepunch_cost'] ?? 0.01));
            foreach (array(10,20,25,30,50,100,200,400,500,1000,2000) as $q) { $pd['qty_' . $q . '_unit'] = floatval($d['qty_' . $q . '_unit'] ?? 0); $pd['qty_' . $q . '_total'] = $pd['qty_' . $q . '_unit'] * $q; }
            $ex = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE product_slug = %s AND size = %s", $pd['product_slug'], $pd['size']));
            if ($ex && $replace) $wpdb->update($table, $pd, array('id' => $ex));
            elseif (!$ex) $wpdb->insert($table, $pd);
            $count++;
        }
        fclose($handle);
        wp_send_json_success(array('message' => "Imported: $count"));
    }
    
    public function export_csv() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Denied');
        global $wpdb;
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pws_pricing", ARRAY_A);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pws-pricing.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($data)) { fputcsv($out, array_keys($data[0])); foreach ($data as $r) fputcsv($out, $r); }
        fclose($out);
        exit;
    }
    
    // =========================================================================
    // WC SESSION + FRAGMENT (T7)
    // =========================================================================
    
    /**
     * Ensure WC session is started for AJAX add-to-cart (guest users).
     * Guards against WC versions that may not have has_session().
     */
    public function maybe_start_wc_session() {
        if (is_admin()) return;
        if (!defined('DOING_AJAX') || !DOING_AJAX) return;
        if (!function_exists('WC') || !WC()->session) return;
        if (method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }
    
    /**
     * Inject pws-cart-count span into WC's native fragment refresh response.
     *
     * WooCommerce calls this filter whenever it returns refreshed fragments
     * via wc-ajax=get_refreshed_fragments (or admin-ajax woocommerce_get_refreshed_fragments).
     * Our basket badge JS listens for the wc_fragments_refreshed event and reads
     * this span to update the badge count – no custom AJAX endpoint needed.
     */
    public function pws_cart_count_fragment($fragments) {
        if (!function_exists('WC') || !WC()->cart) return $fragments;
        $count = count(WC()->cart->get_cart());
        $fragments['span.pws-wc-cart-count'] = '<span class="pws-wc-cart-count" style="display:none;">' . intval($count) . '</span>';
        return $fragments;
    }
    
    // =========================================================================
    // CART PAGE ID SHIM (T9)
    // =========================================================================
    
    /**
     * Return the ID of the page that contains [pws_cart] as the WC cart page.
     *
     * Always returns the [pws_cart] page ID when one exists, overriding whatever
     * WooCommerce has saved in its settings. This is necessary because the WC
     * settings may still point to the old server URL after a migration, and we need
     * WC to treat /my-basket/ (which uses [pws_cart]) as the canonical cart page.
     * This ensures wc_get_cart_url(), is_cart(), and Worldpay origin checks are
     * all consistent.
     *
     * Caches the result in a static variable so the DB query fires only once per
     * request. Falls back to $cart_page_id if no [pws_cart] page is found.
     */
    public function get_pws_cart_page_id($cart_page_id) {
        static $pws_cart_page = null;
        
        if ($pws_cart_page !== null) {
            return $pws_cart_page > 0 ? $pws_cart_page : $cart_page_id;
        }
        
        // Direct DB query – more reliable than get_posts('s'=>…) which also
        // searches post titles and can miss exact shortcode matches.
        global $wpdb;
        $found_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type    = 'page'
               AND post_status  = 'publish'
               AND post_content LIKE '%[pws_cart]%'
             ORDER BY ID ASC
             LIMIT 1"
        );
        
        $pws_cart_page = $found_id ? intval($found_id) : 0;
        return $pws_cart_page > 0 ? $pws_cart_page : $cart_page_id;
    }
    
    /**
     * Make WooCommerce treat the [pws_cart] page as a cart page.
     *
     * When is_cart() returns true WC enqueues its cart scripts (including
     * wc-cart-fragments) which powers the native WC fragment refresh AJAX
     * endpoint (wc-ajax=get_refreshed_fragments). Our woocommerce_add_to_cart_fragments
     * filter then injects the badge count into every fragment response, keeping the
     * basket icon badge in sync without any custom AJAX endpoint.
     */
    public function pws_is_cart_page($is_cart) {
        if ($is_cart) return true;
        global $post;
        if ($post && has_shortcode($post->post_content, 'pws_cart')) {
            return true;
        }
        return false;
    }
    
    // =========================================================================
    // AJAX: UPDATE CART (T8)
    // =========================================================================
    
    public function ajax_update_cart() {
        check_ajax_referer('pws_nonce', 'nonce');
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available'));
            return;
        }
        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'message'    => 'Cart updated',
            'cart_count' => count(WC()->cart->get_cart()),
        ));
    }
    
    // =========================================================================
    // BASKET BADGE – wp_head CSS + wp_footer JS (T8)
    // Targets icon links (img/svg parent) only; skips plain text links
    // Works on ALL pages, not just PWS pages
    // =========================================================================
    
    public function output_basket_badge_styles() {
        ?>
<style id="pws-badge-css">
.pws-basket-badge{position:absolute;top:-6px;right:-8px;background:#FF7239;color:#fff;font-size:10px;font-weight:700;line-height:1;padding:2px 5px;border-radius:10px;min-width:16px;text-align:center;pointer-events:none;z-index:9999;}
.pws-badge-anchor{position:relative;display:inline-block;}
</style>
        <?php
    }
    
    public function output_basket_badge_init() {
        $cart_count = (function_exists('WC') && WC()->cart) ? count(WC()->cart->get_cart()) : 0;
        ?>
<span class="pws-wc-cart-count" style="display:none;"><?php echo intval($cart_count); ?></span>
<script id="pws-badge-js">
(function(){
    function pwsInjectBadge(count){
        // Remove existing badges
        document.querySelectorAll('.pws-basket-badge').forEach(function(b){b.remove();});
        document.querySelectorAll('.pws-badge-anchor').forEach(function(a){
            a.classList.remove('pws-badge-anchor');
            if(a.getAttribute('data-pws-badge-wrap')){
                var parent=a.parentNode;
                while(a.firstChild) parent.insertBefore(a.firstChild,a);
                parent.removeChild(a);
            }
        });
        if(!count||count<1) return;
        // Find all basket/my-basket href links
        var links=document.querySelectorAll('a[href*="my-basket"],a[href*="/basket"],a[href*="cart"]');
        links.forEach(function(link){
            // Only badge links that contain an img or svg (icon links), skip pure text links
            if(!link.querySelector('img,svg')) return;
            link.classList.add('pws-badge-anchor');
            var badge=document.createElement('span');
            badge.className='pws-basket-badge';
            badge.textContent=count;
            link.appendChild(badge);
        });
    }
    window.pwsInjectBadge=pwsInjectBadge;
    // Init on DOM ready
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded',function(){pwsInjectBadge(<?php echo intval($cart_count); ?>);});
    } else {
        pwsInjectBadge(<?php echo intval($cart_count); ?>);
    }
    // Sync when WC refreshes fragments (jQuery required)
    document.addEventListener('DOMContentLoaded',function(){
        if(typeof jQuery==='undefined') return;
        jQuery(document.body).on('wc_fragments_refreshed wc_fragment_refresh',function(){
            var span=document.querySelector('span.pws-wc-cart-count');
            if(span) pwsInjectBadge(parseInt(span.textContent)||0);
        });
    });
})();
</script>
        <?php
    }
    
    public function activate() {
        // Set default pricing formula (CRITICAL: must be 0.00072)
        if (!get_option('pws_custom_pricing_params')) {
            update_option('pws_custom_pricing_params', array(
                'multiplier' => 0.00072,
                'base_cost' => 13.5
            ));
        }
        
        // Set default product sizes with WC IDs
        if (!get_option('pws_product_sizes_config')) {
            update_option('pws_product_sizes_config', $this->get_default_product_sizes());
        }
        
        // Set default category mappings
        if (!get_option('pws_category_mappings')) {
            update_option('pws_category_mappings', $this->get_default_category_mappings_v2());
        }
        
        // Initialize A Sizes config if not exists
        if (!get_option('pws_a_sizes_config')) {
            update_option('pws_a_sizes_config', $this->get_default_a_sizes_config());
        }
    }
    
    /**
     * Reset all PWS data to defaults (called via AJAX)
     */
    public function ajax_reset_all_defaults() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        // Reset pricing formula
        update_option('pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        
        // Reset product sizes
        update_option('pws_product_sizes_config', $this->get_default_product_sizes());
        
        // Reset category mappings
        update_option('pws_category_mappings', $this->get_default_category_mappings_v2());
        
        wp_send_json_success(array(
            'message' => 'All settings reset to defaults! Pricing: 0.00072, Base: £13.50, 25 products configured.'
        ));
    }
    
    /**
     * Render A Sizes Configuration Admin Page
     * Allows adding, editing, deleting A sizes and configuring pricing formula
     */
    public function render_a_sizes_config_page() {
        $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
        $pricing_params = get_option( 'pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        
        // Check if multiplier seems wrong (should be around 0.00072)
        $multiplier_warning = '';
        if ($pricing_params['multiplier'] > 0.001) {
            $multiplier_warning = '<div style="background: #d63638; color: #fff; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                <strong>⚠️ WARNING:</strong> Your multiplier (' . esc_html($pricing_params['multiplier']) . ') seems too high! 
                The correct value should be <strong>0.00072</strong>. 
                This is causing prices to be much higher than expected. 
                <br><br>Please click "Reset to Defaults" below to fix this.
            </div>';
        }
        ?>
        <div class="wrap pws-admin">
            <h1>📐 A Sizes Configuration</h1>
            <p>Configure A Sizes (A3-A8+) and the pricing formula used for ALL products.</p>
            
            <!-- Pricing Formula Section -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>💰 Pricing Formula</h2>
                <?php echo $multiplier_warning; ?>
                <p class="description">The formula used to calculate prices for ALL products (Custom Sizes, A Sizes, and Regular Products):</p>
                <p style="background: #f0f0f1; padding: 15px; font-family: monospace; font-size: 14px; border-radius: 4px;">
                    <strong>Total</strong> = ROUND((((Width_mm × Height_mm) × <span style="color: #0073aa;">Multiplier</span>) × Quantity ÷ 100) + <span style="color: #d63638;">Base Cost</span>, 2)<br>
                    <strong>Unit Price</strong> = Total ÷ Quantity<br><br>
                    <strong>+ Add-ons:</strong> Thumbcut +£0.01/unit, Hole Punch +£0.01/unit
                </p>
                <p class="description" style="margin-top: 10px;">
                    <strong>Example:</strong> Driving License (88mm × 55mm), pack of 100<br>
                    Base = ROUND((((88 × 55) × 0.00072) × 100 ÷ 100) + 13.5, 2) = <strong>£16.98</strong>
                </p>
                
                <form id="pws-pricing-params-form" style="margin-top: 20px;">
                    <table class="form-table">
                        <tr>
                            <th><label for="pws-multiplier">Multiplier</label></th>
                            <td>
                                <input type="number" id="pws-multiplier" name="multiplier" 
                                       value="<?php echo esc_attr( $pricing_params['multiplier'] ); ?>" 
                                       step="0.00001" min="0" style="width: 120px;">
                                <p class="description">Default: <strong>0.00072</strong> — Do not change unless you know what you're doing!</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="pws-base-cost">Base Cost (£)</label></th>
                            <td>
                                <input type="number" id="pws-base-cost" name="base_cost" 
                                       value="<?php echo esc_attr( $pricing_params['base_cost'] ); ?>" 
                                       step="0.01" min="0" style="width: 120px;">
                                <p class="description">Fixed cost added to every order. Default: <strong>13.50</strong></p>
                            </td>
                        </tr>
                    </table>
                    <p style="display: flex; gap: 10px;">
                        <button type="submit" class="button button-primary">💾 Save Pricing Formula</button>
                        <button type="button" id="pws-reset-pricing-defaults" class="button" style="background: #d63638; color: #fff; border-color: #d63638;">🔄 Reset to Defaults (0.00072 / £13.50)</button>
                    </p>
                </form>
                <div id="pws-pricing-params-message" style="margin-top: 10px;"></div>
            </div>
            
            <!-- A Sizes Management Section -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>📏 Manage A Sizes</h2>
                <p class="description">Add, edit, or delete A sizes. Prices are calculated automatically using the formula above.</p>
                
                <!-- Add New Size Form -->
                <div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;">➕ Add New Size</h3>
                    <form id="pws-add-a-size-form">
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th style="width: 100px;"><label for="new-size-name">Size Name</label></th>
                                <td>
                                    <input type="text" id="new-size-name" name="size_name" placeholder="e.g., A9" style="width: 100px;" required>
                                </td>
                                <th style="width: 100px;"><label for="new-size-width">Width (mm)</label></th>
                                <td>
                                    <input type="number" id="new-size-width" name="width" placeholder="37" min="1" style="width: 80px;" required>
                                </td>
                                <th style="width: 100px;"><label for="new-size-height">Height (mm)</label></th>
                                <td>
                                    <input type="number" id="new-size-height" name="height" placeholder="52" min="1" style="width: 80px;" required>
                                </td>
                                <td>
                                    <button type="submit" class="button button-primary">➕ Add Size</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                    <div id="pws-add-size-message" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Existing Sizes Table -->
                <table class="widefat striped" id="pws-a-sizes-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Width (mm)</th>
                            <th>Height (mm)</th>
                            <th>Dimensions</th>
                            <th>Sample Price (Qty 100)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $a_sizes_config as $size_key => $size_data ) : 
                            $sample_result = $this->calculate_price_from_dimensions($size_data['width'], $size_data['height'], 100);
                            $sample_total = $sample_result['total'];
                            $sample_unit = $sample_result['unit'];
                        ?>
                        <tr data-size="<?php echo esc_attr( $size_key ); ?>">
                            <td><strong><?php echo esc_html( $size_key ); ?></strong></td>
                            <td>
                                <input type="number" class="pws-size-width" value="<?php echo esc_attr( $size_data['width'] ); ?>" style="width: 70px;" min="1">
                            </td>
                            <td>
                                <input type="number" class="pws-size-height" value="<?php echo esc_attr( $size_data['height'] ); ?>" style="width: 70px;" min="1">
                            </td>
                            <td class="pws-dimensions"><?php echo esc_html( $size_data['dimensions'] ); ?></td>
                            <td class="pws-sample-price">
                                £<?php echo number_format( $sample_total, 2 ); ?> 
                                <small>(£<?php echo number_format( $sample_unit, 2 ); ?>/unit)</small>
                            </td>
                            <td>
                                <button type="button" class="button pws-save-size" data-size="<?php echo esc_attr( $size_key ); ?>">💾 Save</button>
                                <button type="button" class="button pws-delete-size" data-size="<?php echo esc_attr( $size_key ); ?>" style="color: #dc3232;">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Info Section -->
            <div class="pws-admin-info" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>📖 How to Use</h2>
                
                <h3>A Sizes Shortcode</h3>
                <p><code>[pws_a_sizes]</code> - Displays A Sizes order form with dropdown</p>
                <p><code>[pws_a_sizes product_id="4453" title="A Size Plastic Wallets"]</code></p>
                
                <h3>Custom Sizes Shortcode</h3>
                <p><code>[pws_custom_sizes]</code> - Displays Custom Sizes form with width/height dropdowns</p>
                
                <h3>Pricing Note</h3>
                <p>Both shortcodes use the <strong>same pricing formula</strong> configured above. The only difference is:</p>
                <ul>
                    <li><strong>A Sizes:</strong> Customer selects from predefined sizes (A3, A4, etc.)</li>
                    <li><strong>Custom Sizes:</strong> Customer enters exact width and height in mm</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save A Sizes Configuration
     */
    public function ajax_save_a_sizes_config() {
        check_ajax_referer( 'pws_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        
        // Handle FORCE reset to defaults (bypass parsing issues)
        if ( isset( $_POST['force_reset_defaults'] ) && $_POST['force_reset_defaults'] === 'yes' ) {
            $pricing_params = array(
                'multiplier' => 0.00072,
                'base_cost' => 13.5
            );
            update_option( 'pws_custom_pricing_params', $pricing_params );
            wp_send_json_success( array( 'message' => 'Reset complete! Multiplier: 0.00072, Base: £13.50' ) );
            return;
        }
        
        // Handle pricing params save
        if ( isset( $_POST['multiplier'] ) || isset( $_POST['base_cost'] ) ) {
            $multiplier = isset( $_POST['multiplier'] ) ? floatval( $_POST['multiplier'] ) : 0.00072;
            $base_cost = isset( $_POST['base_cost'] ) ? floatval( $_POST['base_cost'] ) : 13.5;
            
            // Validate multiplier - must be a very small decimal like 0.00072
            // Common mistakes: 0.72 (1000x), 0.072 (100x), 0.0072 (10x)
            if ( $multiplier > 0.001 ) {
                wp_send_json_error( array( 'message' => 'Multiplier is too high (' . $multiplier . '). The correct value is 0.00072. Please enter exactly: 0.00072' ) );
                return;
            }
            if ( $multiplier > 0.0009 ) {
                // Value like 0.00072 would be < 0.0009, so this catches 0.001-0.009 range
                wp_send_json_error( array( 'message' => 'Multiplier (' . $multiplier . ') seems incorrect. The correct value is 0.00072. Please enter exactly: 0.00072' ) );
                return;
            }
            if ( $multiplier <= 0 || $multiplier < 0.0001 ) {
                $multiplier = 0.00072; // Force default if too low or zero
            }
            
            $pricing_params = array(
                'multiplier' => $multiplier,
                'base_cost' => $base_cost
            );
            update_option( 'pws_custom_pricing_params', $pricing_params );
            wp_send_json_success( array( 'message' => 'Pricing formula saved! Multiplier: ' . number_format($multiplier, 5) . ', Base: £' . $base_cost ) );
            return;
        }
        
        // Handle add new size
        if ( isset( $_POST['action_type'] ) && $_POST['action_type'] === 'add_size' ) {
            $size_name = sanitize_text_field( $_POST['size_name'] ?? '' );
            $width     = absint( $_POST['width']  ?? 0 );
            $height    = absint( $_POST['height'] ?? 0 );
            
            if ( empty( $size_name ) || $width <= 0 || $height <= 0 ) {
                wp_send_json_error( array( 'message' => 'Invalid size data' ) );
                return;
            }
            
            $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
            
            if ( isset( $a_sizes_config[ $size_name ] ) ) {
                wp_send_json_error( array( 'message' => 'Size already exists' ) );
                return;
            }
            
            $a_sizes_config[ $size_name ] = array(
                'dimensions' => $width . ' x ' . $height,
                'width' => $width,
                'height' => $height
            );
            
            update_option( 'pws_a_sizes_config', $a_sizes_config );
            wp_send_json_success( array( 'message' => 'Size added!', 'size' => $size_name ) );
            return;
        }
        
        // Handle update size
        if ( isset( $_POST['action_type'] ) && $_POST['action_type'] === 'update_size' ) {
            $size_name = sanitize_text_field( $_POST['size_name'] ?? '' );
            $width     = absint( $_POST['width']  ?? 0 );
            $height    = absint( $_POST['height'] ?? 0 );
            
            $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
            
            if ( ! isset( $a_sizes_config[ $size_name ] ) ) {
                wp_send_json_error( array( 'message' => 'Size not found' ) );
                return;
            }
            
            $a_sizes_config[ $size_name ] = array(
                'dimensions' => $width . ' x ' . $height,
                'width' => $width,
                'height' => $height
            );
            
            update_option( 'pws_a_sizes_config', $a_sizes_config );
            wp_send_json_success( array( 'message' => 'Size updated!' ) );
            return;
        }
        
        // Handle delete size
        if ( isset( $_POST['action_type'] ) && $_POST['action_type'] === 'delete_size' ) {
            $size_name = sanitize_text_field( $_POST['size_name'] ?? '' );
            
            $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
            
            if ( isset( $a_sizes_config[ $size_name ] ) ) {
                unset( $a_sizes_config[ $size_name ] );
                update_option( 'pws_a_sizes_config', $a_sizes_config );
                wp_send_json_success( array( 'message' => 'Size deleted!' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Size not found' ) );
            }
            return;
        }
        
        wp_send_json_error( array( 'message' => 'Invalid request' ) );
    }
    
    /**
     * AJAX: Calculate A Size Price
     * Uses the central calculate_price_from_dimensions with A size dimensions
     */
    public function ajax_calculate_a_size_price() {
        check_ajax_referer( 'pws_nonce', 'nonce' );
        
        $size      = isset( $_POST['size'] ) ? sanitize_text_field( $_POST['size'] ) : 'A4';
        $quantity  = max( 1, isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 100 );
        $thumbcut  = isset( $_POST['thumbcut'] ) ? sanitize_text_field( $_POST['thumbcut'] ) : 'no';
        $holepunch = isset( $_POST['holepunch'] ) ? sanitize_text_field( $_POST['holepunch'] ) : 'none';
        
        $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
        
        $width  = isset( $a_sizes_config[ $size ]['width'] ) ? intval( $a_sizes_config[ $size ]['width'] ) : 210;
        $height = isset( $a_sizes_config[ $size ]['height'] ) ? intval( $a_sizes_config[ $size ]['height'] ) : 297;
        
        $result = $this->calculate_price_from_dimensions( $width, $height, $quantity, $thumbcut, $holepunch );
        $final_total = $result['total'];
        $final_unit  = $result['unit'];
        
        // Bulk suggestions
        $bulk_suggestions = array();
        foreach ( array( 200, 400, 500, 1000 ) as $bq ) {
            if ( $bq > $quantity ) {
                $bq_result = $this->calculate_price_from_dimensions( $width, $height, $bq, $thumbcut, $holepunch );
                $bulk_suggestions[] = array(
                    'qty'   => $bq,
                    'total' => number_format( $bq_result['total'], 2 ),
                    'unit'  => number_format( $bq_result['unit'], 2 ),
                );
                if ( count( $bulk_suggestions ) >= 2 ) break;
            }
        }
        
        wp_send_json_success( array(
            'total'            => number_format( $final_total, 2 ),
            'unit_price'       => number_format( $final_unit, 2 ),
            'raw_total'        => $final_total,
            'raw_unit'         => $final_unit,
            'bulk_suggestions' => $bulk_suggestions,
        ) );
    }
    
    /**
     * AJAX: Add A Size to Cart
     */
    public function ajax_add_a_size_to_cart() {
        check_ajax_referer( 'pws_nonce', 'nonce' );
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available')); return;
        }
        
        $original_product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 4453;
        $product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( $_POST['product_name'] ) : 'A Size Wallets';
        $size         = isset( $_POST['size'] ) ? sanitize_text_field( $_POST['size'] ) : 'A4';
        $quantity     = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 100;
        $thumbcut     = isset( $_POST['thumbcut'] ) ? sanitize_text_field( $_POST['thumbcut'] ) : 'no';
        $holepunch    = isset( $_POST['holepunch'] ) ? sanitize_text_field( $_POST['holepunch'] ) : 'none';
        $openside     = isset( $_POST['openside'] ) ? sanitize_text_field( $_POST['openside'] ) : 'both';
        $total_price  = isset( $_POST['total_price'] ) ? floatval( $_POST['total_price'] ) : 0;
        $unit_price   = isset( $_POST['unit_price'] ) ? floatval( $_POST['unit_price'] ) : 0;
        
        // Get A size dimensions for display
        $a_sizes_config = get_option( 'pws_a_sizes_config', $this->get_default_a_sizes_config() );
        $dimensions = isset( $a_sizes_config[ $size ]['dimensions'] ) ? $a_sizes_config[ $size ]['dimensions'] : '';
        $size_display = $size . ( $dimensions ? ' (' . $dimensions . ')' : '' );
        
        // Store dimensions for cart qty recalculation
        $a_width  = isset( $a_sizes_config[ $size ]['width'] )  ? intval( $a_sizes_config[ $size ]['width'] )  : 0;
        $a_height = isset( $a_sizes_config[ $size ]['height'] ) ? intval( $a_sizes_config[ $size ]['height'] ) : 0;
        
        $cart_item_data = array(
            'pws_custom'               => true,
            'pws_original_product_id'  => $original_product_id,
            'pws_product_name'         => $product_name,
            'pws_size'                 => $size_display,
            'pws_size_type'            => 'A Size',
            'pws_width'                => $a_width,
            'pws_height'               => $a_height,
            'pws_thumbcut'             => $thumbcut,
            'pws_holepunch'            => $holepunch,
            'pws_openside'             => $openside,
            'pws_unit_price'           => $unit_price,
            'pws_total_price'          => $total_price,
            'pws_unique_key'           => md5( microtime() . wp_rand() ),
        );
        
        $added = WC()->cart->add_to_cart(
            $this->get_cart_product_id(),
            $quantity,
            0,
            array(),
            $cart_item_data
        );
        
        if ( $added ) {
            wp_send_json_success( array(
                'message'    => 'Added to basket',
                'cart_count' => count(WC()->cart->get_cart()),
                'cart_url'   => wc_get_cart_url(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to add to cart' ) );
        }
    }
    
    /**
     * =========================================================================
     * PRODUCT SIZES ADMIN PAGE (NEW in v10)
     * Allows admin to add products with mm dimensions for dynamic pricing
     * =========================================================================
     */
    
    /**
     * Get product sizes configuration
     */
    public function get_product_sizes_config() {
        $config = get_option('pws_product_sizes_config', array());
        $defaults = $this->get_default_product_sizes();
        
        // If empty, populate with defaults
        if (empty($config)) {
            update_option('pws_product_sizes_config', $defaults);
            return $defaults;
        }
        
        // CRITICAL VALIDATION: Check if driving-license has correct data
        // This is our "canary" - if it's wrong, the whole config is suspect
        $needs_reset = false;
        
        if (!isset($config['driving-license'])) {
            $needs_reset = true;
        } else {
            $dl = $config['driving-license'];
            // Check dimensions AND wc_product_id
            if (!isset($dl['width']) || !isset($dl['height']) || !isset($dl['wc_product_id']) ||
                intval($dl['width']) !== 88 || intval($dl['height']) !== 55 || intval($dl['wc_product_id']) !== 3917) {
                $needs_reset = true;
            }
        }
        
        if ($needs_reset) {
            // Reset to defaults with all the correct WC IDs
            update_option('pws_product_sizes_config', $defaults);
            return $defaults;
        }
        
        return $config;
    }
    
    /**
     * Get default product sizes (pre-populated from client's spreadsheet)
     */
    public function get_default_product_sizes() {
        return array(
            // ORGANISE (8 products)
            'driving-license' => array('name' => 'Driving License', 'width' => 88, 'height' => 55, 'wc_product_id' => 3917, 'category' => 'organise', 'order' => 1),
            'passport' => array('name' => 'Passport', 'width' => 95, 'height' => 130, 'wc_product_id' => 3918, 'category' => 'organise', 'order' => 2),
            'boarding-passes' => array('name' => 'Boarding Passes', 'width' => 100, 'height' => 210, 'wc_product_id' => 3919, 'category' => 'organise', 'order' => 3),
            'currency' => array('name' => 'Currency', 'width' => 146, 'height' => 77, 'wc_product_id' => 3920, 'category' => 'organise', 'order' => 4),
            'crafting-patterns' => array('name' => 'Crafting Patterns', 'width' => 307, 'height' => 425, 'wc_product_id' => 3923, 'category' => 'organise', 'order' => 5),
            'recipes' => array('name' => 'Recipes', 'width' => 152, 'height' => 215, 'wc_product_id' => 3925, 'category' => 'organise', 'order' => 6),
            'utility-bills' => array('name' => 'Utility Bills', 'width' => 220, 'height' => 307, 'wc_product_id' => 5019, 'category' => 'organise', 'order' => 7),
            'appliance-manuals' => array('name' => 'Appliance Manuals', 'width' => 220, 'height' => 307, 'wc_product_id' => 5020, 'category' => 'organise', 'order' => 8),
            
            // KEEP SAFE (8 products)
            'legal-documents' => array('name' => 'Legal Documents', 'width' => 220, 'height' => 307, 'wc_product_id' => 3921, 'category' => 'keep-safe', 'order' => 1),
            'birth-certificates' => array('name' => 'Birth Certificates', 'width' => 220, 'height' => 307, 'wc_product_id' => 3922, 'category' => 'keep-safe', 'order' => 2),
            'album' => array('name' => 'Album', 'width' => 320, 'height' => 314, 'wc_product_id' => 3933, 'category' => 'keep-safe', 'order' => 3),
            'single' => array('name' => 'Single', 'width' => 185, 'height' => 181, 'wc_product_id' => 3935, 'category' => 'keep-safe', 'order' => 4),
            'kids-art' => array('name' => 'Kids Art', 'width' => 220, 'height' => 307, 'wc_product_id' => 3937, 'category' => 'keep-safe', 'order' => 5),
            'invitations' => array('name' => 'Invitations', 'width' => 152, 'height' => 215, 'wc_product_id' => 3940, 'category' => 'keep-safe', 'order' => 6),
            'coins' => array('name' => 'Coins', 'width' => 50, 'height' => 50, 'wc_product_id' => 3941, 'category' => 'keep-safe', 'order' => 7),
            'certificates' => array('name' => 'Certificates', 'width' => 220, 'height' => 307, 'wc_product_id' => 5120, 'category' => 'keep-safe', 'order' => 8),
            
            // FOR BUSINESS (9 products)
            'presentations' => array('name' => 'Presentations', 'width' => 220, 'height' => 307, 'wc_product_id' => 3927, 'category' => 'for-business', 'order' => 1),
            'handout-cover' => array('name' => 'Handout Cover', 'width' => 220, 'height' => 307, 'wc_product_id' => 3929, 'category' => 'for-business', 'order' => 2),
            'welcome-packs' => array('name' => 'Welcome Packs', 'width' => 220, 'height' => 307, 'wc_product_id' => 3931, 'category' => 'for-business', 'order' => 3),
            'id-badge-holders' => array('name' => 'ID Badge Holders', 'width' => 86, 'height' => 54, 'wc_product_id' => 3932, 'category' => 'for-business', 'order' => 4),
            'banking' => array('name' => 'Banking', 'width' => 220, 'height' => 307, 'wc_product_id' => 5024, 'category' => 'for-business', 'order' => 5),
            'invoices' => array('name' => 'Invoices', 'width' => 220, 'height' => 307, 'wc_product_id' => 5025, 'category' => 'for-business', 'order' => 6),
            'receipts' => array('name' => 'Receipts', 'width' => 80, 'height' => 150, 'wc_product_id' => 5026, 'category' => 'for-business', 'order' => 7),
            'contracts' => array('name' => 'Contracts', 'width' => 220, 'height' => 307, 'wc_product_id' => 5027, 'category' => 'for-business', 'order' => 8),
            'samples' => array('name' => 'Samples', 'width' => 150, 'height' => 150, 'wc_product_id' => 5028, 'category' => 'for-business', 'order' => 9),
        );
    }
    
    /**
     * Calculate price for a product based on mm dimensions
     */
    public function calculate_price_from_dimensions($width, $height, $quantity, $thumbcut = 'no', $holepunch = 'none') {
        $width = max(1, intval($width));
        $height = max(1, intval($height));
        $quantity = max(1, intval($quantity));
        
        $pricing_params = get_option('pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        
        $multiplier = floatval($pricing_params['multiplier']);
        $base_cost = floatval($pricing_params['base_cost']);
        
        if ($multiplier > 0.001 || $multiplier <= 0) {
            $multiplier = 0.00072;
        }
        
        $base_price = round(((($width * $height) * $multiplier) * $quantity / 100) + $base_cost, 2);
        
        $thumbcut_addon = ($thumbcut === 'yes') ? 0.01 : 0;
        $holepunch_addon = ($holepunch !== 'none' && $holepunch !== '') ? 0.01 : 0;
        
        $addon_total = ($thumbcut_addon + $holepunch_addon) * $quantity;
        $final_total = round($base_price + $addon_total, 2);
        $final_unit = round($final_total / $quantity, 4);
        
        return array(
            'total' => $final_total,
            'unit' => $final_unit
        );
    }
    
    /**
     * Render Product Sizes Admin Page
     */
    public function render_product_sizes_page() {
        $product_sizes = $this->get_product_sizes_config();
        
        // If empty, use defaults
        if (empty($product_sizes)) {
            $product_sizes = $this->get_default_product_sizes();
            update_option('pws_product_sizes_config', $product_sizes);
        }
        
        // Get WooCommerce products for linking
        $wc_products = wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        
        // Get pricing params for sample calculation
        $pricing_params = get_option('pws_custom_pricing_params', array(
            'multiplier' => 0.00072,
            'base_cost' => 13.5
        ));
        ?>
        <div class="wrap pws-admin">
            <h1>📦 Product Sizes Configuration</h1>
            <p>Add products and set their mm dimensions. Prices are calculated automatically using the formula.</p>
            
            <!-- Pricing Formula Reference -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>💰 Current Pricing Formula</h2>
                <p style="background: #f0f0f1; padding: 15px; font-family: monospace; font-size: 14px; border-radius: 4px;">
                    <strong>Total</strong> = ROUND((((Width_mm × Height_mm) × <?php echo $pricing_params['multiplier']; ?>) × Quantity ÷ 100) + <?php echo $pricing_params['base_cost']; ?>, 2)<br>
                    <small>+ Thumbcut: £0.01/unit | + Hole Punch: £0.01/unit</small>
                </p>
                <p><a href="<?php echo admin_url('admin.php?page=pws-a-sizes'); ?>">Edit formula parameters →</a></p>
            </div>
            
            <!-- Add New Product -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>➕ Add New Product</h2>
                <form id="pws-add-product-size-form" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Product Name *</label>
                        <input type="text" name="product_name" id="pws-new-product-name" style="width: 200px;" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Width (mm) *</label>
                        <input type="number" name="width" id="pws-new-width" style="width: 100px;" min="1" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Height (mm) *</label>
                        <input type="number" name="height" id="pws-new-height" style="width: 100px;" min="1" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Link to WC Product</label>
                        <select name="wc_product_id" id="pws-new-wc-product" style="width: 200px;">
                            <option value="0">-- None --</option>
                            <?php foreach ($wc_products as $p) : ?>
                            <option value="<?php echo $p->get_id(); ?>"><?php echo esc_html($p->get_name()); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="button button-primary">➕ Add Product</button>
                    </div>
                </form>
                <div id="pws-add-product-message" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Product Sizes Table -->
            <div class="pws-admin-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>📋 All Products (<?php echo count($product_sizes); ?>)</h2>
                <form id="pws-product-sizes-form">
                    <table class="widefat striped" id="pws-product-sizes-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">⋮⋮</th>
                                <th>Product Name</th>
                                <th style="width: 100px;">Width (mm)</th>
                                <th style="width: 100px;">Height (mm)</th>
                                <th style="width: 200px;">WC Product</th>
                                <th style="width: 120px;">Sample Price (Qty 100)</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pws-product-sizes-tbody">
                            <?php foreach ($product_sizes as $slug => $data) : 
                                $sample_price = $this->calculate_price_from_dimensions($data['width'], $data['height'], 100);
                            ?>
                            <tr data-slug="<?php echo esc_attr($slug); ?>">
                                <td class="pws-drag-handle" style="cursor: move; text-align: center;">⋮⋮</td>
                                <td>
                                    <input type="text" name="products[<?php echo esc_attr($slug); ?>][name]" 
                                           value="<?php echo esc_attr($data['name']); ?>" style="width: 100%;">
                                </td>
                                <td>
                                    <input type="number" name="products[<?php echo esc_attr($slug); ?>][width]" 
                                           value="<?php echo esc_attr($data['width']); ?>" style="width: 80px;" min="1" class="pws-dimension-input">
                                </td>
                                <td>
                                    <input type="number" name="products[<?php echo esc_attr($slug); ?>][height]" 
                                           value="<?php echo esc_attr($data['height']); ?>" style="width: 80px;" min="1" class="pws-dimension-input">
                                </td>
                                <td>
                                    <select name="products[<?php echo esc_attr($slug); ?>][wc_product_id]" style="width: 100%;">
                                        <option value="0">-- None --</option>
                                        <?php foreach ($wc_products as $p) : ?>
                                        <option value="<?php echo $p->get_id(); ?>" <?php selected($data['wc_product_id'] ?? 0, $p->get_id()); ?>><?php echo esc_html($p->get_name()); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="pws-sample-price" style="font-weight: bold; color: #0073aa;">
                                    £<?php echo number_format($sample_price['total'], 2); ?>
                                    <small style="display: block; color: #666;">(£<?php echo number_format($sample_price['unit'], 2); ?>/unit)</small>
                                </td>
                                <td>
                                    <button type="button" class="button pws-delete-product-size" data-slug="<?php echo esc_attr($slug); ?>">🗑️ Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 20px;">
                        <button type="submit" class="button button-primary button-large">💾 Save All Products</button>
                        <span id="pws-save-products-message" style="margin-left: 15px;"></span>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save all product sizes
     */
    public function ajax_save_product_sizes() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $products = isset($_POST['products']) ? $_POST['products'] : array();
        $cleaned_products = array();
        
        foreach ($products as $slug => $data) {
            $slug = sanitize_title($slug);
            if (empty($slug)) continue;
            
            $cleaned_products[$slug] = array(
                'name' => sanitize_text_field($data['name']),
                'width' => absint($data['width']),
                'height' => absint($data['height']),
                'wc_product_id' => absint($data['wc_product_id'] ?? 0),
            );
        }
        
        update_option('pws_product_sizes_config', $cleaned_products);
        wp_send_json_success(array('message' => 'All products saved successfully!', 'count' => count($cleaned_products)));
    }
    
    /**
     * AJAX: Add new product size
     */
    public function ajax_add_product_size() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $name   = sanitize_text_field($_POST['product_name'] ?? '');
        $width  = absint($_POST['width']  ?? 0);
        $height = absint($_POST['height'] ?? 0);
        $wc_product_id = absint($_POST['wc_product_id'] ?? 0);
        
        if (empty($name) || $width <= 0 || $height <= 0) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
            return;
        }
        
        $slug = sanitize_title($name);
        $product_sizes = $this->get_product_sizes_config();
        
        if (isset($product_sizes[$slug])) {
            wp_send_json_error(array('message' => 'A product with this name already exists'));
            return;
        }
        
        $product_sizes[$slug] = array(
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'wc_product_id' => $wc_product_id,
        );
        
        update_option('pws_product_sizes_config', $product_sizes);
        
        // Calculate sample price
        $sample_price = $this->calculate_price_from_dimensions($width, $height, 100);
        
        wp_send_json_success(array(
            'message' => 'Product added successfully!',
            'slug' => $slug,
            'sample_price' => $sample_price
        ));
    }
    
    /**
     * AJAX: Delete product size
     */
    public function ajax_delete_product_size() {
        check_ajax_referer('pws_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $slug = sanitize_title($_POST['slug'] ?? '');
        if (empty($slug)) {
            wp_send_json_error(array('message' => 'Invalid product'));
            return;
        }
        
        $product_sizes = $this->get_product_sizes_config();
        
        if (isset($product_sizes[$slug])) {
            unset($product_sizes[$slug]);
            update_option('pws_product_sizes_config', $product_sizes);
            wp_send_json_success(array('message' => 'Product deleted successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Product not found'));
        }
    }
}

// Initialize plugin when WooCommerce is loaded
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        PWS_Pricing_System::get_instance();
    }
} );
