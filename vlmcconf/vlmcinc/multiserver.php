<?php
// ============================================
// ФАЙЛ: vlmcinc/multiserver.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Мультисервер (набросок, п. 46): список других панелей KMS Monitor и реальная проверка при добавлении — подключение к панели и вход под указанным логином и паролем
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';
require_once __DIR__ . '/notify.php';

if (basename($_SERVER['PHP_SELF'] ?? '') === 'multiserver.php') {
    http_response_code(403);
    exit('Access denied');
}

const MS_MAX = 10;

/** Список серверов из конфига. */
function msLoad($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $out = [];
    foreach ((array)($config['integrations']['multiservers'] ?? []) as $s) {
        if (is_array($s) && !empty($s['id'])) {
            $out[] = $s;
        }
    }
    return $out;
}

/** Данные для интерфейса (пароль не отдаётся). */
function msPublic(array $s) {
    return [
        'id' => (string)$s['id'],
        'name' => (string)($s['name'] ?? ''),
        'url' => (string)($s['url'] ?? ''),
        'user' => (string)($s['user'] ?? ''),
        'version' => (string)($s['version'] ?? ''),
        'last_time' => (string)($s['last_time'] ?? ''),
        'last_ok' => !empty($s['last_ok']),
        'last_text' => (string)($s['last_text'] ?? ''),
    ];
}

/** Адрес панели: http(s)://узел[:порт]/путь/ — без login.php/vlmcconf.php, с «/» в конце. Возвращает [url|'', код ошибки]. */
function msNormalizeUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 300) {
        return ['', 'ms_err_url'];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    $p = parse_url($url);
    if ($p === false || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
        return ['', 'ms_err_url'];
    }
    $host = $p['host'];
    if (!preg_match('/^[A-Za-z0-9.\-]+$/', $host) && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
        return ['', 'ms_err_url'];
    }
    $path = $p['path'] ?? '/';
    $path = preg_replace('#/(?:login|vlmcconf|index)\.php$#i', '/', $path);
    if (substr($path, -1) !== '/') {
        $path .= '/';
    }
    if (isset($p['port']) && ($p['port'] < 1 || $p['port'] > 65535)) {
        return ['', 'ms_err_url'];
    }
    return [strtolower($p['scheme']) . '://' . $host . (isset($p['port']) ? ':' . $p['port'] : '') . $path, ''];
}

/** Нельзя обращаться к служебным адресам: нулевой, link-local (облачные метаданные), multicast. */
function msBlockedHost($host) {
    $host = trim($host, '[]');
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (string)@gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;   // не разрешилось — сообщим об этом при подключении
    }
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return true;
    }
    if (strlen($bin) === 4) {
        $o = array_map('ord', str_split($bin));
        return $o[0] === 0 || ($o[0] === 169 && $o[1] === 254) || $o[0] >= 224;
    }
    return $ip === '::' || (ord($bin[0]) === 0xff) || (ord($bin[0]) === 0xfe && (ord($bin[1]) & 0xc0) === 0x80);
}

/** Один запрос curl с общими cookie. Возвращает [код, тело, заголовок Location, ошибка curl, errno]. */
function msRequest($ch, $url, $post = null) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'KMS-Monitor-Multiserver/1.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_COOKIEFILE => '',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    if ($raw === false) {
        return [0, '', '', curl_error($ch), $errno];
    }
    $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $size);
    $loc = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
    return [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), (string)substr($raw, $size), $loc, '', 0];
}

/**
 * Настоящая проверка: открывает страницу входа удалённой панели, убеждается, что это KMS Monitor,
 * входит под указанным логином и паролем, читает версию и выходит.
 * Возвращает ['ok' => bool, 'code' => 'ms_err_*'|'', 'detail' => string, 'url' => нормализованный адрес, 'version' => string].
 */
function msVerify($url, $user, $pass) {
    $res = ['ok' => false, 'code' => '', 'detail' => '', 'url' => '', 'version' => ''];
    if (!function_exists('curl_init')) {
        $res['code'] = 'ms_err_no_curl';
        return $res;
    }
    list($base, $err) = msNormalizeUrl($url);
    if ($err !== '') {
        $res['code'] = $err;
        return $res;
    }
    $host = (string)parse_url($base, PHP_URL_HOST);
    if (msBlockedHost($host)) {
        $res['code'] = 'ms_err_host';
        return $res;
    }
    // Если указан только узел, панель обычно лежит в /vlmcconf/
    $path = (string)parse_url($base, PHP_URL_PATH);
    $candidates = [$base];
    if ($path === '/') {
        $candidates[] = $base . 'vlmcconf/';
    }
    $ch = curl_init();
    $found = '';
    $lastErr = null;
    foreach ($candidates as $cand) {
        list($code, $body, , $cerr, $errno) = msRequest($ch, $cand . 'login.php');
        if ($errno !== 0) {
            $lastErr = [$cerr, $errno];
            continue;
        }
        if ($code === 200 && stripos($body, 'KMS Monitor') !== false && stripos($body, 'name="password"') !== false) {
            $found = $cand;
            break;
        }
        $lastErr = ['HTTP ' . $code, 0];
    }
    if ($found === '') {
        if ($lastErr !== null && $lastErr[1] !== 0) {
            $ssl = in_array($lastErr[1], [35, 51, 58, 60, 77, 83, 90], true);
            $res['code'] = $ssl ? 'ms_err_ssl' : 'ms_err_connect';
            $res['detail'] = mb_substr((string)$lastErr[0], 0, 120);
        } else {
            $res['code'] = 'ms_err_not_panel';
            $res['detail'] = (string)($lastErr[0] ?? '');
        }
        curl_close($ch);
        return $res;
    }
    $res['url'] = $found;
    list($code, $body, $loc) = msRequest($ch, $found . 'login.php', ['username' => $user, 'password' => $pass]);
    if ($code === 429) {
        $res['code'] = 'ms_err_locked';
    } elseif (($code === 302 || $code === 303) && stripos($loc, 'vlmcconf.php') !== false) {
        $res['ok'] = true;
        list($c2, $b2) = msRequest($ch, $found . 'vlmcconf.php');
        if ($c2 === 200 && preg_match('/(?:Версия|Version)\s+(\d+\.\d+\.\d+)/u', $b2, $m)) {
            $res['version'] = $m[1];
        }
        msRequest($ch, $found . 'logout.php');   // закрываем созданную сессию
    } elseif ($code === 200 && stripos($body, 'name="new_password"') !== false) {
        $res['ok'] = true;                         // логин и пароль верные, но панель просит сменить пароль
        $res['detail'] = 'must_change_password';
    } elseif ($code === 200) {
        $res['code'] = 'ms_err_login';
    } else {
        $res['code'] = 'ms_err_http';
        $res['detail'] = 'HTTP ' . $code;
    }
    curl_close($ch);
    return $res;
}

/** Сохраняет результат проверки в записи сервера. */
function msRemember($id, array $r) {
    $file = vlmcConfigFile();
    config_lock($file);
    $x = config_read($file);
    if ($x['status'] !== 'ok' || !is_array($x['config'])) {
        return;
    }
    $c = $x['config'];
    foreach ((array)($c['integrations']['multiservers'] ?? []) as $i => $s) {
        if (is_array($s) && ($s['id'] ?? '') === $id) {
            $c['integrations']['multiservers'][$i]['last_time'] = date('Y-m-d H:i:s');
            $c['integrations']['multiservers'][$i]['last_ok'] = $r['ok'];
            $c['integrations']['multiservers'][$i]['last_text'] = $r['ok'] ? '' : ($r['code'] . ($r['detail'] !== '' ? '|' . $r['detail'] : ''));
            if ($r['version'] !== '') {
                $c['integrations']['multiservers'][$i]['version'] = $r['version'];
            }
        }
    }
    save_config($file, $c);
}
