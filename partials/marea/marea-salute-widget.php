<?php
// Widget marea – robusto, “umano” e più fedele
// Mostra ultimo estremo passato, i prossimi 3, livello attuale stimato e velocità

$tide_data = $GLOBALS['tide_salute_forecast'] ?? [];
$tz       = new DateTimeZone(defined('TIMEZONE') ? TIMEZONE : 'Europe/Rome');
$now      = new DateTimeImmutable('now', $tz);

// --- Normalizzazione dati (supporta nuova/vecchia struttura) ---
if (isset($tide_data['data']) && is_array($tide_data['data'])) {
    $raw = $tide_data['data'];
    $last_update = $tide_data['last_update'] ?? null;
    $station_name = trim($tide_data['station'] ?? '') ?: 'Punta della Salute';
} else {
    $raw = is_array($tide_data) ? $tide_data : [];
    $last_update = null;
    $station_name = 'Punta della Salute';
}

// Sanity check
if (!$raw) {
    echo '<div class="alert alert-warning">Previsione marea non disponibile.</div>';
    return;
}

// Converte in array uniforme: ['time'=>Y-m-d H:i, 'val'=>int/float, 'type'=>min|max]
$tide_forecast = array_values(array_filter(array_map(function($r) use ($tz) {
    $time = $r['time'] ?? null;
    if (!$time) return null;

    // supporta 'Y-m-d H:i' e 'Y-m-d H:i:s'
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $time, $tz)
       ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $time, $tz);
    if (!$dt) return null;

    $val = isset($r['val']) ? (float)$r['val'] : null;
    $type = strtolower(trim($r['type'] ?? ''));
    if ($type !== 'min' && $type !== 'max') $type = null;

    return [
        'dt'   => $dt,
        'time' => $dt->format('Y-m-d H:i'),
        'val'  => $val,
        'type' => $type
    ];
}, $raw), function($r) {
    return $r && is_finite($r['val']);
}));

// Ordina per data crescente
usort($tide_forecast, fn($a,$b) => $a['dt'] <=> $b['dt']);

if (!$tide_forecast) {
    echo '<div class="alert alert-warning">Previsione marea non disponibile.</div>';
    return;
}

// --- Helpers “umani” ---
function rel_datetime_it(DateTimeImmutable $dt, DateTimeZone $tz): string {
    $today  = new DateTimeImmutable('today', $tz);
    $tom    = $today->modify('+1 day');
    $yest   = $today->modify('-1 day');

    if ($dt >= $today && $dt < $tom)     return 'Oggi alle '  . $dt->format('H:i');
    if ($dt >= $tom   && $dt < $tom->modify('+1 day')) return 'Domani alle ' . $dt->format('H:i');
    if ($dt >= $yest  && $dt < $today)   return 'Ieri alle '  . $dt->format('H:i');
    return $dt->format('d/m/Y H:i');
}
function rel_short_it(DateTimeImmutable $dt, DateTimeZone $tz): string {
    $today  = new DateTimeImmutable('today', $tz);
    $tom    = $today->modify('+1 day');
    $yest   = $today->modify('-1 day');
    if ($dt >= $today && $dt < $tom)     return 'Oggi alle '  . $dt->format('H:i');
    if ($dt >= $tom   && $dt < $tom->modify('+1 day')) return 'Domani alle ' . $dt->format('H:i');
    if ($dt >= $yest  && $dt < $today)   return 'Ieri alle '  . $dt->format('H:i');
    return $dt->format('d/m H:i');
}
function countdown_hm(DateTimeImmutable $from, DateTimeImmutable $to): string {
    $sec = max(0, $to->getTimestamp() - $from->getTimestamp());
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    if ($h && $m) return "tra {$h}h {$m}m";
    if ($h)       return "tra {$h}h";
    if ($m)       return "tra {$m}m";
    return "ora";
}

// --- Ultimo estremo passato + prossimi 3 ---
$prev_extremal = null;
$next_extremals = [];
foreach ($tide_forecast as $row) {
    if ($row['dt'] <= $now) {
        $prev_extremal = $row;
    } elseif (count($next_extremals) < 3) {
        $next_extremals[] = $row;
    }
}

// --- Livello attuale: interpolazione cosinusoidale tra due estremi ---
// più fedele dell'interpolazione lineare: y = y0 + (y1 - y0) * 0.5 * (1 - cos(pi * t))
$current_level = null;
$speed_cm_h    = null; // velocità istantanea
$speed_text    = '';
if ($prev_extremal && !empty($next_extremals)) {
    $dt0 = $prev_extremal['dt'];
    $dt1 = $next_extremals[0]['dt'];
    $v0  = $prev_extremal['val'];
    $v1  = $next_extremals[0]['val'];

    $T   = max(1, $dt1->getTimestamp() - $dt0->getTimestamp()); // sec
    $t   = min(max(0, $now->getTimestamp() - $dt0->getTimestamp()), $T);
    $p   = $t / $T; // 0..1

    // Cosine easing
    $e   = 0.5 * (1 - cos(M_PI * $p));
    $current_level = round($v0 + ($v1 - $v0) * $e);

    // Derivata (velocità): dy/dt = (v1-v0) * 0.5 * (pi/T) * sin(pi*p)
    $dy_dt = ($v1 - $v0) * 0.5 * (M_PI / $T) * sin(M_PI * $p); // cm/sec
    $speed_cm_h = round($dy_dt * 3600, 1); // cm/h

    $speed_text = number_format(abs($speed_cm_h), 1, ',', '') . ' cm/h'; // solo valore
}

// --- Flusso (crescente/calante) reale in base agli estremi adiacenti ---
$flow_text = '---';
$flow_icon = '';
if ($prev_extremal && !empty($next_extremals)) {
    $t0 = strtolower((string)$prev_extremal['type']);
    $t1 = strtolower((string)$next_extremals[0]['type']);
    if ($t0 === 'min' && $t1 === 'max') { $flow_text = 'Crescente'; $flow_icon = '▲'; }
    if ($t0 === 'max' && $t1 === 'min') { $flow_text = 'Calante';   $flow_icon = '▼'; }
}

// --- Icona in base al prossimo tipo ---
$icon_svg = '/assets/icons/svg/tide-high.svg';
$icon_alt = 'Alta marea';
if (!empty($next_extremals) && strtolower((string)$next_extremals[0]['type']) === 'min') {
    $icon_svg = '/assets/icons/svg/tide-low.svg';
    $icon_alt = 'Bassa marea';
}

// --- “Ultimo aggiornamento” in forma relativa ---
$update_text = '';
if (!empty($last_update)) {
    $update_dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $last_update, $tz)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $last_update, $tz);
    if ($update_dt) $update_text = rel_short_it($update_dt, $tz);
}
?>
<section class="widget day-forecast" aria-label="Widget marea">
  <header class="widget-header">
    <span class="widget-title">Marea – <?= htmlspecialchars($station_name, ENT_QUOTES, 'UTF-8') ?></span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true"
       title="
       <?php if ($current_level !== null): ?>
         Livello attuale stimato: <?= $current_level ?> cm<br>
         Velocità: <?= $speed_text ?><br>
       <?php endif; ?>
       <?php if (!empty($next_extremals)): 
              $n0 = $next_extremals[0];
              $eta = countdown_hm($now, $n0['dt']);
       ?>
         Prossimo estremale: <?= $n0['val'] ?> cm (<?= $n0['type'] ?>) &middot; <?= rel_datetime_it($n0['dt'], $tz) ?> (<?= $eta ?>)<br>
       <?php endif; ?>
       Flusso di marea: <?= $flow_text ?> <?= $flow_icon ?><br>
       Ultimo estremale passato:
       <?php if ($prev_extremal): ?>
         <?= ucfirst($prev_extremal['type']) ?> (<?= $prev_extremal['val'] ?> cm) &middot; <?= rel_datetime_it($prev_extremal['dt'], $tz) ?><br>
       <?php else: ?>
         ---
       <?php endif; ?>
       <?php if (!empty($next_extremals)): ?>
         Prossimi estremi:<br>
         <?php foreach ($next_extremals as $extremal): ?>
           <?= $extremal['val'] ?> cm (<?= $extremal['type'] ?>) &middot; <?= rel_datetime_it($extremal['dt'], $tz) ?><br>
         <?php endforeach; ?>
       <?php endif; ?>
       <small>Punto di misura: <?= htmlspecialchars($station_name, ENT_QUOTES, 'UTF-8') ?></small>
       <?php if ($update_text): ?>
         <br><small>Previsione: <?= $update_text ?></small>
       <?php endif; ?>"></i>
  </header>

  <div class="widget-cont d-flex align-items-center gap-2">
    <img src="<?= $icon_svg ?>" class="weather-svg-icon" alt="<?= $icon_alt ?>" loading="lazy" />
    <div class="flex-grow-1">
      <?php if ($current_level !== null): ?>
        <div class="fw-bold" aria-live="polite">
          Livello attuale: <b><?= $current_level ?> cm</b>
        </div>
      <?php endif; ?>
      <?php if (!empty($next_extremals)): 
            $n0 = $next_extremals[0];
      ?>
        <div class="fw-bold">
          <b><?= $flow_text ?> <?= $flow_icon ?></b>
          <?= $speed_text ? ' · ' . $speed_text : '' ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="chart-container">
    <canvas id="chartTide"
            data-chart="tide"
            data-range="custom"
            data-days="3"
            data-zoom="0.31"
            data-lock="false"
            aria-label="Grafico marea 3 giorni"></canvas>
  </div>
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Previsione</span>
      <span class="widget-value"><?= $update_text ?: '—' ?></span>
    </div>

    <?php if (!empty($next_extremals)): ?>
      <div class="data-row">
        <span class="widget-text">Prossimi estremi</span>
        <span class="widget-value">
          <?php foreach ($next_extremals as $n => $extremal): ?>
            <?= $n > 0 ? '<br>' : '' ?>
            <?= $extremal['val'] ?> cm (<?= $extremal['type'] ?>) &middot; <?= rel_short_it($extremal['dt'], $tz) ?>
          <?php endforeach; ?>
        </span>
      </div>
    <?php endif; ?>
  </footer>
</section>