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
      <?php if ($is_supermoon): ?>
      <div class="badge bg-info text-dark pulse mb-2">Superluna</div>
      <?php endif; ?>
      <span class="now-desc"><?= htmlspecialchars($moon_name) ?></span>
      <span class="now-feels">Illuminazione <?= $illum ?>%</span>
    </div>

  </div>

  <div class="now-maxmin">

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-up-short"></i></span>
      <span class="temp">Sorge<br><?= htmlspecialchars($moonrise) ?></span>
    </div>

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-down-short"></i></span>
      <span class="temp">Tramonta<br><?= htmlspecialchars($moonset) ?></span>
    </div>

  </div>

</section>

