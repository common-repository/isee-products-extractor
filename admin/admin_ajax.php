<?php defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add site to isee 
 * @return object
 */
function addSiteToIseeCallback()
{
    require_once(WPEI_ROOTDIR . 'env.php');
    $config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../config.json', 'a'), true);
    $apiUrl = $config['API_BASE_URL'];

    if (
        !current_user_can('administrator')
        || !wp_verify_nonce($_POST['nonce'], 'wpei-activation-nonce')
        || !check_ajax_referer(getenv('ADMIN_AJAX_SECURITY'), 'security')
    ) {
        wp_send_json_error([
            'message' => 'Forbidden!'
        ], 403);
        exit;
    }

    $checkStatus = unserialize(get_option('site_status_in_isee'));
    if (boolval($checkStatus) && $checkStatus['site_id'] !== '') {
        wp_send_json_error([
            'message' => esc_attr__('Your site has already been registered!', 'woo_products_extractor')
        ], 200);
        exit;
    }

    $siteUrl = wp_parse_url(get_site_url());
    $shopDomain = str_replace('www.', '', $siteUrl['host']);
    $response = wp_remote_post(
        "{$apiUrl}/crawler/add-update-site-by-plugin",
        array(
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'title' => get_bloginfo(),
                'currency' => 'تومان',
                'disabled' => true,
                'domain' => $shopDomain,
                'link' => sprintf('%s://%s', $siteUrl['scheme'], $siteUrl['host']),
                'logo' => 1,
                'city_id' => 1,
                'price_is_not_update' => false,
            ]),
            'timeout' => 30,
        )
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error([
            'message' => esc_attr__("Error in registering the store in IC! \n Please try again.", 'woo_products_extractor')
        ], 200);
        exit;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);
    update_option('site_status_in_isee', serialize([
        'has_checked' => 1,
        'site_id' => $result['site_id']
    ]));

    wp_send_json_success([
        'message' => esc_attr__('The operation was done successfully.', 'woo_products_extractor')
    ], 200);
    exit;
}
add_action('wp_ajax_addSiteToIsee', 'addSiteToIseeCallback');

/**
 * Get product minimum prices in isee base on product_name
 * @return object
 */
function getMinPricesCallback()
{
    require_once(WPEI_ROOTDIR . 'env.php');
    $config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../config.json', 'a'), true);

    if (
        !current_user_can('administrator')
        || !wp_verify_nonce($_POST['nonce'], 'wpei-products-list-nonce')
        || !check_ajax_referer(getenv('ADMIN_AJAX_SECURITY'), 'security')
    ) {
        wp_send_json_error([
            'message' => 'Forbidden!'
        ], 403);
        exit;
    }

    $endpointUrl = $config['API_BASE_URL'] . '/products/get_cheaper_products';
    $productName = sanitize_text_field($_POST['product_name']);
    $response = wp_remote_post(
        $endpointUrl,
        array(
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'product_name' => $productName
            ]),
            'timeout' => 30,
        )
    );

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error([
            'message' => esc_attr__("Error in fetching data! \n Please try again.", 'woo_products_extractor')
        ], 200);
        exit;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);
    wp_send_json_success($result, 200);
    exit;
}
add_action('wp_ajax_getMinPrices', 'getMinPricesCallback');
