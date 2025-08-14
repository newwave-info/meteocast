<?php
/**
 * partials/stazioni/stazioni.php - Vista Stazioni laguna (mappa sempre visibile)
 * - struttura a widget
 * - nessuno <script> inline
 * - dati stazioni in data-attribute
 * - passa anche lat/lon utente in data-attribute
 */

require_once ROOT_PATH . '/includes/stazioni-venezia-fetch.php';

$stazioni = $GLOBALS['stazioni_venezia_data'] ?? [];
$stazioniCount = (int) count($stazioni);

function formatStationTimestamp(?string $timestamp): string {
  if (empty($timestamp)) return 'N/D';
  $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $timestamp);
  if (!$dt) return 'N/D';
    return $dt->modify('+1 hour')->format('H:i'); // dataset UTC+1 fisso
  }
  function getSensorIcon(string $sensor_type): string {
    return match($sensor_type) {
      'marea'       => '🌊',
      'vento'       => '💨',
      'temp_aria'   => '🌡️',
      'temp_acqua'  => '💧',
      'onde_laguna' => '〰️',
      'onde_mare'   => '🌊',
      'umidita'     => '💦',
      'pressione'   => '📊',
      default       => '📡'
    };
  }
  function getSensorName(string $sensor_type): string {
    return match($sensor_type) {
      'marea'       => 'Livello Mare',
      'vento'       => 'Vento',
      'temp_aria'   => 'Temp. Aria',
      'temp_acqua'  => 'Temp. Acqua',
      'onde_laguna' => 'Onde Laguna',
      'onde_mare'   => 'Onde Mare',
      'umidita'     => 'Umidità',
      'pressione'   => 'Pressione',
      default       => ucfirst(str_replace('_', ' ', $sensor_type))
    };
  }
  function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

  $userLat = defined('LATITUDE')  ? LATITUDE  : null;
  $userLon = defined('LONGITUDE') ? LONGITUDE : null;
  $userLatAttr = $userLat !== null ? number_format((float)$userLat, 6, '.', '') : '';
  $userLonAttr = $userLon !== null ? number_format((float)$userLon, 6, '.', '') : '';
  ?>

<!-- ========== WIDGET MAPPA STAZIONI (sempre visibile) ========== -->
<section id="stazioni-map"
class="widget widget-riga mb-3"
data-stazioni='<?= esc(json_encode($stazioni, JSON_UNESCAPED_UNICODE)) ?>'
data-user-lat="<?= esc($userLatAttr) ?>"
data-user-lon="<?= esc($userLonAttr) ?>">

<div class="widget-header">
  <div class="widget-cont">
    <i class="bi bi-geo-alt-fill text-primary me-2"></i>
    <span class="widget-title">Mappa Stazioni Laguna</span>
    <small class="text-muted ms-2"><?= $stazioniCount ?> stazioni attive</small>
  </div>
  <div class="widget-cont"><!-- nessun bottone --></div>
</div>

<div class="widget-body p-0">
  <div id="map-container" class="leaflet-map-widget"></div>
</div>
</section>



<?php if (empty($stazioni)): ?>
  <div class="alert alert-warning">
    <i class="bi bi-wifi-off me-2"></i>
    Dati stazioni temporaneamente non disponibili. Riprova tra qualche minuto.
  </div>
<?php else: ?>

<!-- ========== LISTA STAZIONI (accordion, stile widget) ========== -->
<div class="day-forecast-accordion-list">
  <?php foreach ($stazioni as $stazione): ?>
    <?php
    $accordionId   = 'stazioneAccordion_' . esc($stazione['id'] ?? uniqid());
    $nome          = $stazione['nome'] ?? 'Stazione';
    $sensori       = is_array($stazione['sensori'] ?? null) ? $stazione['sensori'] : [];
    $sensori_count = count($sensori);
    $last_update   = formatStationTimestamp($stazione['ultimo_aggiornamento'] ?? null);
    ?>

    <section class="widget widget-riga">
      <button class="widget-header btn-accordion" type="button"
      data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>"
      aria-expanded="false" aria-controls="<?= $accordionId ?>">
      <div class="widget-cont">
        <div class="d-flex align-items-center">
          <i class="bi bi-broadcast-pin text-primary me-2" style="font-size: 1.5rem;"></i>
          <span class="widget-title"><?= esc($nome) ?></span>
        </div>
      </div>
      <div class="widget-cont">
        <span class="widget-data-preview">
          <strong><?= (int)$sensori_count ?> sensori</strong><br>
          <small class="text-muted"><?= esc($last_update) ?></small>
        </span>
        <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
      </div>
    </button>

    <div class="collapse" id="<?= $accordionId ?>">
      <?php if (empty($sensori)): ?>
        <div class="widget-body p-3">
          <div class="text-muted text-center py-3">
            <i class="bi bi-wifi-off"></i> Nessun dato disponibile per questa stazione
          </div>
        </div>
      <?php else: ?>
        <ul class="day-hourly-list">
          <?php foreach ($sensori as $sensor_type => $sensor_data): ?>
            <?php
            $sensor_ts = formatStationTimestamp($sensor_data['timestamp'] ?? null);
            $label     = getSensorName($sensor_type);
            $icon      = getSensorIcon($sensor_type);
            ?>
            <li class="day-hourly-row">
              <div class="list-container">
                <span class="sensor-icon me-2" style="font-size: 1.2em;"><?= $icon ?></span>
                <span class="hour-desc">
                  <strong><?= esc($label) ?></strong>
                  <small class="text-muted ms-2"><?= esc($sensor_ts) ?></small>
                </span>
              </div>
              <div class="list-container bottom">
                <div class="sensor-value-display">
                  <?php
                  switch ($sensor_type) {
                    case 'marea':
                    $val = $sensor_data['livello'] ?? null; $u = $sensor_data['unita'] ?? 'm';
                    echo '<span class="badge bg-info text-dark"><i class="bi bi-water"></i> '.esc($val).' '.esc($u).'</span>';
                    break;

                    case 'vento':
                    $int = isset($sensor_data['intensita']) ? (float)$sensor_data['intensita'] : null;
                    $gst = isset($sensor_data['raffica'])    ? (float)$sensor_data['raffica']    : null;
                    $dir = isset($sensor_data['direzione'])  ? (float)$sensor_data['direzione']  : null;
                    $dirTxt = (function_exists('getWindDirection') && $dir !== null) ? getWindDirection($dir) : '—';
                    $intTxt = ($int !== null) ? (int)round($int) : '—';
                    $gstTxt = ($gst !== null) ? (int)round($gst) : '—';
                    $pct = ($gst !== null) ? max(0, min(100, (int)round(($gst / 80) * 100))) : 0;
                    echo '<div class="windbar-box">';
                    echo '  <span class="windbar-label">'.$intTxt.' / '.$gstTxt.' km/h '.$dirTxt.'</span>';
                    echo '  <span class="windbar-bar" style="--w: '.$pct.'%;"></span>';
                    echo '</div>';
                    break;

                    case 'temp_aria':
                    $t = $sensor_data['temperatura'] ?? null; $u = $sensor_data['unita'] ?? '°C';
                    echo '<span class="badge bg-warning text-dark"><i class="bi bi-thermometer"></i> '.esc($t).esc($u).'</span>';
                    break;

                    case 'temp_acqua':
                    $t = $sensor_data['temperatura'] ?? null; $u = $sensor_data['unita'] ?? '°C';
                    echo '<span class="badge bg-primary"><i class="bi bi-droplet"></i> '.esc($t).esc($u).'</span>';
                    break;

                    case 'onde_laguna':
                    case 'onde_mare':
                    $sig = $sensor_data['significativa'] ?? null;
                    $max = $sensor_data['massima'] ?? null;
                    $u   = $sensor_data['unita'] ?? 'm';
                    echo '<span class="badge bg-secondary"><i class="bi bi-water"></i> '.esc($sig).' '.esc($u).' (sig.)</span>';
                    if ($max !== null) echo '<small class="text-muted ms-2">Max: '.esc($max).' '.esc($u).'</small>';
                    break;

                    case 'umidita':
                    $v = $sensor_data['valore'] ?? null; $u = $sensor_data['unita'] ?? '%';
                    echo '<span class="badge bg-light text-dark"><i class="bi bi-moisture"></i> '.esc($v).esc($u).'</span>';
                    break;

                    case 'pressione':
                    $v = $sensor_data['valore'] ?? null; $u = $sensor_data['unita'] ?? 'hPa';
                    echo '<span class="badge bg-success"><i class="bi bi-speedometer2"></i> '.esc($v).' '.esc($u).'</span>';
                    break;

                    default:
                    echo '<span class="badge bg-light text-dark">Dato disponibile</span>';
                    break;
                  }
                  ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>

          <li class="day-hourly-row border-top pt-2 mt-2">
            <div class="list-container">
              <i class="bi bi-info-circle text-muted me-2"></i>
              <span class="hour-desc text-muted small"><strong>Info Stazione</strong></span>
            </div>
            <div class="list-container bottom">
              <div class="station-info">
                <span class="badge bg-light text-dark">
                  <i class="bi bi-geo-alt"></i>
                  <?= esc(number_format((float)($stazione['latitudine'] ?? 0), 4)) ?>,
                  <?= esc(number_format((float)($stazione['longitudine'] ?? 0), 4)) ?>
                </span>
                <span class="badge bg-light text-dark ms-1">
                  <i class="bi bi-database"></i>
                  ID: <?= esc($stazione['id'] ?? '-') ?>
                </span>
              </div>
            </div>
          </li>
        </ul>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<?php endif; ?>