<?php
// ============================================
// ФАЙЛ: vlmcinc/scheduler.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Единый планировщик фоновых задач: список задач, запуск просроченных (из браузера и из консоли cron.php),
//               состояние и история запусков для вкладки «Фоновые» в «Журнале событий»
// @description: Single background task scheduler: task list, running due tasks (from the browser and from cron.php), state and run history
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';

if (basename($_SERVER['PHP_SELF'] ?? '') === 'scheduler.php') {
    http_response_code(403);
    exit('Access denied');
}

const SCHED_HISTORY_KEEP = 200;
const SCHED_CRON_FRESH = 7200;   // консольный запуск считается настроенным, если был не позже 2 часов назад

function schedStateFile() { return vlmcDataFile('scheduler.json'); }
function schedLogFile()   { return vlmcDataFile('scheduler_log.json'); }
function schedLockFile()  { return vlmcDataFile('scheduler.lock'); }

function schedState() {
    $r = config_read(schedStateFile());
    $d = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    $d['tasks'] = isset($d['tasks']) && is_array($d['tasks']) ? $d['tasks'] : [];
    return $d;
}

function schedSaveState(array $d) {
    $f = schedStateFile();
    config_lock($f);
    save_config($f, $d);
}

function schedHistory($limit = 50) {
    $r = config_read(schedLogFile());
    $rows = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    return array_slice($rows, 0, $limit);
}

function schedHistoryAdd($task, $ok, $text, $via, $ms) {
    $f = schedLogFile();
    config_lock($f);
    $r = config_read($f);
    $rows = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    array_unshift($rows, ['time' => time(), 'task' => $task, 'ok' => (bool)$ok, 'text' => mb_substr((string)$text, 0, 400), 'via' => $via, 'ms' => (int)$ms]);
    save_config($f, array_slice($rows, 0, SCHED_HISTORY_KEEP));
}

/**
 * Список задач. У каждой: label, interval (сек), enabled, due (необязательно: своё условие «пора»), run (возвращает [ok, текст]).
 * Новую фоновую задачу достаточно добавить сюда.
 */
function schedTasks() {
    $tasks = [];

    // Проверка обновлений проекта на GitHub (интервал и включение — в «Инструменты → Обновление»)
    require_once __DIR__ . '/updater.php';
    $upd = new Updater();
    $us = $upd->getSettings();
    $tasks['update_check'] = [
        'label'    => __('sched_t_update'),
        'interval' => (int)$us['interval'] * 3600,
        'enabled'  => !empty($us['auto_check']),
        'due'      => function () use ($upd, $us) { return $upd->isStale($upd->loadState(), $us['interval']); },
        'run'      => function ($force = false) use ($upd) {
            $res = $upd->checkAll($force);
            $state = $res['state'];
            $parts = [];
            $ok = true;
            foreach (Updater::CHANNELS as $ch) {
                $r = $state[$ch] ?? [];
                if (empty($r['success'])) {
                    $ok = false;
                    $parts[] = strtoupper($ch) . ': ' . __('sched_err');
                } else {
                    $parts[] = strtoupper($ch) . ': ' . (!empty($r['available']) ? __('sched_update_yes') : __('sched_update_no'));
                }
            }
            return [$ok, implode(', ', $parts)];
        },
    ];

    // Статус подключения к LDAP (раз в 12 часов)
    require_once __DIR__ . '/ldap.php';
    $ldOn = !empty(ldapSettings()['enabled']);
    $tasks['ldap_status'] = [
        'label'    => __('sched_t_ldap'),
        'interval' => LDAP_STATUS_HOURS * 3600,
        'enabled'  => $ldOn,
        'due'      => function () { return ldapStatusDue(); },
        'run'      => function () {
            $r = ldapStatusCheck();
            return [!empty($r['success']), !empty($r['success']) ? __('ldap_status_active') : __('ldap_status_down') . ': ' . __('ldap_err_' . ($r['code'] ?? 'connect'))];
        },
    ];

    // Удаление записей журнала старше срока хранения (раз в сутки)
    require_once __DIR__ . '/audit.php';
    $tasks['audit_prune'] = [
        'label'    => __('sched_t_audit'),
        'interval' => 86400,
        'enabled'  => true,
        'run'      => function () {
            $n = auditPrune();
            @touch(vlmcDataFile('audit.prune'));
            return [true, sprintf(__('sched_audit_removed'), $n)];
        },
    ];

    return $tasks;
}

/** Пора ли запускать задачу. */
function schedIsDue(array $t, $id, array $state) {
    if (empty($t['enabled'])) {
        return false;
    }
    if (isset($t['due']) && is_callable($t['due'])) {
        return (bool)call_user_func($t['due']);
    }
    $last = (int)($state['tasks'][$id]['time'] ?? 0);
    return (time() - $last) >= (int)$t['interval'];
}

/**
 * Запускает просроченные задачи (или одну заданную, принудительно).
 * @param string      $via   'web' | 'cli' | 'manual'
 * @param string|null $only  идентификатор задачи для принудительного запуска
 * @return array ['busy'=>bool, 'ran'=>[id=>['ok','text','ms']]]
 */
function schedRun($via = 'web', $only = null) {
    $lockFh = @fopen(schedLockFile(), 'c');
    if (!$lockFh || !flock($lockFh, LOCK_EX | LOCK_NB)) {
        if ($lockFh) { fclose($lockFh); }
        return ['busy' => true, 'ran' => []];
    }
    @set_time_limit(180);
    $ran = [];
    $state = schedState();
    if ($via === 'cli') {
        $state['cron_time'] = time();
    }
    foreach (schedTasks() as $id => $t) {
        if ($only !== null) {
            if ($id !== $only || empty($t['enabled'])) { continue; }
        } elseif (!schedIsDue($t, $id, $state)) {
            continue;
        }
        $t0 = microtime(true);
        try {
            [$ok, $text] = call_user_func($t['run'], $only !== null);
        } catch (\Throwable $e) {
            $ok = false;
            $text = mb_substr($e->getMessage(), 0, 200);
        }
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $state['tasks'][$id] = ['time' => time(), 'ok' => (bool)$ok, 'text' => (string)$text, 'ms' => $ms, 'via' => $via];
        schedHistoryAdd($id, $ok, $text, $via, $ms);
        $ran[$id] = ['ok' => (bool)$ok, 'text' => (string)$text, 'ms' => $ms];
    }
    schedSaveState($state);
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    return ['busy' => false, 'ran' => $ran];
}

/** Данные для вкладки «Фоновые»: задачи, история, состояние cron. */
function schedView() {
    $state = schedState();
    $rows = [];
    foreach (schedTasks() as $id => $t) {
        $st = $state['tasks'][$id] ?? null;
        $next = null;
        if (!empty($t['enabled'])) {
            $next = $st ? (int)$st['time'] + (int)$t['interval'] : time();
            if ($next < time()) { $next = time(); }
        }
        $rows[] = [
            'id' => $id, 'label' => $t['label'], 'interval' => (int)$t['interval'], 'enabled' => !empty($t['enabled']),
            'time' => $st ? date('Y-m-d H:i', (int)$st['time']) : '', 'ok' => $st ? !empty($st['ok']) : null,
            'text' => $st['text'] ?? '', 'via' => $st['via'] ?? '', 'next' => $next ? date('Y-m-d H:i', $next) : '',
        ];
    }
    $hist = [];
    $labels = [];
    foreach (schedTasks() as $id => $t) { $labels[$id] = $t['label']; }
    foreach (schedHistory(50) as $h) {
        $hist[] = ['time' => date('Y-m-d H:i:s', (int)$h['time']), 'task' => $labels[$h['task']] ?? $h['task'], 'ok' => !empty($h['ok']),
                   'text' => $h['text'] ?? '', 'via' => $h['via'] ?? '', 'ms' => (int)($h['ms'] ?? 0)];
    }
    $cron = (int)($state['cron_time'] ?? 0);
    return [
        'tasks' => $rows, 'history' => $hist,
        'cron' => ['time' => $cron ? date('Y-m-d H:i', $cron) : '', 'active' => $cron && (time() - $cron) < SCHED_CRON_FRESH],
        'cron_cmd' => schedCronCommand(),
    ];
}

/** Команда для планировщика ОС (Ubuntu — cron, Windows — Планировщик заданий). */
function schedCronCommand() {
    $script = realpath(__DIR__ . '/../cron.php') ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cron.php');
    if (PHP_OS_FAMILY === 'Windows') {
        return ['os' => 'windows', 'cmd' => 'schtasks /Create /SC MINUTE /MO 15 /TN "KMS Monitor cron" /TR "php \"' . $script . '\"" /F'];
    }
    return ['os' => 'linux', 'cmd' => '*/15 * * * * www-data php ' . $script . ' >/dev/null 2>&1'];
}
