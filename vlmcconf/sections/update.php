<?php
// ============================================
// ФАЙЛ: sections/update.php
// ВЕРСИЯ: 2.6.0
// ДАТА: 2026-06-10
// @description: Секция обновления проекта
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}
?>

<div id="toolsTabUpdate" class="tools-tab-content <?= $activeTab === 'update' ? 'active' : '' ?>">
    
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
    
    <div id="updateGithubSettings" class="update-info-panel" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px;">
        <div style="display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
            <div><span style="color: #8aa0bb;">📦 <?= __('update_repository') ?>:</span> <code style="font-size: 11px;">AlexandreKz/Monitor-VLMCSD</code></div>
            <div><span style="color: #8aa0bb;">🌿 <?= __('update_branch') ?>:</span> <code style="font-size: 11px;">main</code></div>
        </div>
    </div>
    
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
    
    <div class="update-status-bar" style="background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 10px 12px; margin-bottom: 15px; display: flex; gap: 30px; flex-wrap: wrap; font-size: 11px;">
        <div><span style="color: #8aa0bb;">📌 <?= __('update_current_version') ?>:</span> <strong><span id="currentVersion"><?= CONFIG_VERSION ?></span></strong></div>
        <div><span style="color: #8aa0bb;">🆕 <?= __('update_latest_version') ?>:</span> <strong><span id="latestVersion">—</span></strong></div>
        <div><span style="color: #8aa0bb;">📊 <?= __('update_status') ?>:</span> <span id="updateStatusText"><?= __('update_not_checked') ?></span></div>
    </div>
    
    <div class="update-console" id="updateConsole" style="background: #121212; color: #00ff00; font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 10px 12px; border-radius: 6px; height: 250px; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>;">
        <div>> <?= __('update_ready') ?></div>
    </div>
    
    <div style="margin-top: 15px; text-align: right;">
        <button class="btn btn-secondary btn-small" id="checkGitHubBtn" style="opacity: 0.6; font-size: 10px; padding: 2px 8px;">🌐 <?= __('update_check_github') ?></button>
    </div>
    
</div>

<div id="updateConfirmModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header"><h2>⚠️ <?= __('update_confirm_title') ?></h2><span class="tools-modal-close" onclick="closeUpdateConfirmModal()">&times;</span></div>
        <div class="tools-modal-body"><p><?= __('update_confirm_warning') ?></p><p class="tools-modal-estimate"><?= __('update_confirm_desc') ?></p><div id="updateFilesList" style="margin-top: 10px; max-height: 200px; overflow-y: auto; font-size: 11px;"></div></div>
        <div class="tools-modal-footer"><button class="btn btn-secondary" onclick="closeUpdateConfirmModal()">❌ <?= __('update_confirm_no') ?></button><button class="btn btn-primary" id="startUpdateBtn">✅ <?= __('update_confirm_yes') ?></button></div>
    </div>
</div>

<style>
.update-console::-webkit-scrollbar { width: 6px; }
.update-console::-webkit-scrollbar-track { background: #2a2a2a; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb { background: #00ff00; border-radius: 3px; }
.update-console::-webkit-scrollbar-thumb:hover { background: #00cc00; }
</style>

<script>
console.log('update.php script loaded');

let updateConsoleLines = [];

function extractVersionFromTag(tagName) {
    let version = tagName.replace(/^v/, '');
    const match = version.match(/(\d+\.\d+\.\d+)/);
    if (match) return match[1];
    return version;
}

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

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

function toggleUpdateService() {
    const service = document.getElementById('updateService');
    if (!service) return;
    const githubSettings = document.getElementById('updateGithubSettings');
    const customSettings = document.getElementById('updateCustomSettings');
    if (service.value === 'github') {
        if (githubSettings) githubSettings.style.display = 'block';
        if (customSettings) customSettings.style.display = 'none';
    } else {
        if (githubSettings) githubSettings.style.display = 'none';
        if (customSettings) customSettings.style.display = 'block';
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

function closeUpdateConfirmModal() { const modal = document.getElementById('updateConfirmModal'); if (modal) modal.style.display = 'none'; }

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
    if (filesList) filesList.innerHTML = '<div style="text-align: center;">⏳ <?= __('loading') ?></div>';
    if (modal) modal.style.display = 'flex';
    setTimeout(() => {
        if (filesList) filesList.innerHTML = '<div style="text-align: center;"><?= __('update_no_files') ?></div>';
        setTimeout(() => closeUpdateConfirmModal(), 1500);
    }, 1000);
}

function startUpdate() {
    closeUpdateConfirmModal();
    consoleLog('🔄 ' + '<?= __('update_in_progress') ?>...', 'info');
    setTimeout(() => { consoleLog('✅ ' + '<?= __('update_success') ?>', 'success'); }, 2000);
}

function initUpdateTab() {
    console.log('initUpdateTab called');
    const checkBtn = document.getElementById('checkUpdatesBtn');
    const gitHubBtn = document.getElementById('checkGitHubBtn');
    const runBtn = document.getElementById('runUpdateBtn');
    const startUpdateBtn = document.getElementById('startUpdateBtn');
    const updateService = document.getElementById('updateService');
    const updateAuthType = document.getElementById('updateAuthType');
    
    console.log('checkUpdatesBtn found:', checkBtn);
    console.log('checkGitHubBtn found:', gitHubBtn);
    
    if (checkBtn) checkBtn.onclick = checkForUpdates;
    if (gitHubBtn) gitHubBtn.onclick = checkGitHubAccess;
    if (runBtn) runBtn.onclick = runUpdate;
    if (startUpdateBtn) startUpdateBtn.onclick = startUpdate;
    if (updateService) updateService.onchange = toggleUpdateService;
    if (updateAuthType) updateAuthType.onchange = toggleUpdateAuth;
    
    toggleUpdateService();
    toggleUpdateAuth();
    // consoleLog('✅ ' + '<?= __('update_ready') ?>', 'success');
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

// Ждём полной загрузки DOM
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