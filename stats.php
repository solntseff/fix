<?php
/**
 * Fix by Solntseff — download stats API
 * GET /stats.php — возвращает JSON с количеством скачиваний.
 * Используется лендингом для отображения счётчика.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$log = __DIR__ . '/downloads.log';

if (!file_exists($log)) {
    echo json_encode(['total' => 0]);
    exit;
}

$count = 0;
$handle = fopen($log, 'r');
if ($handle) {
    while (fgets($handle) !== false) {
        $count++;
    }
    fclose($handle);
}

echo json_encode(['total' => $count]);
