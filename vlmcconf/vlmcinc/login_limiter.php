<?php
// ============================================
// ФАЙЛ: vlmcinc/login_limiter.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-01
// @description: Ограничение попыток входа: 5 неудач за 15 мин на пару IP+логин, 10 неудач за 15 мин на IP
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'login_limiter.php') {
    http_response_code(403);
    exit('Access denied');
}

if (!defined('LOGIN_LIMIT_WINDOW'))   define('LOGIN_LIMIT_WINDOW', 900);  // окно подсчёта неудач, сек (15 мин)
if (!defined('LOGIN_LOCK_SECONDS'))   define('LOGIN_LOCK_SECONDS', 900);  // на сколько блокируем, сек (15 мин)
if (!defined('LOGIN_LIMIT_PER_PAIR')) define('LOGIN_LIMIT_PER_PAIR', 5);  // неудач на пару IP + логин
if (!defined('LOGIN_LIMIT_PER_IP'))   define('LOGIN_LIMIT_PER_IP', 10);   // неудач на один IP (любые логины)
if (!defined('LOGIN_LIMIT_MAX_KEYS')) define('LOGIN_LIMIT_MAX_KEYS', 5000);

/** Адрес клиента. Панель работает без обратного прокси, поэтому берём адрес соединения. */
function loginLimiterIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function loginLimiterStorePath() {
    $dir = dirname(__DIR__) . '/cache/login_limiter';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    return $dir . '/attempts.json';
}

/**
 * Выполняет $fn(&$store) под эксклюзивной блокировкой файла (flock работает и на Ubuntu,
 * и на Windows) и сохраняет изменения. Если файл недоступен — возвращает null:
 * вход при этом не блокируется (иначе сбой прав на папку закрыл бы доступ администратору).
 */
function loginLimiterWithStore($fn, $write = true) {
    $path = loginLimiterStorePath();
    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('login_limiter: cannot open ' . $path);
        return null;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return null;
    }

    $raw = stream_get_contents($fh);
    $store = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($store)) {
        $store = [];
    }

    // Чистим устаревшее
    $now = time();
    foreach ($store as $key => $entry) {
        $fails = array_values(array_filter($entry['fails'] ?? [], function ($t) use ($now) {
            return $t > $now - LOGIN_LIMIT_WINDOW;
        }));
        if (!$fails && (int)($entry['locked_until'] ?? 0) <= $now) {
            unset($store[$key]);
        } else {
            $store[$key]['fails'] = $fails;
        }
    }
    // Ограничиваем размер файла (защита от забивания случайными логинами)
    if (count($store) > LOGIN_LIMIT_MAX_KEYS) {
        uasort($store, function ($a, $b) {
            return ($b['last'] ?? 0) <=> ($a['last'] ?? 0);
        });
        $store = array_slice($store, 0, LOGIN_LIMIT_MAX_KEYS, true);
    }

    $result = $fn($store);

    if ($write) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($store));
        fflush($fh);
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function loginLimiterKeys($username) {
    $ip = loginLimiterIp();
    $user = trim((string)$username);
    $user = function_exists('mb_strtolower') ? mb_strtolower(mb_substr($user, 0, 100)) : strtolower(substr($user, 0, 100));
    return ['ip' => 'ip|' . $ip, 'pair' => 'pair|' . sha1($ip . '|' . $user)];
}

/** Сколько секунд ещё действует блокировка (0 — можно пробовать входить). */
function loginLimiterCheck($username) {
    $keys = loginLimiterKeys($username);
    $now = time();
    $left = loginLimiterWithStore(function (&$store) use ($keys, $now) {
        $left = 0;
        foreach ($keys as $key) {
            $until = (int)($store[$key]['locked_until'] ?? 0);
            if ($until > $now) {
                $left = max($left, $until - $now);
            }
        }
        return $left;
    }, false);
    return (int)$left;
}

/** Записать неудачную попытку: по паре IP+логин и по IP. */
function loginLimiterFail($username) {
    $keys = loginLimiterKeys($username);
    $now = time();
    loginLimiterWithStore(function (&$store) use ($keys, $now) {
        $limits = [$keys['pair'] => LOGIN_LIMIT_PER_PAIR, $keys['ip'] => LOGIN_LIMIT_PER_IP];
        foreach ($limits as $key => $limit) {
            $entry = $store[$key] ?? ['fails' => [], 'locked_until' => 0];
            $entry['fails'][] = $now;
            if (count($entry['fails']) >= $limit) {
                $entry['locked_until'] = $now + LOGIN_LOCK_SECONDS;
                $entry['fails'] = [];
            }
            $entry['last'] = $now;
            $store[$key] = $entry;
        }
    });
}

/**
 * Успешный вход сбрасывает счётчик пары IP+логин. Счётчик самого IP НЕ сбрасывается:
 * иначе тот, у кого есть свой рабочий аккаунт, мог бы обнулять потолок и перебирать чужие пароли.
 */
function loginLimiterSuccess($username) {
    $keys = loginLimiterKeys($username);
    loginLimiterWithStore(function (&$store) use ($keys) {
        unset($store[$keys['pair']]);
    });
}
