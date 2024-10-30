<?php defined('ABSPATH') or die('No script kiddies please!');

include_once(WPEI_INC . 'helpers.php');
$config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../../config.json', 'a'), true);

$currencyUnit = getWoocomerceCurrency();
$products = new stdClass();
$cachedResult = get_transient('cached_most_clicked_data');
if (boolval($cachedResult)) {
    $products = $cachedResult;
} else {
    $endpointUrl = $config['API_BASE_URL'] . '/products/fetch_most_clicked_products';
    $shopDomain = getShopDomain();
    $response = wp_remote_post(
        $endpointUrl,
        array(
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'domain' => $shopDomain
            ]),
            'timeout' => 30
        )
    );

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
        $results = json_decode(wp_remote_retrieve_body($response));
        $products = $results->data;
        set_transient('cached_most_clicked_data', $products, $config['CACHE_DURATION']);
    }
}

?>


<section class="wpei-products-list">
    <h1 class="wp-heading-inline">
        <?= esc_attr__('List Of Most Clicked Products In Isee', 'woo_products_extractor') ?>
    </h1>
    <br>

    <table class="wp-list-table widefat fixed striped table-view-list isee-products">
        <thead>
            <tr>
                <th scope="col" id="title" class="manage-column column-title column-primary" style="width: 22%;"><?= esc_attr__('Title', 'woo_products_extractor') ?></th>
                <th scope="col" id="picture" class="manage-column column-picture" style="width: 13%;"><?= esc_attr__('Picture', 'woo_products_extractor') ?></th>
                <th scope="col" id="status" class="manage-column column-status" style="width: 13%;"><?= esc_attr__('Status', 'woo_products_extractor') ?></th>
                <th scope="col" id="price" class="manage-column column-price" style="width: 13%;"><?= esc_attr__('Price', 'woo_products_extractor') ?></th>
                <th scope="col" id="grouping_status" class="manage-column column-grouping-status" style="width: 13%;"><?= esc_attr__('Grouping Status', 'woo_products_extractor') ?></th>
                <th scope="col" id="min_prices" class="manage-column column-min_prices" style="width: 13%;">
                    <?= esc_attr__('Show Minimum Prices', 'woo_products_extractor') ?>
                </th>
                <th scope="col" id="most_clicked" class="manage-column column-most-clicked" style="width: 13%;">
                    <?= esc_attr__('Click Count', 'woo_products_extractor') ?>
                </th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (sizeof((array) $products) === 0) : ?>
                <tr>
                    <td colspan="7" style="text-align: center;padding: 15px;">
                        <?= esc_attr__('There is no data to display!', 'woo_products_extractor');
                        ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($products as $product) :
                    $localDate = date('Y-m-d H:i:s', strtotime($product->date) - 60 * 60 * 3.5);
                    $isReferenceProduct = boolval($product->ref_pid);
                    $productLink = $isReferenceProduct
                        ? sprintf('%s/products/%s/%s', $config['ISEE_BASE_URL'], $product->ref_pid, $product->name)
                        : sprintf('%s/nc-products/%s/%s', $config['ISEE_BASE_URL'], $product->id, $product->name);
                ?>
                    <tr>
                        <td class="title column-title has-row-actions column-primary page-title">
                            <a href="<?= $productLink; ?>" target="_blank">
                                <strong><?= esc_attr($product->name) ?></strong>
                            </a>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text">نمایش جزئیات بیشتر</span>
                            </button>
                        </td>
                        <td>
                            <?php
                            $imageSrc = esc_url($config['ISEE_ICDN_URL'] . $product->picture, null, 'display');
                            if (count((array) $product->picture) === 0) {
                                $imageSrc = WPEI_ADMIN_STATIC . 'images/default-img.png';
                            }
                            ?>
                            <img src="<?= $imageSrc; ?>" width="65" height="65">
                        </td>
                        <td class="column-status">
                            <?php if ($product->stock_count === 0) : ?>
                                <span class="unavailable"><?= esc_attr__('Unavailable', 'woo_products_extractor') ?></span>
                            <?php else : ?>
                                <span class="available"><?= esc_attr__('Available', 'woo_products_extractor') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-price">
                            <strong>
                                <?= $product->price != 0 ?  number_format(esc_html($product->price)) . $currencyUnit : 'بدون قیمت' ?>
                            </strong>
                        </td>
                        <td class="column-date">
                            <span><?= $isReferenceProduct ? 'ادغام شده' : 'ادغام نشده' ?></span>
                        </td>
                        <td class="column-min-price">
                            <?php if ($isReferenceProduct) : ?>
                                <a href="#TB_inline?width=600&height=400&inlineId=min_price_modal" class="button thickbox" onclick="getMinPrices('<?= $shopDomain ?>','<?= $product->name ?>', '<?= $product->price ?>')">
                                    <span class="dashicons-before dashicons-visibility"></span>
                                </a>
                            <?php else : ?>
                                <a href="javascript:void(0)" class="button" disabled>
                                    <span class="dashicons-before dashicons-visibility"></span>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= $product->click_count; ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php wp_nonce_field('wpei-products-list-nonce', 'wpei_products_list_nonce'); ?>
    </table>

    <!-- Minimum Prices Table Modal -->
    <?php include(WPEI_ADMIN . 'templates/minPriceModal_template.php'); ?>
</section>