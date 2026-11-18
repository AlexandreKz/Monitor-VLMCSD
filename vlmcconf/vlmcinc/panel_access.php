<?php
// ============================================
// ФАЙЛ: vlmcinc/panel_access.php
// ВЕРСИЯ: 1.0.1
// ДАТА: 2026-11-18
// @description: Белый список IP для доступа к панели управления (п. 29): проверка адреса, CIDR, аварийное отключение
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';

if (basename($_SERVER['PHP_SELF'] ?? '') === 'panel_access.php') {
    http_response_code(403);
    exit('Access denied');
}

/** IPv4 в IPv6-записи (::ffff:1.2.3.4, так отдают адрес некоторые связки веб-сервера) приводится к обычному IPv4. */
function vlmcPanelUnmap($ip) {
    return preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', (string)$ip, $m) ? $m[1] : (string)$ip;
}

/** Адрес клиента (панель работает без обратного прокси — берём адрес соединения). */
function vlmcPanelClientIp() {
    $ip = vlmcPanelUnmap($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/** Адрес или сеть в виде строки: «10.0.0.5», «10.0.0.0/24», «fe80::/10». Возвращает нормализованную строку или null. */
function vlmcPanelNormalizeEntry($s) {
    $s = trim((string)$s);
    if ($s === '') {
        return null;
    }
    if (strpos($s, '/') === false) {
        return filter_var($s, FILTER_VALIDATE_IP) ? $s : null;
    }
    list($ip, $bits) = explode('/', $s, 2);
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !ctype_digit($bits)) {
        return null;
    }
    $max = strpos($ip, ':') !== false ? 128 : 32;
    return ((int)$bits >= 0 && (int)$bits <= $max) ? $ip . '/' . (int)$bits : null;
}

/** Входит ли адрес в запись списка (одиночный адрес или сеть CIDR, IPv4 и IPv6). */
function vlmcPanelIpMatches($ip, $entry) {
    $ip = vlmcPanelUnmap($ip);
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) {
        return false;
    }
    if (strpos($entry, '/') === false) {
        $e = @inet_pton($entry);
        return $e !== false && $e === $ipBin;
    }
    list($net, $bits) = explode('/', $entry, 2);
    $netBin = @inet_pton($net);
    if ($netBin === false || strlen($netBin) !== strlen($ipBin)) {
        return false;   // IPv4 против IPv6 не сравниваем
    }
    $bits = (int)$bits;
    $bytes = intdiv($bits, 8);
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

/** Петлевой адрес (сам сервер) всегда допускается, чтобы администратор не потерял доступ. */
function vlmcPanelIsLoopback($ip) {
    return $ip === '::1' || strpos($ip, '127.') === 0;
}

/** Настройки: ['enabled' => bool, 'entries' => [['ip' => '...', 'comment' => '...'], ...]]. */
function vlmcPanelAccessSettings($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $pw = isset($config['security']['panel_whitelist']) && is_array($config['security']['panel_whitelist'])
        ? $config['security']['panel_whitelist'] : [];
    $entries = [];
    foreach (($pw['entries'] ?? []) as $e) {
        $ip = vlmcPanelNormalizeEntry($e['ip'] ?? '');
        if ($ip !== null) {
            $entries[] = ['ip' => $ip, 'comment' => mb_substr((string)($e['comment'] ?? ''), 0, 100)];
        }
    }
    return ['enabled' => !empty($pw['enabled']), 'entries' => $entries];
}

/** Аварийное отключение белого списка: файл panel_whitelist.off в папке данных или переменная VLMC_PANEL_WHITELIST_OFF=1. */
function vlmcPanelAccessEmergencyOff() {
    if (getenv('VLMC_PANEL_WHITELIST_OFF') === '1') {
        return true;
    }
    return is_file(vlmcDataDir() . DIRECTORY_SEPARATOR . 'panel_whitelist.off');
}

/** Разрешён ли доступ к панели с адреса $ip (по умолчанию — с адреса клиента). */
function vlmcPanelAccessAllowed($ip = null, $settings = null) {
    $ip = $ip === null ? vlmcPanelClientIp() : $ip;
    if ($settings === null) {
        $settings = vlmcPanelAccessSettings();
    }
    if (empty($settings['enabled']) || vlmcPanelAccessEmergencyOff()) {
        return true;
    }
    if ($ip === '' || vlmcPanelIsLoopback($ip)) {
        return $ip !== '';
    }
    foreach ($settings['entries'] as $e) {
        if (vlmcPanelIpMatches($ip, $e['ip'])) {
            return true;
        }
    }
    return false;
}

/** Вызывается в начале страниц панели: чужому адресу отвечает 403 и завершает работу. */
function vlmcPanelAccessEnforce() {
    if (vlmcPanelAccessAllowed()) {
        return;
    }
    error_log('KMS Monitor: panel access denied for ' . vlmcPanelClientIp());
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>'
       . '<body style="font-family:sans-serif;text-align:center;padding:60px;color:#555">'
       . '<h1>403</h1><p>Access denied: your IP address is not allowed to open the control panel.</p>'
       . '<p>Доступ запрещён: ваш IP-адрес не входит в список разрешённых для панели управления.</p>'
       . '<p style="font-size:13px;color:#888">IP, как его видит сервер / IP as seen by the server: <b>' . htmlspecialchars(vlmcPanelClientIp() ?: (string)($_SERVER['REMOTE_ADDR'] ?? '?')) . '</b></p></body></html>';
    exit;
}
