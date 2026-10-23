<?php
// ============================================
// ФАЙЛ: vlmcinc/log_cache.php
// ВЕРСИЯ: 1.0.0
// @description: Кэш агрегатов лога. Результат тяжёлого разбора лога хранится
//               в JSON и пересчитывается только при изменении размера/даты лога.
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'log_cache.php') {
    http_response_code(403);
    exit('Access denied');
}

function getLogAggCacheDir() {
    $dir = dirname(__DIR__) . '/cache/log_agg';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

/** Подпись состояния лога: меняется при любой дозаписи или очистке. */
function getLogSignature($logFile) {
    clearstatcache(true, $logFile);
    $size = @filesize($logFile);
    $mtime = @filemtime($logFile);
    if ($size === false || $mtime === false) {
        return null;
    }
    return $size . ':' . $mtime;
}

/**
 * Вернуть результат $compute() из кэша, если лог не менялся, иначе пересчитать.
 * $compute не должна зависеть ни от чего, кроме лога (и $extra, входящего в ключ кэша).
 * Если кэш недоступен (нет прав на запись и т.п.) — просто считает без кэша.
 */
function logAggCached($name, $logFile, callable $compute, $extra = '') {
    $sig = getLogSignature($logFile);
    if ($sig === null) {
        return $compute();
    }

    $dir = getLogAggCacheDir();
    $file = $dir . '/' . preg_replace('/[^a-z0-9_]/i', '_', $name) . '-' . md5($logFile . '|' . $extra) . '.json';

    if (is_file($file)) {
        $cached = json_decode((string)@file_get_contents($file), true);
        if (is_array($cached) && ($cached['sig'] ?? null) === $sig && array_key_exists('data', $cached)) {
            return $cached['data'];
        }
    }

    $data = $compute();

    $json = json_encode(['sig' => $sig, 'data' => $data]);
    if ($json !== false && is_dir($dir) && is_writable($dir)) {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $json) !== false) {
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
            }
        }
    }

    return $data;
}
