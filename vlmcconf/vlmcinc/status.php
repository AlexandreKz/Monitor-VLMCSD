<?php
// ============================================
// ФАЙЛ: vlmcinc/status.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-18
// @description: Статус функций панели («Информация → Статус»): проверка ядра (vlmcsd, лог, данные, PHP), интеграций,
//               безопасности и обслуживания. Проверки только читают состояние и ничего не меняют
// @description: Panel function status (Information → Status): checks of the core, integrations, security and maintenance. Read-only
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';

if (basename($_SERVER['PHP_SELF'] ?? '') === 'status.php') {
    http_response_code(403);
    exit('Access denied');
}

const STATUS_VLMCSD_PORT = 1688;

/** Строка результата. state: ok | warn | err | off */
function stRow($group, $icon, $label, $state, $detail, $params = '') {
    return ['group' => $group, 'icon' => $icon, 'label' => __($label), 'state' => $state, 'detail' => (string)$detail, 'params' => (string)$params];
}

function stConfig() {
    $r = config_read(vlmcConfigFile());
    return ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
}

function stAgo($ts) {
    $d = max(0, time() - (int)$ts);
    if ($d < 90) { return sprintf(__('st_ago_s'), $d); }
    if ($d < 5400) { return sprintf(__('st_ago_m'), (int)round($d / 60)); }
    if ($d < 172800) { return sprintf(__('st_ago_h'), (int)round($d / 3600)); }
    return sprintf(__('st_ago_d'), (int)round($d / 86400));
}

function stBytes($b) {
    if ($b >= 1073741824) { return round($b / 1073741824, 1) . ' ' . __('st_gb'); }
    if ($b >= 1048576) { return round($b / 1048576, 1) . ' ' . __('st_mb'); }
    return max(1, round($b / 1024)) . ' ' . __('st_kb');
}

/** Все проверки. @return array список строк */
function statusCollect() {
    $cfg = stConfig();
    $rows = [];
    $G = ['core' => __('st_g_core'), 'int' => __('st_g_int'), 'sec' => __('st_g_sec'), 'maint' => __('st_g_maint')];

    // ---------- Ядро ----------
    // KMS-сервер: порт 1688 принимает TCP-подключения
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client('tcp://127.0.0.1:' . STATUS_VLMCSD_PORT, $errno, $errstr, 1.0);
    if ($sock) {
        fclose($sock);
        $rows[] = stRow($G['core'], '🔑', 'st_c_vlmcsd', 'ok', sprintf(__('st_vlmcsd_ok'), STATUS_VLMCSD_PORT), '127.0.0.1:' . STATUS_VLMCSD_PORT);
    } else {
        $rows[] = stRow($G['core'], '🔑', 'st_c_vlmcsd', 'err', sprintf(__('st_vlmcsd_down'), STATUS_VLMCSD_PORT), '127.0.0.1:' . STATUS_VLMCSD_PORT);
    }

    // Лог vlmcsd (путь — как в основной панели)
    $logPath = (string)($cfg['logPath'] ?? 'vlmcsd.log');
    $base = realpath(__DIR__ . '/../..') ?: dirname(dirname(__DIR__));
    $full = (strpos($logPath, '/') === 0 || preg_match('/^[A-Za-z]:[\\\\\/]/', $logPath)) ? $logPath : $base . '/' . ltrim($logPath, './');
    if (!is_file($full)) {
        $rows[] = stRow($G['core'], '📜', 'st_c_log', 'err', __('st_log_missing'), $full);
    } elseif (!is_readable($full)) {
        $rows[] = stRow($G['core'], '📜', 'st_c_log', 'err', __('st_log_unreadable'), $full);
    } else {
        $mt = (int)@filemtime($full);
        $old = (time() - $mt) > 30 * 86400;
        $rows[] = stRow($G['core'], '📜', 'st_c_log', $old ? 'warn' : 'ok', sprintf(__($old ? 'st_log_old' : 'st_log_ok'), stAgo($mt), stBytes((int)@filesize($full))), $full);
    }

    // Папка данных
    $di = vlmcDataInfo();
    $dir = $di['dir'];
    $free = @disk_free_space($dir);
    $freeTxt = $free !== false ? stBytes((int)$free) : '—';
    if (!is_dir($dir) || !is_writable($dir)) {
        $rows[] = stRow($G['core'], '🗄️', 'st_c_data', 'err', __('st_data_ro'), $dir);
    } elseif (!empty($di['legacy']) || !empty($di['stale'])) {
        $rows[] = stRow($G['core'], '🗄️', 'st_c_data', 'warn', __('st_data_legacy'), $dir);
    } elseif ($free !== false && $free < 50 * 1048576) {
        $rows[] = stRow($G['core'], '🗄️', 'st_c_data', 'warn', sprintf(__('st_data_low'), $freeTxt), $dir);
    } else {
        $rows[] = stRow($G['core'], '🗄️', 'st_c_data', 'ok', sprintf(__('st_data_ok'), $freeTxt), $dir);
    }

    // Конфигурация и пользователи
    $cr = config_read(vlmcConfigFile());
    $ur = config_read(vlmcUsersFile());
    if ($cr['status'] !== 'ok' || $ur['status'] !== 'ok' || !is_array($ur['config'])) {
        $rows[] = stRow($G['core'], '⚙️', 'st_c_conf', 'err', __($cr['status'] !== 'ok' ? 'st_conf_bad' : 'st_users_bad'), '');
    } else {
        $admins = 0;
        foreach ($ur['config'] as $u) {
            if (is_array($u) && defined('PERM_ADMIN_FULL') && ((int)($u['permissions'] ?? 0) & PERM_ADMIN_FULL) === PERM_ADMIN_FULL) { $admins++; }
        }
        $rows[] = stRow($G['core'], '⚙️', 'st_c_conf', $admins > 0 ? 'ok' : 'warn', sprintf(__($admins > 0 ? 'st_conf_ok' : 'st_conf_noadmin'), count($ur['config']), $admins), '');
    }

    // PHP и расширения
    $need = ['curl', 'json', 'mbstring', 'openssl', 'session'];
    $miss = array_values(array_filter($need, function ($e) { return !extension_loaded($e); }));
    $ldapOn = !empty($cfg['integrations']['ad']['enabled']);
    $ldapHave = function_exists('ldap_connect');
    if ($miss) {
        $rows[] = stRow($G['core'], '🐘', 'st_c_php', 'err', sprintf(__('st_php_miss'), implode(', ', $miss)), 'PHP ' . PHP_VERSION);
    } elseif ($ldapOn && !$ldapHave) {
        $rows[] = stRow($G['core'], '🐘', 'st_c_php', 'warn', __('st_php_ldap'), 'PHP ' . PHP_VERSION);
    } else {
        $rows[] = stRow($G['core'], '🐘', 'st_c_php', 'ok', __('st_php_ok'), 'PHP ' . PHP_VERSION . ($ldapHave ? ' · ldap' : ''));
    }

    // ---------- Интеграции ----------
    require_once __DIR__ . '/ldap.php';
    $lv = ldapStatusView($cfg);
    $map = ['active' => ['ok', 'st_ldap_active'], 'down' => ['err', 'st_ldap_down'], 'off' => ['off', 'st_off'], 'unknown' => ['warn', 'st_ldap_unknown']];
    $m = $map[$lv['state']] ?? $map['unknown'];
    $rows[] = stRow($G['int'], '🏢', 'st_c_ldap', $m[0], __($m[1]) . ($lv['state'] === 'down' && $lv['text'] !== '' ? ': ' . $lv['text'] : ''), $lv['time'] !== '' ? __('st_checked') . ' ' . $lv['time'] : '');

    require_once __DIR__ . '/webhook.php';
    $hooks = array_values(array_filter(whLoad($cfg), function ($h) { return !empty($h['enabled']); }));
    $whState = whState();
    $bad = [];
    foreach ($hooks as $h) {
        if (isset($whState[$h['id']]) && empty($whState[$h['id']]['ok'])) { $bad[] = (string)($h['name'] ?? $h['id']); }
    }
    if (!whLoad($cfg)) {
        $rows[] = stRow($G['int'], '🔗', 'st_c_webhook', 'off', __('st_not_set'));
    } elseif ($bad) {
        $rows[] = stRow($G['int'], '🔗', 'st_c_webhook', 'warn', sprintf(__('st_wh_bad'), count($hooks), count($bad)), implode(', ', $bad));
    } else {
        $rows[] = stRow($G['int'], '🔗', 'st_c_webhook', 'ok', sprintf(__('st_wh_ok'), count($hooks)));
    }

    require_once __DIR__ . '/cloud.php';
    $cl = cloudLoad($cfg);
    $sched = array_filter($cl, function ($t) { return !empty($t['enabled']) && ($t['schedule'] ?? 'off') !== 'off'; });
    if (!$cl) {
        $rows[] = stRow($G['int'], '☁️', 'st_c_cloud', 'off', __('st_not_set'));
    } elseif ($sched) {
        $rows[] = stRow($G['int'], '☁️', 'st_c_cloud', 'warn', sprintf(__('st_cl_nobackup'), count($cl)));
    } else {
        $rows[] = stRow($G['int'], '☁️', 'st_c_cloud', 'ok', sprintf(__('st_cl_ok'), count($cl)));
    }

    $rows[] = stRow($G['int'], '🛰️', 'st_c_ms', 'off', __('st_wip'));

    require_once __DIR__ . '/notify.php';
    $nf = notifyLoad();
    $chOn = array_filter($nf['channels'], function ($c) { return !empty($c['enabled']); });
    $rows[] = $chOn ? stRow($G['int'], '🔔', 'st_c_notify', 'ok', sprintf(__('st_nf_ok'), count($chOn)))
                    : stRow($G['int'], '🔔', 'st_c_notify', 'off', __('st_not_set'));

    // ---------- Безопасность ----------
    require_once __DIR__ . '/panel_access.php';
    $pa = vlmcPanelAccessSettings($cfg);
    if (vlmcPanelAccessEmergencyOff()) {
        $rows[] = stRow($G['sec'], '🛡️', 'st_c_access', 'warn', __('st_acc_emergency'), 'panel_whitelist.off');
    } elseif ($pa['enabled']) {
        $rows[] = stRow($G['sec'], '🛡️', 'st_c_access', 'ok', sprintf(__('st_acc_on'), count($pa['entries'])));
    } else {
        $rows[] = stRow($G['sec'], '🛡️', 'st_c_access', 'off', __('st_acc_off'));
    }

    require_once __DIR__ . '/firewall.php';
    $fs = fwSettings($cfg);
    // Firewall в разработке: проверки отключены, пока функция не доработана
    if (true) {
        $rows[] = stRow($G['sec'], '🧱', 'st_c_fw', 'off', __('int_ms_wip'));
    } else {
        $fst = fwStatus($fs);
        $list = fwList();
        $pending = count(array_filter($list, function ($b) { return ($b['status'] ?? '') === 'pending'; }));
        if ($fst['ready']) {
            $rows[] = stRow($G['sec'], '🧱', 'st_c_fw', $pending ? 'warn' : 'ok', sprintf(__($pending ? 'st_fw_pending' : 'st_fw_ok'), $fst['effective'], count($list), $pending), $fst['helper']);
        } else {
            $key = ['exec_disabled' => 'st_fw_p_exec', 'helper_missing' => 'st_fw_p_helper', 'sudo_denied' => 'st_fw_p_sudo', 'no_backend' => 'st_fw_p_backend'][$fst['problem']] ?? 'st_fw_p_other';
            $rows[] = stRow($G['sec'], '🧱', 'st_c_fw', 'err', __($key), $fst['helper']);
        }
    }

    // ---------- Обслуживание ----------
    require_once __DIR__ . '/scheduler.php';
    $ss = schedState();
    $cron = (int)($ss['cron_time'] ?? 0);
    if (!$cron) {
        $rows[] = stRow($G['maint'], '⏱️', 'st_c_cron', 'warn', __('st_cron_none'), schedCronCommand()['os'] === 'linux' ? '/etc/cron.d/kms-monitor' : '');
    } elseif ((time() - $cron) >= SCHED_CRON_FRESH) {
        $rows[] = stRow($G['maint'], '⏱️', 'st_c_cron', 'err', sprintf(__('st_cron_stale'), stAgo($cron)), '/etc/cron.d/kms-monitor');
    } else {
        $rows[] = stRow($G['maint'], '⏱️', 'st_c_cron', 'ok', sprintf(__('st_cron_ok'), stAgo($cron)), '/etc/cron.d/kms-monitor');
    }

    $problems = [];
    foreach (schedTasks() as $id => $t) {
        if (empty($t['enabled'])) { continue; }
        $st = $ss['tasks'][$id] ?? null;
        if ($st && empty($st['ok'])) { $problems[] = strip_tags(preg_replace('/^[^\p{L}\p{N}]+/u', '', $t['label'])) . ': ' . __('st_task_failed'); }
        elseif ($st && (time() - (int)$st['time']) > 2 * max(3600, (int)$t['interval']) + 3600) { $problems[] = strip_tags(preg_replace('/^[^\p{L}\p{N}]+/u', '', $t['label'])) . ': ' . __('st_task_overdue'); }
    }
    $rows[] = $problems ? stRow($G['maint'], '🕘', 'st_c_tasks', 'warn', implode('; ', $problems))
                        : stRow($G['maint'], '🕘', 'st_c_tasks', 'ok', __('st_tasks_ok'));

    require_once __DIR__ . '/updater.php';
    $upd = new Updater();
    $us = $upd->loadState();
    $avail = [];
    foreach (Updater::CHANNELS as $ch) {
        if (!empty($us[$ch]['available'])) { $avail[] = strtoupper($ch); }
    }
    $checked = (int)($us['checked_at'] ?? 0);
    if (!$checked) {
        $rows[] = stRow($G['maint'], '🔄', 'st_c_update', 'off', __('st_upd_never'), 'v' . $upd->getCurrentVersion());
    } elseif ($avail) {
        $rows[] = stRow($G['maint'], '🔄', 'st_c_update', 'warn', sprintf(__('st_upd_avail'), implode(', ', $avail)), 'v' . $upd->getCurrentVersion() . ' · ' . __('st_checked') . ' ' . stAgo($checked));
    } else {
        $rows[] = stRow($G['maint'], '🔄', 'st_c_update', 'ok', __('st_upd_ok'), 'v' . $upd->getCurrentVersion() . ' · ' . __('st_checked') . ' ' . stAgo($checked));
    }

    require_once __DIR__ . '/audit.php';
    $as = auditStats();
    $big = $as['size'] > 50 * 1048576;
    $rows[] = stRow($G['maint'], '📜', 'st_c_audit', $big ? 'warn' : 'ok', sprintf(__('st_audit_ok'), $as['records'], stBytes($as['size']), $as['retention_days']));

    return $rows;
}

/** Данные для вкладки: строки + счётчики. */
function statusView() {
    $rows = statusCollect();
    $cnt = ['ok' => 0, 'warn' => 0, 'err' => 0, 'off' => 0];
    foreach ($rows as $r) { $cnt[$r['state']]++; }
    return ['rows' => $rows, 'counts' => $cnt, 'time' => date('Y-m-d H:i:s')];
}
