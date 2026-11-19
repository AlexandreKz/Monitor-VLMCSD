<?php
// ============================================
// ФАЙЛ: vlmcinc/notify_events.php
// ВЕРСИЯ: 1.2.0
// ДАТА: 2026-11-19
// @description: Каталог всех событий уведомлений (пункт 27): единый справочник — группа, важность, область (группа/устройство), параметры-пороги, признак «скоро» (нужна фоновая проверка)
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'notify_events.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Каталог событий. Названия: локаль notify_ev_<id>, группы: notify_grp_<group>.
 *   group  — раздел в окне правила (security | panel | updates | devices | service | integrations)
 *   sev    — важность: high | mid | low (для оформления и сортировки)
 *   scope  — к чему привязано событие: none | group | device (фильтр «группа/устройство» в правиле)
 *   params — настраиваемые пороги: имя => [по умолчанию, минимум, максимум]
 *   soon   — true: событие появится позже, в правило пока не выбирается (сейчас таких нет)
 *   События группы devices и service (кроме задач) проверяет фоновая задача notify_watch (notify_watch.php)
 */
function notifyCatalog() {
    static $cat = null;
    if ($cat !== null) {
        return $cat;
    }
    $cat = [
        // Безопасность и пользователи
        'failed_logins'    => ['group' => 'security', 'sev' => 'high', 'scope' => 'none', 'params' => ['fail_count' => [5, 2, 100], 'fail_minutes' => [10, 1, 1440]]],
        'access_denied'    => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'user_added'       => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'user_deleted'     => ['group' => 'security', 'sev' => 'high', 'scope' => 'none'],
        'user_updated'     => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'api_key_created'  => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'api_key_deleted'  => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'panel_access_changed' => ['group' => 'security', 'sev' => 'high', 'scope' => 'none'],
        'password_changed' => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        'login_success' => ['group' => 'security', 'sev' => 'low', 'scope' => 'none'],
        'firewall_changed' => ['group' => 'security', 'sev' => 'high', 'scope' => 'none'],
        'whitelist_changed' => ['group' => 'security', 'sev' => 'mid', 'scope' => 'none'],
        'settings_changed' => ['group' => 'security', 'sev' => 'mid', 'scope' => 'none'],
        // Изменения в панели
        'device_added'     => ['group' => 'panel', 'sev' => 'low',  'scope' => 'device'],
        'devices_moved'    => ['group' => 'panel', 'sev' => 'low',  'scope' => 'group'],
        'group_renamed'    => ['group' => 'panel', 'sev' => 'low',  'scope' => 'group'],
        'group_deleted'    => ['group' => 'panel', 'sev' => 'mid',  'scope' => 'group'],
        'device_deleted'   => ['group' => 'panel', 'sev' => 'mid',  'scope' => 'device'],
        'config_reset'     => ['group' => 'panel', 'sev' => 'high', 'scope' => 'none'],
        'group_created' => ['group' => 'panel', 'sev' => 'low', 'scope' => 'group'],
        'device_edited' => ['group' => 'panel', 'sev' => 'low', 'scope' => 'device'],
        'log_path_changed' => ['group' => 'panel', 'sev' => 'mid', 'scope' => 'none'],
        'project_exported' => ['group' => 'panel', 'sev' => 'high', 'scope' => 'none'],
        'log_cleared'      => ['group' => 'panel', 'sev' => 'mid',  'scope' => 'none'],
        'audit_cleared'    => ['group' => 'panel', 'sev' => 'high', 'scope' => 'none'],
        // Обновления
        'update_available' => ['group' => 'updates', 'sev' => 'low',  'scope' => 'none'],
        'update_installed' => ['group' => 'updates', 'sev' => 'low',  'scope' => 'none'],
        'update_failed'    => ['group' => 'updates', 'sev' => 'mid',  'scope' => 'none'],
        // Устройства (фоновая проверка)
        'new_device'       => ['group' => 'devices', 'sev' => 'low',  'scope' => 'device'],
        'device_inactive'  => ['group' => 'devices', 'sev' => 'low',  'scope' => 'device', 'params' => ['days' => [7, 1, 365]]],
        'multi_ip_device'  => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'device', 'params' => ['ip_count' => [4, 2, 100]]],
        'multi_device_ip'  => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'none', 'params' => ['device_count' => [5, 2, 1000]]],
        'new_country'      => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'none'],
        // Служба и система (фоновая проверка)
        'service_stopped'  => ['group' => 'service', 'sev' => 'high', 'scope' => 'none'],
        'service_restored' => ['group' => 'service', 'sev' => 'low',  'scope' => 'none'],
        'log_stale'        => ['group' => 'service', 'sev' => 'mid',  'scope' => 'none', 'params' => ['minutes' => [60, 5, 10080]]],
        'log_big'          => ['group' => 'service', 'sev' => 'low',  'scope' => 'none', 'params' => ['megabytes' => [500, 10, 100000]]],
        'disk_low'         => ['group' => 'service', 'sev' => 'mid',  'scope' => 'none', 'params' => ['free_mb' => [200, 10, 1000000]]],
        'task_failed'      => ['group' => 'service', 'sev' => 'mid',  'scope' => 'none'],
        // Интеграции
        'ldap_down'           => ['group' => 'integrations', 'sev' => 'high', 'scope' => 'none'],
        'ldap_restored'       => ['group' => 'integrations', 'sev' => 'low',  'scope' => 'none'],
        'ldap_account_failed' => ['group' => 'integrations', 'sev' => 'mid',  'scope' => 'none'],
        'integration_changed' => ['group' => 'integrations', 'sev' => 'mid', 'scope' => 'none'],
        'ldap_settings_changed' => ['group' => 'integrations', 'sev' => 'mid', 'scope' => 'none'],
    ];
    return $cat;
}

/** Порядок групп в окне правила. */
function notifyEventGroups() {
    return ['security', 'panel', 'updates', 'devices', 'service', 'integrations'];
}
