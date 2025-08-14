<?php
$cfg = __DIR__ . '/../../config/config.php';
require_once $cfg;
require_once ROOT_PATH . '/includes/api-forecast-15min.php';
require_once ROOT_PATH . '/includes/api-fetch.php';
require_once ROOT_PATH . '/includes/helpers.php';

// DEBUG: verifica che il file esista
if (file_exists(ROOT_PATH . '/includes/api-forecast-15min.php')) {
    echo "<!-- File api-forecast-15min.php ESISTE -->";
} else {
    echo "<!-- File api-forecast-15min.php NON ESISTE -->";
}

// DEBUG: verifica che le variabili siano definite dopo l'include
echo "<!-- DOPO INCLUDE: minutely_15_timestamps = " . (isset($minutely_15_timestamps) ? 'DEFINITO' : 'NON DEFINITO') . " -->";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 'On');
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">
  <meta name="theme-color" content="#2196f3">

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

  <title><?php echo isset($page_title) ? $page_title : 'Meteo Attuale + Previsioni'; ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet" />

  <!-- Bootstrap & Icons (CSS first) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.10/css/weather-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Leaflet CSS (usato nella vista Stazioni) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">

  <!-- Style -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css" />

  <!-- Chart.js core -->
  <script src="<?php echo BASE_URL; ?>/assets/js/chartjs-core.js"></script>
  <!-- Bootstrap bundle (JS) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Geolocalizzazione sempre caricata -->
  <script defer src="<?= BASE_URL; ?>/assets/js/geolocate.js"></script>
  
</head>
<body>
  <div id="meteocast-loader" style="display:none;">
    <div class="loader-spinner"></div>
  </div>

<div class="weather-background" id="weatherBg">
  <div class="weather-gradient" id="weatherGradient"></div>
  <canvas class="weather-canvas" id="weatherCanvas"></canvas>
</div>
<div class="weather-overlay" id="weatherOverlay"></div>

<section class="container-custom">
  <?php include ROOT_PATH . '/partials/common/location-bar.php'; ?>

  <script>
  // Config globale accessibile ovunque
    window.LAGOON_CONFIG = {
      RECHECK_HOURS: <?= RECHECK_HOURS ?>,
      MIN_DISTANCE_KM: <?= MIN_DISTANCE_KM ?>
    };

  // Inizializza tooltip dopo caricamento iniziale
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof initTooltips === 'function') initTooltips();
    });

  // Service Worker (PWA)
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("service-worker.js")
      .then(() => console.log("Service Worker registrato!"))
      .catch((err) => console.error("Errore Service Worker:", err));
    }

  // Aggiorna la pagina se i dati sono troppo vecchi dopo uno switch tab/app
    document.addEventListener('visibilitychange', function() {
      if (document.visibilityState === 'visible') {
        var el = document.querySelector('.update-time[data-update]');
        var lastUpdate = el ? el.getAttribute('data-update') : null;
        if (lastUpdate) {
          var last = new Date(lastUpdate);
          var now = new Date();
        var diffMin = (now - last) / 60000; // minuti
        if (diffMin > 10) {
          location.reload();
        }
      } else {
        // Se manca info, meglio aggiornare!
        location.reload();
      }
    }
  });
</script>