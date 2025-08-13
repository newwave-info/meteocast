<?php
$sunrise_map = [];
$sunset_map = [];
if (!empty($daily_sunrise_times) && !empty($daily_sunset_times)) {
    foreach ($daily_sunrise_times as $i => $ts) {
        $date = (new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE)))->format('Y-m-d');
        $sunrise_map[$date] = new DateTimeImmutable($ts, new DateTimeZone(TIMEZONE));
        $sunset_map[$date] = isset($daily_sunset_times[$i])
            ? new DateTimeImmutable($daily_sunset_times[$i], new DateTimeZone(TIMEZONE))
            : null;
    }
}

$now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));

// Calcolo indice ora attuale
$now_hour = $now->format('Y-m-d\TH:00');
$now_index = 0;
if (!empty($timestamps)) {
    foreach ($timestamps as $i => $ts) {
        if ($ts >= $now_hour) {
            $now_index = ($ts === $now_hour || $i === 0) ? $i : $i - 1;
            break;
        }
    }
}

$desc         = isset($current_code) ? getWeatherDescription($current_code) : '-';
$icon_class   = isset($current_code) ? getWeatherIcon($current_code, $isNight) : '';
$weather_class= isset($current_code) ? getWeatherClass($current_code, $isNight) : '';

// Check temperature
$current_temp = isset($current_temp) ? $current_temp : '-';
$current_apparent_temp = isset($current_apparent_temp) ? $current_apparent_temp : '-';
$max_temp = isset($daily_max_temps[0]) ? $daily_max_temps[0] : '-';
$min_temp = isset($daily_min_temps[0]) ? $daily_min_temps[0] : '-';

?>


<section class="current-weather today">
  <div class="main-data">
    <img src="<?= htmlspecialchars(getWeatherSvgIcon($current_code, $isNight, true)) ?>" class="weather-svg-icon <?= htmlspecialchars($weather_class) ?>" alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
    
    <div class="current-data">
      <span class="now-temp"><?= is_numeric($current_temp) ? round($current_temp) . "°" : "-" ?></span>
      <span class="now-desc"><?= htmlspecialchars($desc) ?></span>
      <span class="now-feels">Percepita <?= is_numeric($current_apparent_temp) ? round($current_apparent_temp) . "°" : "-" ?></span>
    </div>

  </div>

  <div class="now-maxmin">

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-up-short"></i></span>
      <span class="temp"><?= is_numeric($max_temp) ? round($max_temp) . "°" : "-" ?></span>
    </div>

    <div class="temp-value">
      <span class="arrow-icon"><i class="bi bi-arrow-down-short"></i></span>
      <span class="temp"><?= is_numeric($min_temp) ? round($min_temp) . "°" : "-" ?></span>
    </div>

  </div>

</section>