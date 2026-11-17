<?php
// ============================================
// ФАЙЛ: vlmcinc/audit.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Журнал действий пользователей панели (кто, что, когда, результат): запись, ротация, фильтры и поиск
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'audit.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/paths.php';

const AUDIT_MAX_BYTES  = 2097152; // размер одного файла журнала до ротации (2 МБ)
const AUDIT_KEEP_FILES = 5;       // сколько файлов хранить: audit.log, audit.log.1 ... .4
const AUDIT_SCAN_LIMIT = 100000;  // предел просматриваемых записей за один запрос

/** Действия, которые пишутся в журнал (ajax-действия); остальные — только чтение. */
function auditAjaxActions() {
    return [
        'backup_log', 'clear_log', 'clear_geo_cache', 'refresh_geo_cache', 'clear_emoji_cache', 'refresh_emoji_cache',
        'export_project', 'perform_update', 'save_update_settings', 'save_github_token',
        'add_user', 'update_user', 'delete_user', 'change_user_password',
        'rename_group', 'delete_group', 'save_group_changes', 'add_device', 'mass_move_devices',
        'add_whitelist_ip', 'remove_whitelist_ip'
    ];
}

/** Все коды действий журнала (для фильтра): ajax, формы настроек, вход и выход. */
function auditAllActions() {
    return array_merge(
        ['login', 'login_failed', 'login_locked', 'logout', 'password_changed'],
        ['save_theme', 'save_language', 'save_log_path', 'add_group', 'save_group_colors', 'add_device_form',
         'delete_device', 'edit_device', 'reset_config'],
        auditAjaxActions(),
        ['access_denied']
    );
}

/** Обрезка строки по символам (без обязательного mbstring). */
function auditSub($s, $len) {
    return function_exists('mb_substr') ? mb_substr($s, 0, $len, 'UTF-8') : substr($s, 0, $len);
}

function auditLower($s) {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function auditFile($n = 0) {
    $base = vlmcDataFile('audit.log');
    return $n > 0 ? $base . '.' . $n : $base;
}

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
        } elseif (is_scalar($v)) {
            $val = trim((string)$v);
            $val = preg_replace('/[\r\n\t]+/', ' ', $val);
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

function auditRotateIfNeeded() {
    $file = auditFile(0);
    clearstatcache(true, $file);
    if (!is_file($file) || (int)@filesize($file) < AUDIT_MAX_BYTES) {
        return;
    }
    $lock = @fopen($file . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) { fclose($lock); }
        return;
    }
    clearstatcache(true, $file);
    if ((int)@filesize($file) >= AUDIT_MAX_BYTES) {
        @unlink(auditFile(AUDIT_KEEP_FILES - 1));
        for ($i = AUDIT_KEEP_FILES - 2; $i >= 1; $i--) {
            if (is_file(auditFile($i))) {
                @rename(auditFile($i), auditFile($i + 1));
            }
        }
        @rename($file, auditFile(1));
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}

/**
 * Запись в журнал. Ошибки записи никогда не ломают действие пользователя.
 * $result: ok | error | denied
 */
function auditLog($action, $details = '', $result = 'ok', $user = null) {
    try {
        if ($user === null) {
            $user = (string)($_SESSION['vlmc_username'] ?? '');
        }
        $entry = [
            't' => time(),
            'u' => auditSub((string)$user, 64),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'a' => (string)$action,
            'd' => auditSub((string)$details, 400),
            'r' => in_array($result, ['ok', 'error', 'denied'], true) ? $result : 'ok'
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }
        auditRotateIfNeeded();
        $file = auditFile(0);
        $isNew = !is_file($file);
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($isNew) {
            @chmod($file, 0600);
        }
    } catch (\Throwable $e) {
        // журнал не должен влиять на работу панели
    }
}

/**
 * Для ajax-действия: после ответа записывает результат (по полю success в JSON).
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
    ob_start(function ($buffer) use ($action, $details, $user) {
        $result = 'ok';
        $data = json_decode((string)$buffer, true);
        if (is_array($data) && array_key_exists('success', $data)) {
            $result = !empty($data['success']) ? 'ok' : 'error';
        } elseif (http_response_code() >= 400) {
            $result = 'error';
        }
        auditLog($action, $details, $result, $user);
        return $buffer;
    });
}

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

/** Файлы журнала от новых к старым. */
function auditFiles() {
    $files = [];
    for ($i = 0; $i < AUDIT_KEEP_FILES; $i++) {
        $f = auditFile($i);
        if (is_file($f)) {
            $files[] = $f;
        }
    }
    return $files;
}

/**
 * Выборка из журнала.
 * Фильтры: user, action, result, from (ГГГГ-ММ-ДД), to (ГГГГ-ММ-ДД), q (поиск по всему).
 * Возвращает записи страницы (новые первыми), общее число, списки пользователей и действий для фильтров.
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
    $total = 0;
    $scanned = 0;
    $offset = ($page - 1) * $perPage;

    foreach (auditFiles() as $file) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (++$scanned > AUDIT_SCAN_LIMIT) {
                break 2;
            }
            $e = json_decode($lines[$i], true);
            if (!is_array($e) || !isset($e['t'], $e['a'])) {
                continue;
            }
            $u = (string)($e['u'] ?? '');
            $a = (string)$e['a'];
            if ($u !== '') { $users[$u] = true; }
            $actions[$a] = true;

            if (!empty($f['user']) && $u !== $f['user']) { continue; }
            if (!empty($f['action']) && $a !== $f['action']) { continue; }
            if (!empty($f['result']) && ($e['r'] ?? 'ok') !== $f['result']) { continue; }
            if ($from && $e['t'] < $from) { continue; }
            if ($to && $e['t'] > $to) { continue; }
            if ($q !== '') {
                $hay = auditLower($u . ' ' . ($e['ip'] ?? '') . ' ' . $a . ' ' . auditLabel($a) . ' ' . ($e['d'] ?? ''));
                if (strpos($hay, $q) === false) { continue; }
            }
            if ($total >= $offset && count($items) < $perPage) {
                $items[] = [
                    'time' => date('Y-m-d H:i:s', (int)$e['t']),
                    'user' => $u,
                    'ip' => (string)($e['ip'] ?? ''),
                    'action' => $a,
                    'label' => auditLabel($a),
                    'details' => (string)($e['d'] ?? ''),
                    'result' => (string)($e['r'] ?? 'ok')
                ];
            }
            $total++;
        }
    }

    $userList = array_keys($users);
    sort($userList, SORT_NATURAL | SORT_FLAG_CASE);
    $actionList = [];
    foreach (array_keys($actions) as $a) {
        $actionList[] = ['code' => $a, 'label' => auditLabel($a)];
    }
    usort($actionList, function ($x, $y) { return strcmp($x['label'], $y['label']); });

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => max(1, (int)ceil($total / $perPage)),
        'per_page' => $perPage,
        'users' => $userList,
        'actions' => $actionList
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
    $out = "\xEF\xBB\xBF" . implode(';', array_map($esc, ['time', 'user', 'ip', 'action', 'details', 'result'])) . "\r\n";
    foreach ($rows as $row) {
        $out .= implode(';', array_map($esc, [$row['time'], $row['user'], $row['ip'], $row['label'], $row['details'], $row['result']])) . "\r\n";
    }
    return $out;
}
