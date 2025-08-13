<?php
// Controllo presenza vento significativo nelle prossime 24h
$windToday = array_slice($hourly_wind_speed ?? [], $now_index ?? 0, 24);
$gustsToday = array_slice($hourly_wind_gusts ?? [], $now_index ?? 0, 24);

$hasWindToday = false;
if ($windToday && $gustsToday) {
    // Rimuovi valori null e fai check su array non vuoto per evitare warning su max()
    $maxWind = $windToday ? max(array_filter($windToday, 'is_numeric')) : 0;
    $maxGust = $gustsToday ? max(array_filter($gustsToday, 'is_numeric')) : 0;
    $hasWindToday = ($maxWind > 5 || $maxGust > 15);
}
?>

<?php if ($hasWindToday): ?>
    
    <div class="widget-cont">
      <!-- Canvas Chart.js -->
      <div class="chart-container">
        <canvas id="chartWind" data-chart="wind" data-range="today" data-days="1" data-zoom="1" data-lock="false"></canvas>
      </div>

    </div><!--widget-cont-->
    
  <?php endif; ?>