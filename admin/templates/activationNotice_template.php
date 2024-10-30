<?php defined('ABSPATH') or die('No script kiddies please!'); ?>

<style scoped>
    .wpei-activation-notice-section {
        padding: 20px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        margin: 36px;
        border-radius: 8px;
        text-align: center;
    }

    .wpei-activation-notice-section p {
        line-height: 2;
    }

    .wpei-activation-notice-section a {
        margin-top: 15px !important;
    }
</style>

<div class="wpei-activation-notice-section">
    <img src="<?= WPEI_ADMIN_STATIC . 'images/logo-96x96.png' ?>" alt="isee-logo" width="55" height="55">
    <h3 style="font-weight: 600;">
        <?= esc_attr__('Products CTR Chart in Isee', 'woo_products_extractor') ?>
    </h3>
    <p>
        برای مشاهده نمودار و استفاده از امکانات پلاگین، فروشگاه شما باید در سامانه آیسی فعال باشد.
    </p>
    <a href="<?= menu_page_url('wpei-activation', false); ?>" class="button button-primary">
        <?= esc_attr__('Submit and enable site', 'woo_products_extractor') ?>
    </a>
    </p>
</div>