<?php
/**
 * ---------------------------------------------------------------------------
 *  previsioni-meteo-alert.php – Bollettino + semafori per la giornata futura
 * ---------------------------------------------------------------------------
 *  • Stessa struttura HTML del widget “Oggi”.
 *  • Condivide la finestra oraria e i semafori via buildMeteoBox().
 * ---------------------------------------------------------------------------
 */

$tz          = new DateTimeZone(TIMEZONE);
$now         = new DateTimeImmutable('now', $tz);
$targetDate  = (new DateTimeImmutable('tomorrow', $tz))->format('Y-m-d');
$escludiPast = false;   // domani: non ci sono fasce “passate”

$meteoBox = buildMeteoBox($targetDate, $escludiPast);
// echo "<!-- meteoBox: "; var_dump($meteoBox); echo " -->";

if ($meteoBox && !empty($meteoBox['lines']) && isset($meteoBox['narrative'])):
    $accordionId = 'alertDetail_' . md5($targetDate . $meteoBox['type']);
?>
<div class="widget alert-widget <?= htmlspecialchars($meteoBox['type']) ?>">

  <?php
  $context = 'previsioni';
  $triStep = 3; // opz.
  include ROOT_PATH . '/partials/meteo/semafori.php'; ?>

  <!-- HEADER ALERT + ACCORDION BUTTON ----------------------------------- -->
  <button class="short-alert alert-accordion-btn d-flex align-items-start justify-content-between w-100"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#<?= $accordionId ?>"
          aria-expanded="false"
          aria-controls="<?= $accordionId ?>">
    <div class="d-flex align-items-start flex-grow-1 text-start">
      <i class="bi <?= htmlspecialchars($meteoBox['icon']) ?> alert-icon me-2"></i>
      <div>
        <?php
        $interval       = '';
        $alert_reasons  = [];
        foreach (($meteoBox['lines'] ?? []) as $line) {
            if (preg_match('/^\d{2}:\d{2}\s*-\s*\d{2}:\d{2}/', $line)) {
                $interval = $line;          // intervallo orario
            } else {
                $alert_reasons[] = $line;   // motivo vero e proprio
            }
        }
        $lines = composeAlertLines($alert_reasons, $meteoBox['type'] ?? 'ok', $interval);

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                echo '<div class="alert-line">'.$line.'</div>';     // header (HTML permesso)
            } else {
                echo '<div class="alert-line">'.htmlspecialchars($line).'</div>';
            }
        }
        ?>
      </div>
    </div>
    <i class="bi bi-chevron-down arrow-accordion ms-2"></i>
  </button>

  <!-- ACCORDION CONTENT -------------------------------------------------- -->
  <div class="collapse alert-scroll" id="<?= $accordionId ?>">
    <?php
    // narrative può avere paragrafi separati da \n\n
    foreach (preg_split('/\n{2,}/', $meteoBox['narrative']) as $p) {
        $p = trim($p);
        if ($p) echo '<p class="narrative-block">'.htmlentities($p).'</p>';
    }
    ?>
  </div>
</div>
<?php endif; ?>
