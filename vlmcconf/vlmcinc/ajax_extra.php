<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax_extra.php
// ВЕРСИЯ: 1.5.0
// ДАТА: 2026-11-18
// @description: AJAX-обработчики разделов «Интеграции» (п. 26: Active Directory / LDAP) и «API» (п. 25: ключи и настройки).
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
// ============================================
// П. 25: REST API — ключи и настройки
// ============================================
if (in_array($xAction, ['api_keys_list', 'api_save_settings', 'api_key_create', 'api_key_toggle', 'api_key_delete'], true)) {
    require_once __DIR__ . '/api_core.php';

    if ($xAction === 'api_keys_list') {
        xjson(['success' => true, 'keys' => apiKeysView()]);
    }

    if ($xAction === 'api_save_settings') {
        $enabled = xBool($_POST['enabled'] ?? '0');
        $limit = (int)($_POST['rate_limit'] ?? 60);
        if ($limit < 0 || $limit > 100000) {
            xjson(['success' => false, 'message' => __('api_msg_bad_limit')]);
        }
        $origins = [];
        foreach (preg_split('/[\s,]+/', (string)($_POST['cors_origins'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $o) {
            if ($o !== '*' && !preg_match('#^https?://[A-Za-z0-9.\-:\[\]]+$#', $o)) {
                xjson(['success' => false, 'message' => sprintf(__('api_msg_bad_origin'), htmlspecialchars(mb_substr($o, 0, 80)))]);
            }
            $origins[] = $o;
        }
        $saved = xConfigUpdate(function (&$config) use ($enabled, $limit, $origins) {
            $api = isset($config['api']) && is_array($config['api']) ? $config['api'] : [];
            $api['enabled'] = $enabled;
            $api['rate_limit'] = $limit;
            $api['cors_origins'] = $origins;
            $config['api'] = $api;
        });
        xjson(['success' => $saved, 'message' => __($saved ? 'api_msg_settings_saved' : 'msg_save_error')]);
    }

    if ($xAction === 'api_key_create') {
        $scopes = array_filter(explode(',', (string)($_POST['scopes'] ?? '')));
        $ips = preg_split('/[\s,]+/', (string)($_POST['allowed_ips'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $r = apiKeyCreate((string)($_POST['name'] ?? ''), $scopes, $ips, (string)($_POST['expires'] ?? ''), xUser());
        $out = ['success' => $r['success'], 'message' => __('api_msg_' . $r['code'])];
        if ($r['success']) {
            $out['key'] = $r['key'];
        }
        xjson($out);
    }

    if ($xAction === 'api_key_toggle') {
        $ok = apiKeySetEnabled((string)($_POST['id'] ?? ''), xBool($_POST['enabled'] ?? '0'));
        xjson(['success' => $ok, 'message' => __($ok ? 'api_msg_key_updated' : 'msg_save_error')]);
    }

    if ($xAction === 'api_key_delete') {
        $ok = apiKeyDelete((string)($_POST['id'] ?? ''));
        xjson(['success' => $ok, 'message' => __($ok ? 'api_msg_key_deleted' : 'msg_save_error')]);
    }
}

if (in_array($xAction, ['ldap_status', 'ldap_save_settings', 'ldap_test', 'ldap_groups', 'ldap_search', 'ldap_import', 'ldap_sync'], true)) {
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
        if ($saved) {
            ldapStatusReset(); // настройки изменились: прежний результат проверки уже не актуален
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'ldap_msg_saved' : 'msg_save_error')]);
    }

    // Статус для плашки; проверку раз в 12 часов выполняет планировщик (vlmcinc/scheduler.php)
    if ($xAction === 'ldap_status') {
        xjson(['success' => true, 'status' => ldapStatusView()]);
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
        ldapStatusStore($r['success'], $r['success'] ? '' : __('ldap_err_' . $r['code']));
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
    /** Строки «поле: было → стало» для журнала событий (только изменившиеся значения; секреты не передаются). */
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

// ============================================
// П. 29: белый список IP для панели
// ============================================
if ($xAction === 'panel_wl_save') {
    require_once __DIR__ . '/panel_access.php';
    $enabled = xBool($_POST['enabled'] ?? '0');
    $raw = json_decode((string)($_POST['entries'] ?? '[]'), true);
    if (!is_array($raw) || count($raw) > 200) {
        xjson(['success' => false, 'message' => __('panel_wl_msg_bad_list')]);
    }
    $entries = [];
    $seen = [];
    foreach ($raw as $e) {
        $ip = vlmcPanelNormalizeEntry($e['ip'] ?? '');
        if ($ip === null) {
            xjson(['success' => false, 'message' => sprintf(__('panel_wl_msg_bad_entry'), htmlspecialchars(mb_substr((string)($e['ip'] ?? ''), 0, 60)))]);
        }
        if (isset($seen[$ip])) {
            continue;
        }
        $seen[$ip] = true;
        $entries[] = ['ip' => $ip, 'comment' => mb_substr(trim((string)($e['comment'] ?? '')), 0, 100)];
    }
    // Защита от блокировки самого себя: при включении адрес администратора должен подходить под список
    $me = vlmcPanelClientIp();
    if ($enabled && !vlmcPanelAccessAllowed($me, ['enabled' => true, 'entries' => $entries])) {
        xjson(['success' => false, 'code' => 'self_lockout', 'message' => sprintf(__('panel_wl_msg_self_lockout'), $me)]);
    }
    $saved = xConfigUpdate(function (&$config) use ($enabled, $entries) {
        $config['security']['panel_whitelist'] = ['enabled' => $enabled, 'entries' => $entries];
    });
    xjson(['success' => $saved, 'enabled' => $enabled,
           'message' => __($saved ? ($enabled ? 'panel_wl_msg_saved_on' : 'panel_wl_msg_saved_off') : 'msg_save_error')]);
}

// ============================================
// П. 30: блокировка IP в фаерволе
// ============================================
if (in_array($xAction, ['fw_save_settings', 'fw_status', 'fw_list', 'fw_block', 'fw_unblock', 'fw_apply'], true)) {
    require_once __DIR__ . '/firewall.php';

    if ($xAction === 'fw_save_settings') {
        $backend = in_array($_POST['backend'] ?? '', FW_BACKENDS, true) ? $_POST['backend'] : 'auto';
        $scope = (($_POST['scope'] ?? 'all') === 'port') ? 'port' : 'all';
        $port = (int)($_POST['port'] ?? 1688);
        if ($port < 1 || $port > 65535) {
            xjson(['success' => false, 'message' => __('fw_msg_bad_port')]);
        }
        $saved = xConfigUpdate(function (&$config) use ($backend, $scope, $port) {
            $config['firewall'] = ['backend' => $backend, 'scope' => $scope, 'port' => $port];
        });
        xjson(['success' => $saved, 'message' => __($saved ? 'fw_msg_settings_saved' : 'msg_save_error')]);
    }

    if ($xAction === 'fw_status') {
        $st = fwStatus();
        xjson(['success' => true, 'ready' => $st['ready'], 'effective' => $st['effective'], 'problem' => $st['problem'],
               'message' => $st['ready'] ? sprintf(__('fw_msg_ready'), $st['effective']) : __('fw_problem_' . $st['problem'])]);
    }

    if ($xAction === 'fw_list') {
        xjson(['success' => true, 'rows' => fwList()]);
    }

    if ($xAction === 'fw_block') {
        $r = fwBlock((string)($_POST['ip'] ?? ''), (string)($_POST['reason'] ?? ''), xUser());
        xjson(['success' => $r['success'], 'applied' => $r['applied'], 'code' => $r['code'], 'message' => __('fw_msg_' . $r['code'])]);
    }

    if ($xAction === 'fw_unblock') {
        $r = fwUnblock((string)($_POST['ip'] ?? ''));
        $msg = __('fw_msg_' . $r['code']);
        if (!empty($r['detail'])) {
            $msg .= ': ' . $r['detail'];
        }
        xjson(['success' => $r['success'], 'code' => $r['code'], 'message' => $msg]);
    }

    if ($xAction === 'fw_apply') {
        $r = fwApplyPending(xBool($_POST['all'] ?? '0'));
        xjson(['success' => $r['success'], 'applied' => $r['applied'],
               'message' => $r['success'] ? sprintf(__('fw_msg_applied_n'), $r['applied']) : __('fw_problem_' . $r['code'])]);
    }
}

// ============================================
// Интеграции: Мультисервер (набросок)
// ============================================
if (in_array($xAction, ['ms_list', 'ms_save', 'ms_delete', 'ms_test'], true)) {
    require_once __DIR__ . '/multiserver.php';

    /** Текст результата проверки для человека: «код|подробности» → переведённое сообщение. */
    $msText = function (array $r) {
        $t = __($r['code']);
        return $t . ($r['detail'] !== '' && $r['detail'] !== 'must_change_password' ? ' (' . $r['detail'] . ')' : '');
    };
    $msPub = function (array $s) {
        $p = msPublic($s);
        $parts = explode('|', $p['last_text'], 2);
        $p['last_text'] = $p['last_text'] === '' ? '' : __($parts[0]) . (isset($parts[1]) ? ' (' . $parts[1] . ')' : '');
        $p['host'] = (string)parse_url($p['url'], PHP_URL_HOST) . (parse_url($p['url'], PHP_URL_PORT) ? ':' . parse_url($p['url'], PHP_URL_PORT) : '');
        return $p;
    };

    if ($xAction === 'ms_list') {
        xjson(['success' => true, 'servers' => array_map($msPub, msLoad())]);
    }

    $id = (string)($_POST['id'] ?? '');

    if ($xAction === 'ms_save') {
        $in = json_decode((string)($_POST['data'] ?? ''), true);
        $in = is_array($in) ? $in : [];
        $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 60);
        $url = trim((string)($in['url'] ?? ''));
        $user = trim((string)($in['user'] ?? ''));
        $pass = (string)($in['pass'] ?? '');
        $old = null;
        foreach (msLoad() as $s) {
            if ($id !== '' && $s['id'] === $id) { $old = $s; }
        }
        if ($id !== '' && $old === null) {
            xjson(['success' => false, 'message' => __('ms_err_notfound')]);
        }
        if ($url === '') { xjson(['success' => false, 'message' => __('ms_err_url')]); }
        if ($user === '') { xjson(['success' => false, 'message' => __('ms_err_user')]); }
        if ($pass === '' && $old === null) { xjson(['success' => false, 'message' => __('ms_err_pass')]); }
        if ($pass === '' && $old !== null) { $pass = notifyDecrypt((string)($old['pass'] ?? '')); }
        if ($old === null && count(msLoad()) >= MS_MAX) { xjson(['success' => false, 'message' => __('ms_err_limit')]); }

        // Реальная проверка: подключение к панели и вход. Без успеха сервер не сохраняется.
        $r = msVerify($url, $user, $pass);
        if (!$r['ok']) {
            auditLog('ms_save', sprintf(__('ms_log_save_fail'), (string)parse_url($r['url'] !== '' ? $r['url'] : (preg_match('#^https?://#i', $url) ? $url : 'http://' . $url), PHP_URL_HOST), $msText($r)), 'error');
            xjson(['success' => false, 'message' => $msText($r)]);
        }
        $newId = $old ? $old['id'] : 'ms_' . bin2hex(random_bytes(4));
        $dup = false;
        $saved = xConfigUpdate(function (&$config) use ($newId, $old, $name, $user, $pass, $r, &$dup) {
            $list = isset($config['integrations']['multiservers']) && is_array($config['integrations']['multiservers']) ? $config['integrations']['multiservers'] : [];
            foreach ($list as $s) {
                if (is_array($s) && $s['id'] !== $newId && ($s['url'] ?? '') === $r['url'] && ($s['user'] ?? '') === $user) { $dup = true; return false; }
            }
            $rec = ['id' => $newId, 'name' => $name !== '' ? $name : (string)parse_url($r['url'], PHP_URL_HOST), 'url' => $r['url'], 'user' => $user,
                    'pass' => notifyEncrypt($pass), 'version' => $r['version'], 'last_time' => date('Y-m-d H:i:s'), 'last_ok' => true, 'last_text' => ''];
            $found = false;
            foreach ($list as $i => $s) {
                if (is_array($s) && $s['id'] === $newId) { $list[$i] = $rec; $found = true; }
            }
            if (!$found) { $list[] = $rec; }
            $config['integrations']['multiservers'] = array_values($list);
        });
        if (!$saved) {
            xjson(['success' => false, 'message' => __($dup ? 'ms_err_exists' : 'msg_save_error')]);
        }
        $host = (string)parse_url($r['url'], PHP_URL_HOST);
        auditLog('ms_save', sprintf(__($old ? 'ms_log_updated' : 'ms_log_added'), $name !== '' ? $name : $host, $host, $user, $r['version'] !== '' ? $r['version'] : '—'));
        xjson(['success' => true, 'message' => __('ms_msg_saved') . ($r['detail'] === 'must_change_password' ? ' ' . __('ms_note_change_password') : '')]);
    }

    $found = null;
    foreach (msLoad() as $s) {
        if ($s['id'] === $id) { $found = $s; }
    }
    if ($found === null) {
        xjson(['success' => false, 'message' => __('ms_err_notfound')]);
    }
    $host = (string)parse_url((string)$found['url'], PHP_URL_HOST);

    if ($xAction === 'ms_delete') {
        $saved = xConfigUpdate(function (&$config) use ($id) {
            $config['integrations']['multiservers'] = array_values(array_filter((array)$config['integrations']['multiservers'], function ($s) use ($id) {
                return !(is_array($s) && ($s['id'] ?? '') === $id);
            }));
        });
        if ($saved) {
            auditLog('ms_delete', sprintf(__('ms_log_deleted'), (string)$found['name'], $host));
        }
        xjson(['success' => $saved, 'message' => __($saved ? 'ms_msg_deleted' : 'msg_save_error')]);
    }

    if ($xAction === 'ms_test') {
        $r = msVerify((string)$found['url'], (string)$found['user'], notifyDecrypt((string)($found['pass'] ?? '')));
        msRemember($id, $r);
        auditLog('ms_test', sprintf(__($r['ok'] ? 'ms_log_test_ok' : 'ms_log_test_fail'), (string)$found['name'], $host, $r['ok'] ? ($r['version'] !== '' ? $r['version'] : '—') : $msText($r)), $r['ok'] ? 'ok' : 'error');
        xjson(['success' => $r['ok'], 'message' => $r['ok'] ? sprintf(__('ms_msg_test_ok'), $r['version'] !== '' ? $r['version'] : '—') : $msText($r)]);
    }
}

// ============================================
// Фоновые задачи (планировщик): запуск просроченных, список для вкладки «Фоновые», запуск одной задачи вручную
// ============================================
if (in_array($xAction, ['sched_run', 'sched_view', 'sched_run_now'], true)) {
    require_once __DIR__ . '/scheduler.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // задачи ходят в сеть: не держим сессию
    }
    if ($xAction === 'sched_run') {
        $r = schedRun('web');
        xjson(['success' => true, 'busy' => $r['busy'], 'ran' => $r['ran']]);
    }
    if ($xAction === 'sched_view') {
        xjson(['success' => true] + schedView());
    }
    $id = (string)($_POST['task'] ?? '');
    $tasks = schedTasks();
    if (!isset($tasks[$id]) || empty($tasks[$id]['enabled'])) {
        xjson(['success' => false, 'message' => __('sched_run_unavailable')]);
    }
    $r = schedRun('manual', $id);
    if (!empty($r['busy'])) {
        xjson(['success' => false, 'message' => __('sched_busy')]);
    }
    $res = $r['ran'][$id] ?? ['ok' => false, 'text' => ''];
    auditLog('sched_run_now', sprintf(__('sched_log_run_now'), $tasks[$id]['label'], $res['text']), $res['ok'] ? 'ok' : 'error');
    xjson(['success' => $res['ok'], 'message' => $res['text'], 'view' => schedView()]);
}

// ============================================
// Информация → Статус: проверки ядра, интеграций, безопасности и обслуживания (только чтение)
// ============================================
if ($xAction === 'status_get') {
    require_once __DIR__ . '/status.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    xjson(['success' => true] + statusView());
}
