<?php
require_once ROOT_PATH . '/includes/suncalc.php';

$lat = LATITUDE;
$lon = LONGITUDE;
$tz  = TIMEZONE;

if (!$lat || !$lon || !$tz) {
    echo '<div class="alert alert-warning text-center my-3">Dati lunari non disponibili (verifica la posizione o riprova).</div>';
    return;
}

$i = $i ?? 0; // di default oggi
$date = new DateTimeImmutable('now', new DateTimeZone($tz));

/**
 * --- 1. FASE LUNARE (PHP accurate) ---
 */
$phase = getMoonPhaseAccurate($date);
$moon_times = moonPhaseOM($phase);
$moon_name = $moon_times['name'];
$moon_svg = $moon_times['icon'];

/**
 * --- SunCalc: illum, moonrise, moonset ---
 */
$suncalc = new \AurorasLive\SunCalc($date, $lat, $lon);
$illumData = $suncalc->getMoonIllumination();
$illum = round($illumData['fraction'] * 100);

$moonTimes = $suncalc->getMoonTimes();
$moonrise = isset($moonTimes['moonrise']) && $moonTimes['moonrise'] instanceof DateTimeInterface
    ? $moonTimes['moonrise']->setTimezone(new DateTimeZone($tz))->format('H:i')
    : '—';
$moonset = isset($moonTimes['moonset']) && $moonTimes['moonset'] instanceof DateTimeInterface
    ? $moonTimes['moonset']->setTimezone(new DateTimeZone($tz))->format('H:i')
    : '—';

/**
 * --- 2. Distanza terra-luna (PHP) ---
 */
$distance = getMoonDistance($date);
$is_supermoon = ($distance && $distance < 358000);


$next_full_moon = getNextMoonPhase($date, 0.5);
$next_new_moon = getNextMoonPhase($date, 0.0);
$next_first_quarter = getNextMoonPhase($date, 0.25);
$next_last_quarter = getNextMoonPhase($date, 0.75);

$full_str = $next_full_moon ? $next_full_moon->format('d/m/Y') : '—';
$new_str = $next_new_moon ? $next_new_moon->format('d/m/Y') : '—';
$first_str = $next_first_quarter ? $next_first_quarter->format('d/m/Y') : '—';
$last_str = $next_last_quarter ? $next_last_quarter->format('d/m/Y') : '—';

?>



<section class="current-weather today">
  <div class="main-data">
    <img src="/assets/icons/svg/<?= $moon_svg ?>" class="weather-svg-icon moon-phase-svg" alt="<?= htmlspecialchars($moon_name) ?>" loading="lazy" />
    
    <div class="current-data">
      <span class="now-temp">C</span>
      <span class="now-desc"><?= htmlspecialchars($moon_name) ?></span>
      <?php if ($is_supermoon): ?>
      <div class="badge bg-info text-dark pulse">Superluna</div>
      <?php endif; ?>
      <span class="now-feels">Illuminazione <?= $illum ?>%</span>
    </div>

  </div>

  <div class="now-maxmin">

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-up-short"></i></span>
      <span class="temp">A</span>
    </div>

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-down-short"></i></span>
      <span class="temp">B</span>
    </div>

  </div>

</section>







<section class="widget day-forecast">
  <header class="widget-header">
    <span class="widget-title">Fase Lunare</span>
    <i class="bi bi-info-circle widget-action" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Prossime fasi lunari</strong><br><small>Luna piena: <?= $full_str ?><br>Ultimo quarto: <?= $last_str ?><br>Luna nuova: <?= $new_str ?><br>Primo quarto: <?= $first_str ?></small>"></i>

  </header>

  <div class="widget-cont">
    <img src="/assets/icons/svg/<?= $moon_svg ?>" class="weather-svg-icon moon-phase-svg" alt="<?= htmlspecialchars($moon_name) ?>" loading="lazy" />
    <div>
    <div class="fw-bold"><?= htmlspecialchars($moon_name) ?></div>
    <?php if ($is_supermoon): ?>
      <div class="badge bg-info text-dark pulse">Superluna</div>
    <?php endif; ?>
  </div>
  </div>
  
  <footer class="widget-footer">
    <div class="data-row">
      <span class="widget-text">Sorge</span>
      <span class="widget-value"><?= htmlspecialchars($moonrise) ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Tramonta</span>
      <span class="widget-value"><?= htmlspecialchars($moonset) ?></span>
    </div>
    <div class="data-row">
      <span class="widget-text">Illuminazione</span>
      <span class="widget-value"><?= $illum ?>%</span>
    </div>
  </footer>
</section>