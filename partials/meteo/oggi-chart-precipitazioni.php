<?php
$precipToday = array_slice($hourly_precip ?? [], $now_index ?? 0, 24);
$probToday   = array_slice($hourly_precip_prob ?? [], $now_index ?? 0, 24);

$precipNums = array_filter($precipToday, 'is_numeric');
$probNums   = array_filter($probToday, 'is_numeric');

$maxPrecip = count($precipNums) ? max($precipNums) : 0;
$maxProb   = count($probNums)   ? max($probNums)   : 0;

//$hasRainToday = ($maxPrecip > 0.1 || $maxProb >= 10);
?>

<?php // if ($hasRainToday): ?>

    
    <div class="widget-cont">
      <!-- Canvas Chart.js -->
      <div class="chart-container">
        <canvas id="chartRain" data-chart="rain" data-range="today" data-days="1" data-zoom="1" data-lock="false"></canvas>
      </div>

    </div><!--widget-cont-->
    
  <?php // endif; ?>