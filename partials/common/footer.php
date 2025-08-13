
<?php
// $allowed_models = ['gfs','icon','arpege','gem','jma'];
// $current_model = (isset($_GET['model']) && in_array($_GET['model'], $allowed_models)) 
//     ? $_GET['model'] 
//     : (defined('OPEN_METEO_MODEL') ? OPEN_METEO_MODEL : 'gfs');
// $model_param = $current_model ? ('?model=' . urlencode($current_model)) : '';

// Questo blocco PHP PRIMA!
if (!isset($isSunset)) $isSunset = false;
if (!isset($isSunrise)) $isSunrise = false;
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
    code: <?= isset($current_code) ? (int)$current_code : 0 ?>,
    isNight: <?= $isNight ? 'true' : 'false' ?>,
    isSunset: <?= $isSunset ? 'true' : 'false' ?>,
    isSunrise: <?= $isSunrise ? 'true' : 'false' ?>
  };
</script>

<script src="<?php echo BASE_URL; ?>/assets/js/weather-background.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    console.log('>>> METEOCAST', window.METEOCAST);
    console.log('>>> updateWeatherBackground:', typeof window.updateWeatherBackground);
    if (
      window.METEOCAST &&
      typeof window.updateWeatherBackground === 'function'
    ) {
      const weatherCode = window.METEOCAST.code || 0;
      const isNight = !!window.METEOCAST.isNight;
      window.isSunset = !!window.METEOCAST.isSunset;
      window.isSunrise = !!window.METEOCAST.isSunrise;
      window.updateWeatherBackground(
        weatherCode, isNight, window.isSunset, window.isSunrise
      );
    } else {
      setTimeout(() => {
        // retry
      }, 100);
    }
  });
</script>


<?php include ROOT_PATH . '/partials/common/js-weather-data.php'; ?>
<script src="<?php echo BASE_URL; ?>/assets/js/my-charts.js"></script>
  <!-- Custom functions & tooltip logic -->
<script src="<?php echo BASE_URL; ?>/assets/js/custom.js"></script>
<script defer src="<?php echo BASE_URL; ?>/assets/js/search-location.js"></script>
<!-- tutto il routing e tab logic ora è già in custom.js -->



<!-- JavaScript per vista stazioni -->
<?php if (isset($_GET['view']) && $_GET['view'] === 'stazioni'): ?>
<script>
// TEST IMMEDIATO: onclick inline per verificare che il pulsante sia cliccabile
function testClick() {
    console.log('>>> CLICK PULSANTE FUNZIONA! <<<');
    alert('CLICK FUNZIONA!');
    toggleStazioniMap();
}

// DEBUG: Verifica caricamento script
console.log('>>> SCRIPT STAZIONI CARICATO DAL FOOTER <<<');

// Assicuriamo che le funzioni siano definite dopo il DOM load
document.addEventListener('DOMContentLoaded', function() {
    console.log('>>> DOM LOADED - STAZIONI <<<');
    
    // Event listener per il toggle mappa
    const toggleBtn = document.getElementById('toggle-map-btn');
    console.log('>>> Toggle button trovato:', toggleBtn);
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            console.log('>>> CLICK PULSANTE MAPPA <<<');
            toggleStazioniMap();
        });
    }
});

function toggleStazioniMap() {
    console.log('>>> toggleStazioniMap() CHIAMATA <<<');
    
    const mapDiv = document.getElementById('stazioni-map');
    const toggleBtn = document.getElementById('toggle-map-btn');
    
    console.log('mapDiv trovato:', mapDiv);
    console.log('toggleBtn trovato:', toggleBtn);
    
    if (!mapDiv) {
        console.error('Elemento #stazioni-map non trovato!');
        alert('ERRORE: #stazioni-map non trovato!');
        return;
    }
    
    const isCurrentlyVisible = window.getComputedStyle(mapDiv).display !== 'none';
    console.log('Mappa attualmente visibile:', isCurrentlyVisible);
    
    if (isCurrentlyVisible) {
        // Nascondi mappa
        mapDiv.style.display = 'none';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-map"></i> Mappa';
        }
        console.log('>>> MAPPA NASCOSTA <<<');
        
        // Cleanup mappa quando nascosta
        if (typeof window.cleanupStazioniMap === 'function') {
            window.cleanupStazioniMap();
        }
    } else {
        // Mostra mappa
        mapDiv.style.display = 'block';
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-map-fill"></i> Nascondi';
        }
        console.log('>>> MAPPA MOSTRATA <<<');
        
        // Fallback: inizializza mappa basic
        fallbackMapInit();
    }
}

// Fallback per test basic
function fallbackMapInit() {
    console.log('>>> FALLBACK MAP INIT <<<');
    const container = document.getElementById('map-container');
    if (container) {
        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 text-success">
                <div class="text-center">
                    <i class="bi bi-check-circle me-2" style="font-size: 2rem;"></i>
                    <div><strong>Toggle funziona!</strong></div>
                    <small>Caricato dal footer.php</small>
                </div>
            </div>
        `;
    }
}
</script>
<?php endif; ?>

<!-- Leaflet e MarkerCluster JS (solo se siamo nella vista stazioni) -->

<!-- Leaflet e MarkerCluster JS (solo se siamo nella vista stazioni) -->
<?php if (isset($_GET['view']) && $_GET['view'] === 'stazioni'): ?>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script>
// DEBUG: Verifica dati stazioni
console.log('GLOBALS stazioni_venezia_data:', <?= json_encode($GLOBALS['stazioni_venezia_data'] ?? null) ?>);
console.log('Numero stazioni:', <?= count($GLOBALS['stazioni_venezia_data'] ?? []) ?>);

// Esponi i dati stazioni per la mappa JavaScript
window.stazioniVeneziaData = <?= json_encode($GLOBALS['stazioni_venezia_data'] ?? []) ?>;
console.log('Window stazioniVeneziaData:', window.stazioniVeneziaData);
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/stazioni-map.js"></script>
<?php endif; ?>


</body>
</html>