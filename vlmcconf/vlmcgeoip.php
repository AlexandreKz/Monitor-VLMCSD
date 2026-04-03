<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmcgeoip.php
// ВЕРСИЯ: 1.2.0
// ДАТА: 2026-03-28
// @description: Геолокация IP-адресов + флаги стран
// ============================================

/**
 * Преобразование HEX в RGB
 */
function hex2rgb($hex) {
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
 * Получение выделенных диапазонов провайдеров
 */
function getProviderRanges($isp) {
    $isp = strtolower($isp);
    $ranges = [];
    
    if (strpos($isp, 'rostelecom') !== false || strpos($isp, 'rt') !== false) {
        $ranges[] = ['range' => '188.16.0.0/12', 'desc' => 'Ростелеком (Центр)'];
        $ranges[] = ['range' => '95.24.0.0/13', 'desc' => 'Ростелеком (Урал)'];
        $ranges[] = ['range' => '95.128.0.0/12', 'desc' => 'Ростелеком (Сибирь)'];
    }
    if (strpos($isp, 'mts') !== false) {
        $ranges[] = ['range' => '95.128.0.0/11', 'desc' => 'МТС (Центр)'];
        $ranges[] = ['range' => '83.169.0.0/16', 'desc' => 'МТС (Юг)'];
    }
    if (strpos($isp, 'beeline') !== false || strpos($isp, 'vimpel') !== false) {
        $ranges[] = ['range' => '95.24.0.0/13', 'desc' => 'Билайн (Москва)'];
        $ranges[] = ['range' => '95.128.0.0/11', 'desc' => 'Билайн (Регионы)'];
    }
    if (strpos($isp, 'megafon') !== false) {
        $ranges[] = ['range' => '95.128.0.0/12', 'desc' => 'Мегафон (Северо-Запад)'];
        $ranges[] = ['range' => '83.169.0.0/16', 'desc' => 'Мегафон (Кавказ)'];
    }
    if (strpos($isp, 'google') !== false) {
        $ranges[] = ['range' => '35.192.0.0/12', 'desc' => 'Google Cloud (US)'];
        $ranges[] = ['range' => '34.0.0.0/15', 'desc' => 'Google Cloud (EU)'];
    }
    if (strpos($isp, 'amazon') !== false || strpos($isp, 'aws') !== false) {
        $ranges[] = ['range' => '52.0.0.0/15', 'desc' => 'AWS (US East)'];
        $ranges[] = ['range' => '18.0.0.0/8', 'desc' => 'AWS (Global)'];
    }
    
    return $ranges;
}

/**
 * Прямой запрос к API (без кеша)
 */
function getGeoLocationDirect($ip) {
    $apis = [
        "http://ip-api.com/json/{$ip}?fields=country",
        "https://ipapi.co/{$ip}/country_name/"
    ];
    
    foreach ($apis as $api) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; rv:91.0) Gecko/20100101 Firefox/91.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            if (strpos($api, 'ip-api.com') !== false) {
                $data = json_decode($response, true);
                if (isset($data['country'])) {
                    return $data['country'];
                }
            } else {
                $country = trim($response);
                if (!empty($country) && $country !== 'Undefined') {
                    return $country;
                }
            }
        }
    }
    
    return 'Неизвестно';
}

/**
 * Получение геолокации по IP с кешированием
 */
function getGeoLocation($ip, &$cacheStatus, &$cacheMessage) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'Локальный';
    }
    
    $cacheDir = '/tmp/kms_cache';
    
    if (!file_exists($cacheDir)) {
        if (!@mkdir($cacheDir, 0777, true)) {
            $cacheStatus = false;
            $cacheMessage = '⚠️ Кеш отключен (нет прав на запись)';
            return getGeoLocationDirect($ip);
        }
    }
    
    if (!is_writable($cacheDir)) {
        $cacheStatus = false;
        $cacheMessage = '⚠️ Кеш отключен (папка недоступна для записи)';
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
    
    if (!@file_put_contents($cacheFile, $country)) {
        $cacheStatus = false;
        $cacheMessage = '⚠️ Кеш отключен (ошибка записи)';
    }
    
    return $country;
}

/**
 * Получение подробной геолокации с несколькими API
 */
function getDetailedGeoLocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [
            'country' => '🏠 Локальный',
            'city' => '—',
            'region' => '—',
            'isp' => '—',
            'org' => '—',
            'timezone' => '—',
            'cidr' => ipToCidr($ip),
            'ip_range' => getIpRange($ip, ipToCidr($ip)),
            'provider_ranges' => [],
            'success' => true
        ];
    }
    
    $apis = [
        ['url' => "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,isp,org,timezone,query,as", 'type' => 'ip-api'],
        ['url' => "https://ipapi.co/{$ip}/json/", 'type' => 'ipapi'],
        ['url' => "http://ipwho.is/{$ip}", 'type' => 'ipwhois']
    ];
    
    foreach ($apis as $api) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $api['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'KMS Monitor GeoIP/1.0',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($api['type'] === 'ip-api' && isset($data['status']) && $data['status'] === 'success') {
                $cidr = ipToCidr($ip);
                return [
                    'country' => $data['country'] ?? 'Неизвестно',
                    'city' => $data['city'] ?? '—',
                    'region' => $data['regionName'] ?? '—',
                    'isp' => $data['isp'] ?? '—',
                    'org' => $data['org'] ?? '—',
                    'timezone' => $data['timezone'] ?? '—',
                    'asn' => $data['as'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'provider_ranges' => getProviderRanges($data['isp'] ?? ''),
                    'success' => true
                ];
            }
            
            if ($api['type'] === 'ipapi' && isset($data['country_name'])) {
                $cidr = ipToCidr($ip);
                return [
                    'country' => $data['country_name'] ?? 'Неизвестно',
                    'city' => $data['city'] ?? '—',
                    'region' => $data['region'] ?? '—',
                    'isp' => $data['org'] ?? '—',
                    'org' => $data['org'] ?? '—',
                    'timezone' => $data['timezone'] ?? '—',
                    'asn' => $data['asn'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'provider_ranges' => getProviderRanges($data['org'] ?? ''),
                    'success' => true
                ];
            }
            
            if ($api['type'] === 'ipwhois' && isset($data['country'])) {
                $cidr = ipToCidr($ip);
                return [
                    'country' => $data['country'] ?? 'Неизвестно',
                    'city' => $data['city'] ?? '—',
                    'region' => $data['region'] ?? '—',
                    'isp' => $data['connection']['isp'] ?? '—',
                    'org' => $data['connection']['org'] ?? '—',
                    'timezone' => $data['timezone']['id'] ?? '—',
                    'asn' => $data['connection']['asn'] ?? '—',
                    'cidr' => $cidr,
                    'ip_range' => getIpRange($ip, $cidr),
                    'provider_ranges' => getProviderRanges($data['connection']['isp'] ?? ''),
                    'success' => true
                ];
            }
        }
    }
    
    $cidr = ipToCidr($ip);
    return [
        'country' => '🌍 Неизвестно',
        'city' => '—',
        'region' => '—',
        'isp' => '—',
        'org' => '—',
        'timezone' => '—',
        'asn' => '—',
        'cidr' => $cidr,
        'ip_range' => getIpRange($ip, $cidr),
        'provider_ranges' => [],
        'success' => false,
        'error' => 'API недоступны'
    ];
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
            // Двухбуквенные коды стран
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
            
            // Специальные
            'Локальный' => '🏠', 'Local' => '🏠',
            'Неизвестно' => '🌍', 'Unknown' => '🌍',
        ];
    }
    
    if (isset($flags[$country])) {
        return $flags[$country];
    }
    
    // Поиск по частичному совпадению
    foreach ($flags as $key => $flag) {
        if (stripos($country, $key) !== false) {
            return $flag;
        }
    }
    
    return '🌍';
}
?>