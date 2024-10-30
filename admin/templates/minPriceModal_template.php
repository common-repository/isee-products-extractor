<?php defined('ABSPATH') or die('No script kiddies please!'); ?>


<div class="modal" id="min_price_modal" style="display:none;">
    <div id="min_price_modal_content">
        <div class="modal-loading" id="min_price_modal_loading">
            <img src="<?= esc_attr(WPEI_ADMIN_STATIC . 'images/tube-spinner.svg') ?>" width="55" height="55">
        </div>
        <table class="modal-simple-table striped widefat wp-list-table" id="min_price_table">
            <caption id="min_price_table_caption">
                قیمت
                <strong></strong>
                در سایر فروشگاه ها
            </caption>
            <thead>
                <th><?= esc_attr__('Row', 'woo_products_extractor') ?></th>
                <th><?= esc_attr__('Site Title', 'woo_products_extractor') ?></th>
                <th><?= esc_attr__('Price', 'woo_products_extractor') ?></th>
            </thead>
            <tbody class="modal-body"></tbody>
        </table>
    </div>
</div>