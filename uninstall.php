<?php defined('ABSPATH') or die('No script kiddies please!');


if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

delete_option('site_status_in_isee');
