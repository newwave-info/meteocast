<?php
$location_label = defined('LOCATION_NAME') ? LOCATION_NAME : '';
$elev_label     = (defined('LOCATION_ELEVATION') && is_numeric(LOCATION_ELEVATION)) ? round(LOCATION_ELEVATION)." m" : '';

// Solo se $current_datetime è valido, usa DateTimeImmutable; altrimenti null
$dt_update = !empty($current_datetime)
  ? new DateTimeImmutable($current_datetime, new DateTimeZone(TIMEZONE))
  : (new DateTimeImmutable('now', new DateTimeZone(TIMEZONE)));

if (empty($now)) {
  $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
}

$diff_seconds = $now->getTimestamp() - $dt_update->getTimestamp();
$update_time  = function_exists('getFriendlyUpdateTimeFromDateTime')
  ? getFriendlyUpdateTimeFromDateTime($dt_update, $now)
  : '';
?>

<section class="location-bar">
  <div class="loc-labels">
    <span class="loc-name">
      <?= htmlspecialchars($location_label ?: 'Posizione non rilevata') ?>
      <?php if ($elev_label): ?>
        <span class="loc-elev"> - <?= $elev_label ?></span>
      <?php endif; ?>
    </span>
    <?php if (!empty($current_datetime)): ?>
      <small class="update-time" data-update="<?= htmlspecialchars($dt_update->format('c')) ?>">dati meteo <?= htmlspecialchars($dt_update->format('H:i')) ?></small>
    <?php else: ?>
      <small class="update-time text-muted">In attesa di posizione…</small>
    <?php endif; ?>
  </div>
  
  <div class="loc-actions">
    <a class="btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#loc-search" aria-controls="loc-search"><i class="bi bi-search"></i></a>
    <a id="loc-gps-btn" class="btn" title="Usa la mia posizione"><i class="bi bi-cursor"></i></a>
    <a id="loc-refresh-btn" class="btn" title="Aggiorna dati" aria-label="Aggiorna dati"><i class="bi bi-arrow-repeat"></i></a>
  </div>
</section>

<!-- Off-canvas -->
<section class="search-panel offcanvas offcanvas-top" tabindex="-1" id="loc-search" aria-labelledby="loc-search-label">
  <div class="offcanvas-header">
    <h5 id="loc-search-label">Cerca località</h5>
    <button type="button" class="btn" data-bs-dismiss="offcanvas" aria-label="Chiudi"><i class="bi bi-x-lg fs-3"></i></button>
  </div>
  <div class="offcanvas-body">
    <div class="input-group">
      <input id="loc-input" type="text" class="form-control" placeholder="Es. Venezia..." autocomplete="off">
    </div>
    <ul id="loc-results" class="list-group small"></ul>
  </div>
</section><!--offcanvas-->

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Bottone "aggiorna" nella location bar
  var refreshBtn = document.getElementById('loc-refresh-btn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function(e) {
      e.preventDefault();
      if (window.mostraLoader) window.mostraLoader();
      setTimeout(function() {
        location.reload();
      }, 100);
    });
  }
});
</script>
