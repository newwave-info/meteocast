<?php
$tz = new DateTimeZone(TIMEZONE);
$now = new DateTimeImmutable('now', $tz);
$targetDate = $targetDate ?? $now->format('Y-m-d');
$escludiPassate = shouldExcludePastHours($targetDate, $tz);

$meteoBox = buildMeteoBox($targetDate, $escludiPassate);
// echo "<!-- meteoBox: "; var_dump($meteoBox); echo " -->";


if ($meteoBox && !empty($meteoBox['lines']) && isset($meteoBox['narrative'])):
  $accordionId = 'alertDetail_' . md5($targetDate . $meteoBox['type']);
?>
<div class="widget alert-widget <?= htmlspecialchars($meteoBox['type']) ?>">


  <?php
  $context = 'oggi';
  include ROOT_PATH . '/partials/meteo/semafori.php'; ?>

  <!-- HEADER ALERT + ACCORDION BUTTON - TUTTA LA RIGA CLICCABILE -->
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
      $interval = '';
      $alert_reasons = [];
      foreach (($meteoBox['lines'] ?? []) as $i => $line) {
    // Se la riga è un intervallo orario ("10:00 - 15:00" ecc.), usala come $interval
        if (preg_match('/^\d{2}:\d{2}\s*-\s*\d{2}:\d{2}/', $line)) {
          $interval = $line;
        } else {
          $alert_reasons[] = $line;
        }
      }
      $lines = composeAlertLines($alert_reasons, $meteoBox['type'] ?? 'ok', $interval);

      foreach ($lines as $i => $line) {
        if ($i === 0) {
        echo '<div class="alert-line">'.$line.'</div>'; // header (può contenere HTML)
      } else {
        echo '<div class="alert-line">'.htmlspecialchars($line).'</div>';
      }
    }
    ?>



  </div>
</div>
<i class="bi bi-chevron-down arrow-accordion ms-2"></i>
</button>

<!-- ACCORDION CONTENT -->
<div class="collapse alert-scroll" id="<?= $accordionId ?>">
  <?php
    // narrative può avere paragrafi separati da \n\n (uno per fascia)
  foreach (preg_split('/\n{2,}/', $meteoBox['narrative']) as $p) {
    $p = trim($p);
    if ($p) echo '<p class="narrative-block">'.htmlentities($p).'</p>';
  }
  ?>
</div>
</div>
<?php endif; ?>