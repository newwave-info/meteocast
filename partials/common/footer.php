<?php
// Questo blocco PHP PRIMA!
if (!isset($isSunset))  $isSunset  = false;
if (!isset($isSunrise)) $isSunrise = false;
if (!isset($isNight))   $isNight   = false;
if (!isset($current_code)) $current_code = 0;

// Se hai commentato il selettore dei modelli, evita warning:
if (!isset($model_param)) $model_param = '';
?>

<nav class="navbar-bottom-nav" role="navigation" aria-label="Menu principale">
  <a href="/oggi<?= $model_param ?>" class="nav-link-nav" data-view="oggi">
    <i class="bi bi-house"></i>
    <span>Adesso</span>
  </a>
  <a href="/previsioni<?= $model_param ?>" class="nav-link-nav" data-view="previsioni">
    <i class="bi bi-calendar-week"></i>
    <span>Previsioni</span>
  </a>
  <a href="/luna-marea<?= $model_param ?>" class="nav-link-nav" data-view="luna-marea">
    <i class="bi bi-moon"></i>
    <span>Luna &amp; Marea</span>
  </a>
  <a href="/stazioni<?= $model_param ?>" class="nav-link-nav" data-view="stazioni">
    <i class="bi bi-compass"></i>
    <span>Stazioni</span>
  </a>
</nav>
</section><!-- /container-custom -->

<script>
  window.METEOCAST = {
    code: <?= (int)$current_code ?>,
    isNight: <?= $isNight ? 'true' : 'false' ?>,
    isSunset: <?= $isSunset ? 'true' : 'false' ?>,
    isSunrise: <?= $isSunrise ? 'true' : 'false' ?>
  };
</script>

<script src="<?= BASE_URL; ?>/assets/js/power-manager.js"></script>
<!-- <script src="<?= BASE_URL; ?>/assets/js/power-debug.js"></script> -->
<script src="<?= BASE_URL; ?>/assets/js/weather-background.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.METEOCAST && typeof window.updateWeatherBackground === 'function') {
      window.updateWeatherBackground(
        window.METEOCAST.code || 0,
        !!window.METEOCAST.isNight,
        !!window.METEOCAST.isSunset,
        !!window.METEOCAST.isSunrise
      );
    }
  });
</script>

<?php include ROOT_PATH . '/partials/common/js-weather-data.php'; ?>
<script src="<?= BASE_URL; ?>/assets/js/my-charts.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/custom.js"></script>
<script defer src="<?= BASE_URL; ?>/assets/js/search-location.js"></script>

<!-- Leaflet + MarkerCluster (caricati sempre per supportare SPA) -->
<script defer src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script defer src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<!-- Modulo mappa stazioni (usa Leaflet già caricato) -->
<script defer src="<?= BASE_URL; ?>/assets/js/stazioni-map.js"></script>

</body>
</html>