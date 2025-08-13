<?php
// Dati vento
$wind_label = isset($current_wind_speed) ? getWindLabel($current_wind_speed) : '-';
$wind_dir = isset($current_wind_direction) ? getWindDirection($current_wind_direction) : '-';
$wind_speed = isset($current_wind_speed) ? round($current_wind_speed) : '-';
$wind_gusts = isset($current_wind_gusts) ? round($current_wind_gusts) : '-';

 $accordionId = 'windAccordion'; // id univoco se hai più di un widget in pagina
?>

<section class="widget widget-riga">

  <button class="widget-header btn-accordion" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
    <div class="widget-cont">
      <i class="wi wi-strong-wind me-2"></i>
      <span class="widget-title"><?= htmlspecialchars($wind_label) ?></span>
    </div>
    
    <div class="widget-cont">
      <span class="widget-data-preview"><strong><?= $wind_speed ?> / <?= $wind_gusts ?></strong> km/h <strong> <?= htmlspecialchars($wind_dir) ?></strong></span> 
      <span class="widget-action"><i class="bi bi-chevron-down arrow-accordion"></i></span>
    </div>
  </button>


  <!-- ACCORDION: Chart o dettagli -->
  <div class="collapse" id="<?= $accordionId ?>">
    <?php include ROOT_PATH . '/partials/meteo/oggi-chart-vento.php'; ?>
    <?php //include ROOT_PATH . '/partials/meteo/previsioni-chart-vento.php'; ?>
  </div>

</section><!--widget-->