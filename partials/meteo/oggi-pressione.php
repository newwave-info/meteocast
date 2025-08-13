<?php
// Etichetta UV con fallback
$uv_value = $current_uv_index ?? 0;
$uv_label = match (true) {
  $uv_value < 3 => ['label' => 'Basso', 'class' => 'text-success'],
  $uv_value < 6 => ['label' => 'Moderato', 'class' => 'text-warning'],
  $uv_value < 8 => ['label' => 'Alto', 'class' => 'text-orange'],
  $uv_value < 11 => ['label' => 'Molto alto', 'class' => 'text-danger'],
  default => ['label' => 'Estremo', 'class' => 'text-danger fw-bold']
};

// Trend pressione
$pressure_now = $current_pressure ?? 1015;
$pressure_3h_ago = $hourly_pressure[$now_index - 3] ?? $pressure_now;
$pressure_trend = $pressure_now - $pressure_3h_ago;
$pressure_delta = round($pressure_trend, 1);
$delta_label = $pressure_delta > 0 ? '+ ' . $pressure_delta : (string) $pressure_delta;

$trend_icon = match (true) {
  $pressure_trend > 1  => 'bi-arrow-up',
  $pressure_trend < -1 => 'bi-arrow-down',
  default              => 'bi-arrow-right'
};

$pressure_time_3h_ago = isset($timestamps[$now_index - 3])
? new DateTime($timestamps[$now_index - 3], new DateTimeZone(TIMEZONE))
: new DateTime();
$time_label = $pressure_time_3h_ago->format('H:i');

$trend_label = match ($trend_icon) {
  'bi-arrow-up'    => "Pressione in aumento (dalle $time_label)",
  'bi-arrow-down'  => "Pressione in calo (dalle $time_label)",
  default          => "Pressione stabile (ultime 3h)"
};

$temp_value = $current_temp ?? 0;
$hum_value = $current_humidity ?? 0;
$dew_point = calculateDewPoint($temp_value, $hum_value);

// COMMENTO UNIFICATO comfort climatico
$humidity_dew_comment = getHumidityDewPointComment($temp_value, $hum_value, $dew_point);

$tooltip_pressure = htmlentities("
  <span>
  <strong>$trend_label</strong><br>
  <small>$humidity_dew_comment</small>
  </span>
  ");
?>

<!-- WIDGET PRESSIONE -->
<section class="widget">
  
  <header class="widget-header">
    <span class="widget-title">Pressione</span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true" title="<?= $tooltip_pressure ?>"></i>
  </header>
  
  <div class="widget-cont">
    <div class="widget-value"><strong><?= round($pressure_now) ?></strong> hPa</div>
    <div class="widget-delta"><strong><?= $delta_label ?> <i class="bi <?= $trend_icon ?>"></i></strong></div>
  </div><!--widget-cont-->
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Umidità</span>
      <span class="widget-value"><?= round($hum_value) ?>%</span>
    </div>
    <div class="data-row">
      <span class="widget-text">Punto di rugiada</span>
      <span class="widget-value"><?= round($dew_point, 1) ?>°</span>
    </div>
  </footer>

</section><!--widget-->
