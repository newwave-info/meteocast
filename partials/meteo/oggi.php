<?php
// Forecast orario
$now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));

// Check essenziale su dati meteo
if (empty($hourly_temperature) || empty($timestamps)) {
    echo '<div class="alert alert-danger my-3">Dati meteo non disponibili. Riprova più tardi.</div>';
    return;
}

// Partials rendering
include ROOT_PATH . '/partials/meteo/oggi-current.php';
include ROOT_PATH . '/partials/meteo/oggi-forecast-orario.php';
include ROOT_PATH . '/partials/meteo/oggi-meteo-alert.php';
?>

<div class="row gx-3">
  <div class="col-6">
    <?php include ROOT_PATH . '/partials/meteo/oggi-pressione.php'; ?>
</div>

<div class="col-6">
    <?php include ROOT_PATH . '/partials/meteo/oggi-visibilita.php'; ?>
</div>
</div>


<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/oggi-vento-row.php'; ?>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/oggi-temp-row.php'; ?>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12">
        <?php include ROOT_PATH . '/partials/meteo/oggi-precipitazioni-row.php'; ?>
    </div>
</div>
