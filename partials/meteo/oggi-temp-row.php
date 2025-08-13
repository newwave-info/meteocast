<?php
$apparent   = $current_apparent_temp ?? $current_temp;
$dewPoint   = $current_dew_point 
?? (isset($current_temp, $current_humidity) 
  ? calculateDewPoint($current_temp, $current_humidity) 
  : null);
$temp_label = getTempLabel($apparent, $dewPoint);



$accordionId = 'tempAccordion'; // id univoco se hai più di un widget in pagina
?>

<section class="widget widget-riga">

  <button class="widget-header btn-accordion" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
    <div class="widget-cont">
      <i class="wi wi-thermometer me-2"></i>
      <span class="widget-title"><?= htmlspecialchars($temp_label) ?></span>
    </div>
    
    <div class="widget-cont">
      <span class="widget-data-preview">attuale <strong><?= $current_temp ?></strong>° - percepita <strong><?= $current_apparent_temp ?></strong>°</span>
      <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
    </div>
  </button>


  <!-- ACCORDION: Chart o dettagli -->
  <div class="collapse" id="<?= $accordionId ?>">
    <?php include ROOT_PATH . '/partials/meteo/oggi-chart-temp.php'; ?>
  </div>

</section><!--widget-->