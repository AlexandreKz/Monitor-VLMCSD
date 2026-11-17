<?php
// ============================================
// ФАЙЛ: vlmcinc/notify.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Уведомления (пункт 27): Email по SMTP (сокеты, без внешних пакетов) и Telegram; события журнала и обновлений, антиспам, тихие часы, история отправок; секреты хранятся зашифрованными
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'notify.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/http_client.php';

const NOTIFY_HISTORY_KEEP = 50;

/** События: важность и канал по умолчанию. Источник — для группировки в интерфейсе. */
function notifyCatalog() {
    return [
        'failed_logins'    => ['sev' => 'high', 'src' => 'audit',  'mail' => true,  'tg' => true],
        'access_denied'    => ['sev' => 'mid',  'src' => 'audit',  'mail' => true,  'tg' => false],
        'user_deleted'     => ['sev' => 'high', 'src' => 'audit',  'mail' => true,  'tg' => true],
        'update_available' => ['sev' => 'low',  'src' => 'update', 'mail' => true,  'tg' => false],
    ];
}

function notifyFile()    { return vlmcDataFile('notify.json'); }
function notifyState()   { return vlmcDataFile('notify_state.json'); }
function notifyHistoryFile() { return vlmcDataFile('notify_history.jsonl'); }
function notifyKeyFile() { return vlmcDataFile('notify.key'); }

function notifyDefaults() {
    $events = [];
    foreach (notifyCatalog() as $id => $e) {
        $events[$id] = ['mail' => $e['mail'], 'tg' => $e['tg']];
    }
    return [
        'email' => ['enabled' => false, 'host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => '', 'from' => '', 'to' => ''],
        'telegram' => ['enabled' => false, 'token' => '', 'chat' => ''],
        'events' => $events,
        'rules' => ['fail_count' => 5, 'fail_minutes' => 10, 'antispam_minutes' => 30, 'quiet_enabled' => false, 'quiet_from' => '23:00', 'quiet_to' => '07:00']
    ];
}

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

// ---------- настройки ----------

/** Настройки; секреты остаются в зашифрованном виде (поля pass и token). */
function notifyLoad() {
    $d = notifyDefaults();
    $f = notifyFile();
    $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($j)) {
        return $d;
    }
    foreach (['email', 'telegram', 'rules'] as $sec) {
        if (isset($j[$sec]) && is_array($j[$sec])) {
            $d[$sec] = array_merge($d[$sec], array_intersect_key($j[$sec], $d[$sec]));
        }
    }
    if (isset($j['events']) && is_array($j['events'])) {
        foreach ($d['events'] as $id => $v) {
            if (isset($j['events'][$id]) && is_array($j['events'][$id])) {
                $d['events'][$id] = ['mail' => !empty($j['events'][$id]['mail']), 'tg' => !empty($j['events'][$id]['tg'])];
            }
        }
    }
    return $d;
}

function notifySave(array $s) {
    $f = notifyFile();
    $ok = @file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    if ($ok) {
        @chmod($f, 0600);
    }
    return $ok;
}

/** Настройки для интерфейса: без секретов, с признаками «задан». */
function notifyPublic(array $s) {
    $s['email']['has_pass'] = $s['email']['pass'] !== '';
    $s['telegram']['has_token'] = $s['telegram']['token'] !== '';
    unset($s['email']['pass'], $s['telegram']['token']);
    return $s;
}

function notifyValidTime($t) {
    return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
}

function notifyAddress($s) {
    return preg_match('/<([^<>\s]+)>/', (string)$s, $m) ? $m[1] : trim((string)$s);
}

/** Проверка и сборка настроек из присланных полей. Возвращает [настройки|null, код ошибки]. */
function notifyBuild(array $in, array $old) {
    $s = $old;
    $e = &$s['email'];
    $e['enabled'] = !empty($in['mail_on']);
    $e['host'] = trim((string)($in['mail_host'] ?? ''));
    $e['port'] = (int)($in['mail_port'] ?? 0);
    $e['secure'] = in_array(($in['mail_secure'] ?? ''), ['none', 'tls', 'ssl'], true) ? $in['mail_secure'] : 'tls';
    $e['user'] = trim((string)($in['mail_user'] ?? ''));
    if (($in['mail_pass'] ?? '') !== '') {
        $e['pass'] = notifyEncrypt((string)$in['mail_pass']);
    }
    $e['from'] = trim((string)($in['mail_from'] ?? ''));
    $e['to'] = trim((string)($in['mail_to'] ?? ''));
    if ($e['enabled']) {
        if ($e['host'] === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $e['host'])) { return [null, 'notify_err_host']; }
        if ($e['port'] < 1 || $e['port'] > 65535) { return [null, 'notify_err_port']; }
        if (!filter_var(notifyAddress($e['from']), FILTER_VALIDATE_EMAIL)) { return [null, 'notify_err_from']; }
        $rcpt = array_filter(array_map('trim', explode(',', $e['to'])));
        if (!$rcpt) { return [null, 'notify_err_to']; }
        foreach ($rcpt as $r) {
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) { return [null, 'notify_err_to']; }
        }
    }
    unset($e);

    $t = &$s['telegram'];
    $t['enabled'] = !empty($in['tg_on']);
    if (($in['tg_token'] ?? '') !== '') {
        if (!preg_match('/^\d+:[A-Za-z0-9_\-]+$/', trim((string)$in['tg_token']))) { return [null, 'notify_err_token']; }
        $t['token'] = notifyEncrypt(trim((string)$in['tg_token']));
    }
    $t['chat'] = trim((string)($in['tg_chat'] ?? ''));
    if ($t['enabled']) {
        if ($t['token'] === '') { return [null, 'notify_err_token']; }
        if (!preg_match('/^(-?\d{1,20}|@[A-Za-z0-9_]{3,64})$/', $t['chat'])) { return [null, 'notify_err_chat']; }
    }
    unset($t);

    $ev = json_decode((string)($in['events'] ?? ''), true);
    if (is_array($ev)) {
        foreach (notifyCatalog() as $id => $c) {
            if (isset($ev[$id]) && is_array($ev[$id])) {
                $s['events'][$id] = ['mail' => !empty($ev[$id]['mail']), 'tg' => !empty($ev[$id]['tg'])];
            }
        }
    }
    $r = json_decode((string)($in['rules'] ?? ''), true);
    if (is_array($r)) {
        $n = (int)($r['fail_count'] ?? 0); $m = (int)($r['fail_minutes'] ?? 0); $a = (int)($r['antispam_minutes'] ?? -1);
        if ($n < 2 || $n > 100 || $m < 1 || $m > 1440 || $a < 0 || $a > 1440) { return [null, 'notify_err_rules']; }
        $qf = (string)($r['quiet_from'] ?? ''); $qt = (string)($r['quiet_to'] ?? '');
        if (!notifyValidTime($qf) || !notifyValidTime($qt)) { return [null, 'notify_err_time']; }
        $s['rules'] = ['fail_count' => $n, 'fail_minutes' => $m, 'antispam_minutes' => $a,
            'quiet_enabled' => !empty($r['quiet_enabled']), 'quiet_from' => $qf, 'quiet_to' => $qt];
    }
    return [$s, ''];
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
        $from = notifyAddress($c['from']);
        notifySmtpCmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        $rcpt = array_filter(array_map('trim', explode(',', $c['to'])));
        foreach ($rcpt as $r) {
            notifySmtpCmd($fp, 'RCPT TO:<' . $r . '>', [250, 251]);
        }
        notifySmtpCmd($fp, 'DATA', [354]);
        $fromHdr = notifyClean($c['from'], 200);
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

function notifyHistoryAdd($event, $title, $channels, $ok, $error = '') {
    $f = notifyHistoryFile();
    $line = json_encode(['t' => time(), 'e' => $event, 'title' => notifyClean($title, 160), 'ch' => $channels, 'ok' => $ok, 'err' => $error], JSON_UNESCAPED_UNICODE);
    @file_put_contents($f, $line . "\n", FILE_APPEND | LOCK_EX);
    @chmod($f, 0600);
    $lines = @file($f, FILE_IGNORE_NEW_LINES);
    if (is_array($lines) && count($lines) > NOTIFY_HISTORY_KEEP * 2) {
        @file_put_contents($f, implode("\n", array_slice($lines, -NOTIFY_HISTORY_KEEP)) . "\n", LOCK_EX);
    }
}

function notifyHistory($limit = 10) {
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
    if (!$fh) { return $fn([]); }
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
    return $a <= $b ? ($now >= $a && $now < $b) : ($now >= $a || $now < $b);
}

// ---------- события ----------

/** Отправляет событие по включённым каналам (после ответа пользователю, чтобы не задерживать панель). */
function notifyEvent($event, $title, $text = '') {
    try {
        $cat = notifyCatalog();
        if (!isset($cat[$event])) { return; }
        $s = notifyLoad();
        $useMail = $s['email']['enabled'] && !empty($s['events'][$event]['mail']);
        $useTg = $s['telegram']['enabled'] && !empty($s['events'][$event]['tg']);
        if (!$useMail && !$useTg) { return; }
        if ($cat[$event]['sev'] !== 'high' && notifyInQuiet($s['rules'])) { return; }

        $window = (int)$s['rules']['antispam_minutes'] * 60;
        $extra = notifyStateUpdate(function (&$st) use ($event, $window) {
            $now = time();
            $last = (int)($st['last'][$event] ?? 0);
            if ($window > 0 && $now - $last < $window) {
                $st['suppressed'][$event] = (int)($st['suppressed'][$event] ?? 0) + 1;
                return -1;
            }
            $n = (int)($st['suppressed'][$event] ?? 0);
            $st['last'][$event] = $now;
            $st['suppressed'][$event] = 0;
            return $n;
        });
        if ($extra < 0) { return; }

        $host = (string)($_SERVER['HTTP_HOST'] ?? gethostname());
        $body = $title . "\n\n" . $text . ($extra > 0 ? "\n\n(+" . $extra . ")" : '') . "\n\nKMS Monitor, " . $host . ', ' . date('Y-m-d H:i:s');
        $subject = '[KMS Monitor] ' . $title;
        $send = function () use ($event, $title, $body, $subject, $s, $useMail, $useTg) {
            $chs = []; $errs = [];
            if ($useMail) {
                [$ok, $er] = notifySendEmail($s['email'], $subject, $body);
                $chs[] = 'mail'; if (!$ok) { $errs[] = 'mail: ' . $er; }
            }
            if ($useTg) {
                [$ok, $er] = notifySendTelegram($s['telegram'], $subject . "\n\n" . $body);
                $chs[] = 'tg'; if (!$ok) { $errs[] = 'tg: ' . $er; }
            }
            notifyHistoryAdd($event, $title, $chs, !$errs, implode('; ', $errs));
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

/** Вызывается из журнала для каждой записи. */
function notifyOnAudit(array $e) {
    $a = (string)($e['a'] ?? '');
    $who = (string)($e['u'] ?? '') . ' (' . (string)($e['ip'] ?? '') . ')';
    if ($a === 'access_denied') {
        notifyEvent('access_denied', 'Отказ в доступе: ' . $who, (string)($e['d'] ?? ''));
    } elseif ($a === 'delete_user' && ($e['r'] ?? '') === 'ok') {
        notifyEvent('user_deleted', 'Удалён пользователь панели', 'Выполнил: ' . $who . "\n" . (string)($e['d'] ?? ''));
    } elseif ($a === 'login_locked') {
        notifyEvent('failed_logins', 'Вход заблокирован после неудачных попыток: ' . $who, '');
    } elseif ($a === 'login_failed') {
        $s = notifyLoad();
        if (!$s['email']['enabled'] && !$s['telegram']['enabled']) { return; }
        $n = (int)$s['rules']['fail_count']; $win = (int)$s['rules']['fail_minutes'] * 60;
        $ip = (string)($e['ip'] ?? '');
        $hit = notifyStateUpdate(function (&$st) use ($ip, $n, $win) {
            $now = time();
            $list = array_values(array_filter((array)($st['fails'][$ip] ?? []), function ($t) use ($now, $win) { return $now - $t <= $win; }));
            $list[] = $now;
            if (count($list) >= $n) { unset($st['fails'][$ip]); return count($list); }
            $st['fails'][$ip] = $list;
            if (count($st['fails']) > 200) { $st['fails'] = array_slice($st['fails'], -100, null, true); }
            return 0;
        });
        if ($hit > 0) {
            notifyEvent('failed_logins', 'Серия неудачных входов: ' . $hit . ' за ' . (int)$s['rules']['fail_minutes'] . ' мин', 'IP: ' . $ip . "\nПоследний логин: " . (string)($e['u'] ?? ''));
        }
    }
}

/** Об обновлении: один раз на версию (релиз) или коммит (ветка main). */
function notifyOnUpdateCheck(array $state) {
    try {
        $s = notifyLoad();
        if (!$s['email']['enabled'] && !$s['telegram']['enabled']) { return; }
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
                notifyEvent('update_available', 'Доступно обновление панели: ' . $what,
                    'Файлов к установке: ' . ((int)($r['new_count'] ?? 0) + (int)($r['changed_count'] ?? 0)));
            }
        }
    } catch (\Throwable $e) {
    }
}
