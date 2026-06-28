<?php
// ============================================
// ФАЙЛ: sections/devices.php
// ВЕРСИЯ: 2.5.0
// ДАТА: 2026-06-09
// @description: Секция "Устройства" с групповыми операциями
// @description: "Devices" section with bulk operations
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
    
    <!-- Форма добавления устройства -->
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
                        <?php foreach ($config['groupColors'] as $group => $data): ?>
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
    
    <!-- Список всех устройств -->
    <div class="settings-card" style="display: flex; flex-direction: column; flex: 1; min-height: 0; margin-bottom: 0;">
        <div class="settings-card-title">📋 <?= __('devices_list') ?></div>
        
        <!-- Фильтр, сортировка и массовые действия -->
        <div class="device-filter" style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 5px;">
                <span><?= __('devices_filter') ?></span>
                <select id="deviceGroupFilter" class="form-control" onchange="filterDevices()" style="width: 150px;">
                    <option value="all"><?= __('devices_all_groups') ?></option>
                    <?php foreach ($config['groupColors'] as $group => $data): ?>
                    <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <span><?= __('devices_sort') ?></span>
                <select id="deviceSort" class="form-control" onchange="sortDevices()" style="width: 160px;">
                    <option value="name"><?= __('devices_sort_name') ?></option>
                    <option value="group"><?= __('devices_sort_group') ?></option>
                    <option value="date_asc"><?= __('devices_sort_date_asc') ?></option>
                    <option value="date_desc"><?= __('devices_sort_date_desc') ?></option>
                </select>
            </div>
            
            <!-- Кнопки массовых действий -->
            <?php if ($canEditDevices): ?>
            <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                <button class="btn btn-secondary btn-small" id="selectAllBtn" onclick="toggleSelectAll()">☐ <?= __('devices_select_all') ?></button>
                <select id="massMoveGroup" class="form-control" style="width: 150px;">
                    <option value=""><?= __('devices_move_to') ?></option>
                    <?php foreach ($config['groupColors'] as $group => $data): ?>
                    <option value="<?= htmlspecialchars($group) ?>"><?= __($group) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-small" id="massMoveBtn" onclick="massMoveDevices()" disabled><?= __('devices_move_selected') ?></button>
            </div>
            <?php endif; ?>
            
            <!-- Счётчики -->
            <div style="margin-left: auto; text-align: right;">
                <div id="deviceCount" style="font-size: 12px; color: #8aa0bb;"></div>
                <div style="font-size: 11px; color: #8aa0bb; margin-top: 2px;">
                    <?= __('devices_selected') ?>: <span id="selectedCount">0</span>
                </div>
            </div>
        </div>
        
        <!-- Список устройств -->
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

.device-checkbox {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    cursor: pointer;
    margin: 0;
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

.device-date {
    font-size: 10px;
    color: #6b8ba4;
    font-family: 'JetBrains Mono', monospace;
    white-space: nowrap;
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

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-secondary:active {
    transform: scale(0.96);
}

.btn-small {
    padding: 4px 10px;
    font-size: 11px;
}
</style>

<script>
// Передаем данные из PHP в JavaScript
const allDevicesData = <?= json_encode($allDevices) ?>;
const canEditDevices = <?= $canEditDevices ? 'true' : 'false' ?>;
const groupColors = <?= json_encode($config['groupColors']) ?>;

const translations = {
    edit: '<?= __('edit') ?>',
    delete: '<?= __('delete') ?>',
    readonly: '<?= __('readonly') ?>',
    deleteConfirm: '<?= __('devices_delete_confirm') ?>',
    showing: '<?= __('showing') ?>',
    of: '<?= __('of') ?>',
    devicesEmpty: '<?= __('devices_empty') ?>',
    confirmMove: '<?= __('devices_confirm_move') ?>',
    devicesSelectAll: '<?= __('devices_select_all') ?>',
    devicesDeselectAll: '<?= __('devices_deselect_all') ?>'
};

let currentDeviceFilter = 'all';
let currentDeviceSort = 'name';
let selectedDevices = new Set();

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleString('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

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
    
    if (currentDeviceFilter !== 'all') {
        devices = devices.filter(d => d.group === currentDeviceFilter);
    }
    
    devices.sort((a, b) => {
        switch(currentDeviceSort) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'group':
                const groupCompare = a.group.localeCompare(b.group);
                if (groupCompare !== 0) return groupCompare;
                return a.name.localeCompare(b.name);
            case 'date_asc':
                return (a.added || '').localeCompare(b.added || '');
            case 'date_desc':
                return (b.added || '').localeCompare(a.added || '');
            default:
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
        updateSelectedStats();
        return;
    }
    
    let html = '';
    devices.forEach(device => {
        const groupColor = groupColors[device.group]?.color || '#888888';
        const isSelected = selectedDevices.has(device.name);
        const dateFormatted = formatDate(device.added);
        
        html += `
            <div class="device-item" data-group="${escapeHtml(device.group)}">
                ${canEditDevices ? `
                <input type="checkbox" class="device-checkbox" data-name="${escapeHtml(device.name)}" ${isSelected ? 'checked' : ''} onchange="toggleDeviceSelection('${escapeHtml(device.name).replace(/'/g, "\\'")}', this.checked)">
                ` : ''}
                <div class="device-color" style="background: ${groupColor};"></div>
                <div class="device-info">
                    <div class="device-name">${escapeHtml(device.name)}</div>
                    ${device.comment ? `<div class="device-comment">${escapeHtml(device.comment)}</div>` : ''}
                </div>
                <div class="device-meta">${escapeHtml(device.group)}</div>
                <div class="device-date">${dateFormatted}</div>
                <div class="device-actions">
                    ${canEditDevices ? `
                    <button class="edit-device-btn" onclick="window.parentEditDevice('${escapeHtml(device.name)}', '${escapeHtml(device.group)}', '${escapeHtml(device.comment || '')}')">✎</button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('${translations.deleteConfirm}');">
                        <input type="hidden" name="action" value="delete_device">
                        <input type="hidden" name="deviceName" value="${escapeHtml(device.name)}">
                        <input type="hidden" name="deviceGroup" value="${escapeHtml(device.group)}">
                        <button type="submit" class="delete-device-btn">✕</button>
                    </form>
                    ` : `
                    <span style="color: #8aa0bb; font-size: 10px;">${translations.readonly}</span>
                    `}
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    updateSelectedStats();
}

function toggleDeviceSelection(deviceName, isChecked) {
    if (isChecked) {
        selectedDevices.add(deviceName);
    } else {
        selectedDevices.delete(deviceName);
    }
    updateSelectedStats();
    updateMassMoveButton();
}

function toggleSelectAll() {
    const btn = document.getElementById('selectAllBtn');
    const checkboxes = document.querySelectorAll('.device-checkbox');
    const allChecked = Array.from(checkboxes).length > 0 && Array.from(checkboxes).every(cb => cb.checked);
    
    if (!allChecked) {
        // Выделяем все
        btn.innerHTML = '✓ ' + translations.devicesDeselectAll;
        btn.style.background = '#28a745';
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
            const deviceName = checkbox.getAttribute('data-name');
            selectedDevices.add(deviceName);
        });
    } else {
        // Снимаем выделение
        btn.innerHTML = '☐ ' + translations.devicesSelectAll;
        btn.style.background = '#6c757d';
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            const deviceName = checkbox.getAttribute('data-name');
            selectedDevices.delete(deviceName);
        });
    }
    
    updateSelectedStats();
    updateMassMoveButton();
}

function updateSelectedStats() {
    const countSpan = document.getElementById('selectedCount');
    if (countSpan) {
        countSpan.textContent = selectedDevices.size;
    }
}

function updateMassMoveButton() {
    const moveBtn = document.getElementById('massMoveBtn');
    if (moveBtn) {
        moveBtn.disabled = selectedDevices.size === 0;
    }
}

function massMoveDevices() {
    if (selectedDevices.size === 0) {
        alert('<?= __('devices_no_selected') ?>');
        return;
    }
    
    const targetGroup = document.getElementById('massMoveGroup').value;
    if (!targetGroup) {
        alert('<?= __('devices_select_group') ?>');
        return;
    }
    
    const deviceNames = Array.from(selectedDevices);
    const confirmMsg = translations.confirmMove
        .replace('{count}', deviceNames.length)
        .replace('{group}', targetGroup);
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    const fd = new FormData();
    fd.append('ajax', 'mass_move_devices');
    fd.append('device_names', JSON.stringify(deviceNames));
    fd.append('target_group', targetGroup);
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?= __('msg_save_error') ?>');
        });
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