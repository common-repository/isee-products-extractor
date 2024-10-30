<?php defined('ABSPATH') or die('No script kiddies please!');


function wpei_activate_function()
{
    if (!get_option('site_status_in_isee')) {
        update_option('site_status_in_isee', serialize([
            'has_checked' => 0,
            'site_id' => ''
        ]));
    }

    flush_rewrite_rules();
}

function wpei_deactivate_function()
{
    // disable site in isee
    require_once(plugin_dir_path(__FILE__) . 'includes/functions.php');
    disableSite();

    flush_rewrite_rules();
}
