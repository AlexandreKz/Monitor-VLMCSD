<?php
// ============================================
// ФАЙЛ: vlmcinc/http_client.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-01
// @description: HTTPS-запросы с обязательной проверкой SSL-сертификата (curl или потоки PHP), диагностика для Windows
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'http_client.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Состояние SSL-настроек PHP: нужно, чтобы подсказать, что исправить (особенно на Windows,
 * где у PHP по умолчанию нет списка доверенных сертификатов).
 */
function vlmcSslDiagnostics() {
    $curlCa = trim((string)ini_get('curl.cainfo'));
    $opensslCa = trim((string)ini_get('openssl.cafile'));
    return [
        'windows'               => DIRECTORY_SEPARATOR === '\\',
        'curl'                  => function_exists('curl_init'),
        'openssl'               => extension_loaded('openssl'),
        'curl_cainfo'           => $curlCa,
        'curl_cainfo_exists'    => $curlCa !== '' && is_file($curlCa),
        'openssl_cafile'        => $opensslCa,
        'openssl_cafile_exists' => $opensslCa !== '' && is_file($opensslCa),
        'php_ini'               => (string)php_ini_loaded_file()
    ];
}

/** Код ошибки: ssl_ca (нет списка доверенных сертификатов), ssl (сертификат не прошёл проверку), network. */
function vlmcHttpClassifyError($errno, $message) {
    $message = (string)$message;
    if (in_array((int)$errno, [60, 77], true)
        || stripos($message, 'local issuer') !== false
        || stripos($message, 'self signed') !== false
        || stripos($message, 'self-signed') !== false
        || stripos($message, 'cafile') !== false) {
        return 'ssl_ca';
    }
    if (in_array((int)$errno, [35, 51, 53, 54, 58, 59, 64, 66, 80, 82, 83, 90, 91], true)
        || stripos($message, 'certificate') !== false
        || stripos($message, 'SSL') !== false) {
        return 'ssl';
    }
    return 'network';
}

/**
 * GET-запрос по HTTPS. Проверка сертификата сервера включена всегда; обычный http:// запрещён.
 * @param string $url
 * @param array  $opts timeout, connect_timeout, headers (массив строк), user_agent
 * @return array ok (2xx), status, body, error (код или ''), detail, headers (в нижнем регистре)
 */
function vlmcHttpRequest($url, array $opts = []) {
    $timeout        = (int)($opts['timeout'] ?? 10);
    $connectTimeout = (int)($opts['connect_timeout'] ?? 5);
    $headers        = $opts['headers'] ?? [];
    $userAgent      = $opts['user_agent'] ?? 'KMS-Monitor/1.0';

    $result = ['ok' => false, 'status' => 0, 'body' => '', 'error' => '', 'detail' => '', 'headers' => []];

    $parts = parse_url($url);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        $result['error'] = 'insecure_scheme';
        return $result;
    }

    if (function_exists('curl_init')) {
        $responseHeaders = [];
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
                }
                return strlen($line);
            }
        ];
        if (defined('CURLPROTO_HTTPS')) {
            // и сам запрос, и редиректы — только HTTPS (без понижения до HTTP)
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $message = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno !== 0 || $body === false) {
            $result['error'] = vlmcHttpClassifyError($errno, $message);
            $result['detail'] = $message;
            return $result;
        }

        $result['status'] = $status;
        $result['body'] = (string)$body;
        $result['headers'] = $responseHeaders;
        $result['ok'] = $status >= 200 && $status < 300;
        return $result;
    }

    // curl не включён: штатные потоки PHP (тоже с проверкой сертификата)
    if (!ini_get('allow_url_fopen') || !extension_loaded('openssl')) {
        $result['error'] = 'no_transport';
        return $result;
    }

    $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
    $caFile = trim((string)ini_get('openssl.cafile'));
    if ($caFile !== '') {
        $ssl['cafile'] = $caFile;
    }
    $context = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'timeout'         => $timeout,
            'user_agent'      => $userAgent,
            'header'          => implode("\r\n", $headers),
            'follow_location' => 1,
            'max_redirects'   => 3,
            'ignore_errors'   => true
        ],
        'ssl' => $ssl
    ]);

    $http_response_header = null;
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $error = error_get_last();
        $message = $error['message'] ?? '';
        $result['error'] = vlmcHttpClassifyError(0, $message);
        $result['detail'] = $message;
        return $result;
    }

    $status = 0;
    $responseHeaders = [];
    foreach ((array)$http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int)$m[1]; // после редиректов остаётся статус последнего ответа
            $responseHeaders = [];
        } elseif (($pos = strpos($line, ':')) !== false) {
            $responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
        }
    }

    $result['status'] = $status;
    $result['body'] = $body;
    $result['headers'] = $responseHeaders;
    $result['ok'] = $status >= 200 && $status < 300;
    return $result;
}
