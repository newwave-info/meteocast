<?php
$days_to_show = defined('FORECAST_DAYS_CAROUSEL') ? FORECAST_DAYS_CAROUSEL : 4;
$timezone = new DateTimeZone(TIMEZONE);
$start_index = 1;
$end_index = min($start_index + $days_to_show - 1, count($daily_weather_codes)-1);
?>

<div class="day-forecast-scroll d-flex flex-nowrap pb-2">
  <?php for ($index = $start_index; $index <= $end_index; $index++): ?>
    <?php
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
      $max_temp = isset($daily_max_temps[$index]) ? round($daily_max_temps[$index]) : '-';
      $min_temp = isset($daily_min_temps[$index]) ? round($daily_min_temps[$index]) : '-';
      $code = $daily_weather_codes[$index] ?? 0;
      $desc = getWeatherDescription($code);
      $weather_class = getWeatherClass($code, false);

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
      $accordionId = "dayForecastAccordion_$index";
    ?>
    <section class="widget day-forecast full">
      <!-- HEADER: accordion button -->
      <button class="widget-header btn-accordion d-flex w-100 justify-content-between align-items-center"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target=".day-forecast-scroll .day-forecast-collapse"
        aria-expanded="false"
        aria-controls="<?= $accordionId ?>">
        <span class="widget-title"><?= $giorno_settimana ?></span>
        <span>
          <i class="bi bi-chevron-down arrow-accordion"></i>
        </span>
      </button>

      <div class="widget-cont">
        <img src="<?= htmlspecialchars(getWeatherSvgIcon($code, false, true)) ?>" class="weather-svg-icon <?= htmlspecialchars($weather_class) ?>" alt="<?= htmlspecialchars($desc) ?>" loading="lazy" />
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

      <!-- -------- ACCORDION: RIGHE TRI-ORARIE --------- -->
      <div class="collapse day-forecast-collapse" id="<?= $accordionId ?>">
        <ul class="day-hourly-list">
          <?php
            $has_data = false;
            foreach ($timestamps ?? [] as $i => $ts) {
              $dt = new DateTimeImmutable($ts, $timezone);
              if ($dt->format('Y-m-d') !== $date_str) continue;
              $h = (int)$dt->format('H');
              if ($h < 1 || $h > 23) continue; // 01-23
              if ( ($h - 1) % 3 !== 0 ) continue; // 01,04,07...22

              $h_str  = $dt->format('H:i');
              $h_code = $hourly_weather_codes[$i] ?? 0;
              $h_desc = getWeatherDescription($h_code);
              $h_icon = getWeatherSvgIcon($h_code, false, true);
              $h_temp = isset($hourly_temperature[$i]) ? round($hourly_temperature[$i]) : '-';
              $h_wind = isset($hourly_wind_speed[$i]) ? round($hourly_wind_speed[$i]) : 0;
              $h_gust = isset($hourly_wind_gusts[$i]) ? round($hourly_wind_gusts[$i]) : 0;
              $h_prec = isset($hourly_precip[$i]) ? round($hourly_precip[$i], 1) : '0.0';

              $has_data = true;
          ?>
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
            }
          ?>
        </ul>
      </div>
      <!-- /ACCORDION -->
    </section>
  <?php endfor; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var accordions = document.querySelectorAll('.day-forecast-collapse');
  var accordionButtons = document.querySelectorAll('.btn-accordion');

  accordionButtons.forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var isOpen = accordions[0].classList.contains('show');
      // Toggle collapse for all
      accordions.forEach(function(el) {
        var bsCollapse = bootstrap.Collapse.getOrCreateInstance(el);
        if (isOpen) {
          bsCollapse.hide();
        } else {
          bsCollapse.show();
        }
      });
      // Aggiorna frecce su tutti i bottoni
      accordionButtons.forEach(function(b){
        if(isOpen){
          b.classList.remove('active');
        } else {
          b.classList.add('active');
        }
      });
    });
  });
});
</script>
