<?php
// Calcola l'indice dell'ora corrente nel dataset (se non già calcolato a monte)
if (!isset($now_index)) {
    $now_index = 0;
    if (isset($timestamps) && is_array($timestamps)) {
        foreach ($timestamps as $i => $ts) {
            $ts_dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $ts, new DateTimeZone(TIMEZONE));
            if ($ts_dt && isset($now) && $ts_dt > $now) {
                $now_index = max(0, $i - 1);
                break;
            }
        }
    }
}

// Estrai le prossime 24 ore di dati (con fallback su array vuoti)
$tempToday = array_slice($hourly_temperature ?? [], $now_index, 24);
$apparentToday = array_slice($hourly_apparent_temperature ?? [], $now_index, 24);

// Controlla che gli array abbiano almeno 2 valori numerici
$tempNums = array_filter($tempToday, 'is_numeric');
$apparentNums = array_filter($apparentToday, 'is_numeric');

// Mostra solo se c'è una variazione significativa
$hasTempToday = (
    (count($tempNums) > 1 && max($tempNums) - min($tempNums) > 3) ||
    (count($apparentNums) > 1 && max($apparentNums) - min($apparentNums) > 3)
);
?>

<?php if ($hasTempToday): ?>


    <div class="widget-cont">
      <div class="chart-container">
    <canvas id="chartTemp" data-chart="temp" data-range="today" data-days="1" data-zoom="1" data-lock="false"></canvas>
  </div>

    </div><!--widget-cont-->
    
  <?php endif; ?>
