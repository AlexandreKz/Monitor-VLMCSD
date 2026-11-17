<?php
// ============================================
// ФАЙЛ: vlmcinc/notify_events.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Каталог всех событий уведомлений (пункт 27): единый справочник — группа, важность, область (группа/устройство), параметры-пороги, признак «скоро» (нужна фоновая проверка)
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'notify_events.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Каталог событий. Названия: локаль notify_ev_<id>, группы: notify_grp_<group>.
 *   group  — раздел в окне правила (security | panel | updates | devices | service)
 *   sev    — важность: high | mid | low (для оформления и сортировки)
 *   scope  — к чему привязано событие: none | group | device (фильтр «группа/устройство» в правиле)
 *   params — настраиваемые пороги: имя => [по умолчанию, минимум, максимум]
 *   soon   — true: событие появится позже (нужна фоновая проверка по расписанию), в правило пока не выбирается
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
        'password_changed' => ['group' => 'security', 'sev' => 'mid',  'scope' => 'none'],
        // Изменения в панели
        'device_added'     => ['group' => 'panel', 'sev' => 'low',  'scope' => 'device'],
        'devices_moved'    => ['group' => 'panel', 'sev' => 'low',  'scope' => 'group'],
        'group_renamed'    => ['group' => 'panel', 'sev' => 'low',  'scope' => 'group'],
        'group_deleted'    => ['group' => 'panel', 'sev' => 'mid',  'scope' => 'group'],
        'log_cleared'      => ['group' => 'panel', 'sev' => 'mid',  'scope' => 'none'],
        'audit_cleared'    => ['group' => 'panel', 'sev' => 'high', 'scope' => 'none'],
        // Обновления
        'update_available' => ['group' => 'updates', 'sev' => 'low',  'scope' => 'none'],
        'update_installed' => ['group' => 'updates', 'sev' => 'low',  'scope' => 'none'],
        'update_failed'    => ['group' => 'updates', 'sev' => 'mid',  'scope' => 'none'],
        // Устройства (фоновая проверка)
        'new_device'       => ['group' => 'devices', 'sev' => 'low',  'scope' => 'device', 'soon' => true],
        'device_inactive'  => ['group' => 'devices', 'sev' => 'low',  'scope' => 'device', 'soon' => true, 'params' => ['days' => [7, 1, 365]]],
        'multi_ip_device'  => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'device', 'soon' => true, 'params' => ['ip_count' => [4, 2, 100]]],
        'multi_device_ip'  => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'none',   'soon' => true, 'params' => ['device_count' => [5, 2, 1000]]],
        'new_country'      => ['group' => 'devices', 'sev' => 'mid',  'scope' => 'device', 'soon' => true],
        // Служба и система (фоновая проверка)
        'service_stopped'  => ['group' => 'service', 'sev' => 'high', 'scope' => 'none', 'soon' => true],
        'log_stale'        => ['group' => 'service', 'sev' => 'mid',  'scope' => 'none', 'soon' => true, 'params' => ['minutes' => [60, 5, 10080]]],
        'log_big'          => ['group' => 'service', 'sev' => 'low',  'scope' => 'none', 'soon' => true, 'params' => ['megabytes' => [500, 10, 100000]]],
    ];
    return $cat;
}

/** Порядок групп в окне правила. */
function notifyEventGroups() {
    return ['security', 'panel', 'updates', 'devices', 'service'];
}
