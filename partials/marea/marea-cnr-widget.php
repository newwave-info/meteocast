<?php
// CNR ISMAR: Widget Estremi di Marea + Flusso calcolato + Footer con estremali storici e futuri

$tide_extremes = $GLOBALS['tide_cnr'] ?? null;

if (
    !is_array($tide_extremes)
    || empty($tide_extremes['all'])
) {
    echo '<div class="alert alert-warning">Estremi di marea non disponibili.</div>';
    return;
}

$station_name = 'CNR ISMAR';
$extremes_all = $tide_extremes['all'];

// Trova prossimo estremale futuro e ultimo passato
$now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
$next_extremal = null;
$prev_extremal = null;
foreach ($extremes_all as $row) {
    $dataField   = $row['DATA']    ?? $row['data']    ?? null;
    $valField    = $row['VALORE']  ?? $row['valore']  ?? null;
    $minmaxField = $row['minmax']  ?? null;
    if (!$dataField || !$valField || !$minmaxField) continue;
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dataField, new DateTimeZone('CET'));
    if ($dt) $dt = $dt->setTimezone(new DateTimeZone(TIMEZONE));
    else continue;

    if ($dt > $now && !$next_extremal) {
        $next_extremal = [
            'type' => strtolower($minmaxField),
            'val'  => round($valField),
            'time' => $dt->format('d/m H:i')
        ];
    }
    if ($dt <= $now) {
        $prev_extremal = [
            'type' => strtolower($minmaxField),
            'val'  => round($valField),
            'time' => $dt->format('d/m H:i')
        ];
    }
}

// Per mostrare in alto anche prossimo max/min
$next_max = $tide_extremes['next_max'];
$next_min = $tide_extremes['next_min'];

// Calcolo flusso/trend
$flow_text = '---';
$flow_icon = '';
$trend = null;
if ($prev_extremal && $next_extremal) {
    if ($prev_extremal['type'] === 'min' && $next_extremal['type'] === 'max') {
        $flow_text = 'Crescente'; $flow_icon = '▲'; $trend = 'salendo';
    } elseif ($prev_extremal['type'] === 'max' && $next_extremal['type'] === 'min') {
        $flow_text = 'Calante'; $flow_icon = '▼'; $trend = 'scendendo';
    }
}

// Scegli icona dinamica
$icon_svg = '/assets/icons/svg/tide-high.svg';
$icon_alt = 'Alta marea';
if ($trend === 'scendendo') {
    $icon_svg = '/assets/icons/svg/tide-low.svg';
    $icon_alt = 'Bassa marea';
}
?>

<section class="widget day-forecast">
  <header class="widget-header">
    <span class="widget-title">Marea – <?= htmlspecialchars($station_name) ?></span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true"
       title="
       Prossimo massimo: <?= $next_max['val'] ?> cm alle <?= $next_max['time'] ?><br>
       Prossimo minimo: <?= $next_min['val'] ?> cm alle <?= $next_min['time'] ?><br>
       Flusso di marea: <?= $flow_text ?> <?= $flow_icon ?><br>
       Trend attuale: la marea sta <b><?= $trend ? $trend : '---' ?></b><br>
       <small>Punto di misura: <?= htmlspecialchars($station_name) ?></small>"></i>
  </header>

  <div class="widget-cont d-flex align-items-center gap-2">
    <img src="<?= $icon_svg ?>" class="weather-svg-icon" alt="<?= $icon_alt ?>" loading="lazy" />
    <div>
      <div class="fw-bold">Prossima alta: <?= $next_max['val'] ?> cm alle <?= $next_max['time'] ?></div>
      <div class="fw-bold">Prossima bassa: <?= $next_min['val'] ?> cm alle <?= $next_min['time'] ?></div>
      <div class="small">
        Flusso di marea: <b><?= $flow_text ?> <?= $flow_icon ?></b>
      </div>
      <div class="small text-success">
        Trend: la marea sta <b><?= $trend ? $trend : '---' ?></b>
      </div>
    </div>
  </div>

  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Prossima alta</span>
      <span class="widget-value"><?= $next_max['val'] ?> cm &middot; <?= $next_max['time'] ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Prossima bassa</span>
      <span class="widget-value"><?= $next_min['val'] ?> cm &middot; <?= $next_min['time'] ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Flusso</span>
      <span class="widget-value"><?= $flow_text ?> <?= $flow_icon ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Trend attuale</span>
      <span class="widget-value">La marea sta <b><?= $trend ? $trend : '---' ?></b></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Ultimo estremale passato</span>
      <span class="widget-value">
        <?= $prev_extremal
          ? ucfirst($prev_extremal['type']) . " ({$prev_extremal['val']} cm) &middot; {$prev_extremal['time']}"
          : '---'
        ?>
      </span>
    </div>
    <div class="data-row">
      <span class="widget-text">Prossimo estremale futuro</span>
      <span class="widget-value">
        <?= $next_extremal
          ? ucfirst($next_extremal['type']) . " ({$next_extremal['val']} cm) &middot; {$next_extremal['time']}"
          : '---'
        ?>
      </span>
    </div>
  </footer>
</section>
