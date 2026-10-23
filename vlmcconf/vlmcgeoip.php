<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmcgeoip.php
// ВЕРСИЯ: 1.8.0
// ДАТА: 2026-06-28
// @description: Геолокация IP-адресов + флаги стран (только .txt кэш)
// @description: GeoIP location + country flags (only .txt cache)
// ============================================

/**
 * Преобразование HEX в RGB
 * Поддерживает как строку "#RRGGBB", так и массив вида {"color": "#RRGGBB", "icon": "🏠"}
 */
function hex2rgb($hex) {
    // Если пришёл массив (новый формат groupColors) — извлекаем color
    if (is_array($hex)) {
        $hex = $hex['color'] ?? '#888888';
    }
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

/**
 * Определение CIDR по IP
 */
function ipToCidr($ip) {
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return '-';
    }
    
    if (($ipLong & 0xFF000000) == 0x0A000000) {
        return '10.0.0.0/8';
    } elseif (($ipLong & 0xFFF00000) == 0xAC100000) {
        return '172.16.0.0/12';
    } elseif (($ipLong & 0xFFFF0000) == 0xC0A80000) {
        return '192.168.0.0/16';
    } elseif (($ipLong & 0xFFFFFF00) == 0x7F000000) {
        return '127.0.0.0/8';
    } else {
        $parts = explode('.', $ip);
        if (count($parts) == 4) {
            return $parts[0] . '.' . $parts[1] . '.0.0/16';
        }
        return '-';
    }
}

/**
 * Получение IP диапазона по маске
 */
function getIpRange($ip, $cidr) {
    if ($cidr === '-' || $cidr === '') {
        return '—';
    }
    
    if (preg_match('#^(\d+\.\d+\.\d+\.\d+)/(\d+)$#', $cidr, $matches)) {
        $network = $matches[1];
        $mask = (int)$matches[2];
        
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        
        if ($ipLong === false || $networkLong === false) {
            return '—';
        }
        
        $hosts = pow(2, 32 - $mask) - 2;
        $firstIp = $networkLong + 1;
        $lastIp = $networkLong + $hosts;
        
        return long2ip($firstIp) . ' — ' . long2ip($lastIp);
    }
    
    return '—';
}

/**
 * Получить директорию кэша геолокации (постоянная)
 */
function getGeoCacheDir() {
    $cacheDir = dirname(__DIR__) . '/vlmcconf/cache/geo_cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    return $cacheDir;
}

/**
 * HTTP GET с короткими тайм-аутами. Работает и без расширения curl
 * (fallback на file_get_contents). Возвращает тело ответа или null.
 */
function geoHttpGet($url, $timeout = 2) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; rv:91.0) Gecko/20100101 Firefox/91.0',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return ($code === 200 && is_string($response) && $response !== '') ? $response : null;
    }

    // curl не включён в php.ini — пробуем штатные потоки PHP
    if (!ini_get('allow_url_fopen')) {
        return null;
    }
    if (stripos($url, 'https://') === 0 && !extension_loaded('openssl')) {
        return null;
    }
    $context = stream_context_create(['http' => [
        'timeout' => $timeout,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; rv:91.0) Gecko/20100101 Firefox/91.0'
    ]]);
    $response = @file_get_contents($url, false, $context);
    return ($response !== false && $response !== '') ? $response : null;
}

if (!defined('GEO_CACHE_TTL')) define('GEO_CACHE_TTL', 2592000); // 30 дней
if (!defined('GEO_FAIL_TTL'))  define('GEO_FAIL_TTL', 3600);     // 1 час: после неудачи API не дёргаем

function geoIsPublicIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function geoCacheFile($ip, $ext = 'txt') {
    return getGeoCacheDir() . '/' . md5($ip) . '.' . $ext;
}

/**
 * Страна ТОЛЬКО из кэша. null — в кэше нет (сетевой запрос не выполняется).
 */
function getGeoCachedCountry($ip) {
    if (!geoIsPublicIp($ip)) {
        return __('geo_local');
    }
    $file = geoCacheFile($ip);
    if (is_file($file) && (time() - filemtime($file) < GEO_CACHE_TTL)) {
        $country = @file_get_contents($file);
        if ($country !== false && $country !== '') {
            return $country;
        }
    }
    return null;
}

/** Недавняя неудача определения страны (маркер .fail живёт GEO_FAIL_TTL секунд). */
function geoLookupFailedRecently($ip) {
    $file = geoCacheFile($ip, 'fail');
    return is_file($file) && (time() - filemtime($file) < GEO_FAIL_TTL);
}

/**
 * Прямой запрос к API (только страна)
 */
function getGeoLocationDirect($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return __('geo_unknown');
    }

    $apis = [
        "http://ip-api.com/json/{$ip}?fields=country",
        "https://ipapi.co/{$ip}/country_name/"
    ];

    foreach ($apis as $api) {
        $response = geoHttpGet($api, 2);
        if ($response === null) {
            continue;
        }

        if (strpos($api, 'ip-api.com') !== false) {
            $data = json_decode($response, true);
            if (isset($data['country'])) {
                return $data['country'];
            }
        } else {
            $country = trim($response);
            if ($country !== '' && $country !== 'Undefined' && $country[0] !== '{' && strlen($country) < 64) {
                return $country;
            }
        }
    }

    return __('geo_unknown');
}

/**
 * Получение геолокации по IP с кэшированием (только страна)
 * Используется на главной странице для отображения флагов
 */
function getGeoLocation($ip, &$cacheStatus, &$cacheMessage) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return __('geo_local');
    }
    
    $cacheDir = getGeoCacheDir();
    
    if (!is_writable($cacheDir)) {
        $cacheStatus = false;
        $cacheMessage = __('geo_cache_warning');
        return getGeoLocationDirect($ip);
    }
    
    $cacheFile = $cacheDir . '/' . md5($ip) . '.txt';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    $country = getGeoLocationDirect($ip);
    
    // Неудачный запрос не кэшируем на 30 дней
    if ($country === __('geo_unknown')) {
        return $country;
    }
    
    if (!@file_put_contents($cacheFile, $country)) {
        $cacheStatus = false;
        $cacheMessage = __('geo_cache_write_error');
    }
    
    return $country;
}

/**
 * Получение подробной геолокации (для модального окна)
 * НЕ сохраняет в кэш, запрашивает API напрямую
 */
function getDetailedGeoLocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [
            'country' => '🏠 ' . __('geo_local'),
            'city' => '—',
            'region' => '—',
            'isp' => '—',
            'org' => '—',
            'timezone' => '—',
            'cidr' => ipToCidr($ip),
            'ip_range' => getIpRange($ip, ipToCidr($ip)),
            'success' => true
        ];
    }
    
    $apis = [
        ['url' => "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,isp,org,timezone,query,as", 'type' => 'ip-api'],
        ['url' => "https://ipapi.co/{$ip}/json/", 'type' => 'ipapi'],
        ['url' => "http://ipwho.is/{$ip}", 'type' => 'ipwhois']
    ];
    
    $result = [
        'country' => '🌍 ' . __('geo_unknown'),
        'city' => '—',
        'region' => '—',
        'isp' => '—',
        'org' => '—',
        'timezone' => '—',
        'asn' => '—',
        'cidr' => ipToCidr($ip),
        'ip_range' => getIpRange($ip, ipToCidr($ip)),
        'success' => false
    ];
    
    foreach ($apis as $api) {
        $response = geoHttpGet($api['url'], 3);
        
        if ($response !== null) {
            $data = json_decode($response, true);
            
            if ($api['type'] === 'ip-api' && isset($data['status']) && $data['status'] === 'success') {
                $cidr = ipToCidr($ip);
                $result = [
                    'country' => $data['country'] ?? __('geo_unknown'),
                    'city' => $data['city'] ?? '—',
                    'region' => $data['regionName'] ?? '—',
                    'isp' => $data['isp'] ?? '—',
                    'org' => $data['org'] ?? '—',
                    'timezone' => $data['timezone'] ?? '—',
                    'asn' => $data['as'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'success' => true
                ];
                break;
            }
            
            if ($api['type'] === 'ipapi' && isset($data['country_name'])) {
                $cidr = ipToCidr($ip);
                $result = [
                    'country' => $data['country_name'] ?? __('geo_unknown'),
                    'city' => $data['city'] ?? '—',
                    'region' => $data['region'] ?? '—',
                    'isp' => $data['org'] ?? '—',
                    'org' => $data['org'] ?? '—',
                    'timezone' => $data['timezone'] ?? '—',
                    'asn' => $data['asn'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'success' => true
                ];
                break;
            }
            
            if ($api['type'] === 'ipwhois' && isset($data['country'])) {
                $cidr = ipToCidr($ip);
                $result = [
                    'country' => $data['country'] ?? __('geo_unknown'),
                    'city' => $data['city'] ?? '—',
                    'region' => $data['region'] ?? '—',
                    'isp' => $data['connection']['isp'] ?? '—',
                    'org' => $data['connection']['org'] ?? '—',
                    'timezone' => $data['timezone']['id'] ?? '—',
                    'asn' => $data['connection']['asn'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'success' => true
                ];
                break;
            }
        }
    }
    
    return $result;
}

/**
 * Получение emoji флага по названию страны
 * @param string $country Название страны (русский или английский)
 * @return string Emoji флаг или 🌍 если не найдено
 */
function getCountryFlag($country) {
    static $flags = null;
    
    if ($flags === null) {
        $flags = [
            'RU' => '🇷🇺', 'US' => '🇺🇸', 'GB' => '🇬🇧', 'DE' => '🇩🇪',
            'FR' => '🇫🇷', 'PL' => '🇵🇱', 'UA' => '🇺🇦', 'NL' => '🇳🇱',
            'BE' => '🇧🇪', 'IT' => '🇮🇹', 'ES' => '🇪🇸', 'SE' => '🇸🇪',
            'FI' => '🇫🇮', 'NO' => '🇳🇴', 'DK' => '🇩🇰', 'CH' => '🇨🇭',
            'AT' => '🇦🇹', 'CZ' => '🇨🇿', 'HU' => '🇭🇺', 'RO' => '🇷🇴',
            'BG' => '🇧🇬', 'GR' => '🇬🇷', 'PT' => '🇵🇹', 'IE' => '🇮🇪',
            'CA' => '🇨🇦', 'MX' => '🇲🇽', 'BR' => '🇧🇷', 'AR' => '🇦🇷',
            'CL' => '🇨🇱', 'PE' => '🇵🇪', 'CO' => '🇨🇴', 'VE' => '🇻🇪',
            'CN' => '🇨🇳', 'JP' => '🇯🇵', 'KR' => '🇰🇷', 'IN' => '🇮🇳',
            'TR' => '🇹🇷', 'IR' => '🇮🇷', 'IL' => '🇮🇱', 'SA' => '🇸🇦',
            'AE' => '🇦🇪', 'SG' => '🇸🇬', 'MY' => '🇲🇾', 'ID' => '🇮🇩',
            'PH' => '🇵🇭', 'VN' => '🇻🇳', 'TH' => '🇹🇭', 'HK' => '🇭🇰',
            'AU' => '🇦🇺', 'NZ' => '🇳🇿', 'ZA' => '🇿🇦', 'EG' => '🇪🇬',
            'NG' => '🇳🇬', 'KZ' => '🇰🇿', 'BY' => '🇧🇾',
            __('geo_local') => '🏠',
            'Local' => '🏠',
            __('geo_unknown') => '🌍',
            'Unknown' => '🌍',
        ];
    }
    
    if (isset($flags[$country])) {
        return $flags[$country];
    }
    
    foreach ($flags as $key => $flag) {
        if (stripos($country, $key) !== false) {
            return $flag;
        }
    }
    
    return '🌍';
}
?>