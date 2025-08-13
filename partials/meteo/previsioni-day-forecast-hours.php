<?php
/** -------------------------------------------------------------------------
 *  partials/previsioni-day-forecast.php
 *  Lista accordion dei giorni successivi (a partire da +2) con dettaglio
 *  tri‑orario (01 → 22) e header max/min.
 *  - numero di giorni mostrati = FORECAST_DAYS_CAROUSEL (config.php)
 *  - orari inclusi: 01, 04, 07, …, 22  (step 3 h, fine 23 escluso)
 *  - etichetta giorno su due righe: "Venerdì 11<br>Luglio"
 * ------------------------------------------------------------------------- */

$start_index = 2;   // +2 = dopodomani
$max_days    = FORECAST_DAYS_CAROUSEL ?? 8;               // fallback 8 se non definita
$end_index   = min($start_index + $max_days - 1, count($daily_weather_codes) - 1);
$tz          = new DateTimeZone(TIMEZONE);

/*  ------- formatter IT per day / month -------- */
$fmtDay   = new IntlDateFormatter('it_IT', IntlDateFormatter::FULL,  IntlDateFormatter::NONE, TIMEZONE, IntlDateFormatter::GREGORIAN, 'EEEE');
$fmtMonth = new IntlDateFormatter('it_IT', IntlDateFormatter::FULL,  IntlDateFormatter::NONE, TIMEZONE, IntlDateFormatter::GREGORIAN, 'MMMM');
?>

<div class="day-forecast-accordion-list">
<?php for ($index = $start_index; $index <= $end_index; $index++): ?>
  <?php
    $accordionId = "dayAccordion_$index";

    /* ---------- data e label ---------- */
    $date = isset($daily_sunrise_times[$index])
      ? new DateTimeImmutable($daily_sunrise_times[$index], $tz)
      : new DateTimeImmutable("+$index day", $tz);
    
      $fmtDay = new IntlDateFormatter(
        'it_IT',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        TIMEZONE,
        IntlDateFormatter::GREGORIAN,
    'EEEE' // Solo giorno della settimana
  );

      $fmtMonth = new IntlDateFormatter(
        'it_IT',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        TIMEZONE,
        IntlDateFormatter::GREGORIAN,
    'MMMM' // Solo mese in lettere
  );

      $day_label = ucfirst($fmtDay->format($date)) . '<br>' . $date->format('j') . ' ' . strtolower($fmtMonth->format($date));


    $date_str  = $date->format('Y-m-d');

    /* ---------- dati daily ---------- */
    $code         = $daily_weather_codes[$index] ?? 0;
    $desc         = getWeatherDescription($code);
    $weather_cls  = getWeatherClass($code, false);
    $max_temp     = isset($daily_max_temps[$index]) ? round($daily_max_temps[$index]) : '-';
    $min_temp     = isset($daily_min_temps[$index]) ? round($daily_min_temps[$index]) : '-';
  ?>

  <section class="widget widget-riga">
    <button class="widget-header btn-accordion" type="button"
            data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>"
            aria-expanded="false" aria-controls="<?= $accordionId ?>">

      <div class="widget-cont">
        <div class="d-flex align-items-center">
          <img src="<?= htmlspecialchars(getWeatherSvgIcon($code,false,true)) ?>"
               class="weather-svg-icon <?= htmlspecialchars($weather_cls) ?>"
               alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
          <span class="widget-title"><?= $day_label ?></span>
        </div>
      </div>

      <div class="widget-cont">
        <span class="widget-data-preview"><strong><?= htmlspecialchars($desc) ?></strong><br><?= $max_temp ?>° / <?= $min_temp ?>°</span>
        <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
      </div>
    </button>

    <!-- ---------- DETTAGLIO TRI‑ORARIO (01 → 22) ---------- -->
    <div class="collapse" id="<?= $accordionId ?>">
      <ul class="day-hourly-list">
        <?php
        $has_data = false;
        foreach ($timestamps as $i => $ts) {
            $dt = new DateTimeImmutable($ts,$tz);
            if ($dt->format('Y-m-d') !== $date_str) continue;
            $h  = (int)$dt->format('H');
            if ($h < 1 || $h > 23)      continue;          // 01‑23
            if ( ($h - 1) % 3 !== 0 )   continue;          // 01,04,07,…22

            /* --- dati orari --- */
            $h_str  = $dt->format('H:i');
            $h_code = $hourly_weather_codes[$i] ?? 0;
            $h_desc = getWeatherDescription($h_code);
            $h_icon = getWeatherSvgIcon($h_code,false,true);
            $h_temp = isset($hourly_temperature[$i]) ? round($hourly_temperature[$i]) : '-';
            $h_wind = isset($hourly_wind_speed[$i]) ? round($hourly_wind_speed[$i]) : 0;
            $h_gust = isset($hourly_wind_gusts[$i]) ? round($hourly_wind_gusts[$i]) : 0;
            $h_prec = isset($hourly_precip[$i]) ? round($hourly_precip[$i],1) : '0.0';

            $has_data = true; ?>

            <li class="day-hourly-row">
              <div class="list-container">
                <img src="<?= htmlspecialchars($h_icon) ?>" class="weather-svg-icon-xs" alt="<?= htmlspecialchars($h_desc) ?>" />
                <span class="hour-desc"><?= $h_str ?> - <?= $h_temp ?>°<strong><?= htmlspecialchars($h_desc) ?></strong></span>
              </div>
              <div class="list-container bottom">
                <div class="windbar-box">
                  <span class="windbar-label"><?= $h_wind ?> / <?= $h_gust ?> km/h</span>
                  <span class="windbar-bar" style="background:<?= windGradientBar($h_wind,$h_gust) ?>;"></span>
                </div>
                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-droplet"></i> <?= $h_prec ?> mm</span>
              </div>
            </li>
        <?php }
        if (!$has_data) {
            echo '<li class="list-group-item text-muted">Nessun dato disponibile per questa giornata.</li>';
        } ?>
      </ul>
    </div>
  </section><!-- /widget -->
<?php endfor; ?>
</div>
