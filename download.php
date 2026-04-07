<?php
/**
 * Fix by Solntseff — download proxy + tracker
 *
 * Проксирует файл с GitHub Releases и логирует скачивания.
 * Пользователь скачивает с вашего домена, файл хранится на GitHub.
 *
 * Кэш: файл скачивается с GitHub и кэшируется локально.
 * Кэш сбрасывается раз в сутки или при смене версии.
 */

// ═══════════ НАСТРОЙКИ ═══════════
$github_user = 'solntseff';
$github_repo = 'fix';
$filename    = 'layout-switch.zip';            // имя файла в GitHub Release
$cache_dir   = __DIR__ . '/cache';             // папка для кэша
$cache_ttl   = 86400;                          // время жизни кэша (секунды, 86400 = сутки)
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

// ═══════════ КЭШИРОВАНИЕ ═══════════
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$cached_file = $cache_dir . '/' . $filename;
$cached_meta = $cache_dir . '/meta.json';

$need_download = true;

if (file_exists($cached_file) && file_exists($cached_meta)) {
    $meta = json_decode(file_get_contents($cached_meta), true);
    if (isset($meta['time']) && (time() - $meta['time']) < $cache_ttl) {
        $need_download = false;
    }
}

if ($need_download) {
    // Получаем URL последнего релиза через GitHub API
    $api_url = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: Fix-Download-Proxy'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        // API недоступен — отдаём кэш если есть, иначе ошибка
        if (file_exists($cached_file)) {
            $need_download = false;
        } else {
            http_response_code(503);
            echo 'Сервис временно недоступен. Попробуйте позже.';
            exit;
        }
    }

    if ($need_download) {
        $release = json_decode($response, true);
        $download_url = null;

        // Ищем нужный файл среди assets релиза
        if (isset($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if ($asset['name'] === $filename) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if (!$download_url) {
            // Файл не найден в релизе — отдаём кэш если есть
            if (file_exists($cached_file)) {
                $need_download = false;
            } else {
                http_response_code(404);
                echo 'Файл не найден в последнем релизе.';
                exit;
            }
        }

        if ($need_download) {
            // Скачиваем файл с GitHub
            $ch = curl_init($download_url);
            $fp = fopen($cached_file . '.tmp', 'wb');
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => ['User-Agent: Fix-Download-Proxy'],
                CURLOPT_TIMEOUT        => 120,
            ]);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($http_code === 200 && filesize($cached_file . '.tmp') > 0) {
                rename($cached_file . '.tmp', $cached_file);
                file_put_contents($cached_meta, json_encode([
                    'time'    => time(),
                    'version' => $release['tag_name'] ?? 'unknown',
                    'url'     => $download_url,
                ]));
            } else {
                @unlink($cached_file . '.tmp');
                if (file_exists($cached_file)) {
                    // Отдаём старый кэш
                } else {
                    http_response_code(502);
                    echo 'Не удалось скачать файл. Попробуйте позже.';
                    exit;
                }
            }
        }
    }
}

// ═══════════ ОТДАЧА ФАЙЛА ═══════════
$size = filesize($cached_file);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

readfile($cached_file);
exit;
