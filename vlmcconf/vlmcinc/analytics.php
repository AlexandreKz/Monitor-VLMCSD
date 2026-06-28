<?php
// ============================================
// ФАЙЛ: vlmcinc/analytics.php
// ВЕРСИЯ: 1.2.0
// ДАТА: 2026-06-27
// @description: Функции для анализа лога и статистики
// ============================================

/**
 * Получение активности по датам (общая)
 */
function getActivityData($logFile, $period = 'day') {
    if (!file_exists($logFile)) return [];
    
    $content = file_get_contents($logFile);
    if ($content === false) return [];
    
    $lines = explode("\n", $content);
    $activity = [];
    
    foreach ($lines as $line) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            $date = $matches[1];
            $timestamp = strtotime($date);
            
            if ($period === 'day') {
                $key = $date;
            } else if ($period === 'week') {
                $weekNumber = date('W', $timestamp);
                $year = date('Y', $timestamp);
                $dto = new DateTime();
                $dto->setISODate($year, $weekNumber);
                $start = $dto->format('d.m');
                $dto->modify('+6 days');
                $end = $dto->format('d.m');
                $key = $start . '-' . $end;
            } else {
                $key = date('Y-m', $timestamp);
            }
            
            if (!isset($activity[$key])) $activity[$key] = 0;
            $activity[$key]++;
        }
    }
    
    ksort($activity);
    
    if ($period === 'day') {
        $activity = array_slice($activity, -30, null, true);
    } else {
        $activity = array_slice($activity, -12, null, true);
    }
    
    return $activity;
}

/**
 * Получение активности конкретного устройства по датам
 */
function getDeviceActivity($logFile, $deviceName, $period = 'day') {
    if (!file_exists($logFile)) return [];
    
    $content = file_get_contents($logFile);
    if ($content === false) return [];
    
    $lines = explode("\n", $content);
    $activity = [];
    
    foreach ($lines as $line) {
        if (preg_match('/KMS v[46]\.0 request from ' . preg_quote($deviceName, '/') . ' for/', $line)) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                $date = $matches[1];
                $timestamp = strtotime($date);
                
                if ($period === 'day') {
                    $key = $date;
                } else if ($period === 'week') {
                    $weekNumber = date('W', $timestamp);
                    $year = date('Y', $timestamp);
                    $dto = new DateTime();
                    $dto->setISODate($year, $weekNumber);
                    $start = $dto->format('d.m');
                    $dto->modify('+6 days');
                    $end = $dto->format('d.m');
                    $key = $start . '-' . $end;
                } else {
                    $key = date('Y-m', $timestamp);
                }
                
                if (!isset($activity[$key])) $activity[$key] = 0;
                $activity[$key]++;
            }
        }
    }
    
    ksort($activity);
    
    if ($period === 'day') {
        $activity = array_slice($activity, -30, null, true);
    } else {
        $activity = array_slice($activity, -12, null, true);
    }
    
    return $activity;
}

/**
 * Получение статистики по устройствам из лога (топ-20)
 */
function getDeviceRequests($logFile) {
    $deviceRequests = [];
    
    if (!file_exists($logFile)) return $deviceRequests;
    
    $content = file_get_contents($logFile);
    if ($content === false) return $deviceRequests;
    
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        if (preg_match('/KMS v[46]\.0 request from (\S+?) for/', $line, $matches)) {
            $device = $matches[1];
            $deviceRequests[$device] = ($deviceRequests[$device] ?? 0) + 1;
        }
    }
    
    arsort($deviceRequests);
    $deviceRequests = array_slice($deviceRequests, 0, 20, true);
    
    return $deviceRequests;
}

/**
 * Получение uptime сервера из лога
 */
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

/**
 * Получение самого старого и самого нового устройства из лога
 */
function getOldestNewestDevice($logFile) {
    $oldestDevice = null;
    $newestDevice = null;
    $oldestDate = PHP_INT_MAX;
    $newestDate = 0;
    
    if (!file_exists($logFile)) {
        return [$oldestDevice, $newestDevice, $oldestDate, $newestDate];
    }
    
    $content = file_get_contents($logFile);
    if ($content === false) {
        return [$oldestDevice, $newestDevice, $oldestDate, $newestDate];
    }
    
    $lines = explode("\n", $content);
    $deviceFirstSeen = [];
    $deviceLastSeen = [];
    
    foreach ($lines as $line) {
        if (preg_match('/KMS v[46]\.0 request from (\S+?) for/', $line, $matches)) {
            $device = $matches[1];
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $dateMatches)) {
                $timestamp = strtotime($dateMatches[1]);
                if (!isset($deviceFirstSeen[$device]) || $timestamp < $deviceFirstSeen[$device]) {
                    $deviceFirstSeen[$device] = $timestamp;
                }
                if (!isset($deviceLastSeen[$device]) || $timestamp > $deviceLastSeen[$device]) {
                    $deviceLastSeen[$device] = $timestamp;
                }
            }
        }
    }
    
    foreach ($deviceFirstSeen as $device => $timestamp) {
        if ($timestamp < $oldestDate) {
            $oldestDate = $timestamp;
            $oldestDevice = $device;
        }
    }
    
    foreach ($deviceLastSeen as $device => $timestamp) {
        if ($timestamp > $newestDate) {
            $newestDate = $timestamp;
            $newestDevice = $device;
        }
    }
    
    return [$oldestDevice, $newestDevice, $oldestDate, $newestDate];
}

/**
 * Получение информации о сервере (исправлено для Ubuntu 26.04)
 */
function getServerInfo() {
    if (file_exists('/etc/os-release')) {
        $osRelease = parse_ini_file('/etc/os-release');
        $osName = $osRelease['PRETTY_NAME'] ?? 'Linux';
    } else {
        $osName = php_uname('s') . ' ' . php_uname('r');
    }
    
    $hostname = gethostname();
    $serverIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? 'не определен';
    $phpVersion = phpversion();
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'не определен';
    
    // ============================================
    // UPTIME — через sysctl -n kern.boottime
    // ============================================
    $uptimeFormatted = '—';
    
    $boottimeRaw = @shell_exec('sysctl -n kern.boottime 2>/dev/null');
    if ($boottimeRaw && preg_match('/sec = (\d+)/', $boottimeRaw, $matches)) {
        $bootTime = (int)$matches[1];
        if ($bootTime > 0) {
            $uptimeSeconds = time() - $bootTime;
            if ($uptimeSeconds > 0) {
                $days = floor($uptimeSeconds / 86400);
                $hours = floor(($uptimeSeconds % 86400) / 3600);
                $minutes = floor(($uptimeSeconds % 3600) / 60);
                if ($days > 0) {
                    $uptimeFormatted = "{$days}д {$hours}ч {$minutes}м";
                } else {
                    $uptimeFormatted = "{$hours}ч {$minutes}м";
                }
            }
        }
    }
    
    // Запасной вариант: /proc/stat (если sysctl не сработал)
    if ($uptimeFormatted === '—') {
        $statContent = @file_get_contents('/proc/stat');
        if ($statContent !== false && preg_match('/btime (\d+)/', $statContent, $matches)) {
            $bootTime = (int)$matches[1];
            if ($bootTime > 0) {
                $uptimeSeconds = time() - $bootTime;
                if ($uptimeSeconds > 0) {
                    $days = floor($uptimeSeconds / 86400);
                    $hours = floor(($uptimeSeconds % 86400) / 3600);
                    $minutes = floor(($uptimeSeconds % 3600) / 60);
                    if ($days > 0) {
                        $uptimeFormatted = "{$days}д {$hours}ч {$minutes}м";
                    } else {
                        $uptimeFormatted = "{$hours}ч {$minutes}м";
                    }
                }
            }
        }
    }
    
    return [
        'osName' => $osName,
        'hostname' => $hostname,
        'serverIp' => $serverIp,
        'phpVersion' => $phpVersion,
        'serverSoftware' => $serverSoftware,
        'uptime' => $uptimeFormatted
    ];
}

/**
 * Подсчёт устройств с комментариями и средней длины комментария
 */
function getCommentsStats($config) {
    $devicesWithComments = 0;
    $totalComments = 0;
    
    foreach ($config['devices'] as $group => $deviceList) {
        foreach ($deviceList as $device) {
            if (!empty($device['comment'])) {
                $devicesWithComments++;
                $totalComments += strlen($device['comment']);
            }
        }
    }
    
    $avgCommentLength = $devicesWithComments > 0 ? round($totalComments / $devicesWithComments) : 0;
    
    return [
        'devicesWithComments' => $devicesWithComments,
        'avgCommentLength' => $avgCommentLength
    ];
}
?>