<?php
// ============================================
// ФАЙЛ: vlmc.php
// ВЕРСИЯ: 4.8.0
// ДАТА: 2026-03-22
// @description: Главный файл мониторинга KMS сервера
// ============================================

// ============================================
// ПОДКЛЮЧЕНИЕ ВНЕШНИХ ФАЙЛОВ
// ============================================
require_once __DIR__ . '/vlmcconf/vlmctheme.php';
require_once __DIR__ . '/vlmcconf/vlmcgeoip.php';
require_once __DIR__ . '/vlmcconf/vlmcloghandler.php';
require_once __DIR__ . '/vlmcconf/vlmcinc/config.php';
require_once __DIR__ . '/vlmcconf/flags.php';

// ============================================
// ЗАГРУЗКА КОНФИГУРАЦИИ
// ============================================
$configFile = __DIR__ . '/vlmcconf/vlmcconf_config.json';
$theme = 'dark';
$config = [
    'logPath' => 'vlmcsd.log',
    'groupColors' => [],
    'devices' => [],
    'language' => 'ru'
];

if (file_exists($configFile)) {
    $loaded = json_decode(file_get_contents($configFile), true);
    if ($loaded) {
        $config = array_merge($config, $loaded);
        if (isset($loaded['theme'])) {
            $theme = $loaded['theme'];
        }
    }
}

// Загрузка переводов
$currentLanguage = $config['language'] ?? 'ru';
$localeFile = __DIR__ . '/vlmcconf/locale/' . $currentLanguage . '.php';
if (file_exists($localeFile)) {
    $translations = include $localeFile;
} else {
    $translations = include __DIR__ . '/vlmcconf/locale/ru.php';
}

$themeCSS = getThemeCSS($theme);

// Используем путь к логу из конфига
$logFile = $config['logPath'];
if (strpos($logFile, '/') !== 0 && strpos($logFile, ':') === false) {
    $logFile = __DIR__ . '/' . ltrim($logFile, './');
}

// Определение групп и их цветов
$groups = [];
$groupColors = [];

foreach ($config['groupColors'] as $group => $color) {
    $groups[$group] = 'rgba(' . hex2rgb($color) . ', 0.15)';
    $groupColors[$group] = $color;
}

// Загружаем устройства из конфига
$devices = [];
if (!empty($config['devices'])) {
    foreach ($config['devices'] as $group => $deviceList) {
        foreach ($deviceList as $device) {
            $devices[$device['name']] = $group;
        }
    }
}

// ============================================
// AJAX ОБРАБОТЧИКИ (ДО HTML)
// ============================================

// Обработчик для получения лога
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_log') {
    $filter = $_GET['filter'] ?? 'all';
    $groupFilter = $_GET['group'] ?? 'all';
    header('Content-Type: text/html; charset=utf-8');
    
    // Собираем комментарии устройств
    $deviceComments = [];
    foreach ($config['devices'] as $group => $deviceList) {
        foreach ($deviceList as $device) {
            if (!empty($device['comment'])) {
                $deviceComments[$device['name']] = $device['comment'];
            }
        }
    }
    
    echo processLog($logFile, $devices, $deviceComments, $groups, $groupColors, $filter, $groupFilter);
    exit;
}

// Обработчик для геолокации
if (isset($_GET['ajax']) && $_GET['ajax'] === 'geo') {
    header('Content-Type: application/json');
    $ip = $_GET['ip'] ?? '';
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        echo json_encode(['error' => 'Неверный IP', 'success' => false]);
        exit;
    }
    echo json_encode(getDetailedGeoLocation($ip));
    exit;
}

// Обработчик для добавления устройства
if (isset($_POST['ajax']) && $_POST['ajax'] === 'add_device') {
    header('Content-Type: application/json');
    $deviceName = trim($_POST['deviceName'] ?? '');
    $deviceGroup = $_POST['deviceGroup'] ?? '';
    $deviceComment = trim($_POST['deviceComment'] ?? '');
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($deviceName) || empty($deviceGroup)) {
        $response['message'] = 'Имя устройства и группа обязательны';
    } else if (!isset($config['devices'][$deviceGroup])) {
        $response['message'] = 'Группа не существует';
    } else {
        $exists = false;
        foreach ($config['devices'][$deviceGroup] as $existing) {
            if ($existing['name'] === $deviceName) {
                $exists = true;
                break;
            }
        }
        
        if ($exists) {
            $response['message'] = 'Устройство уже существует';
        } else {
            $config['devices'][$deviceGroup][] = [
                'name' => $deviceName,
                'comment' => $deviceComment,
                'added' => date('Y-m-d H:i:s')
            ];
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response['success'] = true;
            $response['message'] = 'Устройство добавлено';
        }
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// ОСНОВНАЯ ЛОГИКА (СТАТИСТИКА)
// ============================================

$cacheStatus = true;
$cacheMessage = '';

function getUptime($logFile) {
    if (!file_exists($logFile)) {
        return ['status' => __('status_file_not_found'), 'days' => 0, 'hours' => 0, 'minutes' => 0];
    }
    
    $content = file_get_contents($logFile);
    if ($content === false) {
        return ['status' => __('status_read_error'), 'days' => 0, 'hours' => 0, 'minutes' => 0];
    }
    
    $lines = explode("\n", $content);
    $startTime = null;
    
    foreach ($lines as $line) {
        if (strpos($line, 'started successfully') !== false) {
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $startTime = strtotime($matches[1]);
                break;
            }
        }
    }
    
    if (!$startTime) {
        $oldestTime = null;
        foreach ($lines as $line) {
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $time = strtotime($matches[1]);
                if ($oldestTime === null || $time < $oldestTime) {
                    $oldestTime = $time;
                }
            }
        }
        $startTime = $oldestTime;
    }
    
    if (!$startTime) {
        return ['status' => __('status_no_data'), 'days' => 0, 'hours' => 0, 'minutes' => 0];
    }
    
    $now = time();
    $diff = $now - $startTime;
    
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    if ($days > 30) $status = __('status_stable');
    elseif ($days < 1) $status = __('status_recent');
    else $status = __('status_active');
    
    return [
        'status' => $status,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'start_time' => $startTime
    ];
}

function analyzeLog($logFile, $devices, &$cacheStatus, &$cacheMessage) {
    if (!file_exists($logFile)) return null;
    
    $content = file_get_contents($logFile);
    if ($content === false) return null;
    
    $lines = explode("\n", $content);
    
    $stats = [
        'products' => [],
        'suspicious_ips' => [],
        'unknown_devices' => [],
        'kms_versions' => []
    ];
    
    $stats['kms_details'] = [
        'v4' => ['count' => 0, 'last_time' => 0, 'devices' => [], 'products' => []],
        'v6' => ['count' => 0, 'last_time' => 0, 'devices' => [], 'products' => []]
    ];
    
    $ipTimes = [];
    $knownDevices = array_keys($devices);
    $lastIp = null;
    $lastTime = null;
    
    foreach ($lines as $line) {
        if (preg_match('/connection accepted: ([\d\.]+):\d+/', $line, $matches)) {
            $lastIp = $matches[1];
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $timeMatches)) {
                $lastTime = strtotime($timeMatches[1]);
            }
            if (!isset($ipTimes[$lastIp])) $ipTimes[$lastIp] = [];
            $ipTimes[$lastIp][] = $lastTime;
        }
        
        if (preg_match('/KMS v([46])\.0 request from/', $line, $versionMatches)) {
            $version = $versionMatches[1];
            $versionKey = 'v' . $version;
            $stats['kms_versions'][$version] = ($stats['kms_versions'][$version] ?? 0) + 1;
            $stats['kms_details'][$versionKey]['count']++;
            if ($lastTime > $stats['kms_details'][$versionKey]['last_time']) {
                $stats['kms_details'][$versionKey]['last_time'] = $lastTime;
            }
        }
        
        if (preg_match('/KMS v[46]\.0 request from/', $line) && $lastIp) {
            $fromPos = strpos($line, 'from') + 4;
            $forPos = strpos($line, 'for', $fromPos);
            
            if ($fromPos && $forPos) {
                $device = substr($line, $fromPos, $forPos - $fromPos);
                
                if (preg_match('/KMS v([46])\.0 request from/', $line, $vMatch)) {
                    $version = $vMatch[1];
                    $versionKey = 'v' . $version;
                    $deviceKey = preg_replace('/[^a-zA-Z0-9\-\.]/', '', $device);
                    if (!empty($deviceKey)) {
                        $stats['kms_details'][$versionKey]['devices'][$deviceKey] = true;
                    }
                }
                
                $isKnown = false;
                foreach ($knownDevices as $knownDevice) {
                    if (strpos($knownDevice, $device) !== false || strpos($device, $knownDevice) !== false) {
                        $isKnown = true;
                        break;
                    }
                }
                
                if (!$isKnown) {
                    if (!isset($stats['unknown_devices'][$device])) {
                        $stats['unknown_devices'][$device] = [
                            'device' => $device,
                            'last_ip' => $lastIp,
                            'count' => 0,
                            'first_seen' => $lastTime,
                            'last_seen' => $lastTime
                        ];
                    }
                    $stats['unknown_devices'][$device]['count']++;
                    $stats['unknown_devices'][$device]['last_ip'] = $lastIp;
                    $stats['unknown_devices'][$device]['last_seen'] = $lastTime;
                }
            }
        }
        
        if (preg_match('/KMS v[46]\.0 request from/', $line) && preg_match('/for (.+)$/', $line, $matches)) {
            $product = trim($matches[1]);
            $stats['products'][$product] = ($stats['products'][$product] ?? 0) + 1;
            
            if (preg_match('/KMS v([46])\.0 request from/', $line, $vMatch)) {
                $version = $vMatch[1];
                $versionKey = 'v' . $version;
                $productKey = substr($product, 0, 30);
                if (!isset($stats['kms_details'][$versionKey]['products'][$productKey])) {
                    $stats['kms_details'][$versionKey]['products'][$productKey] = 0;
                }
                $stats['kms_details'][$versionKey]['products'][$productKey]++;
            }
        }
    }
    
    foreach ($ipTimes as $ip => $times) {
        if (count($times) >= 3) {
            $hasKmsRequest = false;
            foreach ($stats['unknown_devices'] as $device) {
                if ($device['last_ip'] === $ip) {
                    $hasKmsRequest = true;
                    break;
                }
            }
            
            if (!$hasKmsRequest) {
                sort($times);
                $first = $times[0];
                $last = $times[count($times) - 1];
                $duration = $last - $first;
                
                $fastCount = 0;
                for ($i = 1; $i < count($times); $i++) {
                    if (($times[$i] - $times[$i-1]) < 30) $fastCount++;
                }
                
                if ($fastCount >= 2 || $duration < 300) {
                    $country = getGeoLocation($ip, $cacheStatus, $cacheMessage);
                    $cidr = ipToCidr($ip);
                    $stats['suspicious_ips'][$ip] = [
                        'count' => count($times),
                        'duration' => $duration,
                        'country' => $country,
                        'cidr' => $cidr
                    ];
                }
            }
        }
    }
    
    return $stats;
}

$stats = analyzeLog($logFile, $devices, $cacheStatus, $cacheMessage);

// Временная отладка
if (!empty($stats['suspicious_ips'])) {
    file_put_contents('/tmp/debug_country.log', "=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
    foreach ($stats['suspicious_ips'] as $ip => $data) {
        file_put_contents('/tmp/debug_country.log', "IP: $ip, Country: " . $data['country'] . "\n", FILE_APPEND);
        $flag = getCountryFlag($data['country']);
        file_put_contents('/tmp/debug_country.log', "Flag: $flag\n", FILE_APPEND);
    }
}

$deviceStats = array_count_values($devices);
$uptime = getUptime($logFile);

$v4Count = $stats['kms_versions'][4] ?? 0;
$v6Count = $stats['kms_versions'][6] ?? 0;
$totalRequests = $v4Count + $v6Count;
$v4Percent = $totalRequests > 0 ? round(($v4Count / $totalRequests) * 100, 1) : 0;
$v6Percent = $totalRequests > 0 ? round(($v6Count / $totalRequests) * 100, 1) : 0;

$v4DevicesCount = isset($stats['kms_details']['v4']['devices']) ? count($stats['kms_details']['v4']['devices']) : 0;
$v6DevicesCount = isset($stats['kms_details']['v6']['devices']) ? count($stats['kms_details']['v6']['devices']) : 0;

$lastV4Time = !empty($stats['kms_details']['v4']['last_time']) ? date('Y-m-d H:i', $stats['kms_details']['v4']['last_time']) : __('never');
$lastV6Time = !empty($stats['kms_details']['v6']['last_time']) ? date('Y-m-d H:i', $stats['kms_details']['v6']['last_time']) : __('never');

$v4Products = isset($stats['kms_details']['v4']['products']) ? $stats['kms_details']['v4']['products'] : [];
$v6Products = isset($stats['kms_details']['v6']['products']) ? $stats['kms_details']['v6']['products'] : [];

arsort($v4Products);
arsort($v6Products);

$v4ProductsList = [];
$v6ProductsList = [];

foreach (array_slice($v4Products, 0, 3, true) as $product => $count) {
    $shortName = strlen($product) > 20 ? substr($product, 0, 18) . '…' : $product;
    $v4ProductsList[] = "$shortName ($count)";
}

foreach (array_slice($v6Products, 0, 3, true) as $product => $count) {
    $shortName = strlen($product) > 20 ? substr($product, 0, 18) . '…' : $product;
    $v6ProductsList[] = "$shortName ($count)";
}

$unknownDevicesArray = [];
if (!empty($stats['unknown_devices'])) {
    foreach ($stats['unknown_devices'] as $device => $data) {
        $unknownDevicesArray[] = $data;
    }
}

// Отладка - проверим что приходит
error_log("=== getCountryFlag DEBUG ===");
if (!empty($stats['suspicious_ips'])) {
    foreach ($stats['suspicious_ips'] as $ip => $data) {
        error_log("IP: $ip, Country raw: " . $data['country']);
        $flag = getCountryFlag($data['country']);
        error_log("Flag returned: " . $flag);
    }
}


$suspiciousIpsArray = [];
if (!empty($stats['suspicious_ips'])) {
    foreach ($stats['suspicious_ips'] as $ip => $data) {
        $countryName = $data['country'];
        $countryCode = getCountryCode($countryName);
        
        $suspiciousIpsArray[] = [
            'ip' => $ip,
            'count' => $data['count'],
            'duration' => $data['duration'],
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'cidr' => $data['cidr']
        ];
    }
}

// Подготовка данных для лога
$deviceComments = [];
foreach ($config['devices'] as $group => $deviceList) {
    foreach ($deviceList as $device) {
        if (!empty($device['comment'])) {
            $deviceComments[$device['name']] = $device['comment'];
        }
    }
}

$initialLog = processLog($logFile, $devices, $deviceComments, $groups, $groupColors);

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="shortcut icon" href="/pic/favicon.png" type="image/x-icon">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.5.0/css/flag-icons.min.css">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>KMS Log • <?= __('title_monitor') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: <?= $themeCSS['bg'] ?>;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0; padding: 15px; height: 100vh; display: flex; flex-direction: column;
            overflow: hidden; color: <?= $themeCSS['text'] ?>; transition: background 0.3s, color 0.3s;
        }
        .top-panel { height: 30%; display: flex; flex-direction: column; margin-bottom: 10px; min-height: 0; }
        .header-container { display: flex; align-items: center; gap: 15px; margin-bottom: 8px; }
        .header {
            background: <?= $themeCSS['header'] ?>; padding: 8px 12px; border-radius: 6px;
            border: 1px solid <?= $themeCSS['border'] ?>; flex: 1; display: flex;
            justify-content: space-between; align-items: center;
        }
        .header-left { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        h1 { color: #ffffff; font-size: 16px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .settings-button {
            display: inline-flex; align-items: center; justify-content: center; height: 44px; width: 44px;
            background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px;
            color: <?= $themeCSS['text'] ?>; text-decoration: none; font-size: 22px; transition: all 0.2s;
            flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .settings-button:hover { background: <?= $themeCSS['hover'] ?>; border-color: <?= $themeCSS['primary'] ?>; color: #ffffff; transform: scale(1.05); }
        .add-device-btn { background: transparent; border: none; color: #4ade80; cursor: pointer; padding: 0 2px; border-radius: 3px; font-size: 12px; transition: all 0.2s; }
        .add-device-btn:hover { color: #6ee7b7; transform: scale(1.1); }
        .cache-warning { color: <?= $themeCSS['warning'] ?>; font-size: 11px; background: rgba(243, 156, 18, 0.1); padding: 3px 8px; border-radius: 12px; border: 1px solid <?= $themeCSS['warning'] ?>; }
        .uptime-info { display: flex; align-items: center; gap: 8px; background: <?= $themeCSS['card'] ?>; padding: 3px 10px; border-radius: 20px; border: 1px solid <?= $themeCSS['border'] ?>; font-size: 11px; }
        .uptime-status { color: #4ade80; }
        .uptime-value { color: <?= $themeCSS['text'] ?>; font-weight: 500; }
        .live-indicator { display: inline-flex; align-items: center; gap: 4px; background: #1e3a2b; color: #4ade80; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 500; border: 1px solid #2e5e3a; }
        .live-indicator .dot { width: 5px; height: 5px; background: #4ade80; border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }
        
        /* Стили для времени */
        .time-display {
            background: <?= $themeCSS['card'] ?>;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid <?= $themeCSS['border'] ?>;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: <?= $themeCSS['text'] ?>;
        }
        
        .columns { display: flex; gap: 12px; flex: 1; min-height: 0; }
        .left-column { flex: 1; display: flex; flex-direction: column; gap: 8px; min-height: 0; }
        .product-stats { background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 8px; border: 1px solid <?= $themeCSS['border'] ?>; overflow-y: auto; overflow-x: hidden; flex: 1; min-height: 0; position: relative; isolation: isolate; }
        .product-stats-title { font-size: 11px; font-weight: 600; color: <?= $themeCSS['text'] ?>; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; position: sticky; top: 0; background: <?= $themeCSS['card'] ?>; z-index: 5; padding-bottom: 4px; }
        .version-info { display: flex; gap: 10px; font-size: 9px; position: relative; }
        .version-badge { position: relative; display: inline-flex; align-items: center; gap: 4px; background: <?= $themeCSS['bg'] ?>; padding: 2px 8px; border-radius: 12px; cursor: help; }
        .version-badge.v4 { color: #9b59b6; border: 1px solid #9b59b6; }
        .version-badge.v6 { color: #3498db; border: 1px solid #3498db; }
        .version-badge .tooltip-text { visibility: hidden; opacity: 0; width: 220px; background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['text'] ?>; text-align: left; border-radius: 8px; padding: 12px 14px; position: fixed; z-index: 10000; transition: opacity 0.2s, visibility 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.5); border: 1px solid; font-size: 11px; font-weight: normal; text-transform: none; letter-spacing: normal; white-space: normal; line-height: 1.5; pointer-events: none; }
        .version-badge.v4 .tooltip-text { border-color: #9b59b6; }
        .version-badge.v6 .tooltip-text { border-color: #3498db; }
        .version-badge:hover .tooltip-text { visibility: visible; opacity: 1; }
        .product-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; position: relative; overflow: visible; }
        .product-grid::before { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 1px; background: linear-gradient(to bottom, transparent, <?= $themeCSS['border'] ?>, transparent); }
        .product-column { display: flex; flex-direction: column; gap: 4px; min-width: 0; overflow: visible; }
        .product-column:first-child { padding-right: 6px; }
        .product-column:last-child { padding-left: 6px; }
        .product-item { display: flex; align-items: center; gap: 6px; font-size: 10px; padding: 2px 0; border-bottom: 1px dashed <?= $themeCSS['border'] ?>; }
        .product-item:last-child { border-bottom: none; }
        .product-name { color: #b0c4de; min-width: 0; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 10px; }
        .product-bar-container { flex: 1; height: 4px; background: <?= $themeCSS['border'] ?>; border-radius: 2px; overflow: hidden; }
        .product-bar-fill { height: 100%; background: linear-gradient(90deg, #3498db, <?= $themeCSS['primary'] ?>); border-radius: 2px; }
        .product-count { color: <?= $themeCSS['text'] ?>; font-weight: 600; min-width: 22px; text-align: right; font-size: 10px; }
        .legend { background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 8px; border: 1px solid <?= $themeCSS['border'] ?>; flex-shrink: 0; }
        .legend-items { display: flex; gap: 15px; flex-wrap: wrap; justify-content: space-around; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 500; color: <?= $themeCSS['text'] ?>; }
        .legend-color { width: 10px; height: 10px; border-radius: 2px; }
        .legend-stats { display: inline-flex; align-items: center; gap: 2px; background: <?= $themeCSS['bg'] ?>; padding: 1px 5px; border-radius: 8px; font-size: 9px; border: 1px solid <?= $themeCSS['border'] ?>; }
        .middle-column { flex: 0.5; display: flex; flex-direction: column; min-height: 0; }
        .right-column { flex: 0.5; display: flex; flex-direction: column; min-height: 0; }
        .unknown-block { background: rgba(149, 165, 166, 0.1); border: 1px solid #95a5a6; border-radius: 6px; padding: 8px; height: 100%; display: flex; flex-direction: column; min-height: 0; }
        .unknown-title { color: #95a5a6; font-weight: 600; font-size: 11px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .unknown-count-badge { background: <?= $themeCSS['card'] ?>; color: #95a5a6; padding: 1px 6px; border-radius: 10px; font-size: 9px; border: 1px solid #95a5a6; }
        .unknown-list { overflow-y: auto; flex: 1; padding-right: 4px; }
        .unknown-header { display: grid; grid-template-columns: 1fr 0.9fr 0.1fr 0.3fr; padding: 3px 8px; margin-bottom: 3px; color: #8aa0bb; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; flex-shrink: 0; cursor: pointer; user-select: none; }
        .unknown-header span { transition: color 0.2s; padding: 2px 0; }
        .unknown-header span:hover { color: <?= $themeCSS['text'] ?>; }
        .unknown-header .sort-arrow { display: inline-block; margin-left: 3px; font-size: 8px; }
        .unknown-item { background: <?= $themeCSS['card'] ?>; border-radius: 12px; padding: 4px 8px; margin-bottom: 4px; font-size: 10px; border: 1px solid #95a5a6; display: grid; grid-template-columns: 1fr 0.9fr 0.1fr 0.3fr; align-items: center; gap: 4px; transition: all 0.2s; min-height: 26px; }
        .unknown-item:hover { background: <?= $themeCSS['hover'] ?>; border-left: 3px solid #95a5a6; }
        .unknown-device { color: <?= $themeCSS['text'] ?>; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 10px; }
        .unknown-ip { color: #95a5a6; font-family: 'JetBrains Mono', monospace; font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; text-decoration: underline dotted; }
        .unknown-ip:hover { color: #b0c4de; }
        .unknown-add { text-align: center; font-size: 10px; }
        .unknown-count { color: #8aa0bb; font-size: 9px; text-align: right; }
        .suspicious-block { background: rgba(231, 76, 60, 0.1); border: 1px solid <?= $themeCSS['danger'] ?>; border-radius: 6px; padding: 8px; height: 100%; display: flex; flex-direction: column; min-height: 0; }
        .suspicious-title { color: <?= $themeCSS['danger'] ?>; font-weight: 600; font-size: 11px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .suspicious-count-badge { background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['danger'] ?>; padding: 1px 6px; border-radius: 10px; font-size: 9px; border: 1px solid <?= $themeCSS['danger'] ?>; }
        .suspicious-list { overflow-y: auto; flex: 1; padding-right: 4px; }
        .suspicious-header span { transition: color 0.2s; padding: 2px 0; }
        .suspicious-header span:hover { color: <?= $themeCSS['text'] ?>; }
        .suspicious-header .sort-arrow { display: inline-block; margin-left: 3px; font-size: 8px; }
.suspicious-header {
    display: grid;
    grid-template-columns: 1fr 0.5fr 1.2fr 1.2fr;
    padding: 3px 8px;
    margin-bottom: 3px;
    color: #8aa0bb;
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.2px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
    flex-shrink: 0;
    cursor: pointer;
    user-select: none;
    gap: 4px;
}

.suspicious-item {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
    padding: 4px 8px;
    margin-bottom: 4px;
    font-size: 10px;
    border: 1px solid <?= $themeCSS['danger'] ?>;
    display: grid;
    grid-template-columns: 1fr 0.5fr 1.2fr 1.2fr;
    align-items: center;
    gap: 4px;
    border-left: 3px solid <?= $themeCSS['danger'] ?>;
    transition: all 0.2s;
    min-height: 26px;
}
        .suspicious-item:hover { background: <?= $themeCSS['hover'] ?>; border-left-width: 5px; }
        .suspicious-ip { color: <?= $themeCSS['danger'] ?>; font-weight: 600; font-family: 'JetBrains Mono', monospace; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; text-decoration: underline dotted; }
        .suspicious-ip:hover { color: #ff6b6b; }
        .suspicious-count { color: <?= $themeCSS['warning'] ?>; font-weight: 600; font-size: 10px; text-align: center; }
.suspicious-cidr-container {
    display: flex;
    align-items: center;
    gap: 2px;
    overflow: hidden;
    width: 100%;
}
 .suspicious-cidr {
    color: #b0c4de;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
.copy-cidr-btn {
    background: transparent;
    border: none;
    color: #8aa0bb;
    cursor: pointer;
    padding: 0px 2px;
    border-radius: 3px;
    font-size: 11px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}
        .copy-cidr-btn:hover { color: <?= $themeCSS['text'] ?>; background: <?= $themeCSS['hover'] ?>; }
        .copy-cidr-btn:active { transform: scale(0.9); }
        .suspicious-country { color: <?= $themeCSS['text'] ?>; font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: right; }
        .filter-bar { display: flex; gap: 8px; align-items: center; margin-top: 8px; flex-shrink: 0; }
        .search-box { display: flex; gap: 6px; flex: 1; }
        .search-box input { flex: 1; padding: 4px 8px; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 4px; font-size: 11px; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; }
        .search-box input:focus { outline: none; border-color: <?= $themeCSS['primary'] ?>; }
        .search-box input::placeholder { font-size: 11px; }
        .search-box button { padding: 4px 12px; border: none; border-radius: 4px; font-size: 11px; font-weight: 500; cursor: pointer; }
        .search-box button:first-of-type { background: <?= $themeCSS['primary'] ?>; color: white; }
        .search-box button:last-child { background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; }
        .filter-select { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; min-width: 120px; }
        .log-wrapper { height: 70%; position: relative; min-height: 0; }
        .log-container { background: <?= $themeCSS['logBg'] ?>; padding: 10px 12px; border-radius: 6px; box-shadow: inset 0 2px 6px rgba(0,0,0,0.3); font-family: 'JetBrains Mono', monospace; line-height: 1.5; font-size: 12px; height: 100%; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['logText'] ?>; }
        .log-container div { margin: 0; padding: 2px 0 2px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .log-container div:last-child { border-bottom: none; }
        .log-container span { cursor: help; }
        .connection-separator { height: 2px; background: linear-gradient(90deg, transparent, <?= $themeCSS['primary'] ?>, <?= $themeCSS['primary'] ?>, <?= $themeCSS['primary'] ?>, transparent); margin: 8px 0 8px 0; opacity: 0.6; border: none; box-shadow: 0 0 3px <?= $themeCSS['primary'] ?>80; }
        .scroll-bottom-btn { position: absolute; bottom: 12px; right: 12px; width: 28px; height: 28px; border-radius: 50%; background: <?= $themeCSS['primary'] ?>; border: 2px solid <?= $themeCSS['primary'] ?>80; color: white; font-size: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0.7; z-index: 100; }
        .scroll-bottom-btn:hover { opacity: 1; }
        .no-results { color: #6f8bad; text-align: center; padding: 30px; font-size: 11px; }
        .footer { margin-top: 10px; padding: 8px; text-align: center; color: #5f7a99; font-size: 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; flex-shrink: 0; }
        .modal { display: none; position: fixed; z-index: 20000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); backdrop-filter: blur(3px); align-items: center; justify-content: center; }
        .modal-content { background: <?= $themeCSS['card'] ?>; margin: 0 auto; padding: 20px; border-radius: 12px; width: 400px; max-width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.4); color: <?= $themeCSS['text'] ?>; position: relative; animation: modalFadeIn 0.3s; border: 2px solid; }
        .modal-content.unknown-geo { border-color: #4ade80; }
        .modal-content.suspicious-geo { border-color: <?= $themeCSS['danger'] ?>; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; }
        .modal-header h2 { font-size: 16px; font-weight: 600; margin: 0; }
        .unknown-geo .modal-header h2 { color: #4ade80; }
        .suspicious-geo .modal-header h2 { color: <?= $themeCSS['danger'] ?>; }
        .modal-close { color: #8aa0bb; font-size: 24px; font-weight: bold; cursor: pointer; transition: color 0.2s; line-height: 1; }
        .modal-close:hover { color: <?= $themeCSS['text'] ?>; }
        .modal-body { display: flex; flex-direction: column; gap: 8px; }
        .geo-row { display: flex; align-items: center; padding: 4px 0; border-bottom: 1px dashed <?= $themeCSS['border'] ?>; font-size: 12px; }
        .geo-row:last-child { border-bottom: none; }
        .geo-label { width: 85px; color: #8aa0bb; font-size: 11px; font-weight: 500; text-transform: uppercase; flex-shrink: 0; }
        .geo-value { flex: 1; color: <?= $themeCSS['text'] ?>; font-size: 12px; word-break: break-word; }
        .geo-value.ip { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
        .unknown-geo .geo-value.ip { color: #4ade80; }
        .suspicious-geo .geo-value.ip { color: <?= $themeCSS['danger'] ?>; }
        .modal-loading { text-align: center; color: #8aa0bb; padding: 20px; font-size: 12px; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(100px); background: <?= $themeCSS['primary'] ?>; color: white; padding: 6px 14px; border-radius: 30px; font-size: 11px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 21000; opacity: 0; transition: transform 0.3s, opacity 0.3s; pointer-events: none; }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .add-device-modal .modal-header h2 { color: #4ade80; }
        .modal-form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .modal-form-group label { font-size: 11px; font-weight: 500; color: #8aa0bb; text-transform: uppercase; }
        .modal-form-control { padding: 8px 10px; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; color: <?= $themeCSS['text'] ?>; font-size: 13px; }
        .modal-form-control:focus { outline: none; border-color: <?= $themeCSS['primary'] ?>; }
        .modal-btn { padding: 10px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 5px; }
        .modal-btn-primary { background: <?= $themeCSS['primary'] ?>; color: white; }
        .modal-btn-success { background: #4ade80; color: white; }
        .modal-btn-success:hover { background: #6ee7b7; transform: translateY(-2px); }
        .modal-btn-success:disabled { opacity: 0.5; cursor: not-allowed; }
        .modal-message { padding: 8px; border-radius: 4px; font-size: 11px; text-align: center; display: none; margin-bottom: 10px; }
        .modal-message.success { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid #4ade80; display: block; }
        .modal-message.error { background: rgba(231, 76, 60, 0.2); color: <?= $themeCSS['danger'] ?>; border: 1px solid <?= $themeCSS['danger'] ?>; display: block; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: <?= $themeCSS['bg'] ?>; border-radius: 2px; }
        ::-webkit-scrollbar-thumb { background: <?= $themeCSS['border'] ?>; border-radius: 2px; }
        ::-webkit-scrollbar-thumb:hover { background: <?= $themeCSS['primary'] ?>; }
        @media (max-width: 768px) { .version-badge .tooltip-text { width: 180px; font-size: 10px; padding: 8px 10px; } .unknown-header { grid-template-columns: 1fr 0.8fr 0.1fr 0.3fr; } .unknown-item { grid-template-columns: 1fr 0.8fr 0.1fr 0.3fr; } }
    </style>
</head>
<body>
    <div class="top-panel">
        <div class="header-container">
            <div class="header">
                <div class="header-left">
                    <h1>📊 KMS MONITOR <span class="live-indicator"><span class="dot"></span>LIVE</span></h1>
                    <div class="uptime-info" title="<?= __('tooltip_server_started') . date('Y-m-d H:i:s', $uptime['start_time']) ?>">
                        <span class="uptime-status"><?= $uptime['status'] ?></span>
                        <span class="uptime-value"><?= $uptime['days'] ?>д <?= $uptime['hours'] ?>ч <?= $uptime['minutes'] ?>м</span>
                    </div>
                    <?php if (!$cacheStatus && !empty($cacheMessage)): ?>
                    <div class="cache-warning" title="<?= __('tooltip_cache_warning') ?>"><?= $cacheMessage ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Время клиента прижато к правому краю шапки -->
                <div class="time-display" id="clientTime">--:--:--</div>
            </div>
            
            <!-- Кнопка настроек за пределами шапки -->
            <a href="vlmcconf/vlmcconf.php" class="settings-button" title="<?= __('tooltip_settings') ?>">⚙️</a>
        </div>
        
        <div class="columns">
            <div class="left-column">
                <?php if (!empty($stats['products'])): 
                    $maxProduct = max($stats['products']);
                    $products = $stats['products'];
                    arsort($products);
                    $half = ceil(count($products) / 2);
                    $firstHalf = array_slice($products, 0, $half, true);
                    $secondHalf = array_slice($products, $half, null, true);
                ?>
                <div class="product-stats">
                    <div class="product-stats-title">
                        📦 <?= __('products_title') ?>
                        <?php if (!empty($stats['kms_versions'])): ?>
                        <div class="version-info">
                            <span class="version-badge v4" id="v4-badge"><span>v4:</span> <?= $v4Count ?>
                                <span class="tooltip-text" id="v4-tooltip">
                                    <strong>🔮 KMS v4.0</strong><br>
                                    • <?= __('tooltip_requests') ?>: <?= $v4Count ?><br>
                                    • <?= __('tooltip_percent') ?>: <?= $v4Percent ?>%<br>
                                    • <?= __('tooltip_last') ?>: <?= $lastV4Time ?><br>
                                    • <?= __('tooltip_devices') ?>: <?= $v4DevicesCount ?><br>
                                    • <?= __('tooltip_products') ?>: <?= !empty($v4ProductsList) ? implode('<br>  ', $v4ProductsList) : __('none') ?>
                                </span>
                            </span>
                            <span class="version-badge v6" id="v6-badge"><span>v6:</span> <?= $v6Count ?>
                                <span class="tooltip-text" id="v6-tooltip">
                                    <strong>⚡ KMS v6.0</strong><br>
                                    • <?= __('tooltip_requests') ?>: <?= $v6Count ?><br>
                                    • <?= __('tooltip_percent') ?>: <?= $v6Percent ?>%<br>
                                    • <?= __('tooltip_last') ?>: <?= $lastV6Time ?><br>
                                    • <?= __('tooltip_devices') ?>: <?= $v6DevicesCount ?><br>
                                    • <?= __('tooltip_products') ?>: <?= !empty($v6ProductsList) ? implode('<br>  ', $v6ProductsList) : __('none') ?>
                                </span>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-grid">
                        <div class="product-column">
                            <?php foreach ($firstHalf as $product => $count): $percent = ($count / $maxProduct) * 100; ?>
                            <div class="product-item">
                                <span class="product-name" title="<?= htmlspecialchars($product) ?>"><?= htmlspecialchars($product) ?></span>
                                <div class="product-bar-container"><div class="product-bar-fill" style="width: <?= $percent ?>%"></div></div>
                                <span class="product-count"><?= $count ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="product-column">
                            <?php foreach ($secondHalf as $product => $count): $percent = ($count / $maxProduct) * 100; ?>
                            <div class="product-item">
                                <span class="product-name" title="<?= htmlspecialchars($product) ?>"><?= htmlspecialchars($product) ?></span>
                                <div class="product-bar-container"><div class="product-bar-fill" style="width: <?= $percent ?>%"></div></div>
                                <span class="product-count"><?= $count ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="legend">
                    <div class="legend-items">
                        <?php foreach ($groupColors as $groupName => $color): ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background: <?= $color ?>"></div>
                            <span><?= __($groupName) ?></span>
                            <span class="legend-stats"><span style="color: <?= $color ?>;">●</span> <?= $deviceStats[$groupName] ?? 0 ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="middle-column">
                <div class="unknown-block">
                    <div class="unknown-title">❓ <?= __('unknown_title') ?> <span class="unknown-count-badge"><?= count($stats['unknown_devices'] ?? []) ?></span></div>
                    <div class="unknown-header" id="unknownHeader">
                        <span data-sort="device"><?= __('unknown_device') ?> <span class="sort-arrow" id="unknownSortDevice">▼</span></span>
                        <span data-sort="ip"><?= __('unknown_ip') ?> <span class="sort-arrow" id="unknownSortIp"></span></span>
                        <span style="text-align: center;"></span>
                        <span style="text-align: right;" data-sort="count"><?= __('unknown_requests') ?> <span class="sort-arrow" id="unknownSortCount"></span></span>
                    </div>
                    <div class="unknown-list" id="unknownList"></div>
                </div>
            </div>
            
            <div class="right-column">
                <div class="suspicious-block">
                    <div class="suspicious-title">⚠️ <?= __('suspicious_title') ?> <span class="suspicious-count-badge"><?= count($stats['suspicious_ips'] ?? []) ?></span></div>
                    <div class="suspicious-header" id="suspiciousHeader">
                        <span data-sort="ip">IP <span class="sort-arrow" id="suspiciousSortIp">▼</span></span>
                        <span data-sort="count"><?= __('suspicious_connections') ?> <span class="sort-arrow" id="suspiciousSortCount"></span></span>
                        <span data-sort="cidr">CIDR <span class="sort-arrow" id="suspiciousSortCidr"></span></span>
                        <span style="text-align: right;" data-sort="country"><?= __('suspicious_country') ?> <span class="sort-arrow" id="suspiciousSortCountry"></span></span>
                    </div>
                    <div class="suspicious-list" id="suspiciousList"></div>
                </div>
            </div>
        </div>
        
        <div class="filter-bar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="<?= __('search_placeholder') ?>">
                <button onclick="filterLog()"><?= __('search_btn') ?></button>
                <button onclick="resetSearch()"><?= __('reset_btn') ?></button>
            </div>
            <select class="filter-select" id="eventFilter">
                <option value="all"><?= __('filter_all') ?></option>
                <option value="requests"><?= __('filter_requests') ?></option>
                <option value="connections"><?= __('filter_connections') ?></option>
            </select>
            <select class="filter-select" id="groupFilter">
                <option value="all"><?= __('filter_all_groups') ?></option>
                <?php foreach ($groupColors as $groupName => $color): ?>
                <option value="<?= $groupName ?>" style="color: <?= $color ?>;"><?= __($groupName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="log-wrapper">
        <div class="log-container" id="logContent">
            <?= $initialLog ?>
        </div>
        <button class="scroll-bottom-btn" id="scrollBottomBtn">↓</button>
    </div>
    
    <div class="footer">KMS Log Monitor • <?= __('version') ?> 4.8.0 • <?= __('footer_copyright') ?></div>
    
    <div id="geoModal" class="modal"><div class="modal-content" id="geoModalContent"><div class="modal-header" id="geoModalHeader"><h2>🌍 <?= __('geo_title') ?></h2><span class="modal-close" onclick="closeGeoModal()">&times;</span></div><div class="modal-body" id="geoModalBody"><div class="modal-loading">⏳ <?= __('geo_loading') ?></div></div></div></div>
    
    <div id="addDeviceModal" class="modal"><div class="modal-content add-device-modal"><div class="modal-header"><h2>➕ <?= __('add_device_title') ?></h2><span class="modal-close" onclick="closeAddDeviceModal()">&times;</span></div><div class="modal-body"><div id="addDeviceMessage" class="modal-message"></div><form id="addDeviceForm"><div class="modal-form-group"><label><?= __('add_device_name') ?></label><input type="text" id="deviceName" class="modal-form-control" required></div><div class="modal-form-group"><label><?= __('add_device_group') ?></label><select id="deviceGroup" class="modal-form-control"><?php foreach ($groupColors as $groupName => $color): ?><option value="<?= $groupName ?>"><?= __($groupName) ?></option><?php endforeach; ?></select></div><div class="modal-form-group"><label><?= __('add_device_comment') ?></label><input type="text" id="deviceComment" class="modal-form-control" placeholder="<?= __('add_device_comment_placeholder') ?>"></div><input type="hidden" id="deviceIp" value=""><button type="submit" class="modal-btn modal-btn-success" id="submitAddDevice"><?= __('add_device_btn') ?></button></form></div></div></div>
    
    <div id="toast" class="toast">📋 <?= __('toast_copied') ?></div>
    
    <script>
    const unknownDevices = <?= json_encode($unknownDevicesArray) ?>;
    const suspiciousIps = <?= json_encode($suspiciousIpsArray) ?>;
    const groupColors = <?= json_encode($groupColors) ?>;
    
    let unknownSort = { field: 'device', direction: 'asc' };
    let suspiciousSort = { field: 'ip', direction: 'asc' };
    let fullLog = '';
    let previousLog = '';
    let updateInterval = 2000;
    let currentFilter = '';
    let currentEventFilter = 'all';
    let currentGroupFilter = 'all';
    
    function positionTooltips() {
        const v4badge = document.getElementById('v4-badge');
        const v6badge = document.getElementById('v6-badge');
        const v4tooltip = document.getElementById('v4-tooltip');
        const v6tooltip = document.getElementById('v6-tooltip');
        const tooltipWidth = 220;
        const margin = 10;
        
        if (v4badge && v4tooltip) {
            const rect = v4badge.getBoundingClientRect();
            let leftPos = rect.left - tooltipWidth - margin;
            let position = 'right';
            if (leftPos < margin) { leftPos = rect.right + margin; position = 'left'; }
            if (leftPos + tooltipWidth > window.innerWidth - margin) { leftPos = window.innerWidth - tooltipWidth - margin; position = 'left'; }
            let topPos = rect.top + (rect.height / 2) - 40;
            if (topPos < margin) topPos = margin;
            if (topPos + 80 > window.innerHeight - margin) topPos = window.innerHeight - 90;
            v4tooltip.style.left = leftPos + 'px';
            v4tooltip.style.top = topPos + 'px';
            v4tooltip.style.transform = 'none';
            v4tooltip.setAttribute('data-position', position);
        }
        
        if (v6badge && v6tooltip) {
            const rect = v6badge.getBoundingClientRect();
            let leftPos = rect.left - tooltipWidth - margin;
            let position = 'right';
            if (leftPos < margin) { leftPos = rect.right + margin; position = 'left'; }
            if (leftPos + tooltipWidth > window.innerWidth - margin) { leftPos = window.innerWidth - tooltipWidth - margin; position = 'left'; }
            let topPos = rect.top + (rect.height / 2) - 40;
            if (topPos < margin) topPos = margin;
            if (topPos + 80 > window.innerHeight - margin) topPos = window.innerHeight - 90;
            v6tooltip.style.left = leftPos + 'px';
            v6tooltip.style.top = topPos + 'px';
            v6tooltip.style.transform = 'none';
            v6tooltip.setAttribute('data-position', position);
        }
    }
    
    window.addEventListener('scroll', positionTooltips, true);
    window.addEventListener('resize', positionTooltips);
    document.getElementById('v4-badge')?.addEventListener('mouseenter', positionTooltips);
    document.getElementById('v6-badge')?.addEventListener('mouseenter', positionTooltips);
    
    function showToast(message = '<?= __('toast_copied') ?>') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2000);
    }
    
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try { document.execCommand('copy'); showToast('📋 CIDR ' + text); } catch(err) { showToast('❌ <?= __('toast_copy_error') ?>'); }
        document.body.removeChild(textarea);
    }
    
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => showToast('📋 CIDR ' + text)).catch(err => fallbackCopy(text));
        } else { fallbackCopy(text); }
    }
    
    function showGeoModal(ip, type = 'suspicious') {
        const modal = document.getElementById('geoModal');
        const modalContent = document.getElementById('geoModalContent');
        const modalHeader = document.getElementById('geoModalHeader');
        const modalBody = document.getElementById('geoModalBody');
        modalContent.className = 'modal-content';
        if (type === 'unknown') {
            modalContent.classList.add('unknown-geo');
            modalHeader.querySelector('h2').textContent = '🌍 <?= __('geo_device_title') ?>';
        } else {
            modalContent.classList.add('suspicious-geo');
            modalHeader.querySelector('h2').textContent = '🌍 <?= __('geo_suspicious_title') ?>';
        }
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div class="modal-loading">⏳ <?= __('geo_loading') ?></div>';
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        fetch('?ajax=geo&ip=' + encodeURIComponent(ip), { signal: controller.signal })
            .then(response => { clearTimeout(timeoutId); if (!response.ok) throw new Error('HTTP error ' + response.status); return response.json(); })
            .then(data => {
                let html = `<div class="geo-row"><span class="geo-label">IP:</span><span class="geo-value ip">${ip}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_country') ?>:</span><span class="geo-value">${data.country || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_region') ?>:</span><span class="geo-value">${data.region || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_city') ?>:</span><span class="geo-value">${data.city || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_isp') ?>:</span><span class="geo-value">${data.isp || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_org') ?>:</span><span class="geo-value">${data.org || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_timezone') ?>:</span><span class="geo-value">${data.timezone || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label">CIDR:</span><span class="geo-value">${data.cidr || '—'}</span></div>
                    <div class="geo-row"><span class="geo-label"><?= __('geo_range') ?>:</span><span class="geo-value">${data.ip_range || '—'}</span></div>`;
                if (data.provider_ranges && data.provider_ranges.length > 0) {
                    html += `<div class="geo-row" style="border-bottom: none;"><span class="geo-label"><?= __('geo_provider_ranges') ?> ${data.isp}:</span></div>`;
                    data.provider_ranges.forEach(range => { html += `<div class="geo-row" style="padding-left: 90px;"><span class="geo-value" style="font-family: 'JetBrains Mono', monospace;">${range.range}</span><span class="geo-value" style="text-align: right;">${range.desc}</span></div>`; });
                }
                modalBody.innerHTML = html;
            })
            .catch(error => { clearTimeout(timeoutId); modalBody.innerHTML = '<div class="geo-row" style="color: #e74c3c;">❌ <?= __('geo_error') ?><br><span style="font-size: 10px;">' + error.message + '</span></div>'; });
    }
    
    function closeGeoModal() { document.getElementById('geoModal').style.display = 'none'; }
    
    function showAddDeviceModal(deviceName, ip) {
        document.getElementById('deviceName').value = deviceName || '';
        document.getElementById('deviceIp').value = ip || '';
        document.getElementById('deviceComment').value = '';
        document.getElementById('addDeviceMessage').style.display = 'none';
        document.getElementById('submitAddDevice').disabled = false;
        document.getElementById('addDeviceModal').style.display = 'flex';
    }
    
    function closeAddDeviceModal() { document.getElementById('addDeviceModal').style.display = 'none'; }
    
    document.getElementById('addDeviceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const deviceName = document.getElementById('deviceName').value.trim();
        const deviceGroup = document.getElementById('deviceGroup').value;
        const deviceComment = document.getElementById('deviceComment').value.trim();
        const submitBtn = document.getElementById('submitAddDevice');
        const messageDiv = document.getElementById('addDeviceMessage');
        if (!deviceName) { messageDiv.className = 'modal-message error'; messageDiv.textContent = '❌ <?= __('add_device_error_name') ?>'; messageDiv.style.display = 'block'; return; }
        submitBtn.disabled = true;
        messageDiv.className = 'modal-message';
        messageDiv.textContent = '⏳ <?= __('add_device_loading') ?>';
        messageDiv.style.display = 'block';
        const formData = new FormData();
        formData.append('ajax', 'add_device');
        formData.append('deviceName', deviceName);
        formData.append('deviceGroup', deviceGroup);
        formData.append('deviceComment', deviceComment);
        fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.className = 'modal-message success';
                    messageDiv.textContent = '✅ ' + data.message;
                    setTimeout(() => { closeAddDeviceModal(); location.reload(); }, 1500);
                } else {
                    messageDiv.className = 'modal-message error';
                    messageDiv.textContent = '❌ ' + data.message;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => { messageDiv.className = 'modal-message error'; messageDiv.textContent = '❌ <?= __('add_device_error') ?>'; submitBtn.disabled = false; });
    });
    
    window.onclick = function(event) {
        if (event.target === document.getElementById('geoModal')) closeGeoModal();
        if (event.target === document.getElementById('addDeviceModal')) closeAddDeviceModal();
    }
    
    function sortUnknown(field) {
        if (unknownSort.field === field) unknownSort.direction = unknownSort.direction === 'asc' ? 'desc' : 'asc';
        else { unknownSort.field = field; unknownSort.direction = 'asc'; }
        document.getElementById('unknownSortDevice').innerHTML = unknownSort.field === 'device' ? (unknownSort.direction === 'asc' ? '▲' : '▼') : '';
        document.getElementById('unknownSortIp').innerHTML = unknownSort.field === 'ip' ? (unknownSort.direction === 'asc' ? '▲' : '▼') : '';
        document.getElementById('unknownSortCount').innerHTML = unknownSort.field === 'count' ? (unknownSort.direction === 'asc' ? '▲' : '▼') : '';
        renderUnknownList();
    }
    
    function sortSuspicious(field) {
        if (suspiciousSort.field === field) suspiciousSort.direction = suspiciousSort.direction === 'asc' ? 'desc' : 'asc';
        else { suspiciousSort.field = field; suspiciousSort.direction = 'asc'; }
        document.getElementById('suspiciousSortIp').innerHTML = suspiciousSort.field === 'ip' ? (suspiciousSort.direction === 'asc' ? '▲' : '▼') : '';
        document.getElementById('suspiciousSortCount').innerHTML = suspiciousSort.field === 'count' ? (suspiciousSort.direction === 'asc' ? '▲' : '▼') : '';
        document.getElementById('suspiciousSortCidr').innerHTML = suspiciousSort.field === 'cidr' ? (suspiciousSort.direction === 'asc' ? '▲' : '▼') : '';
        document.getElementById('suspiciousSortCountry').innerHTML = suspiciousSort.field === 'country' ? (suspiciousSort.direction === 'asc' ? '▲' : '▼') : '';
        renderSuspiciousList();
    }
    
    function renderUnknownList() {
        const list = document.getElementById('unknownList');
        if (!unknownDevices || unknownDevices.length === 0) { list.innerHTML = '<div style="color: #8aa0bb; text-align: center; padding: 15px; font-size: 10px;"><?= __('unknown_empty') ?></div>'; return; }
        let sorted = [...unknownDevices];
        sorted.sort((a, b) => {
            let valA, valB;
            switch(unknownSort.field) {
                case 'device': valA = a.device.toLowerCase(); valB = b.device.toLowerCase(); break;
                case 'ip': valA = a.last_ip; valB = b.last_ip; break;
                case 'count': valA = a.count; valB = b.count; break;
                default: valA = a.device; valB = b.device;
            }
            if (valA < valB) return unknownSort.direction === 'asc' ? -1 : 1;
            if (valA > valB) return unknownSort.direction === 'asc' ? 1 : -1;
            return 0;
        });
        let html = '';
        sorted.forEach(item => {
            const escapedDevice = escapeHtml(item.device);
            const escapedIp = escapeHtml(item.last_ip);
            html += `<div class="unknown-item" title="<?= __('unknown_first_seen') ?> ${new Date(item.first_seen * 1000).toLocaleString()}\n<?= __('unknown_last_seen') ?> ${new Date(item.last_seen * 1000).toLocaleString()}">
                <span class="unknown-device">${escapedDevice}</span>
                <span class="unknown-ip" onclick="showGeoModal('${escapedIp}', 'unknown')">${escapedIp}</span>
                <span class="unknown-add"><button class="add-device-btn" onclick="showAddDeviceModal('${escapedDevice.replace(/'/g, "\\'")}', '${escapedIp}')" title="<?= __('unknown_add_tooltip') ?>">➕</button></span>
                <span class="unknown-count">${item.count}</span>
            </div>`;
        });
        list.innerHTML = html;
    }
    
function renderSuspiciousList() {
    const list = document.getElementById('suspiciousList');
    if (!suspiciousIps || suspiciousIps.length === 0) { 
        list.innerHTML = '<div style="color: #8aa0bb; text-align: center; padding: 15px; font-size: 10px;"><?= __('suspicious_empty') ?></div>'; 
        return; 
    }
    let sorted = [...suspiciousIps];
    sorted.sort((a, b) => {
        let valA, valB;
        switch(suspiciousSort.field) {
            case 'ip': valA = a.ip; valB = b.ip; break;
            case 'count': valA = a.count; valB = b.count; break;
            case 'cidr': valA = a.cidr; valB = b.cidr; break;
            case 'country': valA = (a.country_name || a.country).toLowerCase(); valB = (b.country_name || b.country).toLowerCase(); break;
            default: valA = a.ip; valB = b.ip;
        }
        if (valA < valB) return suspiciousSort.direction === 'asc' ? -1 : 1;
        if (valA > valB) return suspiciousSort.direction === 'asc' ? 1 : -1;
        return 0;
    });
    let html = '';
    sorted.forEach(item => {
        const escapedIp = escapeHtml(item.ip);
        const escapedCidr = escapeHtml(item.cidr);
        const countryCode = item.country_code || '';
        const countryName = escapeHtml(item.country_name || item.country || 'Unknown');
        const flagUrl = countryCode ? `https://cdn.jsdelivr.net/npm/flag-icons@7.5.0/flags/4x3/${countryCode}.svg` : '';
        
        html += `<div class="suspicious-item" title="<?= __('suspicious_duration') ?>: ${item.duration}с">
            <span class="suspicious-ip" onclick="showGeoModal('${escapedIp}', 'suspicious')">${escapedIp}</span>
            <span class="suspicious-count">${item.count}</span>
            <div class="suspicious-cidr-container"><span class="suspicious-cidr">${escapedCidr}</span><button class="copy-cidr-btn" onclick="event.stopPropagation(); copyToClipboard('${escapedCidr}')" title="<?= __('suspicious_copy_cidr') ?>">📋</button></div>
            <span class="suspicious-country">${flagUrl ? `<img src="${flagUrl}" alt="${countryCode}" style="width: 16px; height: 12px; margin-right: 6px; vertical-align: middle;">` : ''}${countryName}</span>
        </div>`;
    });
    list.innerHTML = html;
}
    
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    function scrollToBottom() { const container = document.getElementById('logContent'); if (container) container.scrollTop = container.scrollHeight; }
    
    // Функция для обновления времени клиента
    function updateClientTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('clientTime').innerHTML = timeStr;
    }
    
    // Запускаем обновление времени
    setInterval(updateClientTime, 1000);
    updateClientTime();
    
	async function fetchLog() {
		try {
			const response = await fetch('?ajax=get_log&filter=' + currentEventFilter + '&group=' + currentGroupFilter + '&t=' + Date.now());
			const log = await response.text();
			if (log !== previousLog) {
				const container = document.getElementById('logContent');
				const scrollPos = container.scrollTop;
				const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
				fullLog = log;
				previousLog = log;
				if (currentFilter === '') container.innerHTML = fullLog;
				else applyFilter(currentFilter);
				if (atBottom) setTimeout(scrollToBottom, 100);
				else container.scrollTop = scrollPos;
			}
		} catch (error) { console.error('Log load error:', error); }
	}
    
    function applyFilter(filter) {
        const container = document.getElementById('logContent');
        if (filter === '') { container.innerHTML = fullLog; return; }
        const temp = document.createElement('div');
        temp.innerHTML = fullLog;
        const lines = Array.from(temp.children);
        const filtered = lines.filter(div => div.textContent.toLowerCase().includes(filter));
        if (filtered.length === 0) container.innerHTML = '<div class="no-results">🔍 <?= __('no_results') ?></div>';
        else container.innerHTML = filtered.map(div => div.outerHTML).join('');
    }
    
    function filterLog() { const input = document.getElementById('searchInput'); currentFilter = input.value.toLowerCase().trim(); applyFilter(currentFilter); }
    function resetSearch() { document.getElementById('searchInput').value = ''; currentFilter = ''; applyFilter(''); }
    
    document.getElementById('eventFilter').addEventListener('change', function(e) { currentEventFilter = e.target.value; previousLog = ''; fetchLog(); });
    document.getElementById('groupFilter').addEventListener('change', function(e) { currentGroupFilter = e.target.value; previousLog = ''; fetchLog(); });
    document.getElementById('searchInput').addEventListener('input', function(e) { if (e.target.value.trim() === '') { currentFilter = ''; applyFilter(''); } });
    document.getElementById('searchInput').addEventListener('keyup', function(e) { if (e.key === 'Enter') filterLog(); });
    document.getElementById('scrollBottomBtn').addEventListener('click', scrollToBottom);
    
    document.addEventListener('DOMContentLoaded', function() {
        fullLog = document.getElementById('logContent').innerHTML;
        previousLog = fullLog;
        renderUnknownList();
        renderSuspiciousList();
        document.getElementById('unknownHeader').addEventListener('click', function(e) { const target = e.target.closest('span[data-sort]'); if (target) sortUnknown(target.dataset.sort); });
        document.getElementById('suspiciousHeader').addEventListener('click', function(e) { const target = e.target.closest('span[data-sort]'); if (target) sortSuspicious(target.dataset.sort); });
        setTimeout(positionTooltips, 100);
        setTimeout(scrollToBottom, 100);
        fetchLog();
        setInterval(fetchLog, updateInterval);
    });
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</body>
</html>