<?php
// ============================================
// ФАЙЛ: vlmcinc/notify.php
// ВЕРСИЯ: 3.3.0
// ДАТА: 2026-11-19
// @description: Уведомления (пункт 27): общие каналы (Email/SMTP, Telegram, Битрикс24) и правила «событие → каналы» с фильтром по группе/устройству, частотой и тихими часами; секреты зашифрованы; история отправок
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'notify.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/http_client.php';
require_once __DIR__ . '/notify_events.php';

const NOTIFY_HISTORY_KEEP = 200;
const NOTIFY_MAX_CHANNELS = 30;
const NOTIFY_MAX_RULES = 100;

function notifyFile()        { return vlmcDataFile('notify.json'); }
function notifyState()       { return vlmcDataFile('notify_state.json'); }
function notifyHistoryFile() { return vlmcDataFile('notify_history.jsonl'); }
function notifyKeyFile()     { return vlmcDataFile('notify.key'); }

// ---------- шифрование секретов ----------

function notifyKey() {
    $f = notifyKeyFile();
    $k = is_file($f) ? (string)@file_get_contents($f) : '';
    if (strlen($k) !== 32) {
        $k = random_bytes(32);
        @file_put_contents($f, $k, LOCK_EX);
        @chmod($f, 0600);
    }
    return $k;
}

function notifyEncrypt($plain) {
    if ($plain === '') {
        return '';
    }
    if (function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($plain, 'aes-256-gcm', notifyKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($c !== false) {
            return 'enc1:' . base64_encode($iv . $tag . $c);
        }
    }
    return 'b64:' . base64_encode($plain);   // без openssl: только файл с правами 0600
}

function notifyDecrypt($stored) {
    $stored = (string)$stored;
    if (strpos($stored, 'enc1:') === 0 && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($stored, 5), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', notifyKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? '' : $p;
    }
    if (strpos($stored, 'b64:') === 0) {
        $p = base64_decode(substr($stored, 4), true);
        return $p === false ? '' : $p;
    }
    return '';
}

// ---------- хранилище: каналы и правила ----------

/** Правила-шаблоны по умолчанию: выключены и без каналов — администратор подключает канал и включает. */
function notifyDefaultRules() {
    $mk = function ($id, $name, array $events, $throttle) {
        $params = [];
        foreach ($events as $e) {
            foreach ((notifyCatalog()[$e]['params'] ?? []) as $k => $v) { $params[$k] = $v[0]; }
        }
        return ['id' => $id, 'name' => $name, 'events' => $events, 'channels' => [], 'groups' => [], 'devices' => [],
            'throttle' => $throttle, 'quiet_enabled' => false, 'quiet_from' => '23:00', 'quiet_to' => '07:00', 'active' => false, 'params' => $params];
    };
    return [
        $mk('d_failed_logins', 'Безопасность — серия неудачных входов', ['failed_logins'], 30),
        $mk('d_access_denied', 'Безопасность — отказ в доступе', ['access_denied'], 30),
        $mk('d_users', 'Безопасность — изменения пользователей', ['user_added', 'user_deleted', 'user_updated', 'password_changed'], 0),
        $mk('d_audit_cleared', 'Журнал событий очищен', ['audit_cleared'], 0),
        $mk('d_update', 'Обновления — новая версия панели', ['update_available'], 60),
    ];
}

/** Данные уведомлений. Секреты каналов остаются зашифрованными. */
function notifyLoad() {
    $f = notifyFile();
    $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    $d = ['ver' => 3, 'channels' => [], 'rules' => null, 'seq' => 0];
    if (is_array($j) && (int)($j['ver'] ?? 0) >= 3) {
        // файл старой модели (без метки ver) не читаем: правила-шаблоны создаются заново
        $d['channels'] = array_values(array_filter((array)($j['channels'] ?? []), 'is_array'));
        $d['rules'] = isset($j['rules']) && is_array($j['rules']) ? array_values(array_filter($j['rules'], 'is_array')) : null;
        $d['seq'] = (int)($j['seq'] ?? 0);
    }
    if ($d['rules'] === null) {
        $d['rules'] = notifyDefaultRules();
    }
    return $d;
}

/** Сохранение атомарно (как конфиг панели). При сбое причина в $GLOBALS['notify_save_error']. */
function notifySave(array $s) {
    require_once __DIR__ . '/safe_json.php';
    $f = notifyFile();
    $GLOBALS['notify_save_error'] = '';
    $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        $GLOBALS['notify_save_error'] = 'json: ' . json_last_error_msg();
        return false;
    }
    if (!config_write_atomic($f, $json)) {
        $err = error_get_last();
        $GLOBALS['notify_save_error'] = $f . (is_dir(dirname($f)) && !is_writable(dirname($f)) ? ' (нет прав на запись в папку)' : '') . ($err ? ': ' . $err['message'] : '');
        return false;
    }
    @chmod($f, 0600);
    return true;
}

/**
 * Чтение–изменение–запись под блокировкой файла: параллельные правки не теряют друг друга.
 * Колбэк получает данные по ссылке; вернёт строку (код ошибки локали) или false — тогда ничего не сохраняется.
 * @return true|string|false
 */
function notifyMutate(callable $fn) {
    require_once __DIR__ . '/safe_json.php';
    config_lock(notifyFile());
    $s = notifyLoad();
    $res = $fn($s);
    if ($res === false || is_string($res)) {
        return $res;
    }
    return notifySave($s);
}

function notifyNextId(array &$s, $prefix) {
    $s['seq'] = (int)($s['seq'] ?? 0) + 1;
    return $prefix . $s['seq'] . dechex(random_int(16, 255));
}

/** Канал для интерфейса: без секретов. */
function notifyChannelPublic(array $c) {
    $out = ['id' => $c['id'], 'name' => $c['name'], 'type' => $c['type'], 'enabled' => !empty($c['enabled'])];
    if ($c['type'] === 'email') {
        $e = $c['email'] ?? [];
        $out['email'] = ['host' => $e['host'] ?? '', 'port' => (int)($e['port'] ?? 587), 'secure' => $e['secure'] ?? 'tls', 'user' => $e['user'] ?? '',
            'from' => $e['from'] ?? '', 'to' => $e['to'] ?? '', 'has_pass' => ($e['pass'] ?? '') !== ''];
    } elseif ($c['type'] === 'bitrix24') {
        $b = $c['bitrix24'] ?? [];
        $out['bitrix24'] = ['dialog' => $b['dialog'] ?? '', 'has_hook' => ($b['hook'] ?? '') !== '', 'host' => parse_url(notifyDecrypt($b['hook'] ?? ''), PHP_URL_HOST) ?: ''];
    } else {
        $t = $c['telegram'] ?? [];
        $out['telegram'] = ['chat' => $t['chat'] ?? '', 'has_token' => ($t['token'] ?? '') !== ''];
    }
    return $out;
}

function notifyFindChannel(array $s, $id) {
    foreach ($s['channels'] as $c) { if (($c['id'] ?? '') === $id) { return $c; } }
    return null;
}

function notifyValidTime($t) {
    return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
}

function notifyAddress($s) {
    return preg_match('/<([^<>\s]+)>/', (string)$s, $m) ? $m[1] : trim((string)$s);
}

/**
 * Отправитель письма: [имя, адрес] или null, если адрес определить нельзя.
 * Пусто — адрес берётся из логина; «Имя» без адреса — имя отправителя, адрес из логина;
 * «адрес» и «Имя <адрес>» — как введено.
 */
function notifyFromParse($from, $login) {
    $from = trim((string)$from);
    $login = trim((string)$login);
    $loginOk = $login !== '' && filter_var($login, FILTER_VALIDATE_EMAIL);
    if ($from === '') {
        return $loginOk ? ['', $login] : null;
    }
    if (preg_match('/^(.*?)\s*<([^<>\s]+)>$/u', $from, $m)) {
        return filter_var($m[2], FILTER_VALIDATE_EMAIL) ? [trim($m[1], " \t\"'"), $m[2]] : null;
    }
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['', $from];
    }
    return ($loginOk && strpos($from, '@') === false) ? [trim($from, " \t\"'"), $login] : null;
}

/** Заголовок From: имя (при необходимости в кодировке UTF-8) и адрес. */
function notifyFromHeader(array $p) {
    $name = notifyClean($p[0], 100);
    if ($name === '') {
        return $p[1];
    }
    if (!preg_match('/^[\x20-\x7E]+$/', $name)) {
        $name = '=?UTF-8?B?' . base64_encode($name) . '?=';
    } else {
        $name = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
    }
    return $name . ' <' . $p[1] . '>';
}

/** Список адресов из строки (запятая, точка с запятой, пробел, перевод строки). */
function notifySplitAddresses($s) {
    return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string)$s))));
}

/**
 * Сборка и проверка канала из присланных данных (JSON в поле data).
 * Пустой секрет = оставить прежний. Возвращает [канал|null, код ошибки локали].
 */
function notifyChannelBuild(array $in, $old) {
    $c = is_array($old) ? $old : [];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 60) { return [null, 'notify_err_name']; }
    $type = (string)($in['type'] ?? ($c['type'] ?? ''));
    if (!in_array($type, ['email', 'telegram', 'bitrix24'], true)) { return [null, 'notify_err_type']; }
    if (!empty($c['type']) && $c['type'] !== $type) { return [null, 'notify_err_type']; }
    $data = json_decode((string)($in['data'] ?? ''), true);
    $data = is_array($data) ? $data : [];
    $c['name'] = $name;
    $c['type'] = $type;
    $c['enabled'] = !empty($data['enabled']);
    if ($type === 'email') {
        $e = $c['email'] ?? ['pass' => ''];
        $e['host'] = trim((string)($data['host'] ?? ''));
        $e['port'] = (int)($data['port'] ?? 0);
        $e['secure'] = in_array(($data['secure'] ?? ''), ['none', 'tls', 'ssl'], true) ? $data['secure'] : 'tls';
        $e['user'] = trim((string)($data['user'] ?? ''));
        if (($data['pass'] ?? '') !== '') { $e['pass'] = notifyEncrypt((string)$data['pass']); }
        $e['from'] = trim((string)($data['from'] ?? ''));
        $to = notifySplitAddresses($data['to'] ?? '');
        if ($e['host'] === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $e['host'])) { return [null, 'notify_err_host']; }
        if ($e['port'] < 1 || $e['port'] > 65535) { return [null, 'notify_err_port']; }
        if (notifyFromParse($e['from'], $e['user']) === null) { return [null, 'notify_err_from']; }
        if (!$to || count($to) > 20) { return [null, 'notify_err_to']; }
        foreach ($to as $a) { if (!filter_var($a, FILTER_VALIDATE_EMAIL)) { return [null, 'notify_err_to']; } }
        $e['to'] = implode(', ', $to);
        $c['email'] = $e;
        unset($c['telegram'], $c['bitrix24']);
    } elseif ($type === 'bitrix24') {
        $b = $c['bitrix24'] ?? ['hook' => ''];
        if (($data['hook'] ?? '') !== '') {
            $h = trim((string)$data['hook']);
            if (!preg_match('~^https://[A-Za-z0-9.\-]+(:\d+)?/rest/\d+/[A-Za-z0-9]+/?$~', $h)) { return [null, 'notify_err_webhook']; }
            $b['hook'] = notifyEncrypt(rtrim($h, '/') . '/');
        }
        $b['dialog'] = trim((string)($data['dialog'] ?? ''));
        if (($b['hook'] ?? '') === '') { return [null, 'notify_err_webhook']; }
        if (!preg_match('/^(chat)?\d{1,12}$/', $b['dialog'])) { return [null, 'notify_err_dialog']; }
        $c['bitrix24'] = $b;
        unset($c['email'], $c['telegram']);
    } else {
        $t = $c['telegram'] ?? ['token' => ''];
        if (($data['token'] ?? '') !== '') {
            if (!preg_match('/^\d+:[A-Za-z0-9_\-]+$/', trim((string)$data['token']))) { return [null, 'notify_err_token']; }
            $t['token'] = notifyEncrypt(trim((string)$data['token']));
        }
        $t['chat'] = trim((string)($data['chat'] ?? ''));
        if (($t['token'] ?? '') === '') { return [null, 'notify_err_token']; }
        if (!preg_match('/^(-?\d{1,20}|@[A-Za-z0-9_]{3,64})$/', $t['chat'])) { return [null, 'notify_err_chat']; }
        $c['telegram'] = $t;
        unset($c['email'], $c['bitrix24']);
    }
    return [$c, ''];
}

/** Сборка и проверка правила. Возвращает [правило|null, код ошибки локали]. */
function notifyRuleBuild(array $in, array $s, $old) {
    $r = is_array($old) ? $old : [];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) { return [null, 'notify_err_name']; }
    $d = json_decode((string)($in['data'] ?? ''), true);
    if (!is_array($d)) { return [null, 'notify_err_rule']; }
    $cat = notifyCatalog();
    $events = [];
    foreach ((array)($d['events'] ?? []) as $e) {
        if (is_string($e) && isset($cat[$e]) && empty($cat[$e]['soon'])) { $events[$e] = true; }
    }
    if (!$events) { return [null, 'notify_err_events']; }
    $channels = [];
    foreach ((array)($d['channels'] ?? []) as $cid) {
        if (is_string($cid) && notifyFindChannel($s, $cid)) { $channels[$cid] = true; }
    }
    $listClean = function ($v) {
        $o = [];
        foreach ((array)$v as $x) { if (is_string($x) && $x !== '' && mb_strlen($x) <= 120) { $o[$x] = true; } }
        return array_slice(array_keys($o), 0, 300);
    };
    $throttle = (int)($d['throttle'] ?? 0);
    if ($throttle < 0 || $throttle > 1440) { return [null, 'notify_err_throttle']; }
    $qf = (string)($d['quiet_from'] ?? '23:00'); $qt = (string)($d['quiet_to'] ?? '07:00');
    if (!notifyValidTime($qf) || !notifyValidTime($qt)) { return [null, 'notify_err_time']; }
    $params = [];
    foreach (array_keys($events) as $e) {
        foreach (($cat[$e]['params'] ?? []) as $k => $lim) {
            $v = (int)($d['params'][$k] ?? $lim[0]);
            if ($v < $lim[1] || $v > $lim[2]) { return [null, 'notify_err_params']; }
            $params[$k] = $v;
        }
    }
    $r['name'] = $name;
    $r['events'] = array_keys($events);
    $r['channels'] = array_keys($channels);
    $r['groups'] = $listClean($d['groups'] ?? []);
    $r['devices'] = $listClean($d['devices'] ?? []);
    $r['throttle'] = $throttle;
    $r['quiet_enabled'] = !empty($d['quiet_enabled']);
    $r['quiet_from'] = $qf; $r['quiet_to'] = $qt;
    $r['active'] = !empty($d['active']);
    if ($r['active'] && !$r['channels']) { $r['active'] = false; }   // без канала правило сохраняется выключенным
    $r['params'] = $params;
    return [$r, ''];
}

// ---------- отправка ----------

function notifyClean($s, $max = 300) {
    return mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)$s)), 0, $max);
}

/** Ответ SMTP: код и текст (поддерживаются многострочные ответы). */
function notifySmtpRead($fp) {
    $text = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return [(int)substr($text, 0, 3), trim($text)];
}

function notifySmtpCmd($fp, $cmd, array $okCodes) {
    fwrite($fp, $cmd . "\r\n");
    [$code, $text] = notifySmtpRead($fp);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP ' . $code . ': ' . notifyClean($text, 120));
    }
    return $text;
}

/** @return array [bool ok, string error] */
function notifySendEmail(array $c, $subject, $body) {
    $fp = null;
    try {
        $host = $c['host']; $port = (int)$c['port'];
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $addr = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($addr, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException('connect: ' . notifyClean($errstr ?: (string)$errno, 100));
        }
        stream_set_timeout($fp, 10);
        [$code, $t] = notifySmtpRead($fp);
        if ($code !== 220) { throw new RuntimeException('SMTP ' . $code); }
        $me = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)gethostname()) ?: 'localhost';
        notifySmtpCmd($fp, 'EHLO ' . $me, [250]);
        if ($c['secure'] === 'tls') {
            notifySmtpCmd($fp, 'STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            notifySmtpCmd($fp, 'EHLO ' . $me, [250]);
        }
        if ($c['user'] !== '') {
            notifySmtpCmd($fp, 'AUTH LOGIN', [334]);
            notifySmtpCmd($fp, base64_encode($c['user']), [334]);
            notifySmtpCmd($fp, base64_encode(notifyDecrypt($c['pass'])), [235]);
        }
        $fromP = notifyFromParse($c['from'], $c['user']);
        if ($fromP === null) {
            throw new Exception(__('notify_err_from'));
        }
        notifySmtpCmd($fp, 'MAIL FROM:<' . $fromP[1] . '>', [250]);
        $rcpt = array_filter(array_map('trim', explode(',', $c['to'])));
        foreach ($rcpt as $r) {
            notifySmtpCmd($fp, 'RCPT TO:<' . $r . '>', [250, 251]);
        }
        notifySmtpCmd($fp, 'DATA', [354]);
        $fromHdr = notifyFromHeader($fromP);
        $msg = 'Date: ' . date('r') . "\r\n"
            . 'From: ' . $fromHdr . "\r\n"
            . 'To: ' . implode(', ', $rcpt) . "\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode(notifyClean($subject, 200)) . "?=\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body), 76, "\r\n");
        fwrite($fp, $msg . ".\r\n");
        [$code, $t] = notifySmtpRead($fp);
        if ($code !== 250) { throw new RuntimeException('SMTP ' . $code . ': ' . notifyClean($t, 120)); }
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return [true, ''];
    } catch (\Throwable $ex) {
        if (is_resource($fp)) { @fclose($fp); }
        return [false, notifyClean($ex->getMessage(), 160)];
    }
}

/** Telegram Bot API через проверенный HTTPS-клиент панели. */
function notifySendTelegram(array $c, $text) {
    $url = 'https://api.telegram.org/bot' . notifyDecrypt($c['token']) . '/sendMessage?' . http_build_query([
        'chat_id' => $c['chat'], 'text' => mb_substr($text, 0, 3500), 'disable_web_page_preview' => 'true'
    ]);
    $r = vlmcHttpRequest($url, ['timeout' => 10, 'connect_timeout' => 5]);
    if ($r['ok']) {
        return [true, ''];
    }
    $j = json_decode((string)$r['body'], true);
    $why = is_array($j) && !empty($j['description']) ? (string)$j['description'] : ($r['error'] ?: ('HTTP ' . $r['status']));
    return [false, notifyClean($why, 160)];
}

// ---------- история и состояние ----------

function notifyHistoryAdd($rule, $event, $title, array $channels, $ok, $error = '', $skip = '') {
    $f = notifyHistoryFile();
    $line = json_encode(['t' => time(), 'rule' => notifyClean($rule, 80), 'e' => $event, 'title' => notifyClean($title, 160),
        'ch' => array_map(function ($x) { return notifyClean($x, 60); }, $channels), 'ok' => $ok, 'err' => $error, 'skip' => $skip], JSON_UNESCAPED_UNICODE);
    @file_put_contents($f, $line . "\n", FILE_APPEND | LOCK_EX);
    @chmod($f, 0600);
    $lines = @file($f, FILE_IGNORE_NEW_LINES);
    if (is_array($lines) && count($lines) > NOTIFY_HISTORY_KEEP * 2) {
        @file_put_contents($f, implode("\n", array_slice($lines, -NOTIFY_HISTORY_KEEP)) . "\n", LOCK_EX);
    }
}

function notifyHistory($limit = 100) {
    $lines = is_file(notifyHistoryFile()) ? @file(notifyHistoryFile(), FILE_IGNORE_NEW_LINES) : [];
    $out = [];
    foreach (array_reverse(is_array($lines) ? $lines : []) as $l) {
        $j = json_decode($l, true);
        if (is_array($j)) { $out[] = $j; }
        if (count($out) >= $limit) { break; }
    }
    return $out;
}

/** Чтение–изменение–запись состояния под блокировкой. */
function notifyStateUpdate(callable $fn) {
    $f = notifyState();
    $fh = @fopen($f, 'c+');
    if (!$fh) { $tmp = []; return $fn($tmp); }
    flock($fh, LOCK_EX);
    $cur = json_decode((string)stream_get_contents($fh), true);
    $cur = is_array($cur) ? $cur : [];
    $res = $fn($cur);
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($cur));
    fflush($fh); flock($fh, LOCK_UN); fclose($fh);
    @chmod($f, 0600);
    return $res;
}

function notifyInQuiet(array $r) {
    if (empty($r['quiet_enabled'])) { return false; }
    $now = date('H:i');
    $a = $r['quiet_from']; $b = $r['quiet_to'];
    if ($a === $b) { return false; }
    return $a < $b ? ($now >= $a && $now < $b) : ($now >= $a || $now < $b);
}

// ---------- события ----------

/** Правило подходит под контекст события (фильтр по группе/устройству действует для событий с такой областью). */
function notifyRuleMatches(array $rule, $event, array $ctx) {
    if (empty($rule['active']) || !in_array($event, (array)$rule['events'], true)) { return false; }
    $scope = notifyCatalog()[$event]['scope'] ?? 'none';
    if ($scope === 'none') { return true; }
    $groups = (array)($rule['groups'] ?? []); $devices = (array)($rule['devices'] ?? []);
    if ($groups && !in_array((string)($ctx['group'] ?? ''), $groups, true)) { return false; }
    if ($scope === 'device' && $devices && !in_array((string)($ctx['device'] ?? ''), $devices, true)) { return false; }
    return true;
}

/** Битрикс24: входящий вебхук, метод im.message.add; DIALOG_ID — число (пользователь) или chatN (чат). */
function notifySendBitrix(array $c, $text) {
    $hook = notifyDecrypt($c['hook'] ?? '');
    if ($hook === '') { return [false, 'webhook']; }
    $url = $hook . 'im.message.add.json?' . http_build_query(['DIALOG_ID' => $c['dialog'], 'MESSAGE' => mb_substr($text, 0, 1800), 'SYSTEM' => 'N']);
    $r = vlmcHttpRequest($url, ['timeout' => 10, 'connect_timeout' => 5]);
    $j = json_decode((string)$r['body'], true);
    if ($r['ok'] && is_array($j) && !empty($j['result'])) { return [true, '']; }
    $why = is_array($j) && !empty($j['error_description']) ? (string)$j['error_description'] : ($r['error'] ?: ('HTTP ' . $r['status']));
    return [false, notifyClean($why, 160)];
}

/** Отправка одним каналом. @return array [bool ok, string error] */
function notifySendVia(array $c, $subject, $body) {
    if ($c['type'] === 'bitrix24') {
        return notifySendBitrix($c['bitrix24'], $subject . "\n\n" . $body);
    }
    if ($c['type'] === 'telegram') {
        return notifySendTelegram($c['telegram'], $subject . "\n\n" . $body);
    }
    return notifySendEmail($c['email'], $subject, $body);
}

/**
 * Событие → подходящие правила (после ответа пользователю, чтобы не задерживать панель).
 * $only — ограничить набор правил (идентификаторы), например при пороге неудачных входов.
 */
function notifyEmit($event, $title, $text = '', array $ctx = [], ?array $only = null) {
    try {
        if (!isset(notifyCatalog()[$event]) || !empty(notifyCatalog()[$event]['soon'])) { return; }
        if ($only === null) {
            // Webhook-эндпоинты (Интеграции → Webhook) подписываются на события независимо от правил уведомлений
            require_once __DIR__ . '/webhook.php';
            whEmit($event, $title, $text, $ctx);
        }
        $s = notifyLoad();
        $jobs = [];
        foreach ($s['rules'] as $rule) {
            if ($only !== null && !in_array($rule['id'], $only, true)) { continue; }
            if (!notifyRuleMatches($rule, $event, $ctx)) { continue; }

            $chans = [];
            foreach ((array)$rule['channels'] as $cid) {
                $c = notifyFindChannel($s, $cid);
                if ($c && !empty($c['enabled'])) { $chans[] = $c; }
            }
            if (!$chans) { continue; }
            if (notifyInQuiet($rule)) {
                notifyHistoryAdd($rule['name'], $event, $title, array_column($chans, 'name'), true, '', 'quiet');
                continue;
            }
            $window = (int)$rule['throttle'] * 60;
            $key = $rule['id'] . ':' . $event;
            $extra = notifyStateUpdate(function (&$st) use ($key, $window) {
                $now = time();
                $last = (int)($st['last'][$key] ?? 0);
                if ($window > 0 && $now - $last < $window) {
                    $st['suppressed'][$key] = (int)($st['suppressed'][$key] ?? 0) + 1;
                    return -1;
                }
                $n = (int)($st['suppressed'][$key] ?? 0);
                $st['last'][$key] = $now;
                $st['suppressed'][$key] = 0;
                return $n;
            });
            if ($extra < 0) { continue; }
            $jobs[] = ['rule' => $rule['name'], 'channels' => $chans, 'extra' => $extra];
        }
        if (!$jobs) { return; }

        $host = (string)($_SERVER['HTTP_HOST'] ?? gethostname());
        $send = function () use ($jobs, $event, $title, $text, $host) {
            foreach ($jobs as $j) {
                $body = $title . "\n\n" . $text . ($j['extra'] > 0 ? "\n\n(+" . $j['extra'] . ")" : '') . "\n\nKMS Monitor, " . $host . ', ' . date('Y-m-d H:i:s');
                $subject = '[KMS Monitor] ' . $title;
                $errs = [];
                foreach ($j['channels'] as $c) {
                    [$ok, $er] = notifySendVia($c, $subject, $body);
                    if (!$ok) { $errs[] = $c['name'] . ': ' . $er; }
                }
                notifyHistoryAdd($j['rule'], $event, $title, array_column($j['channels'], 'name'), !$errs, implode('; ', $errs));
            }
        };
        if (PHP_SAPI === 'cli') {
            $send();
        } else {
            register_shutdown_function(function () use ($send) {
                if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
                $send();
            });
        }
    } catch (\Throwable $e) {
        // уведомления не должны ломать работу панели
    }
}

/** Параметры записи журнала «ключ=значение; …» (так их пишет auditCleanParams). */
function notifyParseDetails($d) {
    $o = [];
    foreach (explode('; ', (string)$d) as $p) {
        $i = strpos($p, '=');
        if ($i !== false) { $o[substr($p, 0, $i)] = substr($p, $i + 1); }
    }
    return $o;
}

/** Вызывается из журнала событий для каждой записи: переводит её в события уведомлений. */
function notifyOnAudit(array $e) {
    $a = (string)($e['a'] ?? '');
    $ok = ($e['r'] ?? '') === 'ok';
    $who = (string)($e['u'] ?? '') . ' (' . (string)($e['ip'] ?? '') . ')';
    $det = (string)($e['d'] ?? '');
    $p = notifyParseDetails($det);
    switch ($a) {
        case 'access_denied':
            return notifyEmit('access_denied', 'Отказ в доступе: ' . $who, $det);
        case 'add_user':
            if ($ok) { notifyEmit('user_added', 'Добавлен пользователь панели', 'Выполнил: ' . $who . "\n" . $det); }
            return;
        case 'delete_user':
            if ($ok) { notifyEmit('user_deleted', 'Удалён пользователь панели', 'Выполнил: ' . $who . "\n" . $det); }
            return;
        case 'update_user':
            if ($ok) { notifyEmit('user_updated', 'Изменены данные или права пользователя', 'Выполнил: ' . $who . "\n" . $det); }
            return;
        case 'change_user_password':
            if ($ok) { notifyEmit('password_changed', 'Изменён пароль пользователя', 'Выполнил: ' . $who . "\n" . $det); }
            return;
        case 'add_device':
            if ($ok) { notifyEmit('device_added', 'Добавлено устройство: ' . ($p['deviceName'] ?? ''), 'Группа: ' . ($p['deviceGroup'] ?? '') . "\nВыполнил: " . $who, ['device' => $p['deviceName'] ?? '', 'group' => $p['deviceGroup'] ?? '']); }
            return;
        case 'mass_move_devices':
            if ($ok) { notifyEmit('devices_moved', 'Устройства перемещены в группу: ' . ($p['target_group'] ?? ''), 'Выполнил: ' . $who, ['group' => $p['target_group'] ?? '']); }
            return;
        case 'rename_group':
            if ($ok) { notifyEmit('group_renamed', 'Группа переименована: ' . ($p['old_name'] ?? '') . ' → ' . ($p['new_name'] ?? ''), 'Выполнил: ' . $who, ['group' => $p['old_name'] ?? '']); }
            return;
        case 'delete_group':
            if ($ok) { notifyEmit('group_deleted', 'Удалена группа: ' . ($p['group_name'] ?? ''), 'Выполнил: ' . $who, ['group' => $p['group_name'] ?? '']); }
            return;
        case 'clear_log':
            if ($ok) { notifyEmit('log_cleared', 'Очищен лог KMS-сервера', 'Выполнил: ' . $who); }
            return;
        case 'clear_audit':
            if ($ok) { notifyEmit('audit_cleared', 'Очищен журнал событий', 'Выполнил: ' . $who); }
            return;
        case 'perform_update':
            notifyEmit($ok ? 'update_installed' : 'update_failed', $ok ? 'Панель обновлена' : 'Ошибка обновления панели', 'Выполнил: ' . $who . "\n" . $det);
            return;
        case 'login_locked':
            return notifyEmit('failed_logins', 'Вход заблокирован после неудачных попыток: ' . $who);
        case 'login_failed':
            return notifyFailedLogin($e);
    }
}

/** Серия неудачных входов: у каждого правила свой порог и окно. */
function notifyFailedLogin(array $e) {
    $s = notifyLoad();
    $rules = array_values(array_filter($s['rules'], function ($r) { return notifyRuleMatches($r, 'failed_logins', []) && !empty($r['channels']); }));
    if (!$rules) { return; }
    $ip = (string)($e['ip'] ?? '');
    $hit = notifyStateUpdate(function (&$st) use ($ip, $rules) {
        $now = time();
        $list = array_values(array_filter((array)($st['fails'][$ip] ?? []), function ($t) use ($now) { return $now - $t <= 86400; }));
        $list[] = $now;
        $st['fails'][$ip] = $list;
        if (count($st['fails']) > 200) { $st['fails'] = array_slice($st['fails'], -100, null, true); }
        $ids = []; $max = 0;
        foreach ($rules as $r) {
            $n = (int)($r['params']['fail_count'] ?? 5); $win = (int)($r['params']['fail_minutes'] ?? 10) * 60;
            $reset = (int)($st['fail_reset'][$r['id']][$ip] ?? 0);
            $cnt = count(array_filter($list, function ($t) use ($now, $win, $reset) { return $now - $t <= $win && $t > $reset; }));
            if ($cnt >= $n) { $ids[] = $r['id']; $st['fail_reset'][$r['id']][$ip] = $now; $max = max($max, $cnt); }
        }
        return [$ids, $max];
    });
    if ($hit[0]) {
        notifyEmit('failed_logins', 'Серия неудачных входов: ' . $hit[1], 'IP: ' . $ip . "\nПоследний логин: " . (string)($e['u'] ?? ''), [], $hit[0]);
    }
}

/** Об обновлении: один раз на версию (релиз) или коммит (ветка main). */
function notifyOnUpdateCheck(array $state) {
    try {
        foreach (['release', 'main'] as $ch) {
            $r = $state[$ch] ?? null;
            if (!is_array($r) || empty($r['success']) || empty($r['available'])) { continue; }
            $mark = $ch . ':' . (string)($ch === 'release' ? ($r['version'] ?? '') : ($r['commit'] ?? ''));
            $fresh = notifyStateUpdate(function (&$st) use ($mark, $ch) {
                if (($st['upd'][$ch] ?? '') === $mark) { return false; }
                $st['upd'][$ch] = $mark;
                return true;
            });
            if ($fresh) {
                $what = $ch === 'release' ? 'релиз ' . ($r['version'] ?? '') : 'ранний доступ (main)';
                notifyEmit('update_available', 'Доступно обновление панели: ' . $what,
                    'Файлов к установке: ' . ((int)($r['new_count'] ?? 0) + (int)($r['changed_count'] ?? 0)));
            }
        }
    } catch (\Throwable $e) {
    }
}
