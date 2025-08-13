<?php
// == CHECK DATI ESSENZIALI ==
if (
    empty($daily_weather_codes) || !is_array($daily_weather_codes)
    || empty($timestamps) || !is_array($timestamps)
) {
    echo '<div class="alert alert-danger my-3">Dati previsioni non disponibili. Riprova più tardi.</div>';
    return;
}

$days_to_show = defined('FORECAST_DAYS_CAROUSEL') ? FORECAST_DAYS_CAROUSEL : 4;
$timezone = new DateTimeZone(TIMEZONE);
$start_index = 2;
$end_index = min($start_index + $days_to_show - 1, count($daily_weather_codes)-1);
?>

<div class="day-forecast-scroll">
  <?php for ($index = $start_index; $index <= $end_index; $index++): ?>
    <?php
  // --- DATA/ETICHETTA GIORNO ---
    $date = isset($daily_sunrise_times[$index]) ? new DateTimeImmutable($daily_sunrise_times[$index], $timezone) : (new DateTimeImmutable("+$index day", $timezone));
    $giorno_settimana = ucfirst(
      (new IntlDateFormatter(
        'it_IT',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        TIMEZONE,
        IntlDateFormatter::GREGORIAN,
        'EEEE d MMMM'
      ))->format($date)
    );

  // --- METEO GENERALE ---
    $max_temp = isset($daily_max_temps[$index]) ? round($daily_max_temps[$index]) : '-';
    $min_temp = isset($daily_min_temps[$index]) ? round($daily_min_temps[$index]) : '-';

    $code = $daily_weather_codes[$index] ?? 0;
    $desc = getWeatherDescription($code);
    $weather_class = getWeatherClass($code, false);

  // --- VENTO (media 8-20) ---
    $date_str = $date->format('Y-m-d');
    $wind_vals = $gust_vals = $dir_vals = [];
    foreach ($timestamps ?? [] as $i => $ts) {
      $dt = new DateTimeImmutable($ts, $timezone);
      $h = (int)$dt->format('H');
      if ($dt->format('Y-m-d') === $date_str && $h >= 8 && $h <= 20) {
        $wind_vals[] = $hourly_wind_speed[$i] ?? 0;
        $gust_vals[] = $hourly_wind_gusts[$i] ?? 0;
        $dir_vals[]  = $hourly_wind_direction[$i] ?? 0;
      }
    }
    $wind_speed = count($wind_vals) ? round(array_sum($wind_vals) / count($wind_vals)) : 0;
    $wind_gusts = count($gust_vals) ? round(max($gust_vals)) : 0;
    $wind_dir   = count($dir_vals) ? getWindDirection(round(array_sum($dir_vals) / count($dir_vals))) : '-';

  // --- PRECIPITAZIONI (totale e max probabilità) ---
    $precip_vals = $prob_vals = [];
    foreach ($timestamps ?? [] as $i => $ts) {
      $dt = new DateTimeImmutable($ts, $timezone);
      if ($dt->format('Y-m-d') === $date_str) {
        $precip_vals[] = $hourly_precip[$i] ?? 0;
        $prob_vals[]   = $hourly_precip_prob[$i] ?? 0;
      }
    }
    $precip_total = count($precip_vals) ? round(array_sum($precip_vals), 1) : 0;
    $precip_prob  = count($prob_vals) ? round(max($prob_vals)) : 0;

  // --- NARRATIVA SMART/COERENTE ---
    $narrative = getWeatherDescription($code);
    $extra = [];
    if ($precip_total >= 8) {
      $extra[] = "Attese precipitazioni abbondanti nell'arco della giornata";
    } elseif ($precip_total >= 3) {
      $extra[] = "Piogge frequenti durante il giorno";
    } elseif ($precip_total > 0 && $code < 80) {
      $extra[] = "Possibili piogge sparse";
    }
    if ($wind_gusts >= 45) {
      $extra[] = "Raffiche di vento intense previste";
    } elseif ($wind_speed >= 25) {
      $extra[] = "Vento sostenuto per gran parte della giornata";
    }
    if ($code === 0 && $precip_total < 1 && $wind_speed < 12) {
      $extra[] = "Condizioni stabili e cieli sereni";
    }
    if ($code === 3 && empty($extra)) {
      $extra[] = "Cielo grigio per tutta la giornata, ma generalmente asciutto";
    }
    if ($code === 2 && empty($extra)) {
      $extra[] = "Qualche schiarita possibile ma prevalenza di nubi";
    }
    if (in_array($code, [45, 48]) && empty($extra)) {
      $extra[] = "Attenzione a possibili nebbie o foschie soprattutto al mattino";
    }
    if ($extra) {
      $narrative .= ".<br><small>" . implode('. ', $extra) . ".</small>";
    }
    ?>
    <section class="widget day-forecast">
      <header class="widget-header">
        <span class="widget-title"><?= $giorno_settimana ?></span>
        <i class="bi bi-info-circle widget-action"
        data-bs-toggle="tooltip"
        data-bs-html="true"
        title="<?= htmlspecialchars($narrative) ?>"></i>
      </header>
      <div class="widget-cont">
        <img src="<?= htmlspecialchars(getWeatherSvgIcon($code, false, true)) ?>"
        class="weather-svg-icon <?= htmlspecialchars($weather_class) ?>"
        alt="<?= htmlspecialchars($desc) ?>"
        loading="lazy" />
        <span><?= htmlspecialchars($desc) ?></span>
      </div>
      <footer class="widget-footer">
        <div class="data-row">
          <span class="widget-text">Vento</span>
          <span class="widget-value"><?= $wind_speed ?> / <?= $wind_gusts ?> km/h <?= htmlspecialchars($wind_dir) ?></span>
        </div>
        <div class="data-row">
          <span class="widget-text">Precipitazioni</span>
          <span class="widget-value"><?= $precip_total ?> mm <?= $precip_prob ?>%</span>
        </div>
        <div class="data-row">
          <span class="widget-text">Temperatura</span>
          <span class="widget-value">
            <i class="bi bi-arrow-up-short"></i><?= is_numeric($max_temp) ? $max_temp . "°" : "-" ?>
            <i class="bi bi-arrow-down-short"></i><?= is_numeric($min_temp) ? $min_temp . "°" : "-" ?>
          </span>
        </div>
      </footer>
    </section>
  <?php endfor; ?>
</div>
