<?php
/**
 * Fix by Solntseff — email subscribe
 * Сохраняет email подписчиков в subscribers.csv.
 * Формат: дата, email, IP
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$file = __DIR__ . '/subscribers.csv';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

$email = trim($_POST['email'] ?? '');

// Валидация
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Неверный email']);
    exit;
}

if (strlen($email) > 254) {
    echo json_encode(['ok' => false, 'error' => 'Слишком длинный email']);
    exit;
}

// Проверка на дубликат
if (file_exists($file)) {
    $handle = fopen($file, 'r');
    if ($handle) {
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[1]) && strcasecmp($row[1], $email) === 0) {
                fclose($handle);
                echo json_encode(['ok' => true, 'message' => 'Вы уже подписаны']);
                exit;
            }
        }
        fclose($handle);
    }
}

// Сохраняем
$ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
$date = date('Y-m-d H:i:s');

$handle = fopen($file, 'a');
if ($handle && flock($handle, LOCK_EX)) {
    fputcsv($handle, [$date, $email, $ip]);
    flock($handle, LOCK_UN);
    fclose($handle);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить']);
}
