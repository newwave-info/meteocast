<?php
/* --------------------------------------------------------------------------
 *  partials/previsioni-previsioni.php – Card "Domani" (index = 1)
 *  ├─ Header: dati daily
 *  ├─ Semafori Safety/Comfort (NO bollettino testuale)
 *  └─ Footer: elenco tri‑orario con wind‑bar e precipitazioni
 * -------------------------------------------------------------------------- */

$index = 1;                               // 1 = domani
$tz     = new DateTimeZone(TIMEZONE);

// == CHECK DATI ESSENZIALI ==
if (
    empty($timestamps) || !is_array($timestamps)
    || empty($daily_weather_codes) || !is_array($daily_weather_codes)
    || !isset($daily_weather_codes[$index])
) {
    echo '<div class="alert alert-danger my-3">Dati previsioni non disponibili. Riprova più tardi.</div>';
    return;
}

/* =============== HEADER (dati daily) =================== */
$date = isset($daily_sunrise_times[$index])
  ? new DateTimeImmutable($daily_sunrise_times[$index], $tz)
  : new DateTimeImmutable("+$index day", $tz);
$date_str = $date->format('Y-m-d');

$giorno_settimana = ucfirst(
  (new IntlDateFormatter(
      'it_IT', IntlDateFormatter::FULL, IntlDateFormatter::NONE,
      TIMEZONE, IntlDateFormatter::GREGORIAN, 'EEEE d MMMM'
  ))->format($date)
);

$code        = $daily_weather_codes[$index] ?? 0;
$desc        = getWeatherDescription($code);
$weather_cls = getWeatherClass($code, false);

$max_temp = $daily_max_temps[$index] ?? '-';
$min_temp = $daily_min_temps[$index] ?? '-';

/* --- Vento medio / raffiche max tra 08–20 --- */
$wind_vals = $gust_vals = $dir_vals = [];
foreach ($timestamps as $i => $ts) {
    $dt = new DateTimeImmutable($ts, $tz);
    $h  = (int)$dt->format('H');
    if ($dt->format('Y-m-d') === $date_str && $h >= 8 && $h <= 20) {
        $wind_vals[] = $hourly_wind_speed[$i]    ?? 0;
        $gust_vals[] = $hourly_wind_gusts[$i]    ?? 0;
        $dir_vals[]  = $hourly_wind_direction[$i]?? 0;
    }
}
$wind_speed = $wind_vals ? round(array_sum($wind_vals) / count($wind_vals)) : 0;
$wind_gusts = $gust_vals ? round(max($gust_vals)) : 0;
$wind_dir   = $dir_vals  ? getWindDirection(round(array_sum($dir_vals) / count($dir_vals))) : '-';

/* --- Precipitazioni totali del giorno --- */
$precip_vals = $prob_vals = [];
foreach ($timestamps as $i => $ts) {
    $dt = new DateTimeImmutable($ts, $tz);
    if ($dt->format('Y-m-d') === $date_str) {
        $precip_vals[] = $hourly_precip[$i]      ?? 0;
        $prob_vals[]   = $hourly_precip_prob[$i] ?? 0;
    }
}
$precip_total = $precip_vals ? round(array_sum($precip_vals), 1) : 0;
$precip_prob  = $prob_vals   ? round(max($prob_vals)) : 0;
?>

<section class="widget domani">
  <!-- ======= CARD HEADER ================================================= -->
  <div class="current-weather today">
    <div class="main-data">
      <img src="<?= htmlspecialchars(getWeatherSvgIcon($code, false, true)) ?>"
           class="weather-svg-icon <?= htmlspecialchars($weather_cls) ?>"
           alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
      <div class="current-data">
        <span class="now-day"><?= $giorno_settimana ?></span>
        <span class="now-desc"><?= htmlspecialchars($desc) ?></span>
        <span class="now-feels">Vento <?= $wind_speed ?> / <?= $wind_gusts ?> km/h <?= $wind_dir ?></span>
        <span class="now-feels">Precipitazioni <?= $precip_total ?> mm <?= $precip_prob ?>%</span>
      </div>
    </div>

    <div class="now-maxmin">
      <div class="temp-value">
        <span class="arrow-icon"><i class="bi bi-arrow-up-short"></i></span>
        <span class="temp"><?= is_numeric($max_temp) ? round($max_temp) . '°' : '-' ?></span>
      </div>
      <div class="temp-value">
        <span class="arrow-icon"><i class="bi bi-arrow-down-short"></i></span>
        <span class="temp"><?= is_numeric($min_temp) ? round($min_temp) . '°' : '-' ?></span>
      </div>
    </div>
  </div>

  <!-- ======= SEMAFORI SAFETY / COMFORT ================================== -->
  <?php
    $context = 'previsioni';  // usa finestra 07–22 tri-oraria
    $triStep = 2;             // tri‑orario
    include ROOT_PATH . '/partials/meteo/semafori.php';
  ?>

  <!-- ======= CARD FOOTER : elenco tri‑orario ============================ -->
  <footer class="widget-footer">
    <ul class="day-hourly-list">
      <?php
      $has_data = false;
      foreach ($timestamps as $i => $ts) {
          $dt = new DateTimeImmutable($ts, $tz);
          if ($dt->format('Y-m-d') !== $date_str) continue;
          if (((int)$dt->format('H')) % 2 !== 0) continue; // 00,03,06…

          $h_str  = $dt->format('H:i');
          $h_code = $hourly_weather_codes[$i] ?? 0;
          $h_desc = getWeatherDescription($h_code);
          $h_icon = getWeatherSvgIcon($h_code, false, true);
          $h_temp = isset($hourly_temperature[$i]) ? round($hourly_temperature[$i]) : '-';
          $h_wind = isset($hourly_wind_speed[$i]) ? round($hourly_wind_speed[$i]) : 0;
          $h_gust = isset($hourly_wind_gusts[$i]) ? round($hourly_wind_gusts[$i]) : 0;
          $h_prec = isset($hourly_precip[$i]) ? round($hourly_precip[$i], 1) : '0.0';

          $has_data = true; ?>

          <li class="day-hourly-row" data-hour="<?= $dt->format('H:i') ?>">
            <div class="list-container">
              <img src="<?= htmlspecialchars($h_icon) ?>" class="weather-svg-icon-xs" alt="<?= htmlspecialchars($h_desc) ?>" />
              <span class="hour-desc"><?= $h_str ?> - <?= $h_temp ?>°<strong><?= htmlspecialchars($h_desc) ?></strong></span>
            </div>

            <div class="list-container bottom">
              <div class="windbar-box">
                <span class="windbar-label"><?= $h_wind ?> / <?= $h_gust ?> km/h</span>
                <span class="windbar-bar" style="background:<?= windGradientBar($h_wind, $h_gust) ?>;"></span>
              </div>
              <span class="badge bg-primary-subtle text-primary"><i class="bi bi-droplet"></i> <?= $h_prec ?> mm</span>
            </div>
          </li>
      <?php }
      if (!$has_data) {
          echo '<li class="list-group-item text-muted">Nessun dato disponibile per questa giornata.</li>';
      } ?>
    </ul>
  </footer>
</section>