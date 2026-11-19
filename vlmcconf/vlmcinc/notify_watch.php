<?php
// ============================================
// ФАЙЛ: vlmcinc/notify_watch.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Фоновая проверка событий уведомлений (пункт 27): новые и давно не появлявшиеся устройства, устройства с множеством IP, множество устройств с одного IP, обращение из новой страны, служба KMS, лог (давно не обновлялся, слишком большой), место на диске. Запускается планировщиком (scheduler.php, задача notify_watch).
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'notify_watch.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/notify.php';

const NOTIFY_WATCH_INTERVAL = 300;      // секунд между проверками
const NOTIFY_WATCH_NEW_DAYS = 2;        // «новое устройство» — первое обращение не раньше, чем N дней назад
const NOTIFY_WATCH_LINES = 15;          // строк в тексте уведомления

/** События, которые проверяет фоновая задача. */
function notifyWatchEvents() {
    return ['new_device', 'device_inactive', 'multi_ip_device', 'multi_device_ip', 'new_country',
            'service_stopped', 'service_restored', 'log_stale', 'log_big', 'disk_low'];
}

/**
 * Кому нужны события: правила (активные, с рабочим каналом) и вебхуки (включённые).
 * @return array список ['key' => 'r:<id>'|'wh', 'rule' => массив|null, 'events' => [..]]
 */
function notifyWatchConsumers() {
    $watch = notifyWatchEvents();
    $out = [];
    $s = notifyLoad();
    foreach ($s['rules'] as $rule) {
        if (empty($rule['active'])) { continue; }
        $ev = array_values(array_intersect($watch, (array)$rule['events']));
        if (!$ev) { continue; }
        $ok = false;
        foreach ((array)$rule['channels'] as $cid) {
            $c = notifyFindChannel($s, $cid);
            if ($c && !empty($c['enabled'])) { $ok = true; break; }
        }
        if ($ok) { $out[] = ['key' => 'r:' . $rule['id'], 'rule' => $rule, 'events' => $ev]; }
    }
    require_once __DIR__ . '/webhook.php';
    $wev = [];
    foreach (whLoad() as $h) {
        if (!empty($h['enabled'])) { $wev = array_merge($wev, (array)($h['events'] ?? [])); }
    }
    $wev = array_values(array_intersect($watch, array_unique($wev)));
    if ($wev) { $out[] = ['key' => 'wh', 'rule' => null, 'events' => $wev]; }
    return $out;
}

/** Задача планировщика нужна, если хоть кто-то подписан на проверяемые события. */
function notifyWatchWanted() {
    try { return count(notifyWatchConsumers()) > 0; } catch (\Throwable $e) { return false; }
}

/** Значение параметра: из правила, иначе по умолчанию из каталога. */
function notifyWatchParam($rule, $event, $name) {
    $def = notifyCatalog()[$event]['params'][$name] ?? [0];
    $v = $rule['params'][$name] ?? $def[0];
    return max((int)$def[1], min((int)$def[2], (int)$v));
}

function notifyWatchFmtLines(array $lines) {
    $n = count($lines);
    $lines = array_slice($lines, 0, NOTIFY_WATCH_LINES);
    return implode("\n", $lines) . ($n > NOTIFY_WATCH_LINES ? "\n…и ещё " . ($n - NOTIFY_WATCH_LINES) : '');
}

/**
 * Текущий набор «сработавших» элементов события.
 * @return array ключ => ['text' => строка, 'val' => число|null, 'device' => , 'group' => ]
 */
function notifyWatchDetect($event, $rule, array $d) {
    $items = [];
    switch ($event) {
        case 'new_device':
            $limit = time() - NOTIFY_WATCH_NEW_DAYS * 86400;
            foreach ($d['summary']() as $dev => $x) {
                $ts = $x['first'] !== '' ? strtotime($x['first']) : false;
                if ($ts !== false && $ts >= $limit) {
                    $items[$dev] = ['text' => $dev . ' — ' . $x['first'] . ', IP ' . $x['last_ip'], 'device' => $dev, 'group' => $d['known'][$dev]['group'] ?? ''];
                }
            }
            break;
        case 'device_inactive':
            $days = notifyWatchParam($rule, $event, 'days');
            $limit = time() - $days * 86400;
            foreach ($d['summary']() as $dev => $x) {
                $ts = $x['last'] !== '' ? strtotime($x['last']) : false;
                if ($ts !== false && $ts < $limit) {
                    $items[$dev] = ['text' => $dev . ' — последний раз ' . $x['last'] . ' (' . (int)floor((time() - $ts) / 86400) . ' дн. назад)',
                                    'device' => $dev, 'group' => $d['known'][$dev]['group'] ?? ''];
                }
            }
            break;
        case 'multi_ip_device':
            $min = notifyWatchParam($rule, $event, 'ip_count');
            foreach ($d['summary']() as $dev => $x) {
                if ($x['ips'] >= $min) {
                    $items[$dev] = ['text' => $dev . ' — IP: ' . $x['ips'] . ' (' . implode(', ', array_slice($x['ip_list'], 0, 5)) . ($x['ips'] > 5 ? ', …' : '') . ')',
                                    'val' => $x['ips'], 'device' => $dev, 'group' => $d['known'][$dev]['group'] ?? ''];
                }
            }
            break;
        case 'multi_device_ip':
            $min = notifyWatchParam($rule, $event, 'device_count');
            $byIp = [];
            foreach (reportPairs($d['log']) as $dev => $ips) {
                foreach ($ips as $ip => $p) { $byIp[(string)$ip][] = (string)$dev; }
            }
            foreach ($byIp as $ip => $devs) {
                if (count($devs) >= $min) {
                    $items[$ip] = ['text' => $ip . ' — устройств: ' . count($devs), 'val' => count($devs)];
                }
            }
            break;
        case 'new_country':
            foreach (reportCountries($d['log']) as $c) {
                if ($c['country'] !== '' && $c['country'] !== __('geo_local')) {
                    $items[$c['country']] = ['text' => $c['country'] . ' — IP: ' . $c['ips'] . ', запросов: ' . $c['count']];
                }
            }
            break;
        case 'service_stopped':
            if (!$d['svc_up']()) { $items['svc'] = ['text' => 'Порт ' . STATUS_VLMCSD_PORT . ' не принимает подключения']; }
            break;
        case 'log_stale':
            $min = notifyWatchParam($rule, $event, 'minutes');
            if (is_file($d['log'])) {
                $age = time() - (int)@filemtime($d['log']);
                if ($age > $min * 60) { $items['log'] = ['text' => 'В логе нет новых записей ' . (int)floor($age / 60) . ' мин. (порог ' . $min . ')']; }
            } else {
                $items['log'] = ['text' => 'Файл лога не найден: ' . $d['log']];
            }
            break;
        case 'log_big':
            $mb = notifyWatchParam($rule, $event, 'megabytes');
            $size = is_file($d['log']) ? (int)@filesize($d['log']) : 0;
            if ($size > $mb * 1048576) { $items['log'] = ['text' => 'Размер лога ' . round($size / 1048576) . ' МБ (порог ' . $mb . ' МБ)']; }
            break;
        case 'disk_low':
            $mb = notifyWatchParam($rule, $event, 'free_mb');
            $free = @disk_free_space(vlmcDataDir());
            if ($free !== false && $free < $mb * 1048576) { $items['disk'] = ['text' => 'Свободно ' . round($free / 1048576) . ' МБ на диске с данными (порог ' . $mb . ' МБ)']; }
            break;
    }
    return $items;
}

/** Заголовок уведомления по событию и числу элементов. */
function notifyWatchTitle($event, $n, array $items) {
    $one = $n === 1 ? (string)array_key_first($items) : '';
    switch ($event) {
        case 'new_device':      return $n === 1 ? 'Новое устройство в логе: ' . $one : 'Новые устройства в логе: ' . $n;
        case 'device_inactive': return $n === 1 ? 'Устройство давно не появлялось: ' . $one : 'Устройства давно не появлялись: ' . $n;
        case 'multi_ip_device': return $n === 1 ? 'Устройство с множеством IP: ' . $one : 'Устройства с множеством IP: ' . $n;
        case 'multi_device_ip': return $n === 1 ? 'Множество устройств с одного IP: ' . $one : 'IP с множеством устройств: ' . $n;
        case 'new_country':     return $n === 1 ? 'Обращение из новой страны: ' . $one : 'Обращения из новых стран: ' . $n;
        case 'service_stopped': return 'Служба KMS остановлена';
        case 'log_stale':       return 'Лог KMS-сервера давно не обновлялся';
        case 'log_big':         return 'Лог KMS-сервера слишком большой';
        case 'disk_low':        return 'Мало места на диске с данными панели';
    }
    return $event;
}

/**
 * Одна проверка. Состояние (что уже отправлено) хранится в notify_state.json, чтобы не повторять уведомления:
 * пока условие держится, повторов нет; после исчезновения условия оно может сработать снова.
 * События со списками (устройства, IP, страны) при первом запуске только запоминаются, чтобы не завалить историей.
 * @return array [bool ok, string text]
 */
function notifyWatchRun() {
    $consumers = notifyWatchConsumers();
    if (!$consumers) { return [true, 'нет подписок']; }

    require_once __DIR__ . '/api_core.php';
    require_once __DIR__ . '/log_cache.php';
    require_once __DIR__ . '/reports.php';
    require_once dirname(__DIR__) . '/vlmcgeoip.php';   // страна по IP (только из кэша)
    require_once __DIR__ . '/status.php';
    $r = config_read(vlmcConfigFile());
    $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    $log = apiLogFile($config);
    $summary = null;
    $svc = null;
    $d = [
        'log' => $log,
        'known' => apiKnownDevices($config),
        'summary' => function () use (&$summary, $log) { if ($summary === null) { $summary = reportDeviceSummary($log); } return $summary; },
        'svc_up' => function () use (&$svc) {
            if ($svc === null) {
                $sock = @stream_socket_client('tcp://127.0.0.1:' . STATUS_VLMCSD_PORT, $en, $es, 1.0);
                $svc = (bool)$sock;
                if ($sock) { fclose($sock); }
            }
            return $svc;
        },
    ];
    $listEvents = ['new_device', 'device_inactive', 'multi_ip_device', 'multi_device_ip', 'new_country'];   // «тихий» первый запуск
    $cumulative = ['new_country'];                                                                        // набор только растёт
    $sent = 0;
    $notes = [];

    // восстановление службы — по смене состояния «остановлена → работает» (общее для всех подписчиков)
    $svcWanted = false;
    foreach ($consumers as $c) { if (array_intersect(['service_stopped', 'service_restored'], $c['events'])) { $svcWanted = true; } }
    if ($svcWanted) {
        $up = $d['svc_up']();
        $was = notifyStateUpdate(function (&$st) use ($up) { $prev = $st['watch']['svc_up'] ?? null; $st['watch']['svc_up'] = $up; return $prev; });
        if ($was === false && $up) {
            notifyEmit('service_restored', 'Служба KMS снова работает', 'Порт ' . STATUS_VLMCSD_PORT . ' принимает подключения');
            $sent++;
        }
    }

    foreach ($consumers as $cons) {
        foreach ($cons['events'] as $event) {
            if ($event === 'service_restored') { continue; }
            $items = notifyWatchDetect($event, $cons['rule'], $d);
            $skey = $cons['key'] . '|' . $event;
            // что уже отправляли и первый ли это запуск
            $prev = notifyStateUpdate(function (&$st) use ($skey, $items, $listEvents, $cumulative, $event) {
                $old = $st['watch']['c'][$skey] ?? null;
                $set = [];
                foreach ($items as $k => $it) { $set[(string)$k] = $it['val'] ?? 1; }
                if ($old !== null && in_array($event, $cumulative, true)) {
                    $set = $set + (array)$old['set'];
                }
                $st['watch']['c'][$skey] = ['set' => $set, 'time' => time()];
                if ($old === null && in_array($event, $listEvents, true)) {
                    return false;           // базовое состояние: ничего не отправляем
                }
                return (array)($old['set'] ?? []);
            });
            if ($prev === false) { $notes[] = $event . ': базовое состояние'; continue; }
            $fresh = [];
            foreach ($items as $k => $it) {
                $k = (string)$k;
                if (!array_key_exists($k, $prev) || (isset($it['val']) && $it['val'] > (int)$prev[$k])) { $fresh[$k] = $it; }
            }
            if ($cons['rule'] !== null) {
                // фильтр правила по группе/устройству
                $fresh = array_filter($fresh, function ($it) use ($cons, $event) {
                    return notifyRuleMatches($cons['rule'], $event, ['device' => $it['device'] ?? '', 'group' => $it['group'] ?? '']);
                });
            }
            if (!$fresh) { continue; }
            $title = notifyWatchTitle($event, count($fresh), $fresh);
            $text = notifyWatchFmtLines(array_column($fresh, 'text'));
            if ($cons['rule'] !== null) {
                notifyEmit($event, $title, $text, [], [$cons['rule']['id']]);
            } else {
                require_once __DIR__ . '/webhook.php';
                whEmit($event, $title, $text, []);
            }
            $sent++;
            $notes[] = $event . ': ' . count($fresh);
        }
    }
    return [true, $sent ? 'отправлено: ' . $sent . ' (' . implode(', ', $notes) . ')' : 'без изменений'];
}
