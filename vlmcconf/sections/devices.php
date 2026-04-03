<?php
// ============================================
// ФАЙЛ: sections/devices.php
// ВЕРСИЯ: 1.9.1
// ДАТА: 2026-03-28
// @description: Секция "Устройства" с исправленной прокруткой
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'devices.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права
$canViewDevices = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_DEVICES_VIEW);
$canEditDevices = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_DEVICES_EDIT);

// Если нет прав на просмотр — не показываем секцию
if (!$canViewDevices) {
    echo '<div class="settings-section" id="section-devices"><div class="section-title"><span>📱 ' . __('devices_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-devices" class="settings-section <?= $activeSection === 'devices' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📱 <?= __('devices_title') ?></span>
        <?php if ($activeSection === 'devices' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Форма добавления устройства (только если есть права на редактирование) -->
    <?php if ($canEditDevices): ?>
    <div class="settings-card" style="margin-bottom: 15px;">
        <div class="settings-card-title">➕ <?= __('devices_add') ?></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_device">
            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_name') ?></label>
                    <input type="text" name="deviceName" class="form-control" placeholder="<?= __('devices_name_placeholder') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_group') ?></label>
                    <select name="deviceGroup" class="form-control">
                        <?php foreach ($config['groupColors'] as $group => $color): ?>
                        <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label><?= __('label_device_comment') ?></label>
                    <input type="text" name="deviceComment" class="form-control" placeholder="<?= __('devices_comment') ?>">
                </div>
                <button type="submit" class="btn btn-primary">➕ <?= __('add') ?></button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Список всех устройств - исправленная прокрутка -->
    <div class="settings-card" style="display: flex; flex-direction: column; height: calc(100% - 70px); margin-bottom: 0;">
        <div class="settings-card-title">📋 <?= __('devices_list') ?></div>
        
        <!-- Фильтр и сортировка -->
        <div class="device-filter" style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 5px;">
                <span><?= __('devices_filter') ?></span>
                <select id="deviceGroupFilter" class="form-control" onchange="filterDevices()" style="width: 150px;">
                    <option value="all"><?= __('devices_all_groups') ?></option>
                    <?php foreach ($config['groupColors'] as $group => $color): ?>
                    <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <span><?= __('devices_sort') ?></span>
                <select id="deviceSort" class="form-control" onchange="sortDevices()" style="width: 130px;">
                    <option value="name"><?= __('devices_sort_name') ?></option>
                    <option value="group"><?= __('devices_sort_group') ?></option>
                </select>
            </div>
            <span id="deviceCount" style="margin-left: auto; font-size: 12px; color: #8aa0bb; flex-shrink: 0;"></span>
        </div>
        
        <!-- Только список устройств прокручивается -->
        <div class="devices-list" id="devicesList" style="flex: 1; overflow-y: auto; min-height: 0; padding-right: 5px;"></div>
    </div>
</div>

<style>
/* Стили для списка устройств */
.devices-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.device-item {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 6px;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
    min-height: 36px;
}

.device-item:hover {
    box-shadow: <?= $themeCSS['shadow'] ?>;
    border-color: <?= $themeCSS['primary'] ?>;
}

.device-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    flex-shrink: 0;
}

.device-info {
    flex: 2;
    line-height: 1.2;
}

.device-name {
    font-weight: 600;
    font-size: 12px;
}

.device-comment {
    font-size: 10px;
    color: #8aa0bb;
    margin-top: 2px;
}

.device-meta {
    flex: 1;
    font-size: 11px;
    color: #8aa0bb;
}

.device-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.edit-device-btn, .delete-device-btn {
    padding: 3px 6px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.2s;
    min-width: 24px;
}

.edit-device-btn {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
}

.delete-device-btn {
    background: <?= $themeCSS['danger'] ?>;
    color: white;
}
</style>

<script>
// Передаем данные из PHP в JavaScript
const allDevicesData = <?= json_encode($allDevices) ?>;
const canEditDevices = <?= $canEditDevices ? 'true' : 'false' ?>;
const translations = {
    edit: '<?= __('edit') ?>',
    delete: '<?= __('delete') ?>',
    readonly: '<?= __('readonly') ?>',
    deleteConfirm: '<?= __('devices_delete_confirm') ?>',
    showing: '<?= __('showing') ?>',
    of: '<?= __('of') ?>',
    devicesEmpty: '<?= __('devices_empty') ?>'
};

let currentDeviceFilter = 'all';
let currentDeviceSort = 'name';

function filterDevices() {
    currentDeviceFilter = document.getElementById('deviceGroupFilter').value;
    renderDevicesList();
}

function sortDevices() {
    currentDeviceSort = document.getElementById('deviceSort').value;
    renderDevicesList();
}

function getSortedDevices() {
    let devices = [...allDevicesData];
    
    // Фильтрация
    if (currentDeviceFilter !== 'all') {
        devices = devices.filter(d => d.group === currentDeviceFilter);
    }
    
    // Сортировка
    devices.sort((a, b) => {
        if (currentDeviceSort === 'name') {
            return a.name.localeCompare(b.name);
        } else {
            // Сначала по группе, затем по имени
            const groupCompare = a.group.localeCompare(b.group);
            if (groupCompare !== 0) return groupCompare;
            return a.name.localeCompare(b.name);
        }
    });
    
    return devices;
}

function renderDevicesList() {
    const devices = getSortedDevices();
    const container = document.getElementById('devicesList');
    const countSpan = document.getElementById('deviceCount');
    
    countSpan.textContent = `${translations.showing} ${devices.length} ${translations.of} ${allDevicesData.length}`;
    
    if (devices.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 30px; color: #8aa0bb;">${translations.devicesEmpty}</div>`;
        return;
    }
    
    let html = '';
    devices.forEach(device => {
        html += `
            <div class="device-item" data-group="${escapeHtml(device.group)}">
                <div class="device-color" style="background: ${escapeHtml(device.color)};"></div>
                <div class="device-info">
                    <div class="device-name">${escapeHtml(device.name)}</div>
                    ${device.comment ? `<div class="device-comment">${escapeHtml(device.comment)}</div>` : ''}
                </div>
                <div class="device-meta">${escapeHtml(device.group)}</div>
                <div class="device-actions">
                    ${canEditDevices ? `
                    <button class="edit-device-btn" onclick="window.parentEditDevice('${escapeHtml(device.name)}', '${escapeHtml(device.group)}', '${escapeHtml(device.comment || '')}')">✎ ${translations.edit}</button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('${translations.deleteConfirm}');">
                        <input type="hidden" name="action" value="delete_device">
                        <input type="hidden" name="deviceName" value="${escapeHtml(device.name)}">
                        <input type="hidden" name="deviceGroup" value="${escapeHtml(device.group)}">
                        <button type="submit" class="delete-device-btn">✕ ${translations.delete}</button>
                    </form>
                    ` : `
                    <span style="color: #8aa0bb; font-size: 10px;">${translations.readonly}</span>
                    `}
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    renderDevicesList();
});
</script>