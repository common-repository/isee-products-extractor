<?php defined('ABSPATH') or die('No script kiddies please!');

/**
 * Calculate time ago 
 * @param date $date 
 * @return string  
 */
function timeAgo($date)
{
    $timestamp = strtotime($date);
    $strTime = array("ثانیه", "دقیقه", "ساعت", "روز", "ماه", "سال");
    $length = array("60", "60", "24", "30", "12", "10");

    $currentTime = time();
    if ($currentTime >= $timestamp) {
        $diff     = time() - $timestamp;
        for ($i = 0; $diff >= $length[$i] && $i < count($length) - 1; $i++) {
            $diff = $diff / $length[$i];
        }

        $diff = round($diff);
        return $diff . " " . $strTime[$i] . " قبل ";
    }
}

/**
 * Get shop domain  
 * @return string  
 */
function getShopDomain()
{
    $siteUrl = wp_parse_url(get_site_url());
    return str_replace('www.', '', $siteUrl['host']);
}

/**
 * Get woocommerce currency unit
 * @return string
 */
function getWoocomerceCurrency()
{
    $currencyUnit = '';
    switch (get_woocommerce_currency()) {
        case 'IRT':
            $currencyUnit = ' تومان ';
            break;
        case 'IRR':
            $currencyUnit = ' ریال ';
            break;
        default:
            $currencyUnit = ' $ ';
            break;
    }
    return $currencyUnit;
}

/**
 * Check if site is active in isee
 * @return bool
 */
function isSiteInactive()
{
    $statusOption = unserialize(get_option('site_status_in_isee'));
    return !boolval($statusOption['has_checked']) || $statusOption['site_id'] === '';
}
