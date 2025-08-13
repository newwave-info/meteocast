<?php
// --- Dati Vento (già calcolati sopra) ---
$wind_label = isset($current_wind_speed) ? getWindLabel($current_wind_speed) : '-';
$wind_dir = isset($current_wind_direction) ? getWindDirection($current_wind_direction) : '-';

$wind_comment = match (true) {
  isset($current_wind_speed) && $current_wind_speed < 10 => 'Vento debole, condizioni tranquille.',
  isset($current_wind_speed) && $current_wind_speed < 20 => 'Vento moderato, condizioni gestibili.',
  isset($current_wind_speed) && $current_wind_speed < 35 => 'Vento sostenuto, attenzione in mare aperto.',
  isset($current_wind_speed) && $current_wind_speed >= 35 => 'Vento forte, navigazione sconsigliata.',
  default => 'Dati vento non disponibili.'
};

$gust_comment = match (true) {
  isset($current_wind_gusts) && $current_wind_gusts < 20 => 'Raffiche lievi.',
  isset($current_wind_gusts) && $current_wind_gusts < 35 => 'Raffiche moderate.',
  isset($current_wind_gusts) && $current_wind_gusts >= 35 => 'Raffiche intense, attenzione a improvvisi rinforzi.',
  default => 'Dati raffiche non disponibili.'
};

$tooltip_wind = htmlentities("
  <span>
  <strong>Vento attuale</strong><br>
  <small>$wind_comment<br>$gust_comment</small>
  </span>
  ");
  ?>

  <!-- WIDGET VENTO -->
  <section class="widget">
    
    <header class="widget-header">
      <span class="widget-title">Vento</span>
      <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true" title="<?= $tooltip_wind ?>"></i>
    </header>
    
    <div class="widget-cont">
      
      <div class="widget-value"><strong><?= isset($current_wind_speed, $current_wind_gusts) ? round($current_wind_speed) . ' / ' . round($current_wind_gusts) : '-' ?></strong> km/h</div>
      <div class="widget-delta"><strong><?= htmlspecialchars($wind_dir) ?></strong></div>

    </div><!--widget-cont-->
    
    <footer class="widget-footer">
      <div class="data-row">
        <span class="widget-text">Tipo</span>
        <span class="widget-value"><?= htmlspecialchars($wind_label) ?></span>
      </div>

      <div class="data-row">
        <span class="widget-text">Tipo</span>
        <span class="widget-value"><?= htmlspecialchars($wind_label) ?></span>
      </div>
    </footer>

  </section><!--widget-->

