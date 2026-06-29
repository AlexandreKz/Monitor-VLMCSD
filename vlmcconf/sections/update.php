<?php
// ============================================
// ФАЙЛ: sections/update.php
// ВЕРСИЯ: 3.2.0
// ДАТА: 2026-06-30
// @description: Секция обновления проекта (интерфейс + вызовы Updater через ajax.php)
// @description: Project update section (interface + Updater calls via ajax.php)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

// Загружаем текущие настройки обновлений
$updateConfig = $config['update'] ?? [
    'type' => 'release',
    'mode' => 'manual',
    'schedule' => [
        'enabled' => false,
        'time' => '03:00',
        'days' => [1, 2, 3, 4, 5, 6, 7]
    ],
    'notifications' => [
        'enabled' => false,
        'email' => '',
        'telegram' => ''
    ],
    'last_check' => null,
    'last_update' => null,
    'version' => CONFIG_VERSION
];
?>

<div id="toolsTabUpdate" class="tools-tab-content <?= $activeTab === 'update' ? 'active' : '' ?>">
    
    <!-- ============================================ -->
    <!-- ЗАГОЛОВОК С ЭЛЕМЕНТАМИ УПРАВЛЕНИЯ / HEADER   -->
    <!-- ============================================ -->
    <div class="update-header" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap;">
        <div style="width: 200px;">
            <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_service') ?></label>
            <select id="updateService" class="form-control" style="width: 100%;">
                <option value="github">GitHub</option>
                <option value="custom"><?= __('update_custom_server') ?></option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">&nbsp;</label>
            <button class="btn btn-primary" id="checkUpdatesBtn">🔍 <?= __('update_check_btn') ?></button>
        </div>
        <div>
            <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">&nbsp;</label>
            <button class="btn btn-warning" id="runUpdateBtn" disabled>📥 <?= __('update_run_btn') ?></button>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ ОБНОВЛЕНИЙ / UPDATE SETTINGS      -->
    <!-- ============================================ -->
    <div id="updateSettingsBlock" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
        <div style="display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <!-- Левая часть: Тип и Режим (выпадающие списки) -->
            <div style="display: flex; gap: 25px; flex-wrap: wrap;">
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_type_label') ?></label>
                    <select id="updateType" class="form-control" style="width: 180px;">
                        <option value="release" <?= ($updateConfig['type'] ?? 'release') === 'release' ? 'selected' : '' ?>>
                            <?= __('update_type_release') ?>
                        </option>
                        <option value="early" <?= ($updateConfig['type'] ?? 'release') === 'early' ? 'selected' : '' ?>>
                            <?= __('update_type_early') ?>
                        </option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_mode_label') ?></label>
                    <select id="updateMode" class="form-control" style="width: 150px;">
                        <option value="manual" <?= ($updateConfig['mode'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>
                            <?= __('update_mode_manual') ?>
                        </option>
                        <option value="auto" <?= ($updateConfig['mode'] ?? 'manual') === 'auto' ? 'selected' : '' ?>>
                            <?= __('update_mode_auto') ?>
                        </option>
                    </select>
                </div>
            </div>
            
            <!-- Правая часть: Кнопка Сохранить (уменьшена на 20%) -->
            <button class="btn btn-primary" onclick="saveUpdateSettings()" 
                    style="padding: 6px 14px; font-size: 11px; flex-shrink: 0;">
                💾 <?= __('save') ?>
            </button>
        </div>
        
        <!-- Автоматическое расписание (показывается только при режиме "Автоматическое") -->
        <div id="autoSettings" style="display: <?= ($updateConfig['mode'] ?? 'manual') === 'auto' ? 'block' : 'none' ?>; margin-top: 12px; padding-top: 12px; border-top: 1px dashed <?= $themeCSS['border'] ?>;">
            <div style="display: flex; gap: 25px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">
                        <input type="checkbox" id="scheduleEnabled" <?= ($updateConfig['schedule']['enabled'] ?? false) ? 'checked' : '' ?>> 
                        <?= __('update_schedule_enable') ?>
                    </label>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_schedule_time') ?></label>
                    <input type="time" id="scheduleTime" class="form-control" value="<?= $updateConfig['schedule']['time'] ?? '03:00' ?>" style="width: 140px;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_schedule_days') ?></label>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        <?php
                        $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                        $selectedDays = $updateConfig['schedule']['days'] ?? [1, 2, 3, 4, 5, 6, 7];
                        foreach ($days as $i => $day) {
                            $dayNum = $i + 1;
                            $checked = in_array($dayNum, $selectedDays) ? 'checked' : '';
                            echo "<label style='font-size: 11px; cursor: pointer;'><input type='checkbox' class='schedule-day' value='{$dayNum}' {$checked}> {$day}</label>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="settingsMessage" class="block-message" style="display: none; margin-top: 10px;"></div>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ GITHUB / GITHUB SETTINGS           -->
    <!-- ============================================ -->
    <div id="updateGithubSettings" class="update-info-panel" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px;">
        <div style="display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
            <div><span style="color: #8aa0bb;">📦 <?= __('update_repository') ?>:</span> <code style="font-size: 11px;">AlexandreKz/Monitor-VLMCSD</code></div>
            <div><span style="color: #8aa0bb;">🌿 <?= __('update_branch') ?>:</span> <code style="font-size: 11px;">main</code></div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ КАСТОМНОГО СЕРВЕРА / CUSTOM SERVER -->
    <!-- ============================================ -->
    <div id="updateCustomSettings" style="display: none; background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">🌐 <?= __('update_server_url') ?></label><input type="text" id="updateServerUrl" class="form-control" placeholder="https://updates.mycompany.com"></div>
            <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">📄 <?= __('update_version_path') ?></label><input type="text" id="updateVersionPath" class="form-control" value="/api/version.json"></div>
            <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">🔍 <?= __('update_check_mode') ?></label><select id="updateCheckMode" class="form-control"><option value="version"><?= __('update_mode_version') ?></option><option value="files"><?= __('update_mode_files') ?></option></select></div>
            <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">🔐 <?= __('update_auth_type') ?></label><select id="updateAuthType" class="form-control"><option value="none"><?= __('update_auth_none') ?></option><option value="basic">Basic Auth</option><option value="bearer">Bearer Token</option></select></div>
        </div>
        <div id="updateAuthBasic" style="display: none; margin-top: 12px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">👤 <?= __('update_username') ?></label><input type="text" id="updateUsername" class="form-control"></div>
                <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">🔑 <?= __('update_password') ?></label><input type="password" id="updatePassword" class="form-control" autocomplete="off"></div>
            </div>
        </div>
        <div id="updateAuthBearer" style="display: none; margin-top: 12px;">
            <div><label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;">🔑 <?= __('update_token') ?></label><input type="text" id="updateToken" class="form-control"></div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- СТАТУС-БАР / STATUS BAR                      -->
    <!-- ============================================ -->
    <div class="update-status-bar" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px; display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
        <div><span style="color: #8aa0bb;">📌 <?= __('update_current_version') ?>:</span> <strong><span id="currentVersion"><?= CONFIG_VERSION ?></span></strong></div>
        <div><span style="color: #8aa0bb;">🆕 <?= __('update_latest_version') ?>:</span> <strong><span id="latestVersion">—</span></strong></div>
        <div><span style="color: #8aa0bb;">📊 <?= __('update_status') ?>:</span> <span id="updateStatusText"><?= __('update_not_checked') ?></span></div>
    </div>
    
    <!-- ============================================ -->
    <!-- КОНСОЛЬ ВЫВОДА / OUTPUT CONSOLE             -->
    <!-- ============================================ -->
    <div class="update-console" id="updateConsole" style="background: #121212; color: #00ff00; font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 10px 12px; border-radius: 6px; height: 250px; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>;">
        <div>> <?= __('update_ready') ?></div>
    </div>
    
    <!-- ============================================ -->
    <!-- КНОПКА ПРОВЕРКИ GITHUB / GITHUB CHECK       -->
    <!-- ============================================ -->
    <div style="margin-top: 15px; text-align: right;">
        <button class="btn btn-secondary btn-small" id="checkGitHubBtn" style="opacity: 0.6; font-size: 10px; padding: 2px 8px;">🌐 <?= __('update_check_github') ?></button>
    </div>
    
    <!-- ============================================ -->
    <!-- НАСТРОЙКИ УВЕДОМЛЕНИЙ / NOTIFICATIONS       -->
    <!-- ============================================ -->
    <div class="update-settings" style="margin-top: 20px; border-top: 1px solid <?= $themeCSS['border'] ?>; padding-top: 15px;">
        <h4 style="margin-bottom: 10px; font-size: 14px; color: <?= $themeCSS['primary'] ?>;">🔔 <?= __('update_notifications_label') ?></h4>
        
        <div class="form-group">
            <label style="display: block; font-size: 12px; color: #8aa0bb; margin-bottom: 4px;">
                <input type="checkbox" id="notifyEnabled" <?= ($updateConfig['notifications']['enabled'] ?? false) ? 'checked' : '' ?>> 
                <?= __('update_notify_enable') ?>
            </label>
        </div>
        
        <div id="notifySettings" style="display: <?= ($updateConfig['notifications']['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="form-group" style="margin-top: 8px;">
                <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_notify_email') ?></label>
                <input type="email" id="notifyEmail" class="form-control" value="<?= htmlspecialchars($updateConfig['notifications']['email'] ?? '') ?>" placeholder="admin@example.com" disabled>
            </div>
            <div class="form-group">
                <label style="display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px;"><?= __('update_notify_telegram') ?></label>
                <input type="text" id="notifyTelegram" class="form-control" value="<?= htmlspecialchars($updateConfig['notifications']['telegram'] ?? '') ?>" placeholder="token:chat_id" disabled>
            </div>
        </div>
    </div>
    
</div>

<!-- ============================================ -->
<!-- МОДАЛЬНОЕ ОКНО ПОДТВЕРЖДЕНИЯ ОБНОВЛЕНИЯ      -->
<!-- ============================================ -->
<div id="updateConfirmModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header">
            <h2>⚠️ <?= __('update_confirm_title') ?></h2>
            <span class="tools-modal-close" onclick="closeUpdateConfirmModal()">&times;</span>
        </div>
        <div class="tools-modal-body">
            <p><?= __('update_confirm_warning') ?></p>
            <p class="tools-modal-estimate"><?= __('update_confirm_desc') ?></p>
            <div id="updateFilesList" style="margin-top: 10px; max-height: 200px; overflow-y: auto; font-size: 11px;"></div>
        </div>
        <div class="tools-modal-footer">
            <button class="btn btn-secondary" onclick="closeUpdateConfirmModal()">❌ <?= __('cancel') ?></button>
            <button class="btn btn-primary" id="startUpdateBtn" disabled>✅ <?= __('update_confirm_yes') ?></button>
        </div>
    </div>
</div>

<style>
.update-console::-webkit-scrollbar { width: 6px; }
.update-console::-webkit-scrollbar-track { background: #2a2a2a; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb { background: #00ff00; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb:hover { background: #00cc00; }

.update-settings .form-group { margin-bottom: 8px; }
.update-settings .form-control { padding: 6px 10px; font-size: 12px; }
.block-message { padding: 8px 12px; border-radius: 6px; font-size: 12px; }
.block-message.success { background: <?= $themeCSS['success'] ?>20; color: <?= $themeCSS['success'] ?>; border: 1px solid <?= $themeCSS['success'] ?>; }
.block-message.error { background: <?= $themeCSS['danger'] ?>20; color: <?= $themeCSS['danger'] ?>; border: 1px solid <?= $themeCSS['danger'] ?>; }
.block-message.info { background: <?= $themeCSS['primary'] ?>20; color: <?= $themeCSS['primary'] ?>; border: 1px solid <?= $themeCSS['primary'] ?>; }
</style>

<script>
// ============================================
// ПЕРЕМЕННЫЕ / VARIABLES
// ============================================
let updateConsoleLines = [];
let updateFilesList = [];

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ / HELPER FUNCTIONS
// ============================================

function extractVersionFromTag(tagName) {
    let version = tagName.replace(/^v/, '');
    const match = version.match(/(\d+\.\d+\.\d+)/);
    if (match) return match[1];
    return version;
}

function escapeHtml(text) { 
    const div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

function consoleLog(message, type = 'info') {
    const consoleDiv = document.getElementById('updateConsole');
    if (!consoleDiv) return;
    const timestamp = new Date().toLocaleTimeString();
    let color = '#00ff00';
    if (type === 'error') color = '#ff4444';
    if (type === 'warning') color = '#ffaa00';
    if (type === 'success') color = '#00ff00';
    if (type === 'info') color = '#00aaff';
    const line = `<div style="color: ${color}; margin-bottom: 2px; font-family: 'JetBrains Mono', monospace; font-size: 11px;">[${timestamp}] ${escapeHtml(message)}</div>`;
    consoleDiv.insertAdjacentHTML('beforeend', line);
    consoleDiv.scrollTop = consoleDiv.scrollHeight;
}

// ============================================
// УПРАВЛЕНИЕ НАСТРОЙКАМИ / SETTINGS MANAGEMENT
// ============================================

function toggleUpdateService() {
    const service = document.getElementById('updateService');
    if (!service) return;
    const githubSettings = document.getElementById('updateGithubSettings');
    const customSettings = document.getElementById('updateCustomSettings');
    const settingsBlock = document.getElementById('updateSettingsBlock');
    
    if (service.value === 'github') {
        if (githubSettings) githubSettings.style.display = 'block';
        if (customSettings) customSettings.style.display = 'none';
        if (settingsBlock) settingsBlock.style.display = 'block';
    } else {
        if (githubSettings) githubSettings.style.display = 'none';
        if (customSettings) customSettings.style.display = 'block';
        if (settingsBlock) settingsBlock.style.display = 'none';
    }
}

function toggleUpdateAuth() {
    const authType = document.getElementById('updateAuthType');
    const authBasic = document.getElementById('updateAuthBasic');
    const authBearer = document.getElementById('updateAuthBearer');
    if (authBasic) authBasic.style.display = 'none';
    if (authBearer) authBearer.style.display = 'none';
    if (authType && authType.value === 'basic' && authBasic) authBasic.style.display = 'block';
    else if (authType && authType.value === 'bearer' && authBearer) authBearer.style.display = 'block';
}

function toggleAutoSettings() {
    const mode = document.getElementById('updateMode');
    const autoSettings = document.getElementById('autoSettings');
    if (mode && autoSettings) {
        autoSettings.style.display = mode.value === 'auto' ? 'block' : 'none';
    }
}

function toggleNotifySettings() {
    const enabled = document.getElementById('notifyEnabled');
    const settings = document.getElementById('notifySettings');
    if (enabled && settings) {
        settings.style.display = enabled.checked ? 'block' : 'none';
    }
}

function closeUpdateConfirmModal() { 
    const modal = document.getElementById('updateConfirmModal'); 
    if (modal) modal.style.display = 'none'; 
}

function showSettingsMessage(message, type = 'success') {
    const msgDiv = document.getElementById('settingsMessage');
    if (!msgDiv) return;
    msgDiv.className = 'block-message ' + type;
    msgDiv.textContent = message;
    msgDiv.style.display = 'block';
    setTimeout(() => {
        msgDiv.style.display = 'none';
    }, 5000);
}

function saveUpdateSettings() {
    const type = document.getElementById('updateType').value;
    const mode = document.getElementById('updateMode').value;
    const notifyEnabled = document.getElementById('notifyEnabled').checked;
    const scheduleEnabled = document.getElementById('scheduleEnabled').checked;
    const scheduleTime = document.getElementById('scheduleTime').value;
    
    const scheduleDays = [];
    document.querySelectorAll('.schedule-day:checked').forEach(cb => {
        scheduleDays.push(parseInt(cb.value));
    });
    
    const fd = new FormData();
    fd.append('ajax', 'save_update_settings');
    fd.append('type', type);
    fd.append('mode', mode);
    fd.append('notify_enabled', notifyEnabled ? 'true' : 'false');
    fd.append('schedule_enabled', scheduleEnabled ? 'true' : 'false');
    fd.append('schedule_time', scheduleTime);
    fd.append('schedule_days', JSON.stringify(scheduleDays));
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSettingsMessage(data.message || '<?= __('settings_saved') ?>', 'success');
                toggleAutoSettings();
                toggleNotifySettings();
            } else {
                showSettingsMessage(data.message || '<?= __('msg_save_error') ?>', 'error');
            }
        })
        .catch(error => {
            showSettingsMessage('<?= __('msg_save_error') ?>: ' + error.message, 'error');
        });
}

// ============================================
// ОСНОВНЫЕ ФУНКЦИИ / MAIN FUNCTIONS
// ============================================

function checkForUpdates() {
    const checkBtn = document.getElementById('checkUpdatesBtn');
    const statusSpan = document.getElementById('updateStatusText');
    const latestSpan = document.getElementById('latestVersion');
    const runBtn = document.getElementById('runUpdateBtn');
    const currentVersion = '<?= CONFIG_VERSION ?>';
    
    consoleLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
    consoleLog('🔍 ' + '<?= __('update_checking') ?>...', 'info');
    consoleLog('📌 ' + '<?= __('update_current_version') ?>: ' + currentVersion, 'info');
    
    if (checkBtn) { checkBtn.disabled = true; checkBtn.innerHTML = '⏳ <?= __('loading') ?>'; }
    if (statusSpan) statusSpan.innerHTML = '<?= __('update_checking') ?>';
    
    const fd = new FormData();
    fd.append('ajax', 'check_updates');
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let latestVersion = data.latest_version;
                latestVersion = extractVersionFromTag(latestVersion);
                if (latestSpan) latestSpan.innerHTML = latestVersion;
                const updateAvailable = latestVersion !== currentVersion && latestVersion !== '—' && latestVersion !== '0.0.0';
                if (updateAvailable) {
                    if (statusSpan) statusSpan.innerHTML = '✅ <?= __('update_available') ?>';
                    if (runBtn) runBtn.disabled = false;
                    consoleLog('✅ ' + '<?= __('update_available') ?>: ' + latestVersion, 'success');
                } else {
                    if (statusSpan) statusSpan.innerHTML = '✅ <?= __('update_not_available') ?>';
                    if (runBtn) runBtn.disabled = true;
                    consoleLog('✅ ' + '<?= __('update_not_available') ?>', 'success');
                }
            } else {
                if (statusSpan) statusSpan.innerHTML = '❌ <?= __('update_error') ?>';
                consoleLog('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            if (statusSpan) statusSpan.innerHTML = '❌ <?= __('update_error') ?>';
            consoleLog('❌ Connection error: ' + error.message, 'error');
        })
        .finally(() => {
            if (checkBtn) { checkBtn.disabled = false; checkBtn.innerHTML = '🔍 <?= __('update_check_btn') ?>'; }
            consoleLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
        });
}

function checkGitHubAccess() {
    const currentVersion = '<?= CONFIG_VERSION ?>';
    consoleLog('🔍 ' + '<?= __('update_checking_github') ?>...', 'info');
    const fd = new FormData();
    fd.append('ajax', 'check_github_access');
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let latestVersion = data.latest_version;
                latestVersion = extractVersionFromTag(latestVersion);
                consoleLog('✅ ' + data.message, 'success');
                consoleLog('🏷️ ' + '<?= __('update_latest_version') ?>: ' + latestVersion, 'info');
                if (document.getElementById('latestVersion')) document.getElementById('latestVersion').innerHTML = latestVersion;
                const updateAvailable = latestVersion !== currentVersion && latestVersion !== '—' && latestVersion !== '0.0.0';
                if (updateAvailable) {
                    document.getElementById('updateStatusText').innerHTML = '✅ <?= __('update_available') ?>';
                    document.getElementById('runUpdateBtn').disabled = false;
                } else {
                    document.getElementById('updateStatusText').innerHTML = '✅ <?= __('update_not_available') ?>';
                }
            } else { consoleLog('❌ ' + data.message, 'error'); }
        })
        .catch(error => { consoleLog('❌ Connection error: ' + error.message, 'error'); });
}

function runUpdate() {
    const modal = document.getElementById('updateConfirmModal');
    const filesList = document.getElementById('updateFilesList');
    const startBtn = document.getElementById('startUpdateBtn');
    const latestVersion = document.getElementById('latestVersion')?.textContent || '';
    
    if (!modal) return;
    
    modal.style.display = 'flex';
    if (filesList) {
        filesList.innerHTML = '<div style="text-align: center; color: #8aa0bb;">⏳ <?= __('loading') ?>...</div>';
    }
    if (startBtn) {
        startBtn.disabled = true;
        startBtn.textContent = '⏳ <?= __('loading') ?>';
    }
    
    const fd = new FormData();
    fd.append('ajax', 'get_update_files');
    fd.append('version', latestVersion);
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            setTimeout(function() {
                if (data.success && data.files && data.files.length > 0) {
                    updateFilesList = data.files;
                    let html = '<div style="font-size: 11px; margin-bottom: 8px; color: #8aa0bb;">📦 ' + data.files.length + ' файлов для обновления:</div>';
                    data.files.forEach(file => {
                        html += '<div style="padding: 2px 0; border-bottom: 1px dashed <?= $themeCSS['border'] ?>; font-size: 11px;">📄 ' + escapeHtml(file) + '</div>';
                    });
                    if (filesList) filesList.innerHTML = html;
                    if (startBtn) {
                        startBtn.disabled = false;
                        startBtn.textContent = '✅ <?= __('update_confirm_yes') ?>';
                    }
                } else {
                    if (filesList) filesList.innerHTML = '<div style="text-align: center; color: #8aa0bb;"><?= __('update_no_files') ?></div>';
                    if (startBtn) {
                        startBtn.disabled = true;
                        startBtn.textContent = '❌ <?= __('update_no_files') ?>';
                    }
                }
            }, 50);
        })
        .catch(error => {
            console.error('Fetch error:', error);
            setTimeout(function() {
                if (filesList) filesList.innerHTML = '<div style="text-align: center; color: #e74c3c;">❌ <?= __('error_loading') ?></div>';
                if (startBtn) {
                    startBtn.disabled = true;
                    startBtn.textContent = '❌ <?= __('update_error') ?>';
                }
            }, 50);
        });
}

function startUpdate() {
    const startBtn = document.getElementById('startUpdateBtn');
    const modal = document.getElementById('updateConfirmModal');
    const latestVersion = document.getElementById('latestVersion')?.textContent || '';
    
    if (!startBtn) return;
    
    startBtn.disabled = true;
    startBtn.textContent = '⏳ <?= __('update_in_progress') ?>...';
    if (modal) modal.style.display = 'none';
    
    const consoleDiv = document.getElementById('updateConsole');
    if (consoleDiv) {
        consoleDiv.innerHTML = '';
    }
    
    consoleLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
    consoleLog('🔄 ' + '<?= __('update_started') ?>', 'info');
    consoleLog('📌 ' + '<?= __('update_target_version') ?>: ' + latestVersion, 'info');
    consoleLog('📦 ' + '<?= __('update_backup_start') ?>...', 'info');
    
    const fd = new FormData();
    fd.append('ajax', 'perform_update');
    fd.append('version', latestVersion);
    fd.append('files', JSON.stringify(updateFilesList));
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                consoleLog('✅ ' + data.message, 'success');
                consoleLog('📦 ' + '<?= __('update_backup_saved') ?>: ' + (data.backup_dir || '—'), 'info');
                consoleLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
                consoleLog('✅ ' + '<?= __('update_complete') ?>', 'success');
                
                const reloadBtn = document.createElement('button');
                reloadBtn.className = 'btn btn-primary';
                reloadBtn.innerHTML = '🔄 <?= __('update_reload_now') ?>';
                reloadBtn.onclick = function() { location.reload(); };
                reloadBtn.style.marginTop = '10px';
                reloadBtn.style.padding = '8px 20px';
                
                const consoleDiv = document.getElementById('updateConsole');
                if (consoleDiv) {
                    const reloadContainer = document.createElement('div');
                    reloadContainer.style.marginTop = '15px';
                    reloadContainer.appendChild(reloadBtn);
                    consoleDiv.appendChild(reloadContainer);
                    consoleDiv.scrollTop = consoleDiv.scrollHeight;
                }
                
                startBtn.disabled = false;
                startBtn.textContent = '✅ <?= __('update_confirm_yes') ?>';
            } else {
                consoleLog('❌ ' + data.message, 'error');
                if (data.failed && data.failed.length > 0) {
                    consoleLog('❌ ' + '<?= __('update_failed_files') ?>: ' + data.failed.join(', '), 'error');
                }
                consoleLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'info');
                startBtn.disabled = false;
                startBtn.textContent = '❌ <?= __('update_error') ?>';
            }
        })
        .catch(error => {
            consoleLog('❌ ' + '<?= __('update_error') ?>: ' + error.message, 'error');
            startBtn.disabled = false;
            startBtn.textContent = '❌ <?= __('update_error') ?>';
        });
}

// ============================================
// ИНИЦИАЛИЗАЦИЯ / INITIALIZATION
// ============================================

function initUpdateTab() {
    console.log('initUpdateTab called');
    const checkBtn = document.getElementById('checkUpdatesBtn');
    const gitHubBtn = document.getElementById('checkGitHubBtn');
    const runBtn = document.getElementById('runUpdateBtn');
    const startUpdateBtn = document.getElementById('startUpdateBtn');
    const updateService = document.getElementById('updateService');
    const updateAuthType = document.getElementById('updateAuthType');
    const updateMode = document.getElementById('updateMode');
    const notifyEnabled = document.getElementById('notifyEnabled');
    const scheduleEnabled = document.getElementById('scheduleEnabled');
    
    if (checkBtn) checkBtn.onclick = checkForUpdates;
    if (gitHubBtn) gitHubBtn.onclick = checkGitHubAccess;
    if (runBtn) runBtn.onclick = runUpdate;
    if (startUpdateBtn) startUpdateBtn.onclick = startUpdate;
    if (updateService) updateService.onchange = toggleUpdateService;
    if (updateAuthType) updateAuthType.onchange = toggleUpdateAuth;
    if (updateMode) updateMode.onchange = toggleAutoSettings;
    if (notifyEnabled) notifyEnabled.onchange = toggleNotifySettings;
    
    toggleUpdateService();
    toggleUpdateAuth();
    toggleAutoSettings();
    toggleNotifySettings();
}

// Экспорт в глобальную область
window.initUpdateTab = initUpdateTab;
window.checkForUpdates = checkForUpdates;
window.checkGitHubAccess = checkGitHubAccess;
window.runUpdate = runUpdate;
window.startUpdate = startUpdate;
window.toggleUpdateService = toggleUpdateService;
window.toggleUpdateAuth = toggleUpdateAuth;
window.closeUpdateConfirmModal = closeUpdateConfirmModal;
window.saveUpdateSettings = saveUpdateSettings;
window.toggleAutoSettings = toggleAutoSettings;
window.toggleNotifySettings = toggleNotifySettings;

// Наблюдатель за активацией вкладки
(function() {
    function checkAndInit() {
        const updateTab = document.getElementById('toolsTabUpdate');
        if (updateTab && updateTab.classList.contains('active')) {
            console.log('Update tab is active, initializing...');
            setTimeout(initUpdateTab, 10);
        }
    }
    
    checkAndInit();
    
    const updateTab = document.getElementById('toolsTabUpdate');
    if (updateTab) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (updateTab.classList.contains('active')) {
                        console.log('Update tab became active');
                        setTimeout(initUpdateTab, 10);
                    }
                }
            });
        });
        observer.observe(updateTab, { attributes: true });
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const updateTab = document.getElementById('toolsTabUpdate');
        if (updateTab && updateTab.classList.contains('active')) {
            initUpdateTab();
        }
    }, 100);
});
</script>
<?php
?>