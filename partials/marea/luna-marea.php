<?php
require_once ROOT_PATH . '/includes/tide-comune-fetch.php';
$lat = LATITUDE ?? null;
$lon = LONGITUDE ?? null;
$tz  = TIMEZONE ?? null;
if (!$lat || !$lon || !$tz) {
    echo '<div class="alert alert-warning my-3">Dati lunari non disponibili.<br>Imposta una località per visualizzare le fasi lunari.</div>';
    return;
}
?>
<div class="row gx-3">
  <div class="col-12">
    <?php include ROOT_PATH . '/partials/marea/luna-widget.php'; ?>
  </div>
</div>
<div class="row gx-3">
  <div class="col-12">
    <?php include ROOT_PATH . '/partials/marea/marea-salute-widget.php'; ?>
  </div>
</div>
<div class="row gx-3">
  <div class="col-12">
    <?php //include ROOT_PATH . '/partials/marea/marea-cnr-widget.php'; ?>
  </div>
</div>

<script type="application/json" id="tideDataJson">
<?= json_encode($GLOBALS['tide_salute_forecast'] ?? ['data' => [], 'last_update' => null, 'station' => 'Punta della Salute', 'updated_at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_SLASHES) ?>
</script>

<script>
// Helper JavaScript per accedere ai dati delle maree con timestamp
window.TideData = (function() {
    const jsonElement = document.getElementById('tideDataJson');
    if (!jsonElement) return null;
    
    try {
        const tideData = JSON.parse(jsonElement.textContent);
        
        return {
            // Accesso ai dati delle maree
            getData: () => tideData.data || [],
            
            // Informazioni sui metadati
            getLastUpdate: () => tideData.last_update,
            getStation: () => tideData.station || 'Punta della Salute',
            getUpdatedAt: () => tideData.updated_at,
            
            // Helper per formattare la data di aggiornamento
            getFormattedLastUpdate: () => {
                if (!tideData.last_update) return null;
                const date = new Date(tideData.last_update);
                return date.toLocaleString('it-IT', {
                    day: '2-digit',
                    month: '2-digit', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },
            
            // Helper per verificare se i dati sono aggiornati
            isDataFresh: (maxAgeHours = 6) => {
                if (!tideData.last_update) return false;
                const updateTime = new Date(tideData.last_update);
                const now = new Date();
                const ageHours = (now - updateTime) / (1000 * 60 * 60);
                return ageHours <= maxAgeHours;
            },
            
            // Dati grezzi per compatibilità
            raw: tideData
        };
    } catch (e) {
        console.error('Errore nel parsing dei dati delle maree:', e);
        return null;
    }
})();

// IMPORTANTE: Compatibilità con il grafico esistente
// Il grafico cerca window.tideData, quindi lo impostiamo
window.tideData = window.TideData ? window.TideData.getData() : [];

// Esempio di utilizzo:
// const tidePoints = window.TideData?.getData() || [];
// const lastUpdate = window.TideData?.getFormattedLastUpdate();
// const isDataFresh = window.TideData?.isDataFresh(6); // freschi se < 6 ore
</script>