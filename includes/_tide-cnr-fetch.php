<?php
require_once __DIR__ . '/cache-util.php';
require_once __DIR__ . '/../config/config.php';


$data = fetchWithCache(
  "https://dati.venezia.it/sites/default/files/dataset/opendata/ascnr2025est.json",
  'tide_cnr'
);

if (!is_array($data)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$marea = [];
$oggi = new DateTimeImmutable('today');
$fine = $oggi->modify('+' . TIDE_DAYS_FORWARD . ' days');

foreach ($data as $record) {
    if (!isset($record['data'], $record['valore'])) continue;

    $data_record = DateTime::createFromFormat('Y-m-d H:i:s', $record['data']);
    if (!$data_record) continue;

    $inizio = $oggi->modify('-1 day'); // includi ieri
    if ($data_record < $inizio || $data_record > $fine) continue;


    $marea[] = [
        'data' => $data_record->format(DateTimeInterface::ATOM),
        'valore_cm' => floatval($record['valore'])
    ];
}

usort($marea, fn($a, $b) => strtotime($a['data']) <=> strtotime($b['data']));

header('Content-Type: application/json');
echo json_encode($marea, JSON_PRETTY_PRINT);
