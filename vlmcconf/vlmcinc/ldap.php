<?php
// ============================================
// ФАЙЛ: vlmcinc/ldap.php
// ВЕРСИЯ: 1.0.1
// ДАТА: 2026-11-18
// @description: Интеграция с Active Directory / LDAP (п. 26): настройки, проверка связи, вход доменных пользователей,
//               поиск и импорт пользователей, журнал синхронизации
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';
require_once __DIR__ . '/users.php';    // пользователи панели и права
require_once __DIR__ . '/notify.php';   // шифрование секретов (notifyEncrypt / notifyDecrypt)

if (basename($_SERVER['PHP_SELF'] ?? '') === 'ldap.php') {
    http_response_code(403);
    exit('Access denied');
}

const LDAP_LOG_KEEP = 200;

/** Расширение PHP LDAP установлено (php-ldap). */
function ldapAvailable() {
    return function_exists('ldap_connect') && function_exists('ldap_bind');
}

function ldapLogFile() {
    return vlmcDataFile('ldap_log.json');
}

/** Настройки из конфига с значениями по умолчанию. Пароль служебной учётной записи наружу не отдаётся. */
function ldapSettings($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    $a = isset($config['integrations']['ad']) && is_array($config['integrations']['ad']) ? $config['integrations']['ad'] : [];
    $security = $a['security'] ?? (!empty($a['use_ssl']) ? 'ldaps' : 'none');
    if (!in_array($security, ['none', 'ldaps', 'starttls'], true)) {
        $security = 'none';
    }
    $attr = (string)($a['login_attr'] ?? 'sAMAccountName');
    if (!in_array($attr, ['sAMAccountName', 'userPrincipalName', 'uid', 'mail'], true)) {
        $attr = 'sAMAccountName';
    }
    $port = (int)($a['port'] ?? 389);
    return [
        'enabled'        => !empty($a['enabled']),
        'auth_enabled'   => !empty($a['auth_enabled']),
        'auto_create'    => !isset($a['auto_create']) || !empty($a['auto_create']),
        'server'         => trim((string)($a['server'] ?? '')),
        'port'           => ($port > 0 && $port < 65536) ? $port : ($security === 'ldaps' ? 636 : 389),
        'security'       => $security,
        'verify_cert'    => !isset($a['verify_cert']) || !empty($a['verify_cert']),
        'base_dn'        => trim((string)($a['base_dn'] ?? '')),
        'domain'         => trim((string)($a['domain'] ?? '')),
        'login_attr'     => $attr,
        'user_filter'    => trim((string)($a['user_filter'] ?? '')),
        'required_group' => trim((string)($a['required_group'] ?? '')),
        'service_user'   => trim((string)($a['service_user'] ?? '')),
        'has_password'   => !empty($a['service_password']),
        'default_permissions' => (int)($a['default_permissions'] ?? (PERM_DEVICES_VIEW | PERM_LOGS_VIEW)),
        'timeout'        => max(2, min(30, (int)($a['timeout'] ?? 5))),
    ];
}

function ldapServicePassword($config = null) {
    if ($config === null) {
        $r = config_read(vlmcConfigFile());
        $config = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    }
    return notifyDecrypt((string)($config['integrations']['ad']['service_password'] ?? ''));
}

/** Экранирование значения для фильтра LDAP. */
function ldapEscapeFilter($v) {
    if (function_exists('ldap_escape')) {
        return ldap_escape((string)$v, '', LDAP_ESCAPE_FILTER);
    }
    return str_replace(['\\', '*', '(', ')', "\0"], ['\5c', '\2a', '\28', '\29', '\00'], (string)$v);
}

function ldapUri($s) {
    $host = $s['server'];
    if (strpos($host, ':') !== false && $host[0] !== '[') {
        $host = '[' . $host . ']';   // IPv6
    }
    return ($s['security'] === 'ldaps' ? 'ldaps://' : 'ldap://') . $host . ':' . $s['port'];
}

/** Запись в журнал синхронизации (последние LDAP_LOG_KEEP записей). */
function ldapLog($type, $ok, $text) {
    $file = ldapLogFile();
    config_lock($file);
    $r = config_read($file);
    $rows = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    array_unshift($rows, ['time' => date('Y-m-d H:i:s'), 'type' => $type, 'ok' => (bool)$ok, 'text' => mb_substr((string)$text, 0, 300)]);
    save_config($file, array_slice($rows, 0, LDAP_LOG_KEEP));
}

function ldapLogRead($limit = 50) {
    $r = config_read(ldapLogFile());
    $rows = ($r['status'] === 'ok' && is_array($r['config'])) ? $r['config'] : [];
    return array_slice($rows, 0, $limit);
}

/**
 * Подключение к серверу (без привязки). Возвращает [соединение|null, код ошибки, текст].
 */
function ldapOpen($s) {
    if (!ldapAvailable()) {
        return [null, 'no_extension', ''];
    }
    if ($s['server'] === '') {
        return [null, 'no_server', ''];
    }
    if (!$s['verify_cert'] && defined('LDAP_OPT_X_TLS_REQUIRE_CERT')) {
        @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);   // только если администратор явно отключил проверку
    }
    $conn = @ldap_connect(ldapUri($s));
    if (!$conn) {
        return [null, 'connect_failed', ''];
    }
    @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $s['timeout']);
    if (defined('LDAP_OPT_TIMELIMIT')) {
        @ldap_set_option($conn, LDAP_OPT_TIMELIMIT, $s['timeout']);
    }
    if ($s['security'] === 'starttls' && !@ldap_start_tls($conn)) {
        $err = ldap_error($conn);
        @ldap_close($conn);
        return [null, 'tls_failed', $err];
    }
    return [$conn, '', ''];
}

/** Привязка служебной учётной записью (или анонимная, если она не задана). Возвращает [ok, код, текст]. */
function ldapServiceBind($conn, $s, $config = null) {
    if ($s['service_user'] === '') {
        $ok = @ldap_bind($conn);
    } else {
        $ok = @ldap_bind($conn, $s['service_user'], ldapServicePassword($config));
    }
    if (!$ok) {
        // -1 = «Can't contact LDAP server»: до проверки пароля дело не дошло (адрес, порт, сеть или сертификат)
        if (function_exists('ldap_errno') && @ldap_errno($conn) === -1) {
            return [false, 'connect_failed', ldap_error($conn)];
        }
        return [false, 'bind_failed', ldap_error($conn)];
    }
    return [true, '', ''];
}

function ldapAttrFirst($entry, $name) {
    $n = strtolower($name);
    return isset($entry[$n][0]) ? (string)$entry[$n][0] : '';
}

/** Преобразует запись каталога в данные пользователя. */
function ldapEntryToUser($entry, $s, $fallbackName = '') {
    $login = ldapAttrFirst($entry, $s['login_attr']);
    if ($s['login_attr'] === 'userPrincipalName' || $s['login_attr'] === 'mail') {
        $login = strstr($login, '@', true) ?: $login;   // имя панели — часть до @
    }
    if ($login === '') {
        $login = $fallbackName;
    }
    $groups = [];
    $groupDns = [];
    if (isset($entry['memberof']['count'])) {
        for ($i = 0; $i < $entry['memberof']['count']; $i++) {
            $dn = (string)$entry['memberof'][$i];
            $groupDns[] = $dn;
            if (preg_match('/^CN=((?:\\\\.|[^,])+)/i', $dn, $m)) {
                $groups[] = stripslashes($m[1]);
            }
        }
    }
    $full = ldapAttrFirst($entry, 'displayName');
    if ($full === '') {
        $full = ldapAttrFirst($entry, 'cn');
    }
    return [
        'username' => $login,
        'fullname' => $full !== '' ? $full : $login,
        'email'    => ldapAttrFirst($entry, 'mail'),
        'dn'       => (string)($entry['dn'] ?? ''),
        'groups'   => $groups,
        'group_dns' => $groupDns,
    ];
}

/** Состоит ли пользователь в обязательной группе (по CN или полному DN). Пустое требование = всегда да. */
function ldapInRequiredGroup($user, $s) {
    $req = $s['required_group'];
    if ($req === '') {
        return true;
    }
    foreach ($user['group_dns'] as $dn) {
        if (strcasecmp($dn, $req) === 0) {
            return true;
        }
    }
    foreach ($user['groups'] as $cn) {
        if (strcasecmp($cn, $req) === 0) {
            return true;
        }
    }
    return false;
}

/** Фильтр поиска пользователя по логину. */
function ldapUserFilter($s, $username) {
    $u = ldapEscapeFilter($username);
    $attr = $s['login_attr'];
    if ($attr === 'sAMAccountName') {
        $core = '(|(sAMAccountName=' . $u . ')(userPrincipalName=' . $u . '))';
    } elseif ($attr === 'userPrincipalName' || $attr === 'mail') {
        $core = '(|(' . $attr . '=' . $u . ')(' . $attr . '=' . $u . '@' . ldapEscapeFilter($s['domain'] !== '' ? $s['domain'] : '*') . '))';
    } else {
        $core = '(' . $attr . '=' . $u . ')';
    }
    $extra = $s['user_filter'];
    if ($extra !== '' && $extra[0] === '(') {
        return '(&' . $core . $extra . ')';
    }
    return $core;
}

function ldapUserAttributes($s) {
    return array_values(array_unique(['cn', 'displayName', 'mail', 'memberOf', 'sAMAccountName', 'userPrincipalName', 'uid', $s['login_attr']]));
}

/** Находит пользователя в каталоге. Возвращает массив пользователя или null. */
function ldapFindUser($conn, $s, $username) {
    $sr = @ldap_search($conn, $s['base_dn'], ldapUserFilter($s, $username), ldapUserAttributes($s), 0, 2);
    if (!$sr) {
        return null;
    }
    $entries = ldap_get_entries($conn, $sr);
    if (empty($entries['count'])) {
        return null;
    }
    return ldapEntryToUser($entries[0], $s, $username);
}

/**
 * Проверка логина и пароля в каталоге.
 * Возвращает данные пользователя или false; причина отказа — в $GLOBALS['vlmc_ldap_reason'] (для журнала).
 */
function ldapAuthenticate($username, $password, $config = null) {
    $GLOBALS['vlmc_ldap_reason'] = '';
    $fail = function ($reason) {
        $GLOBALS['vlmc_ldap_reason'] = $reason;
        return false;
    };
    $s = ldapSettings($config);
    if (!$s['enabled'] || !$s['auth_enabled']) {
        return $fail('disabled');
    }
    // Пустой пароль в LDAP означает анонимную привязку, которая «успешна» — поэтому отсекаем заранее
    if ($username === '' || $password === '' || strlen($username) > 128 || strlen($password) > 512 || preg_match('/[\x00-\x1f]/', $username)) {
        return $fail('empty_credentials');
    }
    if ($s['base_dn'] === '') {
        return $fail('no_base_dn');
    }
    list($conn, $code) = ldapOpen($s);
    if (!$conn) {
        return $fail($code);
    }
    $user = null;
    if ($s['service_user'] !== '') {
        // 1) служебная учётка находит запись и её DN, 2) пароль проверяется привязкой от имени найденной записи
        list($ok) = ldapServiceBind($conn, $s, $config);
        if (!$ok) {
            @ldap_close($conn);
            return $fail('service_bind_failed');
        }
        $found = ldapFindUser($conn, $s, $username);
        if ($found === null || $found['dn'] === '') {
            @ldap_close($conn);
            return $fail('user_not_found');
        }
        if (!@ldap_bind($conn, $found['dn'], $password)) {
            @ldap_close($conn);
            return $fail('bad_password');
        }
        $user = $found;
    } else {
        // Без служебной учётки: прямая привязка по UPN (user@domain) либо введённому имени, затем чтение своей записи
        $bindName = $username;
        if ($s['domain'] !== '' && strpos($username, '@') === false && strpos($username, '\\') === false) {
            $bindName = $username . '@' . $s['domain'];
        }
        if (!@ldap_bind($conn, $bindName, $password)) {
            @ldap_close($conn);
            return $fail('bad_password');
        }
        $user = ldapFindUser($conn, $s, $username);
        if ($user === null) {
            $user = ['username' => $username, 'fullname' => $username, 'email' => '', 'dn' => '', 'groups' => [], 'group_dns' => []];
            if ($s['required_group'] !== '') {
                @ldap_close($conn);
                return $fail('group_check_failed');   // группу проверить нельзя, а она обязательна
            }
        }
    }
    @ldap_close($conn);
    if (!ldapInRequiredGroup($user, $s)) {
        return $fail('not_in_group');
    }
    if (!preg_match('/^[A-Za-z0-9._\-]{1,64}$/', $user['username'])) {
        return $fail('bad_username');   // имя для панели ограничено, чтобы не нарушать формат users.json и журнала
    }
    return $user;
}

/** Проверка настроек: связь, привязка, поиск. Возвращает ['success' => bool, 'code' => строка, 'detail' => текст, 'users' => число]. */
function ldapTest($config = null) {
    $s = ldapSettings($config);
    if (!ldapAvailable()) {
        return ['success' => false, 'code' => 'no_extension', 'detail' => ''];
    }
    if ($s['server'] === '' || $s['base_dn'] === '') {
        return ['success' => false, 'code' => 'incomplete', 'detail' => ''];
    }
    list($conn, $code, $detail) = ldapOpen($s);
    if (!$conn) {
        return ['success' => false, 'code' => $code, 'detail' => $detail];
    }
    list($ok, $bcode, $bdetail) = ldapServiceBind($conn, $s, $config);
    if (!$ok) {
        @ldap_close($conn);
        return ['success' => false, 'code' => $bcode, 'detail' => $bdetail];
    }
    $sr = @ldap_search($conn, $s['base_dn'], '(objectClass=*)', ['dn'], 0, 1, $s['timeout']);
    if (!$sr) {
        $err = ldap_error($conn);
        @ldap_close($conn);
        return ['success' => false, 'code' => 'search_failed', 'detail' => $err];
    }
    $n = 0;
    $us = @ldap_search($conn, $s['base_dn'], ldapListFilter($s), ['dn'], 0, 1000, $s['timeout']);
    if ($us) {
        $n = (int)ldap_count_entries($conn, $us);
    }
    @ldap_close($conn);
    return ['success' => true, 'code' => 'ok', 'detail' => '', 'users' => $n];
}

/** Фильтр для списка пользователей (объекты-люди). */
function ldapListFilter($s, $query = '') {
    if ($s['login_attr'] === 'sAMAccountName' || $s['login_attr'] === 'userPrincipalName') {
        $base = '(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';   // без отключённых
    } else {
        $base = '(objectClass=person)';
    }
    if ($s['user_filter'] !== '' && $s['user_filter'][0] === '(') {
        $base = '(&' . $base . $s['user_filter'] . ')';
    }
    if ($query !== '') {
        $q = ldapEscapeFilter($query);
        $base = '(&' . $base . '(|(cn=*' . $q . '*)(displayName=*' . $q . '*)(' . $s['login_attr'] . '=*' . $q . '*)(mail=*' . $q . '*)))';
    }
    return $base;
}

/** Список пользователей каталога для импорта (не более $limit). */
function ldapSearchUsers($query, $limit = 200, $config = null) {
    $s = ldapSettings($config);
    list($conn, $code, $detail) = ldapOpen($s);
    if (!$conn) {
        return ['success' => false, 'code' => $code, 'detail' => $detail, 'users' => []];
    }
    list($ok, $bcode, $bdetail) = ldapServiceBind($conn, $s, $config);
    if (!$ok) {
        @ldap_close($conn);
        return ['success' => false, 'code' => $bcode, 'detail' => $bdetail, 'users' => []];
    }
    $sr = @ldap_search($conn, $s['base_dn'], ldapListFilter($s, trim((string)$query)), ldapUserAttributes($s), 0, $limit, $s['timeout']);
    if (!$sr) {
        $err = ldap_error($conn);
        @ldap_close($conn);
        return ['success' => false, 'code' => 'search_failed', 'detail' => $err, 'users' => []];
    }
    $entries = ldap_get_entries($conn, $sr);
    @ldap_close($conn);
    $local = [];
    foreach (getAllUsers() as $u) {
        $local[$u['username']] = $u['source'] ?? 'local';
    }
    $out = [];
    for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
        $u = ldapEntryToUser($entries[$i], $s);
        if ($u['username'] === '' || !ldapInRequiredGroup($u, $s)) {
            continue;
        }
        $state = $local[$u['username']] ?? '';
        $out[] = ['username' => $u['username'], 'fullname' => $u['fullname'], 'email' => $u['email'], 'groups' => array_slice($u['groups'], 0, 5),
                  'state' => $state === '' ? 'new' : ($state === 'ad' ? 'imported' : 'local_conflict')];
    }
    usort($out, function ($a, $b) { return strcasecmp($a['username'], $b['username']); });
    return ['success' => true, 'code' => 'ok', 'detail' => '', 'users' => $out];
}

/**
 * Создаёт или обновляет доменного пользователя в списке пользователей панели.
 * Существующего локального пользователя доменная учётка не «перехватывает» (возвращается null).
 * @return array|null  запись пользователя панели
 */
function ldapUpsertUser(array $adUser, $createIfMissing, $permissions) {
    $file = getUsersFile();
    config_lock($file);
    $users = getAllUsers();
    if (!$users || ($GLOBALS['vlmc_users_status'] ?? '') === 'corrupt') {
        return null;
    }
    foreach ($users as &$u) {
        if ($u['username'] === $adUser['username']) {
            if (($u['source'] ?? 'local') !== 'ad') {
                return null;   // имя занято локальным пользователем
            }
            $u['fullname'] = $adUser['fullname'];
            $u['email'] = $adUser['email'];
            $u['last_ad_login'] = date('Y-m-d H:i:s');
            $saved = $u;
            unset($u);
            return saveUsers($users) ? $saved : null;
        }
    }
    unset($u);
    if (!$createIfMissing) {
        return null;
    }
    $id = 1;
    foreach ($users as $u) {
        if ($u['id'] >= $id) {
            $id = $u['id'] + 1;
        }
    }
    $new = [
        'id' => $id,
        'username' => $adUser['username'],
        'password_hash' => '',   // пароля в панели нет: вход только через каталог
        'permissions' => (int)$permissions,
        'must_change_password' => false,
        'source' => 'ad',
        'fullname' => $adUser['fullname'],
        'email' => $adUser['email'],
        'created' => date('Y-m-d H:i:s'),
        'last_login' => null,
    ];
    $users[] = $new;
    return saveUsers($users) ? $new : null;
}

/** Импорт выбранных пользователей из каталога. Возвращает ['created' => n, 'updated' => n, 'skipped' => [..], 'failed' => n]. */
function ldapImportUsers(array $usernames, $config = null) {
    $s = ldapSettings($config);
    $res = ['success' => false, 'created' => 0, 'updated' => 0, 'skipped' => [], 'failed' => 0, 'code' => ''];
    list($conn, $code, $detail) = ldapOpen($s);
    if (!$conn) {
        $res['code'] = $code;
        return $res;
    }
    list($ok, $bcode) = ldapServiceBind($conn, $s, $config);
    if (!$ok) {
        @ldap_close($conn);
        $res['code'] = $bcode;
        return $res;
    }
    $existing = [];
    foreach (getAllUsers() as $u) {
        $existing[$u['username']] = $u['source'] ?? 'local';
    }
    foreach (array_slice(array_unique($usernames), 0, 200) as $name) {
        $name = (string)$name;
        $found = ldapFindUser($conn, $s, $name);
        if ($found === null || !ldapInRequiredGroup($found, $s) || !preg_match('/^[A-Za-z0-9._\-]{1,64}$/', $found['username'])) {
            $res['failed']++;
            continue;
        }
        if (isset($existing[$found['username']]) && $existing[$found['username']] !== 'ad') {
            $res['skipped'][] = $found['username'];   // совпадает с локальным пользователем
            continue;
        }
        $isNew = !isset($existing[$found['username']]);
        $saved = ldapUpsertUser($found, true, $s['default_permissions']);
        if ($saved === null) {
            $res['failed']++;
        } elseif ($isNew) {
            $res['created']++;
        } else {
            $res['updated']++;
        }
    }
    @ldap_close($conn);
    $res['success'] = true;
    $res['code'] = 'ok';
    return $res;
}

/** Обновление имени и почты у всех доменных пользователей панели. Возвращает сводку. */
function ldapSyncAll($config = null) {
    $names = [];
    foreach (getAllUsers() as $u) {
        if (($u['source'] ?? '') === 'ad') {
            $names[] = $u['username'];
        }
    }
    if (!$names) {
        return ['success' => true, 'created' => 0, 'updated' => 0, 'skipped' => [], 'failed' => 0, 'code' => 'nothing'];
    }
    return ldapImportUsers($names, $config);
}
