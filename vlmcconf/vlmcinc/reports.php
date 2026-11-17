<?php
// ============================================
// ФАЙЛ: vlmcinc/reports.php
// ВЕРСИЯ: 1.2.0
// ДАТА: 2026-11-17
// @description: Отчёты по логу: IP устройства, устройства на IP, устройства с несколькими IP, IP с несколькими
//               устройствами, топ устройств, давно не появлявшиеся, новые устройства, продукты, по стране.
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

// ---------------- Дополнительные отчёты ----------------

define('REPORT_MAX_ROWS', 500);

/** Сводка по каждому устройству: число IP, запросов, первый/последний раз, последний IP и список IP. */
function reportDeviceSummary($logFile) {
    $out = [];
    foreach (reportPairs($logFile) as $dev => $byIp) {
        $count = 0; $first = ''; $last = ''; $lastIp = '';
        $ipList = [];
        foreach ($byIp as $ip => $p) {
            $count += $p[0];
            if ($p[1] !== '' && ($first === '' || $p[1] < $first)) $first = $p[1];
            if ($p[2] !== '' && $p[2] >= $last) { $last = $p[2]; $lastIp = (string)$ip; }
            $ipList[(string)$ip] = $p[2];
        }
        arsort($ipList);
        $out[(string)$dev] = ['ips' => count($byIp), 'count' => $count, 'first' => $first, 'last' => $last,
                              'last_ip' => $lastIp, 'ip_list' => array_keys($ipList)];
    }
    return $out;
}

function reportSortLast(array &$rows) {
    usort($rows, function ($a, $b) { return strcmp($b['last'], $a['last']) ?: ($b['count'] <=> $a['count']); });
}

function reportDaysAgo($time) {
    $ts = $time !== '' ? strtotime($time) : false;
    return $ts === false ? null : (int)floor((time() - $ts) / 86400);
}

/** Устройства, которые обращались с нескольких IP (смена сети, VPN, ноутбук в разных офисах). */
function reportMultiIp($logFile) {
    $rows = [];
    foreach (reportDeviceSummary($logFile) as $dev => $d) {
        if ($d['ips'] > 1) {
            $rows[] = ['device' => $dev, 'ips' => $d['ips'], 'count' => $d['count'], 'last' => $d['last'],
                       'ip_list' => implode(', ', array_slice($d['ip_list'], 0, 5)) . ($d['ips'] > 5 ? ', …' : '')];
        }
    }
    usort($rows, function ($a, $b) { return ($b['ips'] <=> $a['ips']) ?: strcmp($b['last'], $a['last']); });
    return $rows;
}

/** IP, с которых обращалось несколько разных устройств (NAT, офис, а также подозрительные адреса). */
function reportSharedIps($logFile) {
    $byIp = [];
    foreach (reportPairs($logFile) as $dev => $ips) {
        foreach ($ips as $ip => $p) {
            $ip = (string)$ip;
            if (!isset($byIp[$ip])) $byIp[$ip] = ['devices' => 0, 'count' => 0, 'last' => ''];
            $byIp[$ip]['devices']++;
            $byIp[$ip]['count'] += $p[0];
            if ($p[2] > $byIp[$ip]['last']) $byIp[$ip]['last'] = $p[2];
        }
    }
    $rows = [];
    foreach ($byIp as $ip => $d) {
        if ($d['devices'] > 1) {
            $c = reportCountry($ip);
            $rows[] = ['ip' => $ip, 'country' => $c['country'], 'flag' => $c['flag'],
                       'devices' => $d['devices'], 'count' => $d['count'], 'last' => $d['last']];
        }
    }
    usort($rows, function ($a, $b) { return ($b['devices'] <=> $a['devices']) ?: ($b['count'] <=> $a['count']); });
    return $rows;
}

/** Самые активные устройства по числу запросов. */
function reportTopDevices($logFile) {
    $rows = [];
    foreach (reportDeviceSummary($logFile) as $dev => $d) {
        $rows[] = ['device' => $dev, 'count' => $d['count'], 'ips' => $d['ips'], 'first' => $d['first'], 'last' => $d['last']];
    }
    usort($rows, function ($a, $b) { return $b['count'] <=> $a['count']; });
    return array_slice($rows, 0, 50);
}

/** Устройства, которых не было в логе дольше $days дней. */
function reportInactive($logFile, $days) {
    $rows = [];
    $limit = time() - $days * 86400;
    foreach (reportDeviceSummary($logFile) as $dev => $d) {
        $ts = $d['last'] !== '' ? strtotime($d['last']) : false;
        if ($ts !== false && $ts < $limit) {
            $rows[] = ['device' => $dev, 'last' => $d['last'], 'days_ago' => reportDaysAgo($d['last']),
                       'count' => $d['count'], 'last_ip' => $d['last_ip']];
        }
    }
    usort($rows, function ($a, $b) { return strcmp($a['last'], $b['last']); });   // самые давние первыми
    return $rows;
}

/** Устройства, впервые появившиеся в логе за последние $days дней. */
function reportNewDevices($logFile, $days) {
    $rows = [];
    $limit = time() - $days * 86400;
    foreach (reportDeviceSummary($logFile) as $dev => $d) {
        $ts = $d['first'] !== '' ? strtotime($d['first']) : false;
        if ($ts !== false && $ts >= $limit) {
            $rows[] = ['device' => $dev, 'first' => $d['first'], 'last' => $d['last'], 'count' => $d['count'], 'ips' => $d['ips']];
        }
    }
    usort($rows, function ($a, $b) { return strcmp($b['first'], $a['first']); });
    return $rows;
}

/** Продукты: сколько уникальных устройств и запросов по каждому. */
function reportProducts($logFile) {
    if (!is_string($logFile) || !is_file($logFile)) {
        return [];
    }
    $agg = logAggCached('report_products', $logFile, function () use ($logFile) {
        $products = [];
        $fh = @fopen($logFile, 'rb');
        if ($fh === false) {
            return [];
        }
        while (($line = fgets($fh)) !== false) {
            if (strpos($line, ' request from ') === false) {
                continue;
            }
            if (preg_match('/KMS v[46]\.0 request from (\S+) for (.+)$/', $line, $m)) {
                $prod = trim($m[2]);
                if (!isset($products[$prod])) $products[$prod] = [0, []];
                $products[$prod][0]++;
                $products[$prod][1][$m[1]] = 1;
            }
        }
        fclose($fh);
        return $products;
    });
    $rows = [];
    foreach ($agg as $prod => $d) {
        $rows[] = ['product' => (string)$prod, 'devices' => count($d[1]), 'count' => $d[0]];
    }
    usort($rows, function ($a, $b) { return ($b['devices'] <=> $a['devices']) ?: ($b['count'] <=> $a['count']); });
    return $rows;
}

/** Метка группы «страна не определена» (страна берётся только из кэша геолокации). */
define('REPORT_COUNTRY_UNKNOWN', '__unknown__');

/** Страны из лога: страна (пусто = не определена), флаг, число IP и запросов. */
function reportCountries($logFile) {
    $byIp = [];
    foreach (reportPairs($logFile) as $dev => $ips) {
        foreach ($ips as $ip => $p) {
            $byIp[(string)$ip] = ($byIp[(string)$ip] ?? 0) + $p[0];
        }
    }
    $groups = [];
    foreach ($byIp as $ip => $cnt) {
        $c = reportCountry($ip);
        $key = $c['country'];
        if (!isset($groups[$key])) $groups[$key] = ['country' => $key, 'flag' => $c['flag'], 'ips' => 0, 'count' => 0];
        $groups[$key]['ips']++;
        $groups[$key]['count'] += $cnt;
    }
    $rows = array_values($groups);
    usort($rows, function ($a, $b) {
        if (($a['country'] === '') !== ($b['country'] === '')) return $a['country'] === '' ? 1 : -1;   // «не определена» в конец
        return $b['count'] <=> $a['count'];
    });
    return $rows;
}

/**
 * Отчёт «По стране»: IP выбранной страны и устройства, обращавшиеся с каждого IP.
 * $country: '' — все страны, REPORT_COUNTRY_UNKNOWN — страна не определена, иначе название страны.
 */
function reportByCountry($logFile, $country) {
    $ipData = [];
    foreach (reportPairs($logFile) as $dev => $ips) {
        foreach ($ips as $ip => $p) {
            $ip = (string)$ip;
            if (!isset($ipData[$ip])) $ipData[$ip] = ['devices' => [], 'count' => 0, 'last' => ''];
            $ipData[$ip]['devices'][(string)$dev] = $p[0];
            $ipData[$ip]['count'] += $p[0];
            if ($p[2] > $ipData[$ip]['last']) $ipData[$ip]['last'] = $p[2];
        }
    }
    $rows = [];
    $allDevices = [];
    $requests = 0;
    foreach ($ipData as $ip => $d) {
        $c = reportCountry($ip);
        if ($country === REPORT_COUNTRY_UNKNOWN) {
            if ($c['country'] !== '') continue;
        } elseif ($country !== '' && $c['country'] !== $country) {
            continue;
        }
        arsort($d['devices']);
        $names = array_map('strval', array_keys($d['devices']));
        foreach ($names as $n) $allDevices[$n] = 1;
        $requests += $d['count'];
        $rows[] = ['country' => $c['country'], 'flag' => $c['flag'], 'ip' => $ip,
                   'devices' => array_slice($names, 0, 6), 'devices_more' => max(0, count($names) - 6),
                   'count' => $d['count'], 'last' => $d['last']];
    }
    usort($rows, function ($a, $b) {
        if (($a['country'] === '') !== ($b['country'] === '')) return $a['country'] === '' ? 1 : -1;
        return strcmp($a['country'], $b['country']) ?: ($b['count'] <=> $a['count']);
    });
    return ['rows' => $rows, 'summary' => ['ips' => count($rows), 'devices' => count($allDevices), 'requests' => $requests]];
}
