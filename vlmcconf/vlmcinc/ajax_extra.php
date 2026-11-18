<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax_extra.php
// ВЕРСИЯ: 1.3.0
// ДАТА: 2026-11-18
// @description: AJAX-обработчики раздела «Интеграции» (п. 26: Active Directory / LDAP).
//               Подключается из ajax.php после проверки прав (ajaxAuthorizeOrDie).
// ============================================

if (!defined('VLMC_AJAX_EXTRA_OK') && basename($_SERVER['PHP_SELF'] ?? '') === 'ajax_extra.php') {
    http_response_code(403);
    exit('Access denied');
}

if (!function_exists('__')) {
    // Прямой вызов ajax.php (например, с главной страницы): переводы ещё не загружены
    $xLang = 'ru';
    if (function_exists('vlmcConfigFile')) {
        $xCfg = @json_decode((string)@file_get_contents(vlmcConfigFile()), true);
        if (is_array($xCfg) && in_array($xCfg['language'] ?? '', ['ru', 'en'], true)) {
            $xLang = $xCfg['language'];
        }
    }
    $translations = @include __DIR__ . '/../locale/' . $xLang . '.php';
    if (!is_array($translations)) {
        $translations = [];
    }
    function __($key) {
        global $translations;
        return isset($translations[$key]) ? $translations[$key] : $key;
    }
}

if (!function_exists('xjson')) {
    /** Ответ JSON и выход. */
    function xjson(array $data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Меняет конфиг под блокировкой: $fn(&$config) возвращает false, чтобы отменить запись. */
    function xConfigUpdate(callable $fn) {
        $file = vlmcConfigFile();
        config_lock($file);
        $r = config_read($file);
        if ($r['status'] !== 'ok' || !is_array($r['config'])) {
            return false;
        }
        $config = $r['config'];
        if ($fn($config) === false) {
            return false;
        }
        $config['last_modified'] = date('Y-m-d H:i:s');
        return save_config($file, $config);
    }

    function xBool($v) {
        return in_array($v, ['1', 1, true, 'true', 'on'], true);
    }

    function xUser() {
        return (string)($_SESSION['vlmc_username'] ?? '');
    }
}

$xAction = (string)($_POST['ajax'] ?? '');

// ============================================
// П. 26: Active Directory / LDAP
// ============================================
if (in_array($xAction, ['ldap_save_settings', 'ldap_test', 'ldap_groups', 'ldap_search', 'ldap_import', 'ldap_sync'], true)) {
    require_once __DIR__ . '/ldap.php';

    if ($xAction === 'ldap_save_settings') {
        $server = trim((string)($_POST['server'] ?? ''));
        if ($server !== '' && !preg_match('/^[A-Za-z0-9.\-:\[\]]{1,253}$/', $server)) {
            xjson(['success' => false, 'message' => __('ldap_msg_bad_server')]);
        }
        $security = in_array($_POST['security'] ?? '', ['none', 'ldaps', 'starttls'], true) ? $_POST['security'] : 'none';
        $port = (int)($_POST['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $port = $security === 'ldaps' ? 636 : 389;
        }
        $attr = in_array($_POST['login_attr'] ?? '', ['sAMAccountName', 'userPrincipalName', 'uid', 'mail'], true) ? $_POST['login_attr'] : 'sAMAccountName';
        $filter = trim((string)($_POST['user_filter'] ?? ''));
        if ($filter !== '' && ($filter[0] !== '(' || substr($filter, -1) !== ')' || substr_count($filter, '(') !== substr_count($filter, ')') || mb_strlen($filter) > 300)) {
            xjson(['success' => false, 'message' => __('ldap_msg_bad_filter')]);
        }
        $perms = (int)($_POST['default_permissions'] ?? 0);
        $allowedPerms = [PERM_LOGS_VIEW | PERM_DEVICES_VIEW, PERM_LOGS_VIEW | PERM_DEVICES_VIEW | PERM_GROUPS_VIEW | PERM_INFO_VIEW,
                         PERM_LOGS_VIEW | PERM_DEVICES_VIEW | PERM_DEVICES_EDIT | PERM_GROUPS_VIEW | PERM_GROUPS_EDIT];
        if (!in_array($perms, $allowedPerms, true)) {
            $perms = $allowedPerms[0];
        }
        $password = (string)($_POST['service_password'] ?? '');
        $backupIn = json_decode((string)($_POST['backup_accounts'] ?? '[]'), true);
        $backupIn = is_array($backupIn) ? array_slice($backupIn, 0, LDAP_BACKUP_MAX) : [];
        $fields = [
            'enabled' => xBool($_POST['enabled'] ?? '0'),
            'auth_enabled' => xBool($_POST['auth_enabled'] ?? '0'),
            'auto_create' => xBool($_POST['auto_create'] ?? '0'),
            'server' => $server,
            'port' => $port,
            'security' => $security,
            'use_ssl' => $security === 'ldaps',
            'verify_cert' => xBool($_POST['verify_cert'] ?? '1'),
            'base_dn' => mb_substr(trim((string)($_POST['base_dn'] ?? '')), 0, 300),
            'domain' => mb_substr(trim((string)($_POST['domain'] ?? '')), 0, 100),
            'login_attr' => $attr,
            'user_filter' => $filter,
            'required_groups' => ldapNormalizeGroups(json_decode((string)($_POST['required_groups'] ?? '[]'), true) ?: []),
            'required_group' => '',
            'service_user' => mb_substr(trim((string)($_POST['service_user'] ?? '')), 0, 300),
            'default_permissions' => $perms,
            'timeout' => max(2, min(30, (int)($_POST['timeout'] ?? 5))),
        ];
        $saved = xConfigUpdate(function (&$config) use ($fields, $password, $backupIn) {
            $ad = isset($config['integrations']['ad']) && is_array($config['integrations']['ad']) ? $config['integrations']['ad'] : [];
            $ad = $fields + $ad;
            $ad = array_merge($ad, $fields);
            if ($password !== '' && $password !== '********') {
                $ad['service_password'] = notifyEncrypt($password);
            }
            // резервные записи: пароль-заглушка оставляет прежний пароль той же записи
            $old = [];
            foreach ((array)($ad['backup_accounts'] ?? []) as $ob) {
                if (is_array($ob) && isset($ob['user'])) {
                    $old[mb_strtolower(trim((string)$ob['user']))] = (string)($ob['password'] ?? '');
                }
            }
            $backups = [];
            foreach ($backupIn as $b) {
                $bu = mb_substr(trim((string)($b['user'] ?? '')), 0, 300);
                if ($bu === '') {
                    continue;
                }
                $bp = (string)($b['password'] ?? '');
                $enc = ($bp === '' || $bp === '********') ? ($old[mb_strtolower($bu)] ?? '') : notifyEncrypt($bp);
                $backups[] = ['user' => $bu, 'password' => $enc];
            }
            $ad['backup_accounts'] = $backups;
            if (empty($fields['service_user'])) {
                $ad['service_password'] = '';
            }
            $config['integrations']['ad'] = $ad;
        });
        xjson(['success' => $saved, 'message' => __($saved ? 'ldap_msg_saved' : 'msg_save_error')]);
    }

    if ($xAction === 'ldap_test') {
        $r = ldapTest();
        $msg = $r['success'] ? sprintf(__('ldap_msg_test_ok'), (int)($r['users'] ?? 0)) : __('ldap_err_' . $r['code']);
        if ($r['success'] && !empty($r['backup_used'])) {
            $msg .= ' — ' . sprintf(__('ldap_msg_backup_used'), $r['backup_used']);
        }
        if (!$r['success'] && !empty($r['detail'])) {
            $msg .= ' (' . mb_substr($r['detail'], 0, 120) . ')';
        }
        // подробная запись в журнал: сервер, учётная запись, причина
        $anon = __('ldap_log_anonymous');
        $logText = $r['success']
            ? sprintf(__('ldap_log_test_ok'), $r['server'], $r['account'] !== '' ? $r['account'] : $anon, (int)($r['users'] ?? 0))
              . (!empty($r['backup_used']) ? ' ' . __('ldap_log_backup_mark') : '')
            : sprintf(__('ldap_log_test_fail'), __('ldap_err_' . $r['code']), $r['server'] ?? '', $r['tried'] ? implode(', ', $r['tried']) : $anon)
              . (!empty($r['detail']) ? ' · ' . sprintf(__('ldap_log_reason'), mb_substr($r['detail'], 0, 200)) : '');
        ldapLog('test', $r['success'], $logText);
        xjson(['success' => $r['success'], 'message' => $msg]);
    }

    if ($xAction === 'ldap_groups') {
        $r = ldapListGroups((string)($_POST['q'] ?? ''), 300);
        $msg = $r['success'] ? '' : __('ldap_err_' . $r['code']);
        if (!$r['success'] && !empty($r['detail'])) {
            $msg .= ' (' . mb_substr($r['detail'], 0, 120) . ')';
        }
        xjson(['success' => $r['success'], 'message' => $msg, 'groups' => $r['groups']]);
    }

    if ($xAction === 'ldap_search') {
        $r = ldapSearchUsers((string)($_POST['q'] ?? ''), 200);
        $msg = $r['success'] ? '' : __('ldap_err_' . $r['code']);
        if (!$r['success'] && !empty($r['detail'])) {
            $msg .= ' (' . mb_substr($r['detail'], 0, 120) . ')';
        }
        xjson(['success' => $r['success'], 'users' => $r['users'], 'message' => $msg]);
    }

    if ($xAction === 'ldap_import' || $xAction === 'ldap_sync') {
        if ($xAction === 'ldap_import') {
            $names = json_decode((string)($_POST['users'] ?? '[]'), true);
            $r = ldapImportUsers(is_array($names) ? array_map('strval', $names) : []);
        } else {
            $r = ldapSyncAll();
        }
        if (!$r['success']) {
            $msg = __('ldap_err_' . $r['code']);
            ldapLog($xAction === 'ldap_sync' ? 'sync' : 'import', false, $msg);
            xjson(['success' => false, 'message' => $msg]);
        }
        $msg = $r['code'] === 'nothing' ? __('ldap_msg_sync_nothing')
             : sprintf(__('ldap_msg_import_done'), $r['created'], $r['updated'], count($r['skipped']), $r['failed']);
        ldapLog($xAction === 'ldap_sync' ? 'sync' : 'import', true, $msg);
        xjson(['success' => true, 'message' => $msg, 'created' => $r['created'], 'updated' => $r['updated']]);
    }
}

// ============================================
// Интеграции: Webhook и Cloud Storage
// ============================================
if (!function_exists('xDiff')) {
    /** Строки «поле: было → стало» для журнала действий (только изменившиеся значения; секреты не передаются). */
    function xDiff(array $old, array $new, array $labels) {
        $out = [];
        foreach ($labels as $k => $label) {
            $a = $old[$k] ?? '';
            $b = $new[$k] ?? '';
            $fa = is_array($a) ? implode(', ', $a) : (is_bool($a) ? ($a ? '1' : '0') : (string)$a);
            $fb = is_array($b) ? implode(', ', $b) : (is_bool($b) ? ($b ? '1' : '0') : (string)$b);
            if ($fa !== $fb) {
                $out[] = $label . ': ' . ($fa === '' ? '—' : mb_substr($fa, 0, 80)) . ' → ' . ($fb === '' ? '—' : mb_substr($fb, 0, 80));
            }
        }
        return $out;
    }
}

if (in_array($xAction, ['webhook_list', 'webhook_save', 'webhook_delete', 'webhook_toggle', 'webhook_test'], true)) {
    require_once __DIR__ . '/webhook.php';

    if ($xAction === 'webhook_list') {
        $state = whState();
        $events = [];
        foreach (whEvents() as $e) {
            $events[] = ['code' => $e, 'label' => __('notify_ev_' . $e)];
        }
        xjson(['success' => true, 'hooks' => array_map(function ($h) use ($state) { return whPublic($h, $state); }, whLoad()), 'events' => $events]);
    }

    $id = (string)($_POST['id'] ?? '');

    if ($xAction === 'webhook_save') {
        $in = json_decode((string)($_POST['data'] ?? ''), true);
        $in = is_array($in) ? $in : [];
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            xjson(['success' => false, 'message' => __('wh_err_name')]);
        }
        $format = in_array(($in['format'] ?? ''), WH_FORMATS, true) ? $in['format'] : 'json';
        $timeout = max(2, min(30, (int)($in['timeout'] ?? 5)));
        $events = array_values(array_intersect(whEvents(), (array)($in['events'] ?? [])));
        $url = trim((string)($in['url'] ?? ''));
        if ($url !== '') {
            $err = whValidateUrl($url);
            if ($err !== '') {
                xjson(['success' => false, 'message' => __($err)]);
            }
        }
        $secret = (string)($in['secret'] ?? '');
        $err = '';
        $created = false;
        $oldPub = [];
        $newPub = [];
        $saved = xConfigUpdate(function (&$config) use ($id, $name, $format, $timeout, $events, $url, $secret, $in, &$err, &$created, &$oldPub, &$newPub) {
            $list = isset($config['integrations']['webhooks']) && is_array($config['integrations']['webhooks']) ? $config['integrations']['webhooks'] : [];
            $idx = null;
            foreach ($list as $i => $h) {
                if (is_array($h) && ($h['id'] ?? '') === $id && $id !== '') {
                    $idx = $i;
                }
            }
            if ($idx === null) {
                if (count($list) >= WH_MAX_HOOKS) {
                    $err = 'wh_err_limit';
                    return false;
                }
                if ($url === '') {
                    $err = 'wh_err_url';
                    return false;
                }
                $h = ['id' => 'wh_' . bin2hex(random_bytes(4)), 'url' => '', 'secret' => ''];
                $created = true;
            } else {
                $h = $list[$idx];
                $oldPub = whPublic($h);
            }
            $h['name'] = $name;
            $h['format'] = $format;
            $h['timeout'] = $timeout;
            $h['events'] = $events;
            $h['enabled'] = !empty($in['enabled']);
            if ($url !== '') {
                $h['url'] = notifyEncrypt($url);
            }
            if (!empty($in['clear_secret'])) {
                $h['secret'] = '';
            } elseif ($secret !== '') {
                $h['secret'] = notifyEncrypt($secret);
            }
            $newPub = whPublic($h);
            if ($idx === null) {
                $list[] = $h;
            } else {
                $list[$idx] = $h;
            }
            $config['integrations']['webhooks'] = array_values($list);
        });
        if (!$saved) {
            xjson(['success' => false, 'message' => __($err !== '' ? $err : 'msg_save_error')]);
        }
        if ($created) {
            auditLog('webhook_save', sprintf(__('wh_log_created'), $name, $newPub['host'], $format));
        } else {
            $labels = ['name' => __('wh_f_name'), 'enabled' => __('wh_f_enabled'), 'format' => __('wh_f_format'), 'host' => __('wh_f_host'),
                       'timeout' => __('wh_f_timeout'), 'events' => __('wh_f_events')];
            $changes = xDiff($oldPub, $newPub, $labels);
            if ($secret !== '') { $changes[] = __('wh_f_secret') . ': ' . __('wh_log_changed'); }
            if (!empty($in['clear_secret'])) { $changes[] = __('wh_f_secret') . ': ' . __('wh_log_cleared'); }
            auditLog('webhook_save', sprintf(__('wh_log_updated'), $name, $newPub['host']), 'ok', null, $changes);
        }
        xjson(['success' => true, 'message' => __('wh_msg_saved')]);
    }

    // остальные действия — над существующим вебхуком
    $found = null;
    foreach (whLoad() as $h) {
        if ($h['id'] === $id) { $found = $h; }
    }
    if ($found === null) {
        xjson(['success' => false, 'message' => __('wh_err_notfound')]);
    }
    $pub = whPublic($found);

    if ($xAction === 'webhook_toggle') {
        $on = xBool($_POST['enabled'] ?? '0');
        $saved = xConfigUpdate(function (&$config) use ($id, $on) {
            foreach ($config['integrations']['webhooks'] as &$h) {
                if (is_array($h) && ($h['id'] ?? '') === $id) { $h['enabled'] = $on; }
            }
        });
        if ($saved) {
            auditLog('webhook_toggle', sprintf(__($on ? 'wh_log_on' : 'wh_log_off'), $pub['name'], $pub['host']));
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'wh_msg_saved' : 'msg_save_error')]);
    }

    if ($xAction === 'webhook_delete') {
        $saved = xConfigUpdate(function (&$config) use ($id) {
            $config['integrations']['webhooks'] = array_values(array_filter((array)$config['integrations']['webhooks'], function ($h) use ($id) {
                return !(is_array($h) && ($h['id'] ?? '') === $id);
            }));
        });
        if ($saved) {
            auditLog('webhook_delete', sprintf(__('wh_log_deleted'), $pub['name'], $pub['host']));
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'wh_msg_deleted' : 'msg_save_error')]);
    }

    if ($xAction === 'webhook_test') {
        list($ok, $info) = whSend($found, 'test', __('wh_test_title'), __('wh_test_text'), ['test' => '1']);
        whRemember($id, $ok, $info);
        $reason = $ok ? $info : (strpos($info, 'wh_err_') === 0 ? __($info) : $info);
        auditLog('webhook_test', sprintf(__($ok ? 'wh_log_test_ok' : 'wh_log_test_fail'), $pub['name'], $pub['host'], $reason), $ok ? 'ok' : 'error');
        xjson(['success' => $ok, 'message' => $ok ? sprintf(__('wh_msg_test_ok'), $info) : sprintf(__('wh_msg_test_fail'), $reason)]);
    }
}

if (in_array($xAction, ['cloud_list', 'cloud_save', 'cloud_delete', 'cloud_toggle', 'cloud_test'], true)) {
    require_once __DIR__ . '/cloud.php';

    if ($xAction === 'cloud_list') {
        xjson(['success' => true, 'targets' => array_map('cloudPublic', cloudLoad())]);
    }

    $id = (string)($_POST['id'] ?? '');

    if ($xAction === 'cloud_save') {
        $in = json_decode((string)($_POST['data'] ?? ''), true);
        $in = is_array($in) ? $in : [];
        $err = '';
        $created = false;
        $oldPub = [];
        $newPub = [];
        $saved = xConfigUpdate(function (&$config) use ($id, $in, &$err, &$created, &$oldPub, &$newPub) {
            $list = isset($config['integrations']['cloud']) && is_array($config['integrations']['cloud']) ? $config['integrations']['cloud'] : [];
            $idx = null;
            foreach ($list as $i => $t) {
                if (is_array($t) && ($t['id'] ?? '') === $id && $id !== '') { $idx = $i; }
            }
            $old = $idx === null ? null : $list[$idx];
            if ($idx === null && count($list) >= CLOUD_MAX_TARGETS) {
                $err = 'cl_err_limit';
                return false;
            }
            list($t, $e) = cloudBuild($in, $old);
            if ($t === null) {
                $err = $e;
                return false;
            }
            if ($idx === null) {
                $t['id'] = 'cl_' . bin2hex(random_bytes(4));
                $created = true;
                $list[] = $t;
            } else {
                $oldPub = cloudPublic($old);
                $list[$idx] = $t;
            }
            $newPub = cloudPublic($t);
            $config['integrations']['cloud'] = array_values($list);
        });
        if (!$saved) {
            xjson(['success' => false, 'message' => __($err !== '' ? $err : 'msg_save_error')]);
        }
        $typeName = strtoupper($newPub['type']);
        if ($created) {
            auditLog('cloud_save', sprintf(__('cl_log_created'), $newPub['name'], $typeName, $newPub['host']));
        } else {
            $labels = ['name' => __('cl_f_name'), 'enabled' => __('cl_f_enabled'), 'type' => __('cl_f_type'), 'host' => __('cl_f_host'), 'port' => __('cl_f_port'),
                       'user' => __('cl_f_user'), 'path' => __('cl_f_path'), 'bucket' => __('cl_f_bucket'), 'region' => __('cl_f_region'),
                       'what' => __('cl_f_what'), 'schedule' => __('cl_f_schedule'), 'time' => __('cl_f_time'), 'keep' => __('cl_f_keep')];
            $changes = xDiff($oldPub, $newPub, $labels);
            if ((string)($in['pass'] ?? '') !== '') { $changes[] = __('cl_f_pass') . ': ' . __('wh_log_changed'); }
            if (!empty($in['clear_pass'])) { $changes[] = __('cl_f_pass') . ': ' . __('wh_log_cleared'); }
            auditLog('cloud_save', sprintf(__('cl_log_updated'), $newPub['name'], $typeName, $newPub['host']), 'ok', null, $changes);
        }
        xjson(['success' => true, 'message' => __('cl_msg_saved')]);
    }

    $found = null;
    foreach (cloudLoad() as $t) {
        if ($t['id'] === $id) { $found = $t; }
    }
    if ($found === null) {
        xjson(['success' => false, 'message' => __('cl_err_notfound')]);
    }
    $pub = cloudPublic($found);

    if ($xAction === 'cloud_toggle') {
        $on = xBool($_POST['enabled'] ?? '0');
        $saved = xConfigUpdate(function (&$config) use ($id, $on) {
            foreach ($config['integrations']['cloud'] as &$t) {
                if (is_array($t) && ($t['id'] ?? '') === $id) { $t['enabled'] = $on; }
            }
        });
        if ($saved) {
            auditLog('cloud_toggle', sprintf(__($on ? 'cl_log_on' : 'cl_log_off'), $pub['name'], $pub['host']));
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'cl_msg_saved' : 'msg_save_error')]);
    }

    if ($xAction === 'cloud_delete') {
        $saved = xConfigUpdate(function (&$config) use ($id) {
            $config['integrations']['cloud'] = array_values(array_filter((array)$config['integrations']['cloud'], function ($t) use ($id) {
                return !(is_array($t) && ($t['id'] ?? '') === $id);
            }));
        });
        if ($saved) {
            auditLog('cloud_delete', sprintf(__('cl_log_deleted'), $pub['name'], $pub['host']));
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'cl_msg_deleted' : 'msg_save_error')]);
    }

    if ($xAction === 'cloud_test') {
        $r = cloudTest($found);
        $reason = $r['success'] ? ($r['partial'] ? __('cl_test_partial_' . $pub['type']) : '') : __('cl_test_err_' . $r['code']);
        if ($r['detail'] !== '') { $reason .= ($reason !== '' ? ' · ' : '') . $r['detail']; }
        auditLog('cloud_test', sprintf(__($r['success'] ? 'cl_log_test_ok' : 'cl_log_test_fail'), $pub['name'], $pub['host'], ($r['success'] && $reason !== '' ? ' — ' : '') . $reason), $r['success'] ? 'ok' : 'error');
        xjson(['success' => $r['success'], 'message' => ($r['success'] ? __('cl_msg_test_ok') : __('cl_msg_test_fail')) . ($reason !== '' ? ': ' . $reason : '')]);
    }
}
