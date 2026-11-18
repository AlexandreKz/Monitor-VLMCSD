<?php
// ============================================
// ФАЙЛ: vlmcinc/cloud.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Внешние хранилища для резервных копий (раздел «Интеграции → Cloud Storage»): SFTP, FTP/FTPS, WebDAV, S3-совместимые; настройки, расписание, срок хранения копий и проверка соединения
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'cloud.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';
require_once __DIR__ . '/notify.php';   // notifyEncrypt / notifyDecrypt

const CLOUD_MAX_TARGETS = 10;
const CLOUD_TYPES = ['sftp', 'ftp', 'ftps', 'webdav', 's3'];
const CLOUD_WHAT = ['config', 'users', 'log', 'audit'];

function cloudLoad($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $out = [];
    foreach ((array)($config['integrations']['cloud'] ?? []) as $t) {
        if (is_array($t) && !empty($t['id'])) {
            $out[] = $t;
        }
    }
    return array_slice($out, 0, CLOUD_MAX_TARGETS);
}

function cloudDefaultPort($type) {
    return ['sftp' => 22, 'ftp' => 21, 'ftps' => 21, 'webdav' => 0, 's3' => 0][$type] ?? 0;
}

/** Данные для формы и списка: без пароля и ключей. */
function cloudPublic(array $t) {
    return [
        'id' => $t['id'],
        'name' => (string)($t['name'] ?? ''),
        'enabled' => !empty($t['enabled']),
        'type' => in_array($t['type'] ?? '', CLOUD_TYPES, true) ? $t['type'] : 'sftp',
        'host' => (string)($t['host'] ?? ''),
        'port' => (int)($t['port'] ?? 0),
        'user' => (string)($t['user'] ?? ''),
        'has_pass' => !empty($t['pass']),
        'path' => (string)($t['path'] ?? ''),
        'bucket' => (string)($t['bucket'] ?? ''),
        'region' => (string)($t['region'] ?? ''),
        'what' => array_values(array_intersect((array)($t['what'] ?? []), CLOUD_WHAT)),
        'schedule' => in_array($t['schedule'] ?? '', ['off', 'daily', 'weekly'], true) ? $t['schedule'] : 'off',
        'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($t['time'] ?? '')) ? $t['time'] : '03:00',
        'keep' => max(1, min(60, (int)($t['keep'] ?? 7))),
    ];
}

/** Сборка и проверка записи. Возвращает [запись|null, код ошибки локали]. */
function cloudBuild(array $in, $old) {
    $t = is_array($old) ? $old : [];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 60) {
        return [null, 'cl_err_name'];
    }
    $type = (string)($in['type'] ?? ($t['type'] ?? ''));
    if (!in_array($type, CLOUD_TYPES, true)) {
        return [null, 'cl_err_type'];
    }
    $host = trim((string)($in['host'] ?? ''));
    if ($type === 'webdav' || $type === 's3') {
        $p = parse_url($host);
        if (!is_array($p) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || mb_strlen($host) > 500 || preg_match('/\s/', $host)) {
            return [null, 'cl_err_url'];
        }
        $host = rtrim($host, '/');
    } elseif (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,251}[A-Za-z0-9])?$/', $host) && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
        return [null, 'cl_err_host'];
    }
    $port = (int)($in['port'] ?? 0);
    if ($port === 0) {
        $port = cloudDefaultPort($type);
    }
    if ($port < 0 || $port > 65535) {
        return [null, 'cl_err_port'];
    }
    $path = trim((string)($in['path'] ?? ''));
    if (mb_strlen($path) > 300 || preg_match('~(^|/)\.\.(/|$)|[\x00-\x1f]~', $path)) {
        return [null, 'cl_err_path'];
    }
    $user = mb_substr(trim((string)($in['user'] ?? '')), 0, 200);
    $bucket = trim((string)($in['bucket'] ?? ''));
    if ($type === 's3' && !preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $bucket)) {
        return [null, 'cl_err_bucket'];
    }
    $region = trim((string)($in['region'] ?? ''));
    if ($region !== '' && !preg_match('/^[A-Za-z0-9\-]{2,40}$/', $region)) {
        return [null, 'cl_err_region'];
    }
    $what = array_values(array_intersect(CLOUD_WHAT, (array)($in['what'] ?? [])));
    if (!$what) {
        return [null, 'cl_err_what'];
    }
    $schedule = in_array(($in['schedule'] ?? ''), ['off', 'daily', 'weekly'], true) ? $in['schedule'] : 'off';
    $time = (string)($in['time'] ?? '03:00');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        return [null, 'cl_err_time'];
    }
    $keep = (int)($in['keep'] ?? 7);
    if ($keep < 1 || $keep > 60) {
        return [null, 'cl_err_keep'];
    }
    $t['name'] = $name;
    $t['type'] = $type;
    $t['enabled'] = !empty($in['enabled']);
    $t['host'] = $host;
    $t['port'] = $port;
    $t['user'] = $user;
    $t['path'] = $path;
    $t['bucket'] = $type === 's3' ? $bucket : '';
    $t['region'] = $type === 's3' ? $region : '';
    $t['what'] = $what;
    $t['schedule'] = $schedule;
    $t['time'] = $time;
    $t['keep'] = $keep;
    $pass = (string)($in['pass'] ?? '');
    if (!empty($in['clear_pass'])) {
        $t['pass'] = '';
    } elseif ($pass !== '') {
        $t['pass'] = notifyEncrypt($pass);
    } else {
        $t['pass'] = $t['pass'] ?? '';
    }
    return [$t, ''];
}

/** Проверка соединения. Возвращает ['success','code','detail','partial'] (partial — проверена только доступность, не вход). */
function cloudTest(array $t) {
    $type = $t['type'] ?? '';
    $pass = notifyDecrypt((string)($t['pass'] ?? ''));
    $timeout = 8;
    if ($type === 'ftp' || $type === 'ftps') {
        if (!function_exists('ftp_connect')) {
            return ['success' => false, 'code' => 'no_ftp', 'detail' => '', 'partial' => false];
        }
        $port = (int)($t['port'] ?: 21);
        $c = $type === 'ftps' ? @ftp_ssl_connect($t['host'], $port, $timeout) : @ftp_connect($t['host'], $port, $timeout);
        if (!$c) {
            return ['success' => false, 'code' => 'connect', 'detail' => '', 'partial' => false];
        }
        if (!@ftp_login($c, (string)($t['user'] ?: 'anonymous'), $pass)) {
            @ftp_close($c);
            return ['success' => false, 'code' => 'login', 'detail' => '', 'partial' => false];
        }
        @ftp_pasv($c, true);
        $okPath = ($t['path'] === '' || $t['path'] === '/') ? true : @ftp_chdir($c, $t['path']);
        @ftp_close($c);
        return $okPath ? ['success' => true, 'code' => 'ok', 'detail' => '', 'partial' => false]
                       : ['success' => false, 'code' => 'path', 'detail' => '', 'partial' => false];
    }
    if ($type === 'sftp') {
        $errno = 0; $errstr = '';
        $fp = @fsockopen($t['host'], (int)($t['port'] ?: 22), $errno, $errstr, $timeout);
        if (!$fp) {
            return ['success' => false, 'code' => 'connect', 'detail' => mb_substr((string)$errstr, 0, 120), 'partial' => false];
        }
        stream_set_timeout($fp, $timeout);
        $banner = (string)fgets($fp, 256);
        fclose($fp);
        if (strpos($banner, 'SSH-') !== 0) {
            return ['success' => false, 'code' => 'not_ssh', 'detail' => '', 'partial' => false];
        }
        return ['success' => true, 'code' => 'ok', 'detail' => trim($banner), 'partial' => true];   // вход по SSH не проверяется
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'code' => 'no_curl', 'detail' => '', 'partial' => false];
    }
    $url = $t['host'];
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
             CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'KMS-Monitor/1.0'];
    if (defined('CURLPROTO_HTTP')) {
        $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    if ($type === 'webdav') {
        $url .= '/' . ltrim((string)$t['path'], '/');
        $opts[CURLOPT_CUSTOMREQUEST] = 'PROPFIND';
        $opts[CURLOPT_HTTPHEADER] = ['Depth: 0'];
        if ($t['user'] !== '') {
            $opts[CURLOPT_USERPWD] = $t['user'] . ':' . $pass;
        }
    } else {   // s3: без подписи запроса — проверяется только доступность адреса
        $url .= '/' . $t['bucket'];
        $opts[CURLOPT_NOBODY] = true;
    }
    $opts[CURLOPT_URL] = $url;
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    @curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $msg = curl_error($ch);
    curl_close($ch);
    if ($errno !== 0) {
        return ['success' => false, 'code' => 'connect', 'detail' => mb_substr($msg, 0, 120), 'partial' => false];
    }
    if ($type === 'webdav') {
        if ($status === 207 || $status === 200) {
            return ['success' => true, 'code' => 'ok', 'detail' => 'HTTP ' . $status, 'partial' => false];
        }
        if ($status === 401 || $status === 403) {
            return ['success' => false, 'code' => 'login', 'detail' => 'HTTP ' . $status, 'partial' => false];
        }
        return ['success' => false, 'code' => $status === 404 ? 'path' : 'http', 'detail' => 'HTTP ' . $status, 'partial' => false];
    }
    // s3: 200/301/307/403/404 означают, что адрес отвечает; ключи не проверялись
    return $status >= 200 && $status < 500
        ? ['success' => true, 'code' => 'ok', 'detail' => 'HTTP ' . $status, 'partial' => true]
        : ['success' => false, 'code' => 'http', 'detail' => 'HTTP ' . $status, 'partial' => false];
}
