<?php
require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . '/includes/api-fetch.php';
require_once ROOT_PATH . '/includes/helpers.php';

$files = [
    'oggi'        => __DIR__ . '/../meteo/oggi.php',
    'previsioni'  => __DIR__ . '/../meteo/previsioni.php',
    'luna-marea'  => __DIR__ . '/../marea/luna-marea.php',
    'stazioni'    => __DIR__ . '/../stazioni/stazioni.php'
];

$view = isset($_GET['view']) && array_key_exists($_GET['view'], $files) ? $_GET['view'] : 'oggi';
require $files[$view];