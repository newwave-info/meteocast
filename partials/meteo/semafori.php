<?php
/**
 * ---------------------------------------------------------------------------
 *  partial: semafori.php  (Safety / Comfort)
 * ---------------------------------------------------------------------------
 *  Richiamato da:
 *    • oggi-meteo-alert.php    → $context = 'oggi'
 *    • previsioni-meteo-alert.php → $context = 'previsioni'
 *
 *  Parametri riconosciuti (da settare PRIMA dell’include):
 *    $context   = 'oggi' | 'previsioni'
 *    $triStep   = 3  (solo per 'previsioni'; default 3)
 * ---------------------------------------------------------------------------
 */

$tz   = new DateTimeZone(TIMEZONE);
$now  = new DateTimeImmutable('now', $tz);
$triStep = $triStep ?? 3;

/* ---------- 1. Indici orari della finestra ---------- */
if ($context === 'oggi') {

    /* finestra dinamica (default_hours_today) passo 1 h */
    $indices = getForecastWindowIndices(
        $timestamps, $now,
        hoursAhead: DEFAULT_HOURS_TODAY,
        stepHours: 1
    );

} else { // 'previsioni'

    $targetDt = (new DateTimeImmutable('tomorrow', $tz))->setTime(0,0);
    $start    = (clone $targetDt)->setTime(ALERT_HOURS_FUTURE_START, 0);
    $end      = (clone $targetDt)->setTime(ALERT_HOURS_FUTURE_END,   0);

    $indices  = getForecastWindowIndices(
        $timestamps, $now,
        hoursAhead: null,
        start: $start,
        end:   $end,
        stepHours: $triStep
    );
}

/* Nessun dato → avviso e ritorno */
if (!$indices) {
    echo '<div class="alert alert-warning my-2">Dati non disponibili per i semafori.</div>';
    return;
}

/* ---------- 2. Livelli semaforo ---------- */
$levels = getTrafficLightLevels(
    array_map(fn($i)=>$hourly_temperature[$i]          ?? null, $indices),
    array_map(fn($i)=>$hourly_apparent_temperature[$i] ?? null, $indices),
    array_map(fn($i)=>$hourly_humidity[$i]             ?? null, $indices),
    array_map(fn($i)=>$hourly_wind_speed[$i]           ?? null, $indices),
    array_map(fn($i)=>$hourly_wind_gusts[$i]           ?? null, $indices),
    array_map(fn($i)=>$hourly_precip[$i]               ?? null, $indices),
    array_map(fn($i)=>$hourly_visibility[$i]           ?? null, $indices),
    array_map(fn($i)=>$hourly_uv_index[$i]             ?? null, $indices),
    array_map(fn($i)=>$hourly_dew_point[$i]            ?? null, $indices),
    $current_pressure ?? $pressure ?? 1015,
    count($indices)
);

$safety          = $levels['safety_levels']   ?? [];
$comfort         = $levels['comfort_levels']  ?? [];
$safety_reasons  = $levels['safety_reasons']  ?? [];
$comfort_reasons = $levels['comfort_reasons'] ?? [];

/* ---------- 3. HTML identico per entrambe le viste ---------- */ ?>
<div class="widget widget-semafori">
  <div class="d-flex w-100">

    <!-- Colonna etichette -->
    <div class="colonna-etichette d-flex flex-column justify-content-between align-items-start me-3 flex-grow-1">
      <div class="widget-title"
           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"
           title="<?= htmlentities("<strong>Sicurezza</strong><br><small>Calcolata da vento, raffiche, pioggia, visibilità e pressione.</small>") ?>">
        <i class="bi bi-exclamation-square me-2"></i><span class="widget-label">Sicurezza</span>
      </div>
      <div class="widget-title"
           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true"
           title="<?= htmlentities("<strong>Comfort</strong><br><small>Infuenza di temperatura, umidità, vento, UV e precipitazioni.</small>") ?>">
        <i class="bi bi-heart me-2"></i><span class="widget-label">Comfort</span>
      </div>
    </div>

    <!-- Colonna semafori -->
    <div class="semaforo-scroll d-flex flex-column justify-content-between flex-grow-1">
      <?php foreach (['safety'=>$safety,'comfort'=>$comfort] as $type=>$dot_levels): ?>
        <div class="d-flex gap-3">
          <?php foreach ($dot_levels as $k=>$class):
              $reasons   = $type==='safety' ? ($safety_reasons[$k]  ?? []) : ($comfort_reasons[$k] ?? []);
              $hasAlert  = in_array($class,['dot-yellow-light','dot-yellow-dark','dot-red','dot-black']);
              $colorCss  = ['dot-green'=>'icon-green','dot-yellow-light'=>'icon-yellow-light','dot-yellow-dark'=>'icon-yellow-dark','dot-red'=>'icon-red','dot-black'=>'icon-black'][$class]??'text-muted';
              $iconType  = $reasons ? 'bi-info-circle-fill' : ($hasAlert ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill');
              $iconHtml  = "<i class='bi me-1 $iconType $colorCss'></i>";

              // tooltip
              if ($reasons) {
                  $intro   = $type==='safety' ? "<strong>Condizioni potenzialmente pericolose.</strong>" : "<strong>Disagio percepito.</strong>";
                  $tooltip = $iconHtml.' '.$intro.'<br><small>'.implode('<br>',array_map('htmlentities',$reasons)).'</small>';
              } elseif ($hasAlert) {
                  $tooltip = $iconHtml.' '.($type==='safety'
                      ? "<strong>Combinazione critica.</strong>"
                      : "<strong>Parziale disagio.</strong>");
              } else {
                  $tooltip = $iconHtml.' '.($type==='safety'
                      ? "<strong>Nessuna criticità.</strong>"
                      : "<strong>Nessun disagio.</strong>");
              }

              $hour = isset($timestamps[$indices[$k]])
                  ? (new DateTimeImmutable($timestamps[$indices[$k]],$tz))->format('H:i')
                  : '--:--'; ?>
              <div class="semaforo-item d-flex align-items-center"
                   data-bs-toggle="tooltip" data-bs-html="true" title="<?= $tooltip ?>">
                <span class="dot <?= $class ?>"></span>
                <span class="semaforo-hour"><?= $hour ?></span>
              </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>