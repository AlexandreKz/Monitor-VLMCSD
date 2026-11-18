<?php
// ============================================
// ФАЙЛ: vlmcconf/api.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Точка входа REST API: /vlmcconf/api.php/v1/<ресурс> (или ?r=/v1/<ресурс>). Только чтение, доступ по ключу.
// ============================================

define('VLMCS_API', true);

require_once __DIR__ . '/vlmcinc/version.php';
require_once __DIR__ . '/vlmcinc/paths.php';
require_once __DIR__ . '/vlmcinc/safe_json.php';
require_once __DIR__ . '/vlmcinc/api_core.php';

$configRead = config_read(vlmcConfigFile());
$config = ($configRead['status'] === 'ok' && is_array($configRead['config'])) ? $configRead['config'] : [];
$settings = apiSettings($config);

// CORS: ответ только для разрешённых источников (ключ передаётся заголовком, куки не используются)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $settings['cors_origins']) {
    if (in_array('*', $settings['cors_origins'], true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif (in_array($origin, $settings['cors_origins'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Путь запроса: PATH_INFO (/v1/status) или параметр r
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '' && isset($_GET['r'])) {
    $path = (string)$_GET['r'];
}
if ($path === '' && !empty($_SERVER['REQUEST_URI'])) {
    $u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pos = is_string($u) ? strpos($u, 'api.php/') : false;
    if ($pos !== false) {
        $path = substr($u, $pos + 7);
    }
}
$path = '/' . trim($path, '/');
if (strpos($path, '/v1') !== 0) {
    apiError(404, 'not_found', 'Use /vlmcconf/api.php/v1/<resource>');
}
$path = substr($path, 3) ?: '/';

// Описание API открыто (без ключа), чтобы инструменты могли его загрузить
if ($path === '/openapi.json') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/vlmcconf/api.php';
    $spec = apiOpenApiSpec(($https ? 'https://' : 'http://') . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) . $script . '/v1');
    apiRespond(200, $spec);
}

if (!$settings['enabled']) {
    apiError(503, 'api_disabled', 'The API is disabled in the panel settings');
}

$ip = vlmcPanelClientIp();

// Защита от перебора ключей: не больше 20 неудачных попыток в минуту с одного адреса
$bad = function ($status, $code, $message) use ($ip) {
    $r = apiRateHit('ip:' . $ip, 20);
    if (!$r['ok']) {
        apiError(429, 'too_many_attempts', 'Too many failed attempts, try again later', ['Retry-After' => max(1, $r['reset'])]);
    }
    apiError($status, $code, $message, $status === 401 ? ['WWW-Authenticate' => 'Bearer'] : []);
};

$token = apiTokenFromRequest();
if ($token === '') {
    $bad(401, 'unauthorized', 'API key is required (Authorization: Bearer <key>)');
}
$key = apiKeyFind($token);
if ($key === null) {
    $bad(401, 'unauthorized', 'Invalid API key');
}
if (empty($key['enabled'])) {
    $bad(401, 'key_disabled', 'The API key is disabled');
}
if (!empty($key['expires']) && strtotime($key['expires'] . ' 23:59:59') < time()) {
    $bad(401, 'key_expired', 'The API key has expired');
}
if (!empty($key['allowed_ips'])) {
    $allowed = false;
    foreach ($key['allowed_ips'] as $entry) {
        if ($ip !== '' && vlmcPanelIpMatches($ip, $entry)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        apiError(403, 'ip_not_allowed', 'This IP address is not allowed for the API key');
    }
}

// Лимит запросов на ключ
$rate = apiRateHit('k:' . $key['id'], $settings['rate_limit']);
if ($rate['limit'] > 0) {
    header('X-RateLimit-Limit: ' . $rate['limit']);
    header('X-RateLimit-Remaining: ' . $rate['remaining']);
    header('X-RateLimit-Reset: ' . $rate['reset']);
}
if (!$rate['ok']) {
    apiError(429, 'rate_limited', 'Rate limit exceeded', ['Retry-After' => max(1, $rate['reset'])]);
}

apiKeyTouch($key['id'], $ip);
$data = apiRoute($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $key, $config);
apiRespond(200, ['data' => $data, 'generated' => date('c')]);
