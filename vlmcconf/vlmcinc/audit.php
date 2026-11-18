<?php
// ============================================
// ФАЙЛ: vlmcinc/audit.php
// ВЕРСИЯ: 2.5.2
// ДАТА: 2026-11-18
// @description: Журнал событий пользователей панели: запись (с изменениями «было → стало»), срок хранения, очистка, фильтры, поиск, сводка по пользователю, активность по часам и дням
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'audit.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';

const AUDIT_DEFAULT_DAYS = 90;     // срок хранения по умолчанию
const AUDIT_MAX_DAYS     = 365;    // верхний предел срока хранения
const AUDIT_SCAN_LIMIT   = 200000; // предел просматриваемых записей за один запрос
const AUDIT_MAX_CHANGES  = 12;     // строк «что изменилось» на одну запись

/** Действия, которые пишутся в журнал (ajax-действия); остальные — только чтение. */
function auditAjaxActions() {
    return [
        'backup_log', 'clear_log', 'clear_geo_cache', 'refresh_geo_cache', 'clear_emoji_cache', 'refresh_emoji_cache',
        'export_project', 'perform_update', 'save_update_settings', 'save_github_token',
        'add_user', 'update_user', 'delete_user', 'change_user_password',
        'rename_group', 'delete_group', 'save_group_changes', 'add_device', 'mass_move_devices',
        'add_whitelist_ip', 'remove_whitelist_ip',
        'save_audit_settings', 'save_privacy_settings', 'save_session_settings', 'clear_audit',
        'panel_wl_save', 'fw_save_settings', 'fw_block', 'fw_unblock', 'fw_apply',
        'panel_wl_save', 'fw_save_settings', 'fw_block', 'fw_unblock', 'fw_apply',
        'api_save_settings', 'api_key_create', 'api_key_toggle', 'api_key_delete',
        'ldap_save_settings', 'ldap_test', 'ldap_import', 'ldap_sync',
        'notify_channel_save', 'notify_channel_delete', 'notify_channel_test', 'notify_rule_save', 'notify_rule_delete', 'notify_rule_toggle'
    ];
}

/** Все коды действий журнала (для проверки входных данных). */
function auditAllActions() {
    return array_merge(
        ['login', 'login_failed', 'login_locked', 'logout', 'password_changed'],
        ['save_theme', 'save_language', 'save_log_path', 'add_group', 'save_group_colors',
         'delete_device', 'edit_device', 'reset_config'],
        auditAjaxActions(),
        ['audit_refresh', 'access_denied', 'webhook_save', 'webhook_delete', 'webhook_toggle', 'webhook_test', 'cloud_save', 'cloud_delete', 'cloud_toggle', 'cloud_test', 'ms_save', 'ms_delete', 'ms_test', 'sched_run_now']
    );
}

/** Обрезка строки по символам (без обязательного mbstring). */
function auditSub($s, $len) {
    return function_exists('mb_substr') ? mb_substr($s, 0, $len, 'UTF-8') : substr($s, 0, $len);
}

function auditLower($s) {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function auditFile() {
    return vlmcDataFile('audit.log');
}

// ============================================
// СРОК ХРАНЕНИЯ
// ============================================

/** Срок хранения в днях из настроек панели (1..365, по умолчанию 90). */
function auditRetentionDays() {
    $file = vlmcConfigFile();
    $days = AUDIT_DEFAULT_DAYS;
    if (is_file($file)) {
        $cfg = json_decode((string)@file_get_contents($file), true);
        if (is_array($cfg) && isset($cfg['audit']['retention_days'])) {
            $days = (int)$cfg['audit']['retention_days'];
        }
    }
    return ($days >= 1 && $days <= AUDIT_MAX_DAYS) ? $days : AUDIT_DEFAULT_DAYS;
}

/** Удаляет записи старше срока хранения. Выполняется под блокировкой файла журнала. */
function auditPrune($days = null) {
    $days = $days === null ? auditRetentionDays() : max(1, min(AUDIT_MAX_DAYS, (int)$days));
    $file = auditFile();
    if (!is_file($file)) {
        return 0;
    }
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return 0;
    }
    $removed = 0;
    if (flock($fh, LOCK_EX)) {
        $cutoff = time() - $days * 86400;
        $kept = '';
        rewind($fh);
        while (($line = fgets($fh)) !== false) {
            $e = json_decode($line, true);
            if (is_array($e) && isset($e['t']) && (int)$e['t'] >= $cutoff) {
                $kept .= rtrim($line, "\r\n") . "\n";
            } else {
                $removed++;
            }
        }
        if ($removed > 0) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $kept);
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    return $removed;
}

/** Раз в сутки (при записи в журнал) убирает устаревшие записи. */
function auditMaybePrune() {
    $marker = vlmcDataFile('audit.prune');
    if (is_file($marker) && (time() - (int)@filemtime($marker)) < 86400) {
        return;
    }
    @touch($marker);
    auditPrune();
}

/** Полная очистка журнала (только для администратора; факт очистки записывается отдельно). */
function auditClear() {
    $file = auditFile();
    $ok = true;
    if (is_file($file)) {
        $fh = @fopen($file, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $ok = ftruncate($fh, 0);
            flock($fh, LOCK_UN);
        } else {
            $ok = false;
        }
        if ($fh) { fclose($fh); }
    }
    for ($i = 1; $i <= 4; $i++) { // файлы старой ротации по размеру
        @unlink($file . '.' . $i);
    }
    return $ok;
}

/** Размер файла и число записей для интерфейса. */
function auditStats() {
    $file = auditFile();
    $size = is_file($file) ? (int)@filesize($file) : 0;
    $count = 0;
    if ($size > 0 && ($fh = @fopen($file, 'rb'))) {
        while (fgets($fh) !== false) { $count++; }
        fclose($fh);
    }
    return ['size' => $size, 'records' => $count, 'retention_days' => auditRetentionDays()];
}

// ============================================
// ИЗМЕНЕНИЯ «БЫЛО → СТАЛО»
// ============================================

/** Копия настроек и пользователей на данный момент (для сравнения до и после действия). */
function auditSnapshot() {
    $read = function ($file) {
        if (!is_file($file)) { return null; }
        $d = json_decode((string)@file_get_contents($file), true);
        return is_array($d) ? $d : null;
    };
    return ['config' => $read(vlmcConfigFile()), 'users' => $read(vlmcUsersFile())];
}

function auditIsList(array $a) {
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

function auditScalarText($v) {
    if ($v === null) { return '—'; }
    if (is_bool($v)) { return $v ? 'true' : 'false'; }
    if (is_array($v)) { return '[' . count($v) . ']'; }
    $s = preg_replace('/[\r\n\t]+/', ' ', (string)$v);
    return $s === '' ? '""' : auditSub($s, 60);
}

/** Плоский вид: путь => ['v' => значение] или ['set' => список значений]. */
function auditFlatten($data, $prefix, array &$out) {
    if (is_array($data)) {
        if (auditIsList($data) && count(array_filter($data, 'is_scalar')) === count($data)) {
            $out[$prefix] = ['set' => array_map('auditScalarText', $data)];
            return;
        }
        foreach ($data as $k => $v) {
            $path = $prefix === '' ? (string)$k : $prefix . '.' . $k;
            auditFlatten($v, $path, $out);
        }
        return;
    }
    $out[$prefix] = ['v' => $data];
}

function auditIgnoredPath($path) {
    return (bool)preg_match('/(^|\.)(last_modified|config_version|config_date|last_login)$/', $path);
}

function auditSecretPath($path) {
    $last = substr(strrchr('.' . $path, '.'), 1);
    return (bool)preg_match('/pass|secret|token|api_?key|hash/i', $last);
}

/** Пользователи по имени: хеши паролей заменяются отпечатком (значения не раскрываются). */
function auditUsersMap($users) {
    $map = [];
    if (!is_array($users)) { return $map; }
    foreach ($users as $u) {
        if (!is_array($u) || !isset($u['username'])) { continue; }
        $rec = $u;
        unset($rec['id'], $rec['last_login']);
        if (isset($rec['password_hash'])) {
            $rec['password_hash'] = sha1((string)$rec['password_hash']);
        }
        $map[(string)$u['username']] = $rec;
    }
    return $map;
}

/** Строки «что изменилось» между двумя снимками. */
function auditDiff(array $before, array $after) {
    $lines = [];

    // Настройки панели
    $a = []; $b = [];
    if (is_array($before['config'])) { auditFlatten($before['config'], '', $a); }
    if (is_array($after['config']))  { auditFlatten($after['config'], '', $b); }
    $paths = array_unique(array_merge(array_keys($a), array_keys($b)));
    sort($paths);
    foreach ($paths as $p) {
        if ($p === '' || auditIgnoredPath($p)) { continue; }
        $x = $a[$p] ?? null; $y = $b[$p] ?? null;
        if ($x === $y) { continue; }
        if (auditSecretPath($p)) { $lines[] = $p . ': ' . '(изменено)'; continue; }
        $xs = $x['set'] ?? null; $ys = $y['set'] ?? null;
        if ($xs !== null || $ys !== null) {
            $xs = $xs ?? []; $ys = $ys ?? [];
            $add = array_values(array_diff($ys, $xs));
            $del = array_values(array_diff($xs, $ys));
            if (!$add && !$del) { continue; }
            $parts = [];
            foreach (array_slice($add, 0, 5) as $v) { $parts[] = '+' . $v; }
            if (count($add) > 5) { $parts[] = '+…' . (count($add) - 5); }
            foreach (array_slice($del, 0, 5) as $v) { $parts[] = '−' . $v; }
            if (count($del) > 5) { $parts[] = '−…' . (count($del) - 5); }
            $lines[] = $p . ': ' . implode(', ', $parts);
            continue;
        }
        $lines[] = $p . ': ' . auditScalarText($x['v'] ?? null) . ' → ' . auditScalarText($y['v'] ?? null);
    }

    // Пользователи
    $ua = auditUsersMap($before['users']);
    $ub = auditUsersMap($after['users']);
    foreach ($ub as $name => $rec) {
        if (!isset($ua[$name])) {
            $lines[] = 'users: +' . $name . ' (permissions ' . auditScalarText($rec['permissions'] ?? null) . ')';
        }
    }
    foreach ($ua as $name => $rec) {
        if (!isset($ub[$name])) { $lines[] = 'users: −' . $name; }
    }
    foreach ($ub as $name => $rec) {
        if (!isset($ua[$name])) { continue; }
        foreach ($rec as $field => $val) {
            $old = $ua[$name][$field] ?? null;
            if ($old === $val) { continue; }
            if ($field === 'password_hash') {
                $lines[] = 'users.' . $name . ': пароль изменён';
            } else {
                $lines[] = 'users.' . $name . '.' . $field . ': ' . auditScalarText($old) . ' → ' . auditScalarText($val);
            }
        }
    }

    if (count($lines) > AUDIT_MAX_CHANGES) {
        $extra = count($lines) - AUDIT_MAX_CHANGES;
        $lines = array_slice($lines, 0, AUDIT_MAX_CHANGES);
        $lines[] = '… +' . $extra;
    }
    return array_map(function ($l) { return auditSub($l, 220); }, $lines);
}

// ============================================
// ЗАПИСЬ
// ============================================

/** Параметры запроса в виде «ключ=значение»: пароли, токены и служебные поля не пишутся. */
function auditCleanParams(array $src) {
    $parts = [];
    foreach ($src as $k => $v) {
        $k = (string)$k;
        if ($k === 'action' || $k === 'ajax' || preg_match('/pass|token|secret|csrf/i', $k)) {
            continue;
        }
        if (is_array($v)) {
            $val = '[' . count($v) . ']';
        } elseif (is_string($v) && isset($v[0]) && ($v[0] === '[' || $v[0] === '{') && is_array($arr = json_decode($v, true))) {
            $val = '[' . count($arr) . ']';   // список (например, файлы обновления) — только количество
        } elseif (is_scalar($v)) {
            $val = preg_replace('/[\r\n\t]+/', ' ', trim((string)$v));
            $val = auditSub($val, 120);
        } else {
            continue;
        }
        if ($val === '') {
            continue;
        }
        $parts[] = $k . '=' . $val;
        if (count($parts) >= 8) {
            break;
        }
    }
    return implode('; ', $parts);
}

/** Передача записи журнала в уведомления (правила и каналы — раздел «Уведомления»). */
function auditNotify(array $entry) {
    if (!function_exists('notifyOnAudit')) {
        require_once __DIR__ . '/notify.php';
    }
    notifyOnAudit($entry);
}

/**
 * Запись в журнал. Ошибки записи никогда не ломают действие пользователя.
 * $result: ok | error | denied; $changes — строки «было → стало».
 */
function auditLog($action, $details = '', $result = 'ok', $user = null, array $changes = []) {
    try {
        if ($user === null) {
            $user = (string)($_SESSION['vlmc_username'] ?? '');
        }
        $entry = [
            't' => time(),
            'u' => auditSub((string)$user, 64),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'a' => (string)$action,
            'd' => auditSub((string)$details, 800),
            'r' => in_array($result, ['ok', 'error', 'denied'], true) ? $result : 'ok'
        ];
        if ($changes) {
            $entry['c'] = array_values($changes);
        }
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }
        $file = auditFile();
        $isNew = !is_file($file);
        $fh = @fopen($file, 'ab');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $line . "\n");
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            if ($isNew) {
                @chmod($file, 0600);
            }
        }
        auditMaybePrune();
        auditNotify($entry);
    } catch (\Throwable $e) {
        // журнал не должен влиять на работу панели
    }
}

/** Действие обычной формы: запись делается по завершении запроса, с изменениями настроек. */
function auditTrack($action, $details = '') {
    $before = auditSnapshot();
    $user = (string)($_SESSION['vlmc_username'] ?? '');
    register_shutdown_function(function () use ($action, $details, $before, $user) {
        auditLog($action, $details, 'ok', $user, auditDiff($before, auditSnapshot()));
    });
}

/**
 * Для ajax-действия: после ответа записывает результат (по полю success в JSON) и изменения.
 * Скачивание проекта не буферизуется (большой файл): пишется сразу.
 */
function auditBeginAjax($action) {
    if (!in_array($action, auditAjaxActions(), true)) {
        return;
    }
    $details = auditCleanParams($_POST);
    $user = (string)($_SESSION['vlmc_username'] ?? '');
    if ($action === 'export_project') {
        auditLog($action, $details, 'ok', $user);
        return;
    }
    $before = auditSnapshot();
    // Запись делается при завершении запроса (до сброса буфера): так уведомления об этом действии
    // можно отправить после ответа пользователю (в функции буфера регистрировать завершающие действия уже поздно).
    ob_start();
    register_shutdown_function(function () use ($action, $details, $user, $before) {
        $buffer = ob_get_level() > 0 ? (string)ob_get_contents() : '';
        $result = 'ok';
        $data = json_decode($buffer, true);
        if (is_array($data) && array_key_exists('success', $data)) {
            $result = !empty($data['success']) ? 'ok' : 'error';
        } elseif (http_response_code() >= 400) {
            $result = 'error';
        }
        $changes = $result === 'ok' ? auditDiff($before, auditSnapshot()) : [];
        auditLog($action, $details, $result, $user, $changes);
        while (ob_get_level() > 0) {
            @ob_end_flush();   // ответ уходит пользователю, затем выполняется отправка уведомлений
        }
    });
}

// ============================================
// ЧТЕНИЕ
// ============================================

function auditLabel($action) {
    $key = 'audit_act_' . $action;
    if (function_exists('__')) {
        $t = __($key);
        if ($t !== $key) {
            return $t;
        }
    }
    return $action;
}

/**
 * Выборка из журнала.
 * Фильтры: user, action, result, from (ГГГГ-ММ-ДД), to (ГГГГ-ММ-ДД), q (поиск по всему).
 * Возвращает записи страницы (новые первыми), общее число, списки для фильтров,
 * активность по часам и дням (по всем найденным записям) и сводку по пользователю.
 */
function auditQuery(array $f, $page = 1, $perPage = 50) {
    $page = max(1, (int)$page);
    $perPage = max(10, min(500, (int)$perPage));
    $from = !empty($f['from']) ? strtotime($f['from'] . ' 00:00:00') : 0;
    $to = !empty($f['to']) ? strtotime($f['to'] . ' 23:59:59') : 0;
    $q = isset($f['q']) ? auditLower(trim((string)$f['q'])) : '';

    $users = [];
    $actions = [];
    $items = [];
    $hours = array_fill(0, 24, 0);
    $days = [];
    $total = 0;
    $scanned = 0;
    $offset = ($page - 1) * $perPage;
    $sum = ['ok' => 0, 'error' => 0, 'denied' => 0, 'first' => 0, 'last' => 0, 'ips' => [], 'top' => []];

    $file = auditFile();
    $lines = is_file($file) ? @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if (!is_array($lines)) {
        $lines = [];
    }
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (++$scanned > AUDIT_SCAN_LIMIT) {
            break;
        }
        $e = json_decode($lines[$i], true);
        if (!is_array($e) || !isset($e['t'], $e['a'])) {
            continue;
        }
        $u = (string)($e['u'] ?? '');
        $a = (string)$e['a'];
        $r = (string)($e['r'] ?? 'ok');
        if ($u !== '') { $users[$u] = true; }
        $actions[$a] = true;

        if (!empty($f['user']) && $u !== $f['user']) { continue; }
        if (!empty($f['action']) && $a !== $f['action']) { continue; }
        if (!empty($f['result']) && $r !== $f['result']) { continue; }
        if ($from && $e['t'] < $from) { continue; }
        if ($to && $e['t'] > $to) { continue; }
        $changes = isset($e['c']) && is_array($e['c']) ? $e['c'] : [];
        if ($q !== '') {
            $hay = auditLower($u . ' ' . ($e['ip'] ?? '') . ' ' . $a . ' ' . auditLabel($a) . ' ' . ($e['d'] ?? '') . ' ' . implode(' ', $changes));
            if (strpos($hay, $q) === false) { continue; }
        }

        $ts = (int)$e['t'];
        $hours[(int)date('G', $ts)]++;
        $day = date('Y-m-d', $ts);
        $days[$day] = ($days[$day] ?? 0) + 1;
        if (isset($sum[$r])) { $sum[$r]++; }
        $sum['first'] = $sum['first'] === 0 ? $ts : min($sum['first'], $ts);
        $sum['last'] = max($sum['last'], $ts);
        $ip = (string)($e['ip'] ?? '');
        if ($ip !== '') { $sum['ips'][$ip] = ($sum['ips'][$ip] ?? 0) + 1; }
        $sum['top'][$a] = ($sum['top'][$a] ?? 0) + 1;

        if ($total >= $offset && count($items) < $perPage) {
            $items[] = [
                'time' => date('Y-m-d H:i:s', $ts),
                'user' => $u,
                'ip' => $ip,
                'action' => $a,
                'label' => auditLabel($a),
                'details' => (string)($e['d'] ?? ''),
                'changes' => $changes,
                'result' => $r
            ];
        }
        $total++;
    }

    $userList = array_keys($users);
    sort($userList, SORT_NATURAL | SORT_FLAG_CASE);
    $actionList = [];
    foreach (array_keys($actions) as $a) {
        $actionList[] = ['code' => $a, 'label' => auditLabel($a)];
    }
    usort($actionList, function ($x, $y) { return strcmp($x['label'], $y['label']); });

    ksort($days);
    $days = array_slice($days, -60, null, true);

    arsort($sum['ips']);
    arsort($sum['top']);
    $topList = [];
    foreach (array_slice($sum['top'], 0, 5, true) as $code => $cnt) {
        $topList[] = ['label' => auditLabel($code), 'count' => $cnt];
    }
    $summary = [
        'total' => $total, 'ok' => $sum['ok'], 'error' => $sum['error'], 'denied' => $sum['denied'],
        'first' => $sum['first'] ? date('Y-m-d H:i', $sum['first']) : '',
        'last' => $sum['last'] ? date('Y-m-d H:i', $sum['last']) : '',
        'ips' => array_slice(array_keys($sum['ips']), 0, 5),
        'top' => $topList
    ];

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => max(1, (int)ceil($total / $perPage)),
        'per_page' => $perPage,
        'users' => $userList,
        'actions' => $actionList,
        'hours' => $hours,
        'days' => $days,
        'summary' => !empty($f['user']) ? $summary : null
    ];
}

/** CSV всех записей под фильтром (до 50 000 строк), UTF-8 с BOM для Excel. */
function auditExportCsv(array $f) {
    $r = auditQuery($f, 1, 500);
    $rows = $r['items'];
    for ($p = 2; $p <= $r['pages'] && count($rows) < 50000; $p++) {
        $rows = array_merge($rows, auditQuery($f, $p, 500)['items']);
    }
    $esc = function ($v) {
        $v = (string)$v;
        if ($v !== '' && strpos('=+-@', $v[0]) !== false) {
            $v = "'" . $v; // защита от формул в Excel
        }
        return '"' . str_replace('"', '""', $v) . '"';
    };
    $out = "\xEF\xBB\xBF" . implode(';', array_map($esc, ['time', 'user', 'ip', 'action', 'details', 'changes', 'result'])) . "\r\n";
    foreach ($rows as $row) {
        $out .= implode(';', array_map($esc, [$row['time'], $row['user'], $row['ip'], $row['label'], $row['details'], implode(' | ', $row['changes']), $row['result']])) . "\r\n";
    }
    return $out;
}
