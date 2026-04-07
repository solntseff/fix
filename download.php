<?php
/**
 * Fix by Solntseff — download redirect + tracker
 *
 * Логирует скачивание, затем перенаправляет на прямую ссылку
 * файла в GitHub Releases. Пользователь не видит интерфейс GitHub —
 * просто начинается скачивание. Chrome не ругается, т.к. домен github.com.
 *
 * URL кэшируется локально чтобы не дёргать GitHub API каждый раз.
 */

// ═══════════ НАСТРОЙКИ ═══════════
$github_user = 'solntseff';
$github_repo = 'fix';
$filename    = 'layout-switch.zip';
$cache_file  = __DIR__ . '/cache/release_url.json';
$cache_ttl   = 3600;  // перепроверять URL раз в час
$log_file    = __DIR__ . '/downloads.log';

// ═══════════ ЛОГИРОВАНИЕ ═══════════
$date    = date('Y-m-d H:i:s');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '-';
$ua      = str_replace('"', '""', $_SERVER['HTTP_USER_AGENT'] ?? '-');
$referer = str_replace('"', '""', $_SERVER['HTTP_REFERER'] ?? '-');

file_put_contents(
    $log_file,
    sprintf('"%s","%s","%s","%s"' . PHP_EOL, $date, $ip, $ua, $referer),
    FILE_APPEND | LOCK_EX
);

// ═══════════ ПОЛУЧЕНИЕ URL ФАЙЛА ═══════════
$download_url = null;
$cache_dir = dirname($cache_file);

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Проверяем кэш
if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if (isset($cached['url'], $cached['time']) && (time() - $cached['time']) < $cache_ttl) {
        $download_url = $cached['url'];
    }
}

// Кэш устарел или отсутствует — запрашиваем GitHub API
if (!$download_url) {
    $api_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: Fix-Download-Proxy'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $release = json_decode($response, true);
        if (isset($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if ($asset['name'] === $filename) {
                    $download_url = $asset['browser_download_url'];
                    // Сохраняем в кэш
                    file_put_contents($cache_file, json_encode([
                        'url'     => $download_url,
                        'version' => $release['tag_name'] ?? 'unknown',
                        'time'    => time(),
                    ]));
                    break;
                }
            }
        }
    }

    // Если API не ответил — пробуем старый кэш
    if (!$download_url && file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        $download_url = $cached['url'] ?? null;
    }
}

// ═══════════ РЕДИРЕКТ ═══════════
if ($download_url) {
    header('Cache-Control: no-cache');
    header('Location: ' . $download_url, true, 302);
    exit;
}

// Фолбэк — прямая ссылка на GitHub
header('Location: https://github.com/' . $github_user . '/' . $github_repo . '/releases/latest', true, 302);
exit;
