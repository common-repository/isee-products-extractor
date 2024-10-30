<?php defined('ABSPATH') or die('No script kiddies please!');

include_once(WPEI_INC . 'helpers.php');
$isDisabled = isSiteInactive();
$submitButtonText = $isDisabled
    ? esc_attr__('Submit and enable site', 'woo_products_extractor')
    : esc_attr__('Check again', 'woo_products_extractor')
?>

<div class="wrap">
    <h2><?= esc_attr__('Registration site in the isee', 'woo_products_extractor') ?></h2>

    <div class="wpei-activation-section">
        <div class="wpei-notifs">
            <div class="alert" role="alert">
                <?php if ($isDisabled) : ?>
                    <div class="alert-error">
                        <p>
                            <i class="material-icons">gpp_maybe</i>
                            <?= esc_attr__('Your site has not been submitted to isee!', 'woo_products_extractor') ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="alert-success">
                        <p>
                            <i class="material-icons">verified_user</i>
                            فروشگاه شما در موتور جستجوی آیسی ثبت شده است.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form class="wpei-form" method="post">
            <?php wp_nonce_field('wpei-activation-nonce', 'settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <td style="text-align: center;">
                            <button class="button button-primary" id="add_shop_btn">
                                <?= $submitButtonText ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</div>