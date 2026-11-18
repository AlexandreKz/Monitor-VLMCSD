<?php
// ============================================
// ФАЙЛ: vlmcinc/webhook.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Исходящие вебхуки (раздел «Интеграции → Webhook»): адреса, формат, подпись секретом, подписка на события панели; отправка событий и тест
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'webhook.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';
require_once __DIR__ . '/notify.php';   // notifyEncrypt / notifyDecrypt, каталог событий

const WH_MAX_HOOKS = 20;
const WH_FORMATS = ['json', 'slack', 'text'];

function whStateFile() { return vlmcDataFile('webhook_state.json'); }

/** Вебхуки из конфига. Адрес и секрет остаются зашифрованными. */
function whLoad($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $list = $config['integrations']['webhooks'] ?? [];
    $out = [];
    foreach ((array)$list as $h) {
        if (is_array($h) && !empty($h['id'])) {
            $out[] = $h;
        }
    }
    return array_slice($out, 0, WH_MAX_HOOKS);
}

/** События, на которые можно подписаться (без заглушек «скоро»). */
function whEvents() {
    $out = [];
    foreach (notifyCatalog() as $code => $e) {
        if (empty($e['soon'])) {
            $out[] = $code;
        }
    }
    return $out;
}

/** Хост адреса для показа и журнала (путь и ключ в адресе скрыты). */
function whHostOf($url) {
    $p = parse_url((string)$url);
    return is_array($p) && !empty($p['host']) ? $p['host'] . (!empty($p['port']) ? ':' . $p['port'] : '') : '';
}

/** Данные для списка: без адреса целиком и без секрета. */
function whPublic(array $h, array $state = []) {
    $url = notifyDecrypt((string)($h['url'] ?? ''));
    $st = $state[$h['id']] ?? [];
    return [
        'id' => $h['id'],
        'name' => (string)($h['name'] ?? ''),
        'enabled' => !empty($h['enabled']),
        'format' => in_array($h['format'] ?? '', WH_FORMATS, true) ? $h['format'] : 'json',
        'host' => whHostOf($url),
        'scheme' => strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? '')),
        'has_secret' => !empty($h['secret']),
        'timeout' => (int)($h['timeout'] ?? 5),
        'events' => array_values(array_intersect((array)($h['events'] ?? []), whEvents())),
        'last_time' => $st['time'] ?? '',
        'last_ok' => isset($st['ok']) ? (bool)$st['ok'] : null,
        'last_text' => $st['text'] ?? '',
    ];
}

function whState() {
    $r = config_read(whStateFile());
    return ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
}

function whRemember($id, $ok, $text) {
    $f = whStateFile();
    config_lock($f);
    $st = whState();
    $st[$id] = ['time' => date('Y-m-d H:i:s'), 'ok' => (bool)$ok, 'text' => mb_substr((string)$text, 0, 160)];
    save_config($f, $st);
}

/**
 * Проверка адреса: только http(s), без логина в адресе; служебные адреса (0.0.0.0, link-local, метаданные облака) запрещены.
 * Адреса внутренней сети и loopback разрешены (корпоративные системы). Возвращает код ошибки локали или ''.
 */
function whValidateUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || mb_strlen($url) > 800 || preg_match('/[\s\x00-\x1f]/', $url)) {
        return 'wh_err_url';
    }
    $p = parse_url($url);
    if (!is_array($p) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
        return 'wh_err_url';
    }
    if (isset($p['user']) || isset($p['pass'])) {
        return 'wh_err_url_auth';
    }
    $host = trim($p['host'], '[]');
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (whBlockedIp($host)) {
            return 'wh_err_url_host';
        }
    } elseif (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,251}[A-Za-z0-9])?$/', $host)) {
        return 'wh_err_url';
    }
    if (isset($p['port']) && ($p['port'] < 1 || $p['port'] > 65535)) {
        return 'wh_err_url';
    }
    return '';
}

/** Служебные адреса, на которые вебхук отправлять нельзя: «весь» 0.0.0.0/8, link-local и метаданные облака 169.254/16, multicast/зарезервированные 224+. Локальная сеть и loopback разрешены (внутренние системы). */
function whBlockedIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        $first = ($n >> 24) & 255;
        return $first === 0 || $first >= 224 || (($n >> 16) & 0xFFFF) === 0xA9FE;
    }
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return true;
    }
    if ($bin === str_repeat("\0", 16)) {
        return true;                                   // ::
    }
    $b0 = ord($bin[0]);
    $b1 = ord($bin[1]);
    return $b0 === 0xFF || ($b0 === 0xFE && ($b1 & 0xC0) === 0x80);   // multicast, link-local fe80::/10
}

/** Тело запроса по формату. */
function whBody($format, $event, $title, $text, array $ctx = []) {
    $host = (string)($_SERVER['HTTP_HOST'] ?? gethostname());
    if ($format === 'slack') {
        return [json_encode(['text' => '*' . $title . '*' . ($text !== '' ? "\n" . $text : '') . "\n_KMS Monitor, " . $host . '_'], JSON_UNESCAPED_UNICODE), 'application/json'];
    }
    if ($format === 'text') {
        return [$title . ($text !== '' ? "\n\n" . $text : '') . "\n\nKMS Monitor, " . $host . ', ' . date('Y-m-d H:i:s'), 'text/plain; charset=utf-8'];
    }
    return [json_encode([
        'source' => 'kms-monitor', 'host' => $host, 'event' => $event, 'title' => $title, 'text' => $text,
        'context' => (object)array_map('strval', array_filter($ctx, 'is_scalar')), 'time' => date('c'),
    ], JSON_UNESCAPED_UNICODE), 'application/json'];
}

/** Отправка одному вебхуку. Возвращает [ok, текст, http-статус]. */
function whSend(array $h, $event, $title, $text, array $ctx = []) {
    $url = notifyDecrypt((string)($h['url'] ?? ''));
    $err = whValidateUrl($url);
    if ($err !== '') {
        return [false, $err, 0];
    }
    if (!function_exists('curl_init')) {
        return [false, 'wh_err_no_curl', 0];
    }
    list($body, $ctype) = whBody($h['format'] ?? 'json', $event, $title, $text, $ctx);
    $headers = ['Content-Type: ' . $ctype, 'X-KMS-Event: ' . $event];
    $secret = notifyDecrypt((string)($h['secret'] ?? ''));
    if ($secret !== '') {
        $headers[] = 'X-KMS-Signature: sha256=' . hash_hmac('sha256', $body, $secret);   // подпись тела: получатель может проверить подлинность
    }
    $timeout = max(2, min(30, (int)($h['timeout'] ?? 5)));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'KMS-Monitor/1.0',
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXFILESIZE => 1048576,
    ]);
    if (defined('CURLPROTO_HTTP')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    @curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $msg = curl_error($ch);
    curl_close($ch);
    if ($errno !== 0) {
        return [false, mb_substr($msg !== '' ? $msg : ('curl ' . $errno), 0, 160), $status];
    }
    if ($status < 200 || $status >= 300) {
        return [false, 'HTTP ' . $status, $status];
    }
    return [true, 'HTTP ' . $status, $status];
}

/**
 * Событие панели → подписанные вебхуки (после ответа пользователю, как и уведомления).
 */
function whEmit($event, $title, $text = '', array $ctx = []) {
    try {
        $hooks = array_filter(whLoad(), function ($h) use ($event) {
            return !empty($h['enabled']) && in_array($event, (array)($h['events'] ?? []), true);
        });
        if (!$hooks) {
            return;
        }
        $send = function () use ($hooks, $event, $title, $text, $ctx) {
            foreach ($hooks as $h) {
                list($ok, $info) = whSend($h, $event, $title, $text, $ctx);
                whRemember($h['id'], $ok, $info);
            }
        };
        if (PHP_SAPI === 'cli') {
            $send();
        } else {
            register_shutdown_function(function () use ($send) {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $send();
            });
        }
    } catch (\Throwable $e) {
        // вебхуки не должны ломать работу панели
    }
}
