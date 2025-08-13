<?php
$forecast_days = defined('FORECAST_DAYS_CAROUSEL') ? CHART_DAYS_FORECAST : 3; // Dal config

?>

    <div class="widget-cont">
      <!-- Canvas Chart.js -->
      <div class="chart-container">
        <canvas id="chartTemp" data-chart="temp" data-range="forecast" data-days="<?= $forecast_days ?>" data-zoom="0.3" data-lock="false"></canvas>
      </div>
    </div>
