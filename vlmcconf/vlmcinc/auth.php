<?php
// ============================================
// ФАЙЛ: vlmcinc/auth.php
// ВЕРСИЯ: 2.0.0
// ДАТА: 2026-06-02
// @description: Функции авторизации (локальная + AD)
// ============================================

/**
 * Проверка авторизации в панели управления
 */
function checkAuth() {
    if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
        header('Location: login.php');
        exit;
    }
    
    if (isset($_SESSION['vlmc_login_time']) && (time() - $_SESSION['vlmc_login_time'] > 1800)) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    
    $_SESSION['vlmc_login_time'] = time();
}

/**
 * Аутентификация через Active Directory
 * 
 * @param string $username Имя пользователя (логин)
 * @param string $password Пароль
 * @return array|false Массив с данными пользователя или false при ошибке
 */
function authenticate_ad($username, $password) {
    global $config;
    
    // Проверка включена ли AD-интеграция и AD-аутентификация
    if (empty($config['integrations']['ad']['enabled']) || 
        empty($config['integrations']['ad']['auth_enabled'])) {
        return false;
    }
    
    // Проверка LDAP расширения
    if (!extension_loaded('ldap')) {
        error_log('KMS Monitor: LDAP extension not loaded');
        return false;
    }
    
    $server = $config['integrations']['ad']['server'] ?? '';
    $port = $config['integrations']['ad']['port'] ?? 389;
    $use_ssl = $config['integrations']['ad']['use_ssl'] ?? false;
    $base_dn = $config['integrations']['ad']['base_dn'] ?? '';
    $domain = $config['integrations']['ad']['domain'] ?? '';
    
    if (empty($server) || empty($base_dn)) {
        error_log('KMS Monitor: AD config incomplete');
        return false;
    }
    
    // Формируем полное имя пользователя (user@domain или DOMAIN\user)
    $user_dn = $username;
    if (!empty($domain)) {
        if (strpos($username, '@') === false && strpos($username, '\\') === false) {
            $user_dn = $username . '@' . $domain;
        }
    }
    
    $ldap_server = $use_ssl ? "ldaps://{$server}:{$port}" : "ldap://{$server}:{$port}";
    
    $ldapconn = @ldap_connect($ldap_server);
    if (!$ldapconn) {
        error_log('KMS Monitor: Failed to connect to LDAP server: ' . $ldap_server);
        return false;
    }
    
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
    
    // Пробуем привязаться с учетными данными пользователя
    $bind = @ldap_bind($ldapconn, $user_dn, $password);
    if (!$bind) {
        error_log('KMS Monitor: AD bind failed for user: ' . $username . ', error: ' . ldap_error($ldapconn));
        ldap_close($ldapconn);
        return false;
    }
    
    // Получаем информацию о пользователе
    $filter = "(|(sAMAccountName={$username})(userPrincipalName={$user_dn}))";
    $search = @ldap_search($ldapconn, $base_dn, $filter, ['cn', 'sAMAccountName', 'mail', 'displayName', 'memberOf']);
    
    if (!$search) {
        ldap_close($ldapconn);
        return false;
    }
    
    $entries = ldap_get_entries($ldapconn, $search);
    $user_info = [];
    
    if ($entries['count'] > 0) {
        $user_info = [
            'username' => $entries[0]['samaccountname'][0] ?? $username,
            'fullname' => $entries[0]['displayname'][0] ?? $entries[0]['cn'][0] ?? $username,
            'email' => $entries[0]['mail'][0] ?? '',
            'groups' => []
        ];
        
        // Извлекаем группы (memberOf)
        if (isset($entries[0]['memberof']) && $entries[0]['memberof']['count'] > 0) {
            for ($i = 0; $i < $entries[0]['memberof']['count']; $i++) {
                $group_dn = $entries[0]['memberof'][$i];
                // Извлекаем CN из DN
                if (preg_match('/CN=([^,]+)/', $group_dn, $matches)) {
                    $user_info['groups'][] = $matches[1];
                }
            }
        }
    } else {
        // Если не нашли в каталоге, но аутентификация прошла — всё равно создаём пользователя
        $user_info = [
            'username' => $username,
            'fullname' => $username,
            'email' => '',
            'groups' => []
        ];
    }
    
    ldap_close($ldapconn);
    
    return $user_info;
}

/**
 * Создание или обновление пользователя из AD
 * 
 * @param array $ad_user Данные пользователя из AD
 * @return bool
 */
function create_or_update_ad_user($ad_user) {
    $users_file = __DIR__ . '/../users.json';
    $users = [];
    
    if (file_exists($users_file)) {
        $users = json_decode(file_get_contents($users_file), true);
        if (!is_array($users)) {
            $users = [];
        }
    }
    
    $username = $ad_user['username'];
    
    // Если пользователь уже существует
    if (isset($users[$username])) {
        // Обновляем только метаданные, пароль не трогаем
        $users[$username]['fullname'] = $ad_user['fullname'];
        $users[$username]['email'] = $ad_user['email'];
        $users[$username]['last_ad_login'] = time();
        
        // Если пользователь был локальным — помечаем, что теперь логинится через AD
        if (!isset($users[$username]['source']) || $users[$username]['source'] === 'local') {
            $users[$username]['source'] = 'ad_local';
        }
        
        return file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    // Новый пользователь из AD
    $users[$username] = [
        'password_hash' => '', // Нет пароля — аутентификация через AD
        'permissions' => PERM_LOGS_VIEW, // Минимальные права по умолчанию
        'fullname' => $ad_user['fullname'],
        'email' => $ad_user['email'],
        'source' => 'ad',
        'created_at' => time(),
        'last_ad_login' => time(),
        'last_login' => null
    ];
    
    return file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
?>