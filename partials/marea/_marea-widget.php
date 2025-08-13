<?php
require_once ROOT_PATH . '/includes/api-tide.php';

if ($sea_level === '—' && $sea_temp === '—' && $wave_height === '—') {
    echo '<div class="alert alert-warning text-center my-3">Dati marini non disponibili per la posizione selezionata.</div>';
    return;
}
?>

<section class="widget day-forecast">
  <header class="widget-header">
    <span class="widget-title">Condizioni Marine Attuali</span>
    <i class="bi bi-info-circle widget-action"
       data-bs-toggle="tooltip"
       data-bs-html="true"
       title="
        Livello mare: <strong><?= is_numeric($sea_level) ? round($sea_level*100) . ' cm' : '—' ?></strong><br>
        Temperatura acqua: <strong><?= is_numeric($sea_temp) ? round($sea_temp, 1) . ' °C' : '—' ?></strong><br>
        Onda: <strong><?= is_numeric($wave_height) ? round($wave_height*100) . ' cm' : '—' ?></strong>
        (dir <?= is_numeric($wave_dir) ? round($wave_dir) : '—' ?>°, periodo <?= is_numeric($wave_period) ? round($wave_period, 1) : '—' ?>s)
       ">
    </i>
  </header>

  <div class="widget-cont">
    <img src="/assets/icons/svg/tide-now.svg"
         class="weather-svg-icon moon-phase-svg"
         alt="Marea/Onda"
         loading="lazy" />
    <div>
      <div class="fw-bold">Livello mare: <?= is_numeric($sea_level) ? round($sea_level*100) . ' cm' : '—' ?></div>
      <div class="small">Acqua: <?= is_numeric($sea_temp) ? round($sea_temp, 1) . ' °C' : '—' ?></div>
      <div class="small">
        Onda: <?= is_numeric($wave_height) ? round($wave_height*100) . ' cm' : '—' ?>
        (<?= is_numeric($wave_dir) ? round($wave_dir) : '—' ?>°/
         <?= is_numeric($wave_period) ? round($wave_period, 1) : '—' ?>s)
      </div>
    </div>
  </div>
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Livello mare</span>
      <span class="widget-value"><?= is_numeric($sea_level) ? round($sea_level*100) . ' cm' : '—' ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Temperatura acqua</span>
      <span class="widget-value"><?= is_numeric($sea_temp) ? round($sea_temp, 1) . ' °C' : '—' ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Onda (altezza)</span>
      <span class="widget-value"><?= is_numeric($wave_height) ? round($wave_height*100) . ' cm' : '—' ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Onda (direzione)</span>
      <span class="widget-value"><?= is_numeric($wave_dir) ? round($wave_dir) : '—' ?>°</span>
    </div>
    <div class="data-row">
      <span class="widget-text">Onda (periodo)</span>
      <span class="widget-value"><?= is_numeric($wave_period) ? round($wave_period, 1) : '—' ?> s</span>
    </div>
  </footer>
</section>