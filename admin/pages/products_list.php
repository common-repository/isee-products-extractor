<?php defined('ABSPATH') or die('No script kiddies please!');

include_once(WPEI_INC . 'helpers.php');
$config = json_decode(file_get_contents(plugin_dir_path(__FILE__) . '../../config.json', 'a'), true);


$products = [];
$searchQuery = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$limit = $config['PAGINATION_LIMIT'];
$page = isset($_GET['paged']) ? sanitize_text_field($_GET['paged']) : 1;
$offset = ($page - 1) * $limit;
$endpointUrl = $config['API_BASE_URL'] . '/products/get_products_by_domain';
$shopDomain = getShopDomain();
$currencyUnit = getWoocomerceCurrency();
$groupingStatus = isset($_GET['grouping'])
    ? sanitize_text_field($_GET['grouping'])
    : 'all';

$payload = [
    'domain' => $shopDomain,
    'search_query' => trim($searchQuery),
    'offset' => $offset,
    'limit' => $limit,
];
if ($groupingStatus !== 'all') {
    $payload = [
        'domain' => $shopDomain,
        'search_query' => trim($searchQuery),
        'offset' => $offset,
        'limit' => $limit,
        'grouping_status' => intval($groupingStatus)
    ];
}
$response = wp_remote_post($endpointUrl, array(
    'headers' => [
        'content-type' => 'application/json',
    ],
    'body' => json_encode($payload),
    'timeout' => 30
));
if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
    $results = json_decode(wp_remote_retrieve_body($response));
}

// map products properties on searching
if ($searchQuery === '') {
    $products =  json_decode(json_encode($results->data->results), true);
} else {
    $searchResults = $results->data->results->hits->hits;
    for ($i = 0; $i < sizeof($searchResults); $i++) {
        $products[$i]['id'] = $searchResults[$i]->_source->id ?? '';
        $products[$i]['name'] = $searchResults[$i]->_source->name ?? '';
        $products[$i]['date'] = $searchResults[$i]->_source->date ?? '';
        $products[$i]['picture'] = $searchResults[$i]->_source->picture ?? '';
        $products[$i]['_source']['media_list'] = $searchResults[$i]->_source->media_list ?? [];
        $products[$i]['stock_count'] = $searchResults[$i]->_source->stock_count ?? 0;
        $products[$i]['price'] = $searchResults[$i]->_source->price ?? 0;
        $products[$i]['ref_pid'] = $searchResults[$i]->_source->product_id;
    }
    // filter grouping products
    if ($groupingStatus !== 'all') {
        $products = array_filter($products, function ($product) use ($groupingStatus) {
            if ($groupingStatus == 1) {
                return boolval($product['ref_pid']);
            }
            return !boolval($product['ref_pid']);
        });
    }
}

$countMetaData = $results->data->metadata;
$totalProducts = $countMetaData->total;
$totalGroupedProducts = $countMetaData->grouped;
$totalNotGroupedProducts = $countMetaData->notGrouped;
?>

<section class="wpei-products-list">
    <h1 class="wp-heading-inline">
        <?= esc_attr__('List Of Products In Isee', 'woo_products_extractor') ?>
    </h1>

    <div class="header-buttons-row">
        <div class="filter-by-grouping-status">
            <?php
            $mainUrl = remove_query_arg(['paged', 's']);
            $allProductsUrl = add_query_arg('grouping', 'all', $mainUrl);
            $groupedProductsUrl = add_query_arg('grouping', '1', $mainUrl);
            $notGroupedProductsUrl = add_query_arg('grouping', '0', $mainUrl);
            ?>
            <ul class="subsubsub">
                <li class="all">
                    <a href="<?= $allProductsUrl ?>" class="<?= $groupingStatus == 'all' ? 'current' : '' ?>" aria-current="page">
                        <?= esc_attr__('All', 'woo_products_extractor') ?>
                        <span class="count"> (<?= $totalProducts; ?>) </span>
                    </a>
                </li>
                &#124;
                <li class="mine">
                    <a href="<?= $groupedProductsUrl; ?>" class="<?= $groupingStatus == '1' ? 'current' : '' ?>">
                        <?= esc_attr__('Grouped', 'woo_products_extractor') ?><span class="count"> (<?= $totalGroupedProducts ?>) </span>
                    </a>
                </li>
                &#124;
                <li class="mine">
                    <a href="<?= $notGroupedProductsUrl; ?>" class="<?= $groupingStatus == '0' ? 'current' : '' ?>">
                        <?= esc_attr__('Not Grouped', 'woo_products_extractor') ?>
                        <span class="count"> (<?= $totalNotGroupedProducts ?>) </span>
                    </a>
                </li>
            </ul>
        </div>

        <div>
            <form class="search-box" action="" method="get">
                <input type="search" id="search_input" name="s" value="<?= $searchQuery ?>">
                <input type="submit" id="search_submit" class="button" value="<?= esc_attr__('Search in products', 'woo_products_extractor') ?>" data-action="<?= menu_page_url('wpei-products', false); ?>">
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list isee-products">
        <thead>
            <tr>
                <th scope="col" id="title" class="manage-column column-title column-primary" style="width: 22%;"><?= esc_attr__('Title', 'woo_products_extractor') ?></th>
                <th scope="col" id="picture" class="manage-column column-picture" style="width: 13%;"><?= esc_attr__('Picture', 'woo_products_extractor') ?></th>
                <th scope="col" id="status" class="manage-column column-status" style="width: 13%;"><?= esc_attr__('Status', 'woo_products_extractor') ?></th>
                <th scope="col" id="price" class="manage-column column-price" style="width: 13%;"><?= esc_attr__('Price', 'woo_products_extractor') ?></th>
                <th scope="col" id="date" class="manage-column column-date" style="width: 13%;"><?= esc_attr__('Last Update', 'woo_products_extractor') ?></th>
                <th scope="col" id="grouping_status" class="manage-column column-grouping-status" style="width: 13%;"><?= esc_attr__('Grouping Status', 'woo_products_extractor') ?></th>
                <th scope="col" id="min_prices" class="manage-column column-min_prices" style="width: 13%;">
                    <?= esc_attr__('Show Minimum Prices', 'woo_products_extractor') ?>
                </th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (count((array) $products) === 0) : ?>
                <tr>
                    <td colspan="7" style="text-align: center;padding: 15px;">
                        <?= esc_attr__('There is no data to display!', 'woo_products_extractor');
                        ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($products as $product) :
                    $localDate = date('Y-m-d H:i:s', strtotime($product['date']) - 60 * 60 * 3.5);
                    $isReferenceProduct = boolval($product['ref_pid']);
                    $productLink = $isReferenceProduct
                        ? sprintf('%s/products/%s/%s', $config['ISEE_BASE_URL'], $product['ref_pid'], $product['name'])
                        : sprintf('%s/nc-products/%s/%s', $config['ISEE_BASE_URL'], $product['id'], $product['name']);
                ?>
                    <tr>
                        <td class="title column-title has-row-actions column-primary page-title">
                            <a href="<?= esc_attr($productLink); ?>" target="_blank">
                                <strong><?= esc_attr($product['name']) ?></strong>
                            </a>
                            <button type="button" class="toggle-row"><span class="screen-reader-text">نمایش جزئیات بیشتر</span></button>
                        </td>
                        <td>
                            <?php
                            $imageSrc = esc_url('https://icdn.sisoog.com/' . $product['picture'], null, 'display');
                            if (count($product['_source']['media_list']) === 0) {
                                $imageSrc = WPEI_ADMIN_STATIC . 'images/default-img.png';
                            }
                            ?>
                            <img src="<?= $imageSrc; ?>" width="65" height="65">
                        </td>
                        <td class="column-status">
                            <?php if ($product['stock_count'] === 0) : ?>
                                <span class="unavailable"><?= esc_attr__('Unavailable', 'woo_products_extractor') ?></span>
                            <?php else : ?>
                                <span class="available"><?= esc_attr__('Available', 'woo_products_extractor') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-price">
                            <strong>
                                <?= $product['price'] != 0 ?  number_format(esc_html($product['price'])) . $currencyUnit : 'بدون قیمت' ?>
                            </strong>
                        </td>
                        <td class="column-date">
                            <span><?= esc_html(timeAgo($localDate)); ?></span>
                        </td>
                        <td class="column-date">
                            <span><?= $isReferenceProduct ? 'ادغام شده' : 'ادغام نشده' ?></span>
                        </td>
                        <td class="column-min-price">
                            <?php if ($isReferenceProduct) : ?>
                                <a href="#TB_inline?width=600&height=400&inlineId=min_price_modal" class="button thickbox" onclick="getMinPrices('<?= $shopDomain ?>','<?= $product['name'] ?>', '<?= $product['price'] ?>')">
                                    <span class="dashicons-before dashicons-visibility"></span>
                                </a>
                            <?php else : ?>
                                <a href="javascript:void(0)" class="button" disabled>
                                    <span class="dashicons-before dashicons-visibility"></span>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php wp_nonce_field('wpei-products-list-nonce', 'wpei_products_list_nonce'); ?>
    </table>

    <!-- Pagination -->
    <?php
    $currentPage = intval($page);
    $nextPage =  $currentPage + 1;
    $nextPageUrl = add_query_arg('paged', $nextPage, $_SERVER['REQUEST_URI']);
    $prevPage =  $currentPage - 1;
    $prevPageUrl = add_query_arg('paged', $prevPage, $_SERVER['REQUEST_URI']);
    $firstPageUrl = remove_query_arg('paged', $_SERVER['REQUEST_URI']);
    $lastPage = $searchQuery === ''
        ? intval($results->data->count)
        : intval(ceil(($results->data->results->hits->total->value) / $limit));
    $lastPageUrl = add_query_arg('paged', $lastPage, $_SERVER['REQUEST_URI']);
    $isFirstPage = $currentPage == 1;
    $isLastPage = $currentPage == $lastPage;
    ?>
    <?php if ($lastPage > 1 && count((array) $products) > 0) :  ?>
        <div class="tablenav-pages">
            <span class="pagination-links">
                <a class="first-page button <?= $isFirstPage ? 'disabled' : '' ?>" href="<?= $isFirstPage ? '#' : esc_url($firstPageUrl); ?>">
                    <span class="screen-reader-text">برگه اول</span>
                    <span aria-hidden="true">&lt;&lt;</span>
                </a>
                <a class="prev-page button <?= $isFirstPage ? 'disabled' : '' ?>" href="<?= $isFirstPage ? '#' : esc_url($prevPageUrl); ?>">
                    <span class="screen-reader-text">برگهٔ قبلی</span>
                    <span aria-hidden="true">&lt;</span>
                </a>

                <span class="screen-reader-text">برگهٔ فعلی</span>
                <span id="table-paging" class="paging-input">
                    <span class="tablenav-paging-text">
                        <?= esc_attr__('Page', 'woo_products_extractor')  ?>
                        <?= $currentPage . esc_attr__(' from ', 'woo_products_extractor') ?>
                        <span class="total-pages"><?= esc_attr($lastPage) ?></span>
                    </span>
                </span>

                <a class="next-page button <?= $isLastPage ? 'disabled' : '' ?>" href="<?= $isLastPage ? '#' : esc_url($nextPageUrl); ?>">
                    <span class="screen-reader-text">برگهٔ بعدی</span>
                    <span aria-hidden="true">&gt;</span>
                </a>
                <a class="last-page button <?= $isLastPage ? 'disabled' : '' ?>" href="<?= $isLastPage ? '#' : esc_url($lastPageUrl); ?>">
                    <span class="screen-reader-text"><?= esc_attr__('Last Page', 'woo_products_extractor') ?></span>
                    <span aria-hidden="true">&gt;&gt;</span>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <!-- Minimum Prices Table Modal -->
    <?php include(WPEI_ADMIN . 'templates/minPriceModal_template.php'); ?>

</section>