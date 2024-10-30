<?php defined('ABSPATH') or die('No script kiddies please!');


add_action('admin_menu', function () {
    require_once(WPEI_ROOTDIR . 'env.php');
    global $wpeiPageHook;

    $wpeiPageHook = add_menu_page(
        esc_html__('Isee products extractor', 'woo_products_extractor'),
        esc_html__('Isee products extractor', 'woo_products_extractor'),
        'administrator',
        'wpei-activation',
        function () {
            include(WPEI_ADMIN . 'pages/activation.php');
        },
        WPEI_ADMIN_STATIC . 'images/logo-24x24.png',
        56
    );
    add_submenu_page(
        'wpei-activation',
        esc_html__('Activation Settings', 'woo_products_extractor'),
        esc_html__('Activation Settings', 'woo_products_extractor'),
        'administrator',
        'wpei-activation'
    );

    // restrict access to other pages if plugin is not activated in isee
    include_once(WPEI_INC . 'helpers.php');
    $isDisabled = isSiteInactive();
    if (!$isDisabled) {
        $wpeiProductsListPageHook = add_submenu_page(
            'wpei-activation',
            esc_html__('Products List Table', 'woo_products_extractor'),
            esc_html__('Products List Table', 'woo_products_extractor'),
            'administrator',
            'wpei-products',
            function () {
                include(WPEI_ADMIN . 'pages/products_list.php');
            }
        );
        $wpeiMCProductsListPageHook = add_submenu_page(
            'wpei-activation',
            esc_html__('Most Clicked Products List Table', 'woo_products_extractor'),
            esc_html__('Most Clicked Products List Table', 'woo_products_extractor'),
            'administrator',
            'wpei-mcproducts',
            function () {
                include(WPEI_ADMIN . 'pages/most_clicked_products.php');
            }
        );

        add_action('load-' . $wpeiProductsListPageHook, 'wpei_load_admin_scripts');
        add_action('load-' . $wpeiMCProductsListPageHook, 'wpei_load_admin_scripts');
    }

    add_action('load-' . $wpeiPageHook, 'wpei_load_admin_scripts');
});

function wpei_load_admin_scripts()
{
    add_action('admin_enqueue_scripts', function () {
        $config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../config.json', 'a'), true);

        add_thickbox();
        wp_enqueue_style('wpei_toastr_css', WPEI_ADMIN_STATIC . 'lib/toastr.min.css', array(), '2.1.3');
        wp_enqueue_style('wpei_admin_styles', WPEI_ADMIN_STATIC . 'css/styles.css', array(), WPEI_PLUGIN_VERSION);
        wp_enqueue_script('wpei_toastr_js', WPEI_ADMIN_STATIC . 'lib/toastr.min.js', array(), '2.1.3');
        wp_enqueue_script('wpei_chart_module', WPEI_ADMIN_STATIC . 'js/chart-module.js', array(), WPEI_PLUGIN_VERSION);
        wp_enqueue_script('wpei_chart_js', WPEI_ADMIN_STATIC . 'lib/chart.umd.js', array(), '4.4.1');
        wp_enqueue_script('wpei_admin_scripts', WPEI_ADMIN_STATIC . 'js/admin_scripts.js', array('jquery'), WPEI_PLUGIN_VERSION, true);
        wp_localize_script('wpei_admin_scripts', 'WPEI_ADMIN_AJAX', array(
            'AJAX_URL' => admin_url('admin-ajax.php'),
            'SECURITY' => wp_create_nonce(getenv('ADMIN_AJAX_SECURITY')),
            'SUBMIT_BTN_TEXT' => esc_attr__('Check again', 'woo_products_extractor'),
            'SITE_PRODUCTS_CTR' => esc_attr__('Site products ctr', 'woo_products_extractor'),
            'NO_DATA' => esc_attr__('There is no available product to display!', 'woo_products_extractor'),
            'ISEE_BASE_URL' => $config['ISEE_BASE_URL'],
            'VIEW' => esc_attr__('View', 'woo_products_extractor'),
            'UNEXPECTED_ERROR' => esc_attr__('An unexpected error occurred!', 'woo_products_extractor'),
            'REQUEST_TIMEOUT' => 30000,
        ));
    });
}
