<?php defined('ABSPATH') or die('No script kiddies please!');

add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget(
        'wpei_crt_chart_dash_widget',
        esc_attr__('CTR Chart', 'woo_products_extractor'),
        'render_crt_chart_widget',
        '',
        array(),
        'side',
        'high'
    );
});

function render_crt_chart_widget($p, $args)
{
    try {
        include_once(WPEI_INC . 'helpers.php');
        $isDisabled = isSiteInactive();
        if ($isDisabled) {
            include(WPEI_ADMIN . 'templates/activationNotice_template.php');
        } else {
            $data = _fetchChartData();
            include(WPEI_ADMIN . 'templates/ctrChart_template.php');
        }
    } catch (Exception $e) {
        echo '<p style="text-align: center;padding: 15px;"> ' . $e->getMessage() . ' </p>';
    }
}

function _fetchChartData()
{
    include_once(WPEI_INC . 'helpers.php');
    $config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../config.json', 'a'), true);

    if (false !== ($result = get_transient('cached_chart_data'))) {
        return $result;
    }

    $endpointUrl = $config['API_BASE_URL'] .  '/products/get_site_ctr';
    $shopDomain = getShopDomain();
    $response = wp_remote_post($endpointUrl, array(
        'headers' => [
            'content-type' => 'application/json',
        ],
        'body' => json_encode([
            'domain' => $shopDomain,
        ]),
        'timeout' => 30
    ));

    if (
        is_wp_error($response) ||
        wp_remote_retrieve_response_code($response) != 200
    ) {
        throw new Exception(esc_html__('An unexpected error occurred!', 'woo_products_extractor'), 0);
    }

    $result = json_decode(wp_remote_retrieve_body($response));
    set_transient('cached_chart_data', $result, $config['CACHE_DURATION']);
    return $result;
}
