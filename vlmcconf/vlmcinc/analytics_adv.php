<?php
// ============================================
// ФАЙЛ: vlmcinc/analytics_adv.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Расширенная аналитика (пункт 33): запросы по часам, дням и месяцам, топ-10 устройств,
//               страны (по кэшу геолокации) с координатами для карты мира, таблицы для экспорта.
// @description: Advanced analytics (item 33): requests by hour/day/month, top 10 devices, countries.
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'analytics_adv.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/log_cache.php';
require_once __DIR__ . '/reports.php';

/** Допустимые периоды (дней); 0 — за всё время. */
function analyticsPeriods() {
    return [7, 30, 90, 365, 0];
}

/**
 * Один проход по логу: запросы KMS с временем. Время берётся из строки запроса,
 * при его отсутствии — из строки «connection accepted» этого соединения.
 * Результат кэшируется вместе с подписью лога (см. logAggCached).
 */
function analyticsAggregate($logFile, $days) {
    $empty = ['total' => 0, 'hours' => array_fill(0, 24, 0), 'weekdays' => array_fill(0, 7, 0),
              'days' => [], 'months' => [], 'devices' => [], 'ips' => [], 'first' => '', 'last' => ''];
    if (!is_string($logFile) || !is_file($logFile)) {
        return $empty;
    }
    return logAggCached('analytics_adv', $logFile, function () use ($logFile, $days, $empty) {
        $r = $empty;
        $cut = $days > 0 ? date('Y-m-d H:i:s', time() - $days * 86400) : '';
        $fh = @fopen($logFile, 'rb');
        if ($fh === false) {
            return $r;
        }
        $ip = null;
        $connTime = '';
        $devs = [];
        $dlast = [];
        $ips = [];
        while (($line = fgets($fh)) !== false) {
            if (strpos($line, 'connection accepted: ') !== false) {
                if (preg_match('/connection accepted: ([\d\.]+):\d+/', $line, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
                    $ip = $m[1];
                    $connTime = preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $t) ? $t[1] : '';
                } else {
                    $ip = null;
                }
                continue;
            }
            if (strpos($line, ' request from ') === false || !preg_match('/KMS v[46]\.0 request from (\S+) for /', $line, $m)) {
                continue;
            }
            $time = preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $t) ? $t[1] : $connTime;
            if ($time === '' || ($cut !== '' && $time < $cut)) {
                continue;
            }
            $r['total']++;
            $r['hours'][(int)substr($time, 11, 2)]++;
            $ts = strtotime($time);
            if ($ts !== false) {
                $r['weekdays'][(int)date('N', $ts) - 1]++;
            }
            $day = substr($time, 0, 10);
            $r['days'][$day] = ($r['days'][$day] ?? 0) + 1;
            $mon = substr($time, 0, 7);
            $r['months'][$mon] = ($r['months'][$mon] ?? 0) + 1;
            $dev = $m[1];
            $devs[$dev] = ($devs[$dev] ?? 0) + 1;
            if (!isset($dlast[$dev]) || $time > $dlast[$dev]) {
                $dlast[$dev] = $time;
            }
            if ($ip !== null) {
                $ips[$ip] = ($ips[$ip] ?? 0) + 1;
            }
            if ($r['first'] === '' || $time < $r['first']) $r['first'] = $time;
            if ($time > $r['last']) $r['last'] = $time;
        }
        fclose($fh);
        ksort($r['days']);
        ksort($r['months']);
        arsort($devs);
        arsort($ips);
        $r['device_count'] = count($devs);
        $r['ip_count'] = count($ips);
        $top = [];
        foreach (array_slice($devs, 0, 10, true) as $d => $c) {
            $top[] = [(string)$d, $c, $dlast[$d] ?? ''];
        }
        $r['top_devices'] = $top;
        $r['devices'] = [];
        $r['ips'] = array_slice($ips, 0, 5000, true);
        return $r;
    }, (string)$days);
}

/** Непрерывный ряд по дням (нули там, где запросов не было); для «за всё время» — не более 400 последних дней. */
function analyticsDaySeries(array $days, $period) {
    if (!$days) {
        return [];
    }
    $keys = array_keys($days);
    $end = strtotime(end($keys));
    $start = $period > 0 ? strtotime('-' . ($period - 1) . ' days', strtotime(date('Y-m-d'))) : strtotime($keys[0]);
    if ($period > 0) {
        $end = strtotime(date('Y-m-d'));
    }
    $out = [];
    for ($t = $start; $t <= $end && count($out) < 800; $t = strtotime('+1 day', $t)) {
        $d = date('Y-m-d', $t);
        $out[] = [$d, $days[$d] ?? 0];
    }
    return $out;
}

/** Полный ответ для вкладки «Аналитика». */
function analyticsBuild($logFile, $period) {
    $period = in_array($period, analyticsPeriods(), true) ? $period : 30;
    $a = analyticsAggregate($logFile, $period);

    // Страны: берутся только из кэша геолокации (сетевых запросов нет)
    $byCountry = [];
    foreach ($a['ips'] as $ip => $cnt) {
        $c = reportCountry((string)$ip);
        $name = $c['country'];
        if (!isset($byCountry[$name])) {
            $byCountry[$name] = ['country' => $name, 'ips' => 0, 'count' => 0];
        }
        $byCountry[$name]['ips']++;
        $byCountry[$name]['count'] += $cnt;
    }
    $countries = array_values($byCountry);
    usort($countries, function ($x, $y) {
        if (($x['country'] === '') !== ($y['country'] === '')) return $x['country'] === '' ? 1 : -1;
        return $y['count'] <=> $x['count'];
    });
    foreach ($countries as &$c) {
        $p = $c['country'] === '' ? null : analyticsCountryPoint($c['country']);
        $c['lat'] = $p ? $p[0] : null;
        $c['lon'] = $p ? $p[1] : null;
    }
    unset($c);

    $peak = 0;
    foreach ($a['hours'] as $h => $v) {
        if ($v > $a['hours'][$peak]) $peak = $h;
    }
    $dayVals = array_values($a['days']);
    $dayAvg = $dayVals ? round(array_sum($dayVals) / max(1, ($period > 0 ? $period : count($dayVals))), 1) : 0;
    $busiest = '';
    $busiestN = 0;
    foreach ($a['days'] as $d => $n) {
        if ($n > $busiestN) { $busiest = $d; $busiestN = $n; }
    }

    return [
        'period'      => $period,
        'total'       => $a['total'],
        'devices'     => $a['device_count'] ?? 0,
        'ips'         => $a['ip_count'] ?? 0,
        'peak_hour'   => $a['total'] ? $peak : null,
        'day_avg'     => $dayAvg,
        'busiest_day' => $busiest,
        'busiest_n'   => $busiestN,
        'first'       => $a['first'],
        'last'        => $a['last'],
        'hours'       => $a['hours'],
        'weekdays'    => $a['weekdays'],
        'days'        => analyticsDaySeries($a['days'], $period),
        'months'      => $a['months'],
        'top_devices' => $a['top_devices'] ?? [],
        'countries'   => $countries,
    ];
}

/** Координаты стран (широта, долгота) для карты: по английскому названию, коду и русскому названию. */
function analyticsCountryPoint($name) {
    static $map = null;
    if ($map === null) {
        $rows = [
            // код, English, Русский, широта, долгота
            ['RU','Russia','Россия',58,60],['US','United States','США',39,-98],['GB','United Kingdom','Великобритания',54,-2],['DE','Germany','Германия',51,10],
            ['FR','France','Франция',46.5,2.5],['PL','Poland','Польша',52,19],['UA','Ukraine','Украина',49,31],['NL','Netherlands','Нидерланды',52.2,5.5],
            ['BE','Belgium','Бельгия',50.6,4.6],['IT','Italy','Италия',42.8,12.5],['ES','Spain','Испания',40.3,-3.7],['SE','Sweden','Швеция',62,15],
            ['FI','Finland','Финляндия',64,26],['NO','Norway','Норвегия',61,9],['DK','Denmark','Дания',56,10],['CH','Switzerland','Швейцария',46.8,8.2],
            ['AT','Austria','Австрия',47.6,14.1],['CZ','Czechia','Чехия',49.8,15.5],['HU','Hungary','Венгрия',47.2,19.5],['RO','Romania','Румыния',45.9,25],
            ['BG','Bulgaria','Болгария',42.7,25.5],['GR','Greece','Греция',39,22],['PT','Portugal','Португалия',39.6,-8],['IE','Ireland','Ирландия',53.2,-8],
            ['CA','Canada','Канада',56,-100],['MX','Mexico','Мексика',23.6,-102.5],['BR','Brazil','Бразилия',-10,-52],['AR','Argentina','Аргентина',-34,-64],
            ['CL','Chile','Чили',-33,-71],['PE','Peru','Перу',-9.2,-75],['CO','Colombia','Колумбия',4.6,-74],['VE','Venezuela','Венесуэла',7,-66],
            ['CN','China','Китай',35,103],['JP','Japan','Япония',36,138],['KR','South Korea','Южная Корея',36.5,128],['IN','India','Индия',21,78],
            ['TR','Turkey','Турция',39,35],['IR','Iran','Иран',32.5,53],['IL','Israel','Израиль',31.5,34.8],['SA','Saudi Arabia','Саудовская Аравия',24,45],
            ['AE','United Arab Emirates','ОАЭ',24,54],['SG','Singapore','Сингапур',1.35,103.8],['MY','Malaysia','Малайзия',4,102],['ID','Indonesia','Индонезия',-2,118],
            ['PH','Philippines','Филиппины',12.8,122],['VN','Vietnam','Вьетнам',16,106],['TH','Thailand','Таиланд',15,101],['HK','Hong Kong','Гонконг',22.3,114.2],
            ['TW','Taiwan','Тайвань',23.7,121],['AU','Australia','Австралия',-25,134],['NZ','New Zealand','Новая Зеландия',-41,173],['ZA','South Africa','ЮАР',-29,24],
            ['EG','Egypt','Египет',26.5,30],['NG','Nigeria','Нигерия',9.5,8],['KE','Kenya','Кения',0.5,38],['MA','Morocco','Марокко',32,-6],
            ['DZ','Algeria','Алжир',28,2.6],['KZ','Kazakhstan','Казахстан',48,67],['BY','Belarus','Беларусь',53.5,28],['UZ','Uzbekistan','Узбекистан',41.5,64],
            ['GE','Georgia','Грузия',42,43.5],['AM','Armenia','Армения',40.2,45],['AZ','Azerbaijan','Азербайджан',40.3,47.7],['MD','Moldova','Молдова',47.2,28.5],
            ['LT','Lithuania','Литва',55.3,23.9],['LV','Latvia','Латвия',56.9,24.8],['EE','Estonia','Эстония',58.7,25.5],['RS','Serbia','Сербия',44,21],
            ['HR','Croatia','Хорватия',45.1,15.5],['SK','Slovakia','Словакия',48.7,19.5],['SI','Slovenia','Словения',46.1,14.8],['LU','Luxembourg','Люксембург',49.8,6.1],
            ['IS','Iceland','Исландия',64.9,-18.5],['CY','Cyprus','Кипр',35,33],['PK','Pakistan','Пакистан',30,70],['BD','Bangladesh','Бангладеш',23.8,90.3],
            ['LK','Sri Lanka','Шри-Ланка',7.8,80.7],['NP','Nepal','Непал',28.3,84],['MN','Mongolia','Монголия',46.8,103],['KG','Kyrgyzstan','Киргизия',41.5,74.6],
            ['TJ','Tajikistan','Таджикистан',38.8,71],['TM','Turkmenistan','Туркмения',39,59.5],['AF','Afghanistan','Афганистан',33.8,66],['IQ','Iraq','Ирак',33,44],
            ['SY','Syria','Сирия',35,38],['JO','Jordan','Иордания',31,36.5],['LB','Lebanon','Ливан',33.9,35.9],['KW','Kuwait','Кувейт',29.3,47.6],
            ['QA','Qatar','Катар',25.3,51.2],['OM','Oman','Оман',21,57],['YE','Yemen','Йемен',15.5,48],['TN','Tunisia','Тунис',34,9.5],
            ['LY','Libya','Ливия',27,17],['ET','Ethiopia','Эфиопия',9,39.5],['GH','Ghana','Гана',7.9,-1.2],['SN','Senegal','Сенегал',14.5,-14.5],
            ['TZ','Tanzania','Танзания',-6.4,34.9],['UG','Uganda','Уганда',1.4,32.3],['AO','Angola','Ангола',-12.3,17.5],['CU','Cuba','Куба',21.5,-79.5],
            ['EC','Ecuador','Эквадор',-1.4,-78.4],['BO','Bolivia','Боливия',-16.7,-64.7],['UY','Uruguay','Уругвай',-32.8,-56],['PY','Paraguay','Парагвай',-23.2,-58.4],
            ['PA','Panama','Панама',8.5,-80],['CR','Costa Rica','Коста-Рика',9.9,-84],['GT','Guatemala','Гватемала',15.5,-90.3],['DO','Dominican Republic','Доминиканская Республика',18.9,-70.2],
            ['AL','Albania','Албания',41,20],['BA','Bosnia and Herzegovina','Босния и Герцеговина',44,17.8],['MK','North Macedonia','Северная Македония',41.6,21.7],['ME','Montenegro','Черногория',42.7,19.3],
            ['MT','Malta','Мальта',35.9,14.4],['KH','Cambodia','Камбоджа',12.6,104.9],['LA','Laos','Лаос',18.2,103.9],['MM','Myanmar','Мьянма',21.9,96],
        ];
        $map = [];
        foreach ($rows as $r) {
            $pt = [$r[3], $r[4]];
            $map[strtolower($r[0])] = $pt;
            $map[strtolower($r[1])] = $pt;
            $map[function_exists('mb_strtolower') ? mb_strtolower($r[2], 'UTF-8') : strtolower($r[2])] = $pt;
        }
        // частые варианты названий из сервисов геолокации
        $alias = ['russian federation' => 'russia', 'usa' => 'united states', 'united states of america' => 'united states', 'uk' => 'united kingdom',
                  'czech republic' => 'czechia', 'republic of korea' => 'south korea', 'korea' => 'south korea', 'türkiye' => 'turkey',
                  'the netherlands' => 'netherlands', 'viet nam' => 'vietnam', 'uae' => 'united arab emirates', 'белоруссия' => 'belarus'];
        foreach ($alias as $k => $v) {
            if (isset($map[$v])) $map[$k] = $map[$v];
        }
    }
    $key = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$name), 'UTF-8') : strtolower(trim((string)$name));
    $key = preg_replace('/^[^\p{L}]+/u', '', $key);   // флаг/значок в начале строки
    return $map[$key] ?? null;
}
