<?php
// ============================================
// ФАЙЛ: vlmcinc/api_core.php
// ВЕРСИЯ: 1.0.1
// ДАТА: 2026-11-18
// @description: REST API для внешних интеграций (п. 25): ключи доступа, лимит запросов, обработчики, описание OpenAPI
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';
require_once __DIR__ . '/panel_access.php';   // сравнение адресов и сетей CIDR

if (basename($_SERVER['PHP_SELF'] ?? '') === 'api_core.php') {
    http_response_code(403);
    exit('Access denied');
}

/** Области доступа ключа: что ключ может читать. */
function apiScopes() {
    return ['status', 'devices', 'groups', 'log', 'stats', 'security'];
}

function apiKeysFile() {
    return vlmcDataFile('api_keys.json');
}

function apiRateFile() {
    return vlmcDataFile('api_rate.json');
}

/** Настройки API из конфига: enabled, rate_limit (запросов в минуту на ключ, 0 = без ограничения), cors_origins. */
function apiSettings($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $a = isset($config['api']) && is_array($config['api']) ? $config['api'] : [];
    $limit = isset($a['rate_limit']) ? (int)$a['rate_limit'] : 60;
    $origins = [];
    foreach ((array)($a['cors_origins'] ?? []) as $o) {
        $o = trim((string)$o);
        if ($o === '*' || preg_match('#^https?://[A-Za-z0-9.\-:\[\]]+$#', $o)) {
            $origins[] = $o;
        }
    }
    return [
        'enabled' => !empty($a['enabled']),
        'rate_limit' => max(0, min(100000, $limit)),
        'cors_origins' => $origins,
    ];
}

// ---------- ключи ----------

function apiKeysList() {
    $r = config_read(apiKeysFile());
    return ($r['status'] === 'ok' && is_array($r['config'])) ? array_values($r['config']) : [];
}

/** Ключи для показа в интерфейсе (без хеша). */
function apiKeysView() {
    return array_map(function ($k) {
        return [
            'id' => $k['id'] ?? '', 'name' => $k['name'] ?? '', 'prefix' => $k['prefix'] ?? '', 'scopes' => $k['scopes'] ?? [],
            'allowed_ips' => $k['allowed_ips'] ?? [], 'expires' => $k['expires'] ?? '', 'enabled' => !empty($k['enabled']),
            'created' => $k['created'] ?? '', 'created_by' => $k['created_by'] ?? '', 'last_used' => $k['last_used'] ?? '',
            'last_ip' => $k['last_ip'] ?? '', 'requests' => (int)($k['requests'] ?? 0),
        ];
    }, apiKeysList());
}

function apiKeysSave(array $keys) {
    return save_config(apiKeysFile(), array_values($keys));
}

function apiHashKey($token) {
    return hash('sha256', (string)$token);
}

/**
 * Создаёт ключ. Сам ключ возвращается один раз (в базе хранится только хеш).
 * @return array ['success' => bool, 'key' => string, 'id' => string, 'code' => string]
 */
function apiKeyCreate($name, array $scopes, array $allowedIps, $expires, $user) {
    $name = trim((string)$name);
    if ($name === '' || mb_strlen($name) > 60) {
        return ['success' => false, 'code' => 'bad_name'];
    }
    $scopes = array_values(array_intersect(apiScopes(), $scopes));
    if (!$scopes) {
        return ['success' => false, 'code' => 'no_scopes'];
    }
    $ips = [];
    foreach ($allowedIps as $ip) {
        $n = vlmcPanelNormalizeEntry($ip);
        if ($ip !== '' && $n === null) {
            return ['success' => false, 'code' => 'bad_ip'];
        }
        if ($n !== null) {
            $ips[] = $n;
        }
    }
    $expires = trim((string)$expires);
    if ($expires !== '') {
        $ts = strtotime($expires . ' 23:59:59');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) || $ts === false || $ts < time()) {
            return ['success' => false, 'code' => 'bad_expires'];
        }
    }
    $file = apiKeysFile();
    config_lock($file);
    $keys = apiKeysList();
    if (count($keys) >= 50) {
        return ['success' => false, 'code' => 'too_many'];
    }
    $token = 'kms_' . bin2hex(random_bytes(20));
    $id = substr(bin2hex(random_bytes(4)), 0, 8);
    $keys[] = [
        'id' => $id,
        'name' => $name,
        'prefix' => substr($token, 0, 8),
        'hash' => apiHashKey($token),
        'scopes' => $scopes,
        'allowed_ips' => $ips,
        'expires' => $expires,
        'enabled' => true,
        'created' => date('Y-m-d H:i:s'),
        'created_by' => (string)$user,
        'last_used' => '',
        'last_ip' => '',
        'requests' => 0,
    ];
    if (!apiKeysSave($keys)) {
        return ['success' => false, 'code' => 'save_error'];
    }
    return ['success' => true, 'key' => $token, 'id' => $id, 'code' => 'created'];
}

/** Включить или выключить ключ. */
function apiKeySetEnabled($id, $enabled) {
    $file = apiKeysFile();
    config_lock($file);
    $keys = apiKeysList();
    $found = false;
    foreach ($keys as &$k) {
        if (($k['id'] ?? '') === $id) {
            $k['enabled'] = (bool)$enabled;
            $found = true;
        }
    }
    unset($k);
    return $found && apiKeysSave($keys);
}

function apiKeyDelete($id) {
    $file = apiKeysFile();
    config_lock($file);
    $keys = apiKeysList();
    $left = array_values(array_filter($keys, function ($k) use ($id) { return ($k['id'] ?? '') !== $id; }));
    if (count($left) === count($keys)) {
        return false;
    }
    return apiKeysSave($left);
}

/** Поиск ключа по предъявленному значению. */
function apiKeyFind($token) {
    if (!is_string($token) || !preg_match('/^kms_[0-9a-f]{40}$/', $token)) {
        return null;
    }
    $h = apiHashKey($token);
    foreach (apiKeysList() as $k) {
        if (isset($k['hash']) && hash_equals($k['hash'], $h)) {
            return $k;
        }
    }
    return null;
}

/** Отметка использования (не чаще раза в минуту на ключ, чтобы не писать файл на каждый запрос). */
function apiKeyTouch($id, $ip) {
    $file = apiKeysFile();
    config_lock($file);
    $keys = apiKeysList();
    $changed = false;
    foreach ($keys as &$k) {
        if (($k['id'] ?? '') === $id) {
            $k['requests'] = (int)($k['requests'] ?? 0) + 1;
            $stale = ($k['last_used'] ?? '') === '' || (time() - (int)strtotime($k['last_used'])) >= 60;
            if ($stale || ($k['last_ip'] ?? '') !== $ip) {
                $k['last_used'] = date('Y-m-d H:i:s');
                $k['last_ip'] = $ip;
            }
            $changed = true;
        }
    }
    unset($k);
    // счётчик запросов копится в памяти запроса; на диск пишем при обновлении метки
    if ($changed) {
        apiKeysSave($keys);
    }
}

// ---------- лимит запросов ----------

/**
 * Лимит запросов в минуту. $bucket — «k:<id ключа>» или «ip:<адрес>» (неудачные попытки).
 * Возвращает ['ok' => bool, 'limit' => int, 'remaining' => int, 'reset' => секунд до сброса].
 */
function apiRateHit($bucket, $limit) {
    if ($limit <= 0) {
        return ['ok' => true, 'limit' => 0, 'remaining' => -1, 'reset' => 0];
    }
    $file = apiRateFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        return ['ok' => true, 'limit' => $limit, 'remaining' => $limit, 'reset' => 60];
    }
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return ['ok' => true, 'limit' => $limit, 'remaining' => $limit, 'reset' => 60];   // лимит не мешает работе при сбое записи
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    $window = $now - ($now % 60);
    foreach ($data as $k => $v) {
        if (!is_array($v) || ($v[0] ?? 0) < $window - 120) {
            unset($data[$k]);   // устаревшие окна
        }
    }
    if (!isset($data[$bucket]) || $data[$bucket][0] !== $window) {
        $data[$bucket] = [$window, 0];
    }
    $data[$bucket][1]++;
    $count = $data[$bucket][1];
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return ['ok' => $count <= $limit, 'limit' => $limit, 'remaining' => max(0, $limit - $count), 'reset' => $window + 60 - $now];
}

// ---------- ответы ----------

function apiRespond($status, array $body, array $headers = []) {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    foreach ($headers as $k => $v) {
        header($k . ': ' . $v);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function apiError($status, $code, $message, array $headers = []) {
    apiRespond($status, ['error' => ['code' => $code, 'message' => $message]], $headers);
}

/** Ключ из заголовка Authorization: Bearer … или X-API-Key. */
function apiTokenFromRequest() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $h = $v;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim((string)$h), $m)) {
        return $m[1];
    }
    return (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
}

// ---------- данные ----------

function apiLogFile($config) {
    $p = (string)($config['logPath'] ?? 'vlmcsd.log');
    if (strpos($p, '/') !== 0 && !preg_match('/^[A-Za-z]:[\\\\\/]/', $p)) {
        $p = dirname(__DIR__, 2) . '/' . ltrim($p, './');
    }
    return $p;
}

function apiKnownDevices($config) {
    $known = [];
    foreach (($config['devices'] ?? []) as $group => $list) {
        foreach ((array)$list as $d) {
            if (isset($d['name'])) {
                $known[$d['name']] = ['group' => (string)$group, 'comment' => (string)($d['comment'] ?? ''), 'added' => (string)($d['added'] ?? '')];
            }
        }
    }
    return $known;
}

/** Последние события запросов KMS из конца лога: time, device, ip, product. */
function apiLogTail($logFile, $limit, $device, $since) {
    if (!is_file($logFile)) {
        return [];
    }
    $size = filesize($logFile);
    $fh = @fopen($logFile, 'rb');
    if ($fh === false) {
        return [];
    }
    $chunk = min($size, 2 * 1024 * 1024);   // читаем не больше 2 МБ с конца
    fseek($fh, $size - $chunk);
    $text = stream_get_contents($fh);
    fclose($fh);
    $lines = explode("\n", $text);
    if ($chunk < $size) {
        array_shift($lines);   // первая строка может быть обрезана
    }
    $events = [];
    $ip = '';
    $time = '';
    foreach ($lines as $line) {
        if (strpos($line, 'connection accepted: ') !== false) {
            $ip = preg_match('/connection accepted: ([0-9a-fA-F\.:]+?):\d+\.?\s*$/', $line, $m) ? $m[1] : '';
            if ($ip === '' && preg_match('/connection accepted: ([\d\.]+):\d+/', $line, $m)) {
                $ip = $m[1];
            }
            $time = preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $t) ? $t[1] : '';
            continue;
        }
        if (strpos($line, ' request from ') === false) {
            continue;
        }
        if (!preg_match('/KMS v([46])\.0 request from (\S+) for (.+?)\s*$/', $line, $m)) {
            continue;
        }
        if ($device !== '' && strcasecmp($m[2], $device) !== 0) {
            continue;
        }
        if ($since !== '' && $time !== '' && $time < $since) {
            continue;
        }
        $events[] = ['time' => $time, 'device' => $m[2], 'ip' => $ip, 'product' => $m[3], 'kms_version' => (int)$m[1]];
    }
    return array_slice(array_reverse($events), 0, $limit);
}

/** Обработка запроса к API по пути (без префикса /v1). Вызывается после проверки ключа. */
function apiRoute($method, $path, array $key, $config) {
    require_once __DIR__ . '/log_cache.php';
    require_once __DIR__ . '/reports.php';
    $logFile = apiLogFile($config);
    $scopes = $key['scopes'] ?? [];
    $need = function ($scope) use ($scopes) {
        if (!in_array($scope, $scopes, true)) {
            apiError(403, 'forbidden', 'The API key has no access to the "' . $scope . '" scope');
        }
    };
    $known = apiKnownDevices($config);

    if ($method !== 'GET') {
        apiError(405, 'method_not_allowed', 'Only GET requests are supported', ['Allow' => 'GET, OPTIONS']);
    }

    if ($path === '/status') {
        $need('status');
        $totalDevices = count($known);
        return [
            'version' => defined('VLMC_VERSION') ? VLMC_VERSION : '',
            'api_version' => 'v1',
            'time' => date('c'),
            'log_file_exists' => is_file($logFile),
            'log_size_bytes' => is_file($logFile) ? (int)filesize($logFile) : 0,
            'log_updated' => is_file($logFile) ? date('c', (int)filemtime($logFile)) : null,
            'groups' => count($config['devices'] ?? []),
            'known_devices' => $totalDevices,
        ];
    }

    if ($path === '/groups') {
        $need('groups');
        $out = [];
        foreach (($config['devices'] ?? []) as $group => $list) {
            $color = $config['groupColors'][$group] ?? [];
            $out[] = [
                'name' => (string)$group,
                'devices' => count((array)$list),
                'icon' => is_array($color) ? ($color['icon'] ?? '') : '',
                'color' => is_array($color) ? ($color['color'] ?? '') : '',
            ];
        }
        return $out;
    }

    if ($path === '/devices') {
        $need('devices');
        $summary = reportDeviceSummary($logFile);
        $group = trim((string)($_GET['group'] ?? ''));
        $out = [];
        foreach ($known as $name => $d) {
            if ($group !== '' && strcasecmp($d['group'], $group) !== 0) {
                continue;
            }
            $s = $summary[$name] ?? null;
            $out[] = $d + [
                'name' => $name,
                'requests' => $s['count'] ?? 0,
                'first_seen' => $s['first'] ?? null,
                'last_seen' => $s['last'] ?? null,
            ];
        }
        return $out;
    }

    if (preg_match('#^/devices/([^/]+)$#', $path, $m)) {
        $need('devices');
        $name = rawurldecode($m[1]);
        $summary = reportDeviceSummary($logFile);
        if (!isset($known[$name]) && !isset($summary[$name])) {
            apiError(404, 'not_found', 'Device not found');
        }
        $s = $summary[$name] ?? null;
        return ($known[$name] ?? ['group' => null, 'comment' => '', 'added' => '']) + [
            'name' => $name,
            'known' => isset($known[$name]),
            'requests' => $s['count'] ?? 0,
            'first_seen' => $s['first'] ?? null,
            'last_seen' => $s['last'] ?? null,
            'ips' => $s['ip_list'] ?? [],
        ];
    }

    if ($path === '/log') {
        $need('log');
        $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
        $device = trim((string)($_GET['device'] ?? ''));
        $since = trim((string)($_GET['since'] ?? ''));
        if ($since !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $since)) {
            apiError(400, 'bad_request', 'Parameter "since" must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS');
        }
        return apiLogTail($logFile, $limit, $device, $since);
    }

    if ($path === '/stats') {
        $need('stats');
        $summary = reportDeviceSummary($logFile);
        $requests = 0;
        foreach ($summary as $s) {
            $requests += $s['count'];
        }
        $unknown = 0;
        foreach ($summary as $name => $s) {
            if (!isset($known[$name])) {
                $unknown++;
            }
        }
        $products = [];
        foreach (reportProducts($logFile) as $row) {
            $products[] = ['product' => $row['product'] ?? '', 'devices' => $row['devices'] ?? ($row['count'] ?? 0)];
            if (count($products) >= 10) {
                break;
            }
        }
        return [
            'requests_total' => $requests,
            'devices_in_log' => count($summary),
            'known_devices' => count($known),
            'unknown_devices' => $unknown,
            'top_products' => $products,
        ];
    }

    if ($path === '/unknown-devices') {
        $need('security');
        $out = [];
        foreach (reportDeviceSummary($logFile) as $name => $s) {
            if (!isset($known[$name])) {
                $out[] = ['name' => $name, 'requests' => $s['count'], 'first_seen' => $s['first'], 'last_seen' => $s['last'], 'last_ip' => $s['last_ip']];
            }
        }
        return $out;
    }

    apiError(404, 'not_found', 'Unknown endpoint');
}

/** Описание API в формате OpenAPI 3.0 (для раздела «API» и внешних инструментов). */
function apiOpenApiSpec($serverUrl) {
    $ref = function ($name) { return ['$ref' => '#/components/schemas/' . $name]; };
    $ok = function ($desc, $schema) {
        return ['200' => ['description' => $desc, 'content' => ['application/json' => ['schema' => $schema]]],
                '401' => ['description' => 'Missing, invalid or expired key'],
                '403' => ['description' => 'Key has no access to this scope or IP address is not allowed'],
                '429' => ['description' => 'Rate limit exceeded (see Retry-After)']];
    };
    $arr = function ($schema) { return ['type' => 'array', 'items' => $schema]; };
    $sec = [['bearerAuth' => []]];
    return [
        'openapi' => '3.0.3',
        'info' => [
            'title' => 'KMS Monitor API',
            'version' => '1.0.0',
            'description' => 'Read-only REST API of the KMS Monitor panel. Create a key in the panel (API section) and pass it as "Authorization: Bearer <key>" or "X-API-Key". Requests are limited per key (default 60 per minute).',
        ],
        'servers' => [['url' => $serverUrl]],
        'security' => $sec,
        'paths' => [
            '/status' => ['get' => ['tags' => ['status'], 'summary' => 'Panel and log status', 'description' => 'Scope: status', 'responses' => $ok('Status', $ref('Status'))]],
            '/groups' => ['get' => ['tags' => ['groups'], 'summary' => 'Device groups', 'description' => 'Scope: groups', 'responses' => $ok('Groups', $arr($ref('Group')))]],
            '/devices' => ['get' => ['tags' => ['devices'], 'summary' => 'Known devices', 'description' => 'Scope: devices',
                'parameters' => [['name' => 'group', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Filter by group name']],
                'responses' => $ok('Devices', $arr($ref('Device')))]],
            '/devices/{name}' => ['get' => ['tags' => ['devices'], 'summary' => 'One device with its IP addresses', 'description' => 'Scope: devices',
                'parameters' => [['name' => 'name', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                'responses' => $ok('Device', $ref('DeviceDetail')) + ['404' => ['description' => 'Device not found']]]],
            '/log' => ['get' => ['tags' => ['log'], 'summary' => 'Latest KMS requests from the log', 'description' => 'Scope: log',
                'parameters' => [
                    ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 100, 'maximum' => 500]],
                    ['name' => 'device', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ['name' => 'since', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'YYYY-MM-DD or YYYY-MM-DD HH:MM:SS'],
                ],
                'responses' => $ok('Events, newest first', $arr($ref('LogEvent')))]],
            '/stats' => ['get' => ['tags' => ['stats'], 'summary' => 'Summary statistics', 'description' => 'Scope: stats', 'responses' => $ok('Statistics', $ref('Stats'))]],
            '/unknown-devices' => ['get' => ['tags' => ['security'], 'summary' => 'Devices seen in the log but not added to the panel', 'description' => 'Scope: security', 'responses' => $ok('Unknown devices', $arr($ref('UnknownDevice')))]],
        ],
        'components' => [
            'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'API key (kms_…)']],
            'schemas' => [
                'Status' => ['type' => 'object', 'properties' => [
                    'version' => ['type' => 'string', 'example' => '6.0.0'], 'api_version' => ['type' => 'string', 'example' => 'v1'],
                    'time' => ['type' => 'string', 'format' => 'date-time'], 'log_file_exists' => ['type' => 'boolean'],
                    'log_size_bytes' => ['type' => 'integer'], 'log_updated' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'groups' => ['type' => 'integer'], 'known_devices' => ['type' => 'integer']]],
                'Group' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'devices' => ['type' => 'integer'], 'icon' => ['type' => 'string'], 'color' => ['type' => 'string']]],
                'Device' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'], 'group' => ['type' => 'string'], 'comment' => ['type' => 'string'], 'added' => ['type' => 'string'],
                    'requests' => ['type' => 'integer'], 'first_seen' => ['type' => 'string', 'nullable' => true], 'last_seen' => ['type' => 'string', 'nullable' => true]]],
                'DeviceDetail' => ['allOf' => [$ref('Device'), ['type' => 'object', 'properties' => ['known' => ['type' => 'boolean'], 'ips' => ['type' => 'array', 'items' => ['type' => 'string']]]]]],
                'LogEvent' => ['type' => 'object', 'properties' => [
                    'time' => ['type' => 'string', 'example' => '2026-11-18 10:15:03'], 'device' => ['type' => 'string'], 'ip' => ['type' => 'string'],
                    'product' => ['type' => 'string'], 'kms_version' => ['type' => 'integer', 'enum' => [4, 6]]]],
                'Stats' => ['type' => 'object', 'properties' => [
                    'requests_total' => ['type' => 'integer'], 'devices_in_log' => ['type' => 'integer'], 'known_devices' => ['type' => 'integer'],
                    'unknown_devices' => ['type' => 'integer'],
                    'top_products' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['product' => ['type' => 'string'], 'devices' => ['type' => 'integer']]]]]],
                'UnknownDevice' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'], 'requests' => ['type' => 'integer'], 'first_seen' => ['type' => 'string'], 'last_seen' => ['type' => 'string'], 'last_ip' => ['type' => 'string']]],
            ],
        ],
    ];
}
