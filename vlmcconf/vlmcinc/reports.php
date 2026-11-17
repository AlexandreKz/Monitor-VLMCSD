<?php
// ============================================
// ФАЙЛ: vlmcinc/reports.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Отчёты по логу: все IP устройства и все устройства на IP.
//               Один потоковый проход по логу, результат в кэше агрегатов (log_cache.php).
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'reports.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Связки «устройство — IP» из лога.
 * IP берётся из ближайшей выше строки «connection accepted: ip:port», как и в основной странице.
 * @return array device => ip => [count, first, last]   (first/last — 'Y-m-d H:i:s')
 */
function reportPairs($logFile) {
    if (!is_string($logFile) || !is_file($logFile)) {
        return [];
    }
    return logAggCached('report_pairs', $logFile, function () use ($logFile) {
        $pairs = [];
        $fh = @fopen($logFile, 'rb');
        if ($fh === false) {
            return [];
        }
        $ip = null;
        $time = '';
        while (($line = fgets($fh)) !== false) {
            if (strpos($line, 'connection accepted: ') !== false) {
                if (preg_match('/connection accepted: ([\d\.]+):\d+/', $line, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
                    $ip = $m[1];
                    $time = preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $t) ? $t[1] : '';
                } else {
                    $ip = null;
                }
                continue;
            }
            if ($ip === null || strpos($line, ' request from ') === false) {
                continue;
            }
            if (!preg_match('/KMS v[46]\.0 request from (\S+) for /', $line, $m)) {
                continue;
            }
            $dev = $m[1];
            if (!isset($pairs[$dev][$ip])) {
                $pairs[$dev][$ip] = [0, $time, $time];
            }
            $p = &$pairs[$dev][$ip];
            $p[0]++;
            if ($time !== '' && ($p[1] === '' || $time < $p[1])) $p[1] = $time;
            if ($time !== '' && $time > $p[2]) $p[2] = $time;
            unset($p);
        }
        fclose($fh);
        return $pairs;
    });
}

/** Списки для подсказок: устройства и IP (по убыванию числа запросов, не более 3000 каждого). */
function reportLists($logFile) {
    $devices = [];
    $ips = [];
    foreach (reportPairs($logFile) as $dev => $byIp) {
        foreach ($byIp as $ip => $p) {
            $devices[$dev] = ($devices[$dev] ?? 0) + $p[0];
            $ips[$ip] = ($ips[$ip] ?? 0) + $p[0];
        }
    }
    arsort($devices);
    arsort($ips);
    return [
        'devices' => array_map('strval', array_slice(array_keys($devices), 0, 3000)),
        'ips'     => array_map('strval', array_slice(array_keys($ips), 0, 3000))
    ];
}

/** Страна IP только из кэша (сетевых запросов нет): ['country' => ..., 'flag' => ...]. */
function reportCountry($ip) {
    $country = function_exists('getGeoCachedCountry') ? getGeoCachedCountry($ip) : null;
    if ($country === null || $country === '') {
        return ['country' => '', 'flag' => ''];
    }
    return ['country' => $country, 'flag' => function_exists('getCountryFlag') ? getCountryFlag($country) : ''];
}

/**
 * Отчёт «IP устройства»: все адреса, с которых устройство обращалось к серверу.
 * Совпадение по имени: сначала точное (без учёта регистра), иначе по вхождению.
 */
function reportDeviceIps($logFile, $query) {
    $query = trim((string)$query);
    $pairs = reportPairs($logFile);
    $matched = reportMatchKeys(array_map('strval', array_keys($pairs)), $query);
    $rows = [];
    foreach ($matched as $dev) {
        foreach ($pairs[$dev] as $ip => $p) {
            $c = reportCountry($ip);
            $rows[] = ['device' => (string)$dev, 'ip' => (string)$ip, 'count' => $p[0], 'first' => $p[1], 'last' => $p[2],
                       'country' => $c['country'], 'flag' => $c['flag']];
        }
    }
    usort($rows, function ($a, $b) { return strcmp($b['last'], $a['last']) ?: ($b['count'] <=> $a['count']); });
    return ['rows' => $rows, 'matched' => $matched];
}

/** Отчёт «Устройства на IP»: все устройства, обращавшиеся с этого адреса. */
function reportIpDevices($logFile, $ip) {
    $ip = trim((string)$ip);
    $rows = [];
    foreach (reportPairs($logFile) as $dev => $byIp) {
        if (isset($byIp[$ip])) {
            $p = $byIp[$ip];
            $rows[] = ['device' => (string)$dev, 'ip' => $ip, 'count' => $p[0], 'first' => $p[1], 'last' => $p[2]];
        }
    }
    usort($rows, function ($a, $b) { return strcmp($b['last'], $a['last']) ?: ($b['count'] <=> $a['count']); });
    $c = reportCountry($ip);
    return ['rows' => $rows, 'country' => $c['country'], 'flag' => $c['flag']];
}

function reportLower($s) {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/** Имена, совпадающие с запросом: точно (без регистра), иначе по вхождению. */
function reportMatchKeys(array $names, $query) {
    if ($query === '') {
        return [];
    }
    $q = reportLower($query);
    $exact = [];
    $part = [];
    foreach ($names as $n) {
        $l = reportLower($n);
        if ($l === $q) {
            $exact[] = $n;
        } elseif (strpos($l, $q) !== false) {
            $part[] = $n;
        }
    }
    return $exact ?: array_slice($part, 0, 50);
}
