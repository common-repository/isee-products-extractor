<?php defined('ABSPATH') or die('No script kiddies please!');


function disableSite()
{
    $config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../config.json', 'a'), true);
    $apiUrl = $config['API_BASE_URL'];

    $checkStatus = unserialize(get_option('site_status_in_isee'));
    if (boolval($checkStatus)) {
        $siteId = $checkStatus['site_id'];
        $siteUrl = wp_parse_url(get_site_url());
        $shopDomain = str_replace('www.', '', $siteUrl['host']);

        wp_remote_post(
            "${apiUrl}/crawler/disable-site-by-plugin",
            array(
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'body' => json_encode([
                    'site_id' => $siteId,
                    'domain' => $shopDomain,
                ]),
                'timeout' => 30,
            )
        );
        update_option('site_status_in_isee', serialize([
            'has_checked' => 1,
            'site_id' => ''
        ]));
    }
}
