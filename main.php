<?php

/**
 * Plugin Name: Isee products extractor 
 * Description: Extract products plugin for isee
 * Version: 2.1.3
 * Author: sisoog
 * Author URI: https://sisoog.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo_products_extractor
 * Domain Path: /l10n
 */

defined('ABSPATH') or die('No script kiddies please!');
/** Define globals and requirements */
define('WPEI_IS_DEV', 0);
define('WPEI_PLUGIN_VERSION', boolval(WPEI_IS_DEV) ? time() : '2.1.3');
define('WPEI_ROOTDIR', plugin_dir_path(__FILE__));
define('WPEI_ADMIN', WPEI_ROOTDIR . 'admin/');
define('WPEI_INC', WPEI_ROOTDIR . 'includes/');
define('WPEI_ADMIN_STATIC', plugin_dir_url(__FILE__) . 'admin/static/');

add_action('plugins_loaded', function () {
    load_plugin_textdomain('woo_products_extractor', false, basename(WPEI_ROOTDIR) . '/l10n/');
    // set default value to site_status_in_isee option
    if (!get_option('site_status_in_isee')) {
        update_option('site_status_in_isee', serialize([
            'has_checked' => 0,
            'site_id' => ''
        ]));
    }
});

/** register activation hooks */
include(WPEI_ROOTDIR . 'register_functions.php');
include(WPEI_INC . 'helpers.php');
register_activation_hook(__FILE__, 'wpei_activate_function');
register_deactivation_hook(__FILE__, 'wpei_deactivate_function');
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
if (is_admin()) {
    require_once(WPEI_ADMIN . 'admin_process.php');
    require_once(WPEI_ADMIN . 'admin_ajax.php');
    require_once(WPEI_ADMIN . 'dashboard_widgets.php');
    // add settings link to plugin-title 
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links) {
        $url = get_admin_url() . "admin.php?page=wpei-activation";
        $settingsLink = '<a href="' . $url . '">' . __('Settings', 'woo_products_extractor') . '</a>';
        $links[] = $settingsLink;
        return $links;
    });
}


// Setting a custom timeout value for cURL. Using a high value for priority to ensure the function runs after any other added to the same action hook.
add_action('http_api_curl', 'wpei_curl_timeout', 9999, 1);
function wpei_curl_timeout($handle)
{
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
}

// Setting custom timeout for the HTTP request
add_filter('http_request_timeout', 'wpei_http_request_timeout', 9999);
function wpei_http_request_timeout($timeout_value)
{
    return 30;
}

// Setting custom timeout in HTTP request args
add_filter('http_request_args', 'wpei_http_request_args', 9999, 1);
function wpei_http_request_args($r)
{
    $r['timeout'] = 30;
    return $r;
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    class WPEI_Products_Extractor extends WP_REST_Controller
    {
        private $wpei_version;
        private $plugin_slug = "woo-products-extractor-sisoog/main.php";
        private $text_domain_slug = "woo_products_extractor";
        private $config;

        public function __get($var)
        {
            return $this->config;
        }

        public function __set($var, $value)
        {
            $this->config = $value;
        }

        public function __construct()
        {
            add_action('rest_api_init', array($this, 'wpei_register_routes'));
            if (is_admin()) {
                $this->wpei_version = (get_plugin_data(__FILE__, false))['Version'];
            }
        }

        /**
         * Check for new updates
         */
        private function wpei_auto_update()
        {
            $result = FALSE;
            try {
                ob_start(function () {
                    return '';
                });
                include_once ABSPATH . '/wp-admin/includes/file.php';
                include_once ABSPATH . '/wp-admin/includes/misc.php';
                include_once ABSPATH . '/wp-includes/pluggable.php';
                include_once ABSPATH . '/wp-admin/includes/plugin.php';
                include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
                if (is_plugin_active($this->plugin_slug)) {
                    $upgrader = new Plugin_Upgrader();
                    $result = $upgrader->upgrade($this->plugin_slug);
                    activate_plugin($this->plugin_slug);
                }
                @ob_end_clean();
            } catch (Exception $e) {
                activate_plugin($this->plugin_slug);
            }
            return $result;
        }

        /**
         * find matching product and variation
         */
        private function wpei_find_matching_variation($product, $attributes)
        {
            foreach ($attributes as $key => $value) {
                if (strpos($key, 'attribute_') === 0) {
                    continue;
                }
                unset($attributes[$key]);
                $attributes[sprintf('attribute_%s', $key)] = $value;
            }
            if (class_exists('WC_Data_Store')) {
                $data_store = WC_Data_Store::load('product');
                return $data_store->find_matching_product_variation($product, $attributes);
            } else {
                return $product->get_matching_variation($attributes);
            }
        }

        /**
         * Register rout: https://domain.com/icwpe/v1/wooproducts
         */
        public function wpei_register_routes()
        {
            $version   = '1';
            $namespace = sprintf('icwpe/v%s', $version);
            $base = 'wooproducts';
            register_rest_route($namespace, '/' . $base, array(
                array(
                    'methods' => 'POST',
                    'callback' => array(
                        $this,
                        'wpei_get_products'
                    ),
                    'permission_callback' => '__return_true',
                    'args' => array()
                )
            ));
        }

        /**
         * Check update and validate the request
         * @param request
         * @return wp_safe_remote_post
         */
        public function wpei_check_request($request)
        {
            // Check and update plugin for first request
            if (!empty($request->get_param('auto_update'))) {
                $update_switch = rest_sanitize_boolean($request->get_param('auto_update'));
            } else {
                $update_switch = TRUE;
            }
            if ($update_switch) {
                if ($this->wpei_auto_update()) {
                    exit();
                }
            }

            // Verify token
            $endpointUrl = $this->config['VERIFY_TOKEN_URL'];
            $expires = sanitize_text_field($request->get_param('expires'));
            $shopDomain = getShopDomain();
            $header = (!empty($request->get_header('X-Authorization')))
                ? $request->get_header('X-Authorization')
                : $request->get_header('Authorization');
            $response = wp_remote_post($endpointUrl, array(
                'headers' => [
                    'content-type' => 'application/json',
                    'Authorization' => $header
                ],
                'body' => json_encode([
                    'shop_domain' => $shopDomain
                ]),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                if (WPEI_IS_DEV) {
                    error_log(sprintf("[Time:%s, Res: %s]", time(), json_encode($response, JSON_PRETTY_PRINT)));
                }
                return false;
            }

            return array(
                'body' => wp_remote_retrieve_body($response)
            );
        }

        /**
         * Get single product values
         */
        public function wpei_get_product_values($product, $is_child = FALSE)
        {
            $temp_product = new \stdClass();
            $parent = NULL;
            if ($is_child) {
                $parent = wc_get_product($product->get_parent_id());
                $temp_product->title = $parent->get_name();
                $temp_product->subtitle = get_post_meta($product->get_parent_id(), 'product_english_name', true);
                $cat_ids = $parent->get_category_ids();
                $temp_product->parent_id = $parent->get_id();
            } else {
                $temp_product->title = $product->get_name();
                $temp_product->subtitle = get_post_meta($product->get_id(), 'product_english_name', true);
                $cat_ids = $product->get_category_ids();
                $temp_product->parent_id = 0;
            }
            $temp_product->page_unique = $product->get_id();
            $temp_product->current_price = $product->get_price();
            $temp_product->old_price = $product->get_regular_price();
            $temp_product->availability = $product->get_stock_status();
            $temp_product->category_name = get_term_by('id', end($cat_ids), 'product_cat', 'ARRAY_A')['name'];
            $temp_product->image_links = [];
            $attachment_ids = $product->get_gallery_image_ids();
            foreach ($attachment_ids as $attachment_id) {
                $t_link = wp_get_attachment_image_src($attachment_id, 'full');
                if ($t_link) {
                    array_push($temp_product->image_links, $t_link[0]);
                }
            }
            $t_image = wp_get_attachment_image_src($product->get_image_id(), 'full');
            if ($t_image) {
                $temp_product->image_link = $t_image[0];
                if (!in_array($t_image[0], $temp_product->image_links)) {
                    array_push($temp_product->image_links, $t_image[0]);
                }
            } else {
                $temp_product->image_link = null;
            }
            $temp_product->page_url = get_permalink($product->get_id());
            $temp_product->short_desc = $product->get_short_description();
            $temp_product->spec = array();
            $temp_product->date = $product->get_date_created();
            $temp_product->registry = '';
            $temp_product->guarantee = '';

            if (!$is_child) {
                if ($product->is_type('variable')) {
                    // Set prices to 0 then calcualte them
                    $temp_product->current_price = 0;
                    $temp_product->old_price = 0;

                    // Find price for default attributes. If can't find return max price of variations
                    $variation_id = $this->wpei_find_matching_variation($product, $product->get_default_attributes());
                    if ($variation_id != 0) {
                        $variation = wc_get_product($variation_id);
                        $temp_product->current_price = $variation->get_price();
                        $temp_product->old_price = $variation->get_regular_price();
                        $temp_product->availability = $variation->get_stock_status();
                    } else {
                        $temp_product->current_price = $product->get_variation_price('max');
                        $temp_product->old_price = $product->get_variation_regular_price('max');
                    }

                    // Extract default attributes
                    foreach ($product->get_default_attributes() as $key => $value) {
                        if (!empty($value)) {
                            if (substr($key, 0, 3) === 'pa_') {
                                $value = get_term_by('slug', $value, $key);
                                if ($value) {
                                    $value = $value->name;
                                } else {
                                    $value = '';
                                }
                                $key = wc_attribute_label($key);
                                $temp_product->spec[urldecode($key)] = rawurldecode($value);
                            } else {
                                $temp_product->spec[urldecode($key)] = rawurldecode($value);
                            }
                        }
                    }
                }
                // add remain attributes
                foreach ($product->get_attributes() as $attribute) {
                    if ($attribute['visible'] == 1) {
                        $name = wc_attribute_label($attribute['name']);
                        if (substr($attribute['name'], 0, 3) === 'pa_') {
                            $values = wc_get_product_terms($product->get_id(), $attribute['name'], array('fields' => 'names'));
                        } else {
                            $values = $attribute['options'];
                        }
                        if (!array_key_exists($name, $temp_product->spec)) {
                            $temp_product->spec[$name] = implode(', ', $values);
                        }
                    }
                }
            } else {
                foreach ($product->get_attributes() as $key => $value) {
                    if (!empty($value)) {
                        if (substr($key, 0, 3) === 'pa_') {
                            $value = get_term_by('slug', $value, $key);
                            if ($value) {
                                $value = $value->name;
                            } else {
                                $value = '';
                            }
                            $key = wc_attribute_label($key);
                            $temp_product->spec[urldecode($key)] = rawurldecode($value);
                        } else {
                            $temp_product->spec[urldecode($key)] = rawurldecode($value);
                        }
                    }
                }
            }

            // Set registry and guarantee
            if (!empty($temp_product->spec['رجیستری'])) {
                $temp_product->registry = $temp_product->spec['رجیستری'];
            } elseif (!empty($temp_product->spec['registry'])) {
                $temp_product->registry = $temp_product->spec['registry'];
            } elseif (!empty($temp_product->spec['ریجیستری'])) {
                $temp_product->registry = $temp_product->spec['ریجیستری'];
            } elseif (!empty($temp_product->spec['ریجستری'])) {
                $temp_product->registry = $temp_product->spec['ریجستری'];
            }

            $guarantee_keys = [
                "گارانتی",
                "guarantee",
                "warranty",
                "garanty",
                "گارانتی:",
                "گارانتی محصول",
                "گارانتی محصول:",
                "ضمانت",
                "ضمانت:"
            ];

            foreach ($guarantee_keys as $guarantee) {
                if (!empty($temp_product->spec[$guarantee])) {
                    $temp_product->guarantee = $temp_product->spec[$guarantee];
                }
            }

            if (!array_key_exists('شناسه کالا', $temp_product->spec)) {
                $sku = $product->get_sku();
                if ($sku != "") {
                    $temp_product->spec['شناسه کالا'] = $sku;
                }
            }

            if (count($temp_product->spec) > 0) {
                $temp_product->spec = [$temp_product->spec];
            }

            return $temp_product;
        }

        /**
         * Get all products
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        private function wpei_get_all_products($show_variations, $limit, $page)
        {
            $parent_ids = array();
            if ($show_variations) {
                // Get all posts have children
                $query = new WP_Query(array(
                    'post_type' => array('product_variation'),
                    'post_status' => 'publish'
                ));
                $products = $query->get_posts();
                $parent_ids = array_column($products, 'post_parent');

                // Make query
                $query = new WP_Query(array(
                    'posts_per_page' => $limit,
                    'paged'  => $page,
                    'post_status' => 'publish',
                    'orderby' => 'ID',
                    'order' => 'DESC',
                    'post_type' => array('product', 'product_variation'),
                    'post__not_in' => $parent_ids
                ));
                $products = $query->get_posts();
            } else {
                // Make query
                $query = new WP_Query(array(
                    'posts_per_page' => $limit,
                    'paged'  => $page,
                    'post_status' => 'publish',
                    'orderby' => 'ID',
                    'order' => 'DESC',
                    'post_type' => array('product')
                ));
                $products = $query->get_posts();
            }

            // Count products
            $data['count'] = $query->found_posts;

            // Total pages
            $data['max_pages'] = $query->max_num_pages;

            $data['products'] = array();

            // Retrieve and send data in json
            foreach ($products as $product) {
                $product = wc_get_product($product->ID);
                $parent_id = $product->get_parent_id();
                // Process for parent product
                if ($parent_id == 0) {
                    // Exclude the variable product. (variations of it will be inserted.)
                    if ($show_variations) {
                        if (!$product->is_type('variable')) {
                            $temp_product = $this->wpei_get_product_values($product);
                            $data['products'][] = $this->prepare_response_for_collection($temp_product);
                        }
                    } else {
                        $temp_product = $this->wpei_get_product_values($product);
                        $data['products'][] = $this->prepare_response_for_collection($temp_product);
                    }
                } else {
                    // Process for visible child
                    if ($product->get_price()) {
                        $temp_product = $this->wpei_get_product_values($product, TRUE);
                        $data['products'][] = $this->prepare_response_for_collection($temp_product);
                    }
                }
            }
            return $data;
        }

        /**
         * Get a product or list of products
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        private function wpei_get_list_products($product_list)
        {
            $data['products'] = array();

            // Retrieve and send data in json
            foreach ($product_list as $pid) {
                $product = wc_get_product($pid);
                if ($product && $product->get_status() === "publish") {
                    $parent_id = $product->get_parent_id();
                    // Process for parent product
                    if ($parent_id == 0) {
                        $temp_product = $this->wpei_get_product_values($product);
                        $data['products'][] = $this->prepare_response_for_collection($temp_product);
                    } else {
                        // Process for visible child
                        if ($product->get_price()) {
                            $temp_product = $this->wpei_get_product_values($product, TRUE);
                            $data['products'][] = $this->prepare_response_for_collection($temp_product);
                        }
                    }
                }
            }
            return $data;
        }

        /**
         * Get a slugs or list of slugs. For getting product's data by its link
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        private function wpei_get_list_slugs($slug_list)
        {
            $data['products'] = array();

            // Retrive and send data in json
            foreach ($slug_list as $sid) {
                $product = get_page_by_path($sid, OBJECT, 'product');
                if ($product && $product->post_status === "publish") {
                    $temp_product = $this->wpei_get_product_values(wc_get_product($product->ID));
                    $data['products'][] = $this->prepare_response_for_collection($temp_product);
                }
            }
            return $data;
        }

        /**
         * Get all or a collection of products
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function wpei_get_products($request)
        {
            // Get Parameters
            $show_variations = rest_sanitize_boolean($request->get_param('variation'));
            $limit = intval($request->get_param('limit'));
            $page = intval($request->get_param('page'));
            if (!empty($request->get_param('products'))) {
                $product_list = explode(',', (sanitize_text_field($request->get_param('products'))));
                if (is_array($product_list)) {
                    foreach ($product_list as $key => $field) {
                        $product_list[$key] = intval($field);
                    }
                }
            }
            if (!empty($request->get_param('slugs'))) {
                $slug_list = explode(',', (sanitize_text_field(urldecode($request->get_param('slugs')))));
            }

            // Check request is valid and update
            $response = $this->wpei_check_request($request);
            if (!is_array($response)) {
                $data['Response'] = '';
                $data['Error'] = $response;
                $response_code = 500;
            } else {
                $response_body = $response['body'];
                $response = json_decode($response_body, true);

                if ($response['data']['success'] === TRUE && $response['data']['message'] === 'the token is valid') {
                    if (!empty($product_list)) {
                        $data = $this->wpei_get_list_products($product_list);
                    } elseif (!empty($slug_list)) {
                        $data = $this->wpei_get_list_slugs($slug_list);
                    } else {
                        $data = $this->wpei_get_all_products($show_variations, $limit, $page);
                    }
                    $response_code = 200;
                } else {
                    $data['Response'] = $response_body;
                    $data['Error'] = $response['data']['error'];
                    $response_code = 401;
                }
            }
            $data['current_page'] = $page !== 0 ? $page : 1;
            $data['Version'] = $this->wpei_version;

            return new WP_REST_Response($data, $response_code);
        }
    }

    $wcProductsExtractor = new WPEI_Products_Extractor();
    $wcProductsExtractor->config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . './config.json', 'a'), true);
}
