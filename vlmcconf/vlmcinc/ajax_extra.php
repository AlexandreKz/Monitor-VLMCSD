<?php
// ============================================
// ФАЙЛ: vlmcinc/ajax_extra.php
// ВЕРСИЯ: 1.1.0
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
        $saved = xConfigUpdate(function (&$config) use ($fields, $password) {
            $ad = isset($config['integrations']['ad']) && is_array($config['integrations']['ad']) ? $config['integrations']['ad'] : [];
            $ad = $fields + $ad;
            $ad = array_merge($ad, $fields);
            if ($password !== '' && $password !== '********') {
                $ad['service_password'] = notifyEncrypt($password);
            }
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
        if (!$r['success'] && !empty($r['detail'])) {
            $msg .= ' (' . mb_substr($r['detail'], 0, 120) . ')';
        }
        ldapLog('test', $r['success'], $msg);
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
