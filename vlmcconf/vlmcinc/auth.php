<?php
// ============================================
// ФАЙЛ: vlmcinc/auth.php
// ВЕРСИЯ: 2.1.0
// ДАТА: 2026-11-18
// @description: Функции авторизации (локальная + AD)
// ============================================

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/safe_json.php';

/**
 * Проверка авторизации в панели управления
 */
function checkAuth() {
    if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
        header('Location: login.php');
        exit;
    }
    
    if (vlmcSessionExpired()) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    
    $_SESSION['vlmc_login_time'] = time();
}

/**
 * Аутентификация через Active Directory / LDAP (реализация — в ldap.php).
 *
 * @param string $username Имя пользователя (логин)
 * @param string $password Пароль
 * @return array|false Данные пользователя или false (причина — в $GLOBALS['vlmc_ldap_reason'])
 */
function authenticate_ad($username, $password) {
    global $config;
    require_once __DIR__ . '/ldap.php';
    return ldapAuthenticate((string)$username, (string)$password, is_array($config ?? null) ? $config : null);
}

/**
 * Создание или обновление доменного пользователя в списке пользователей панели.
 * Локального пользователя с тем же именем доменная учётная запись не перехватывает.
 *
 * @param array $ad_user Данные пользователя из каталога
 * @return bool
 */
function create_or_update_ad_user($ad_user) {
    global $config;
    require_once __DIR__ . '/ldap.php';
    $s = ldapSettings(is_array($config ?? null) ? $config : null);
    return ldapUpsertUser($ad_user, $s['auto_create'], $s['default_permissions']) !== null;
}
