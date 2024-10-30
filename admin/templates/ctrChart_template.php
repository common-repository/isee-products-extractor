<?php defined('ABSPATH') or die('No script kiddies please!');
$chartData = new stdClass();
$chartData->label = esc_attr__('Site products ctr', 'woo_products_extractor');
$chartData->data = $data;
?>

<style type="text/css" scoped>
.ctr-chart-widget .ctr-chart-widget-caption {
    margin-bottom: 20px;
    display: flex;
    flex-flow: row wrap;
    justify-content: start;
    align-items: flex-start;
    gap: 10px;
  }
</style>


<div class="ctr-chart-widget">
    <div class="ctr-chart-widget-caption">
        <img src="<?= WPEI_ADMIN_STATIC . 'images/logo-96x96.png' ?>" alt="isee-logo" width="55" height="55">
        <p>
            <?= esc_attr__('Products CTR Chart in Isee', 'woo_products_extractor'); ?>
        </p>
    </div>
    <canvas id="site_ctr_canvas"></canvas>
</div>

<script src="<?= WPEI_ADMIN_STATIC . 'lib/chart.umd.js' ?>"></script>
<script src="<?= WPEI_ADMIN_STATIC . 'js/chart-module.js' ?>"></script>
<script type="application/ld+json" id="statsJson">
    <?= json_encode($chartData); ?>
</script>
<script type="text/javascript">
    // Show stats chart
    const statsJsonElm = document.getElementById('statsJson').innerText;
    const chartData = JSON.parse(statsJsonElm);
    const data = chartData.data.data;

    if (data) {
        const canvas = document.getElementById('site_ctr_canvas');
        showChart(canvas, data, chartData.label);
    }
</script>