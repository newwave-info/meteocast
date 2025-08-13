<?php
// ==========================
// Previsioni - Domani (main widget)
// ==========================
include ROOT_PATH . '/partials/meteo/previsioni-previsioni.php';
?>


<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/previsioni-vento-row.php'; ?>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/previsioni-temp-row.php'; ?>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/previsioni-precipitazioni-row.php'; ?>
    </div>
</div>

<?php include ROOT_PATH . '/partials/meteo/previsioni-day-forecast-hours.php'; ?>