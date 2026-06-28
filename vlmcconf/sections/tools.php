<?php
// ============================================
// ФАЙЛ: sections/tools.php
// ВЕРСИЯ: 6.4.0
// ДАТА: 2026-06-28
// @description: Секция "Инструменты" с вкладками (управление логом, кэш, проект)
// @description: Tools section with tabs (log management, cache, project)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'tools.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/../vlmcinc/geo_cache.php';
require_once __DIR__ . '/../vlmcloghandler.php';

$userPerms = $_SESSION['vlmc_permissions'] ?? 0;
$canViewTools = hasPermission($userPerms, PERM_TOOLS_VIEW) || hasPermission($userPerms, PERM_TOOLS_EDIT);
$canEditTools = hasPermission($userPerms, PERM_TOOLS_EDIT);
$isAdmin = hasPermission($userPerms, PERM_USERS_EDIT);

if (!$canViewTools) {
    echo '<div class="settings-section" id="section-tools"><div class="section-title"><span>🛠️ ' . __('tools_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}

$cacheStats = getGeoCacheStats();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'project';
$firstLogDate = getFirstLogDate($fullLogPath);
?>

<div id="section-tools" class="settings-section <?= $activeSection === 'tools' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🛠️ <?= __('tools_title') ?></span>
        <?php if ($activeSection === 'tools' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Вкладки -->
    <div class="tools-tabs">
        <button class="tools-tab-btn <?= $activeTab === 'project' ? 'active' : '' ?>" onclick="switchToolsTab('project')">🚀 <?= __('tools_tab_project') ?></button>
        <button class="tools-tab-btn <?= $activeTab === 'update' ? 'active' : '' ?>" onclick="switchToolsTab('update')">🔄 <?= __('tools_tab_update') ?></button>
        <button class="tools-tab-btn <?= $activeTab === 'log' ? 'active' : '' ?>" onclick="switchToolsTab('log')">📝 <?= __('tools_tab_log') ?></button>
        <button class="tools-tab-btn <?= $activeTab === 'cache' ? 'active' : '' ?>" onclick="switchToolsTab('cache')">🗑️ <?= __('tools_tab_cache') ?></button>
    </div>
    
    <!-- Вкладка: Проект -->
    <div id="toolsTabProject" class="tools-tab-content <?= $activeTab === 'project' ? 'active' : '' ?>">
        <div class="tools-row">
            <div class="tools-card">
                <div class="tools-card-title">💾 <?= __('export_title') ?></div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🧹 <?= __('export_clean') ?></div>
                    <p class="tools-desc"><?= __('export_clean_desc') ?></p>
                    <button class="btn btn-primary" onclick="toolsExportProject('clean')" style="width: 100%;">📦 <?= __('export_clean') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">⚙️ <?= __('export_with_config') ?></div>
                    <p class="tools-desc"><?= __('export_config_desc') ?></p>
                    <button class="btn btn-primary" onclick="toolsExportProject('config')" style="width: 100%;">⚙️ <?= __('export_with_config') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">💾 <?= __('export_full') ?></div>
                    <p class="tools-desc"><?= __('export_full_desc') ?></p>
                    <button class="btn btn-primary" onclick="toolsExportProject('full')" style="width: 100%;">🗃️ <?= __('export_full') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🔄 <?= __('info_reset') ?></div>
                    <p class="tools-desc"><?= __('info_reset_desc') ?></p>
                    <button class="btn btn-danger reset-config-btn" onclick="toolsResetConfig()" style="width: 100%;">🔄 <?= __('info_reset') ?></button>
                </div>
                <div id="exportOperationMessage" class="block-message" style="display: none;"></div>
            </div>
            <div class="tools-card" style="visibility: hidden;"></div>
        </div>
    </div>
    
    <!-- Вкладка: Обновление -->
    <div id="toolsTabUpdate" class="tools-tab-content <?= $activeTab === 'update' ? 'active' : '' ?>">
        <?php include __DIR__ . '/update.php'; ?>
    </div>
    
    <!-- Вкладка: Лог -->
    <div id="toolsTabLog" class="tools-tab-content <?= $activeTab === 'log' ? 'active' : '' ?>">
        <div class="tools-row">
            <div class="tools-card">
                <div class="tools-card-title">📝 <?= __('tools_log_management') ?></div>
                <div class="log-info-compact">
                    <div><strong><?= __('security_log_file') ?>:</strong> <?= htmlspecialchars(basename($fullLogPath)) ?></div>
                    <div><strong><?= __('security_size') ?>:</strong> <?= formatSize($logSize) ?></div>
                    <div><strong><?= __('security_status') ?>:</strong> 
                        <?php if ($logFileExists): ?>
                            <span style="color: <?= $themeCSS['success'] ?>;">✓ <?= __('log_status_found') ?></span>
                        <?php else: ?>
                            <span style="color: <?= $themeCSS['danger'] ?>;">✗ <?= __('log_status_not_found') ?></span>
                        <?php endif; ?>
                    </div>
                    <div><strong><?= __('security_first_event') ?>:</strong> 
                        <?= $firstLogDate ? htmlspecialchars($firstLogDate) : '<span style="color: #8aa0bb;">—</span>' ?>
                    </div>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">📅 <?= __('security_clear_by_date') ?></div>
                    <div class="date-range-row">
                        <div class="date-field">
                            <label><?= __('security_from') ?></label>
                            <input type="date" id="toolsStartDate" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        </div>
                        <div class="date-field">
                            <label><?= __('security_to') ?></label>
                            <input type="date" id="toolsEndDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <button class="btn btn-warning" onclick="showClearLogModal('date_range')" <?= (!$canEditTools) ? 'disabled' : '' ?>>🗑️ <?= __('security_clear') ?></button>
                    </div>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🗑️ <?= __('security_full_clear') ?></div>
                    <div class="two-buttons">
                        <button class="btn btn-danger" onclick="showClearLogModal('all')" <?= (!$logFileExists || !$canEditTools) ? 'disabled' : '' ?>><?= __('security_clear_all') ?></button>
                        <button class="btn btn-success" onclick="toolsBackupLog()" <?= (!$logFileExists || !$canEditTools) ? 'disabled' : '' ?>><?= __('security_log_backup') ?></button>
                    </div>
                </div>
                <div id="logOperationMessage" class="block-message" style="display: none;"></div>
            </div>
            <div class="tools-card" style="visibility: hidden;"></div>
            <div class="tools-card" style="visibility: hidden;"></div>
        </div>
    </div>
    
    <!-- Вкладка: Кэш -->
    <div id="toolsTabCache" class="tools-tab-content <?= $activeTab === 'cache' ? 'active' : '' ?>">
        <div class="tools-row">
            <div class="tools-card">
                <div class="tools-card-title">🗺️ <?= __('tools_geo_cache') ?></div>
                <div class="cache-stats" id="cacheStats">
                    <div><strong><?= __('tools_cache_files') ?>:</strong> <span id="cacheCount"><?= $cacheStats['count'] ?></span></div>
                    <div><strong><?= __('tools_cache_size') ?>:</strong> <span id="cacheSize"><?= $cacheStats['size_formatted'] ?></span></div>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🧹 <?= __('tools_cache_clear') ?></div>
                    <p class="tools-desc"><?= __('tools_cache_clear_desc') ?></p>
                    <button class="btn btn-warning" id="clearCacheBtn" onclick="toolsClearCache()" style="width: 100%;" <?= !$canEditTools ? 'disabled' : '' ?>><?= __('tools_cache_clear_btn') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🔄 <?= __('tools_cache_refresh') ?></div>
                    <p class="tools-desc"><?= __('tools_cache_refresh_desc') ?></p>
                    <button class="btn btn-primary" id="refreshCacheBtn" onclick="showToolsConfirmModal()" style="width: 100%;" <?= !$canEditTools ? 'disabled' : '' ?>><?= __('tools_cache_refresh_btn') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">📊 <?= __('tools_cache_check') ?></div>
                    <p class="tools-desc"><?= __('tools_cache_check_desc') ?></p>
                    <button class="btn btn-info" id="checkCacheBtn" onclick="toolsCheckCache()" style="width: 100%;">📊 <?= __('tools_cache_check_btn') ?></button>
                </div>
                <div id="cacheOperationMessage" class="block-message" style="display: none;"></div>
            </div>
            <div class="tools-card">
                <div class="tools-card-title">😀 <?= __('tools_emoji_cache') ?></div>
                <div class="cache-stats" id="emojiCacheStats">
                    <div><strong><?= __('tools_cache_status') ?>:</strong> <span id="emojiCacheExists">—</span></div>
                    <div><strong><?= __('tools_cache_size') ?>:</strong> <span id="emojiCacheSize">—</span></div>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🧹 <?= __('tools_emoji_cache_clear') ?></div>
                    <p class="tools-desc"><?= __('tools_emoji_cache_clear_desc') ?></p>
                    <button class="btn btn-warning" id="clearEmojiCacheBtn" onclick="toolsClearEmojiCache()" style="width: 100%;" <?= !$canEditTools ? 'disabled' : '' ?>>🗑️ <?= __('tools_emoji_cache_clear_btn') ?></button>
                </div>
                <div class="tools-subsection">
                    <div class="tools-subsection-title">🔄 <?= __('tools_emoji_cache_refresh') ?></div>
                    <p class="tools-desc"><?= __('tools_emoji_cache_refresh_desc') ?></p>
                    <button class="btn btn-primary" id="refreshEmojiCacheBtn" onclick="toolsRefreshEmojiCache()" style="width: 100%;" <?= !$canEditTools ? 'disabled' : '' ?>>🔄 <?= __('tools_emoji_cache_refresh_btn') ?></button>
                </div>
                <div id="emojiCacheMessage" class="block-message" style="display: none;"></div>
            </div>
            <div class="tools-card" style="visibility: hidden;"></div>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения очистки лога -->
<div id="clearLogConfirmModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header">
            <h2>⚠️ <?= __('security_clear_confirm_title') ?></h2>
            <span class="tools-modal-close" onclick="closeClearLogModal()">&times;</span>
        </div>
        <div class="tools-modal-body">
            <p id="clearLogConfirmMessage"><?= __('security_clear_all_confirm') ?></p>
            <p class="tools-modal-estimate" id="clearLogConfirmDetails"></p>
        </div>
        <div class="tools-modal-footer">
            <button class="btn btn-secondary" onclick="closeClearLogModal()">❌ <?= __('cancel') ?></button>
            <button class="btn btn-danger" id="clearLogConfirmBtn" onclick="executeClearLog()">✅ <?= __('security_clear') ?></button>
        </div>
    </div>
</div>

<!-- Модальные окна (кэш) -->
<div id="toolsConfirmModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header"><h2>⚠️ <?= __('tools_cache_confirm_title') ?></h2><span class="tools-modal-close" onclick="closeToolsConfirmModal()">&times;</span></div>
        <div class="tools-modal-body"><p><?= __('tools_cache_confirm_warning') ?></p><p class="tools-modal-estimate"><?= __('tools_cache_estimate') ?></p></div>
        <div class="tools-modal-footer"><button class="btn btn-secondary" onclick="closeToolsConfirmModal()">❌ <?= __('tools_cache_confirm_no') ?></button><button class="btn btn-primary" onclick="startToolsRefreshCache()">✅ <?= __('tools_cache_confirm_yes') ?></button></div>
    </div>
</div>

<div id="toolsResultModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header"><h2>✅ <?= __('tools_cache_result_title') ?></h2><span class="tools-modal-close" onclick="closeToolsResultModal()">&times;</span></div>
        <div class="tools-modal-body"><p id="resultMessage"><?= __('tools_cache_complete') ?></p><p class="tools-modal-estimate" id="resultDetails"></p></div>
        <div class="tools-modal-footer"><button class="btn btn-primary" onclick="closeToolsResultModal()"><?= __('tools_cache_result_ok') ?></button></div>
    </div>
</div>

<div id="toolsBlockingOverlay" class="tools-blocking-overlay" style="display: none;">
    <div class="overlay-content"><div class="spinner"></div><h3>🔄 <?= __('tools_cache_in_progress_title') ?></h3><p id="overlayMessage"><?= __('tools_cache_started') ?></p></div>
</div>

<style>
/* ===== Вкладки ===== */
.tools-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.tools-tab-btn { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; }
.tools-tab-btn:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.tools-tab-btn.active { opacity: 1; color: <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.tools-tab-content { display: none; animation: fadeIn 0.3s; }
.tools-tab-content.active { display: block; }

/* ===== Карточки ===== */
.tools-row { display: flex; gap: 15px; align-items: stretch; }
.tools-card { flex: 1; min-width: 260px; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.tools-card-title { font-size: 14px; font-weight: 600; padding-bottom: 6px; border-bottom: 2px solid <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['primary'] ?>; }
.tools-subsection { background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 6px 8px; }
.tools-subsection-title { font-size: 11px; font-weight: 600; margin-bottom: 4px; color: #8aa0bb; }
.tools-desc { font-size: 10px; color: #8aa0bb; margin-bottom: 6px; }

/* ===== Лог ===== */
.log-info-compact { background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 6px 8px; font-size: 11px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; }
.date-range-row { display: flex; gap: 6px; align-items: flex-end; flex-wrap: wrap; }
.date-field { flex: 1; }
.date-field label { display: block; font-size: 9px; margin-bottom: 2px; color: #8aa0bb; }
.date-field input { padding: 4px 6px; font-size: 11px; }
.two-buttons { display: flex; gap: 8px; }
.two-buttons button { flex: 1; }

/* ===== Кэш ===== */
.cache-stats { background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 6px 8px; font-size: 12px; display: flex; justify-content: space-around; text-align: center; }

/* ===== Кнопки ===== */
.btn { padding: 4px 8px; border: none; border-radius: 4px; font-size: 11px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: <?= $themeCSS['primary'] ?>; color: white; }
.btn-primary:hover { background: #2563eb; }
.btn-warning { background: <?= $themeCSS['warning'] ?>; color: white; }
.btn-danger { background: <?= $themeCSS['danger'] ?>; color: white; }
.btn-success { background: <?= $themeCSS['success'] ?>; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ===== Стилизация календаря под тему ===== */
input[type="date"] {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 6px;
    color: <?= $themeCSS['text'] ?>;
    padding: 6px 10px;
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    transition: border-color 0.2s;
    width: 100%;
}
input[type="date"]:focus {
    outline: none;
    border-color: <?= $themeCSS['primary'] ?>;
    box-shadow: 0 0 0 2px <?= $themeCSS['primary'] ?>20;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: <?= $config['theme'] === 'dark' ? 'invert(0.8)' : 'invert(0.2)' ?>;
    cursor: pointer;
}
input[type="date"]::-webkit-datetime-edit {
    color: <?= $themeCSS['text'] ?>;
}
input[type="date"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ===== Модальные окна ===== */
.tools-modal { position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; }
.tools-modal-content { background: <?= $themeCSS['card'] ?>; border-radius: 12px; width: 450px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); color: <?= $themeCSS['text'] ?>; animation: modalFadeIn 0.3s; }
.tools-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; }
.tools-modal-header h2 { font-size: 18px; font-weight: 600; margin: 0; }
.tools-modal-close { color: #8aa0bb; font-size: 24px; font-weight: bold; cursor: pointer; line-height: 1; }
.tools-modal-body { padding: 20px; }
.tools-modal-estimate { font-size: 12px; color: #8aa0bb; background: <?= $themeCSS['input'] ?>; padding: 8px; border-radius: 6px; margin-top: 10px; }
.tools-modal-footer { padding: 15px 20px; border-top: 1px solid <?= $themeCSS['border'] ?>; display: flex; justify-content: flex-end; gap: 10px; }

/* ===== Оверлей блокировки ===== */
.tools-blocking-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100001; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.tools-blocking-overlay .overlay-content { background: <?= $themeCSS['card'] ?>; border-radius: 12px; padding: 30px; text-align: center; border: 2px solid <?= $themeCSS['primary'] ?>; min-width: 300px; }
.tools-blocking-overlay .spinner { width: 40px; height: 40px; border: 3px solid <?= $themeCSS['border'] ?>; border-top-color: <?= $themeCSS['primary'] ?>; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px; }

/* ===== Сообщения ===== */
.block-message { margin-top: 8px; padding: 6px 8px; font-size: 11px; text-align: center; border-radius: 6px; background: <?= $themeCSS['card'] ?>; }

/* ===== Анимации ===== */
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
// ============================================
// ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК
// ============================================
function switchToolsTab(tab) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tools-tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    document.querySelectorAll('.tools-tab-content').forEach(content => content.classList.remove('active'));
    const targetTab = document.getElementById('toolsTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (targetTab) targetTab.classList.add('active');
    
    // Для вкладки update — загружаем содержимое через AJAX
    if (tab === 'update') {
        const updateContainer = document.getElementById('toolsTabUpdate');
        if (updateContainer && (updateContainer.innerHTML.trim() === '' || updateContainer.innerHTML.includes('spinner') || updateContainer.innerHTML.includes('Загрузка'))) {
            updateContainer.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="width: 30px; height: 30px; border: 3px solid #33485d; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div><?= __('loading') ?></div>';
            
            fetch('?ajax=get_update_tab')
                .then(response => response.text())
                .then(html => {
                    updateContainer.innerHTML = html;
                    setTimeout(function() {
                        if (typeof window.initUpdateTab === 'function') {
                            window.initUpdateTab();
                        }
                    }, 100);
                })
                .catch(error => {
                    updateContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ <?= __('error_loading') ?></div>';
                });
        }
    }
}

// ============================================
// ПЕРЕМЕННЫЕ
// ============================================
let isRefreshing = false;
let currentOffset = 0;
let currentTotalAll = 0;
let currentTotalUpdated = 0;
let currentTotalProcessed = 0;
let logMessageTimer = null;
let exportMessageTimer = null;
let cacheMessageTimer = null;
let emojiMessageTimer = null;
const BATCH_SIZE = 30;

// ============================================
// МОДАЛЬНОЕ ОКНО ОЧИСТКИ ЛОГА
// ============================================
let clearLogType = 'all';
let clearLogStartDate = '';
let clearLogEndDate = '';

function showClearLogModal(type) {
    const sd = document.getElementById('toolsStartDate')?.value;
    const ed = document.getElementById('toolsEndDate')?.value;
    
    if (type === 'date_range') {
        if (!sd || !ed) {
            showLogMessage('<?= __('date_range_required') ?>', 'error');
            return;
        }
        if (sd > ed) {
            showLogMessage('<?= __('date_range_invalid') ?>', 'error');
            return;
        }
        clearLogStartDate = sd;
        clearLogEndDate = ed;
        document.getElementById('clearLogConfirmMessage').textContent = '<?= __('security_clear_date_confirm') ?>'.replace('%s', sd).replace('%s', ed);
        document.getElementById('clearLogConfirmDetails').textContent = '<?= __('security_clear_date_range_details') ?>: ' + sd + ' — ' + ed;
    } else {
        clearLogStartDate = '';
        clearLogEndDate = '';
        document.getElementById('clearLogConfirmMessage').textContent = '<?= __('security_clear_all_confirm') ?>';
        document.getElementById('clearLogConfirmDetails').textContent = '<?= __('security_clear_all_details') ?>';
    }
    
    clearLogType = type;
    document.getElementById('clearLogConfirmModal').style.display = 'flex';
}

function closeClearLogModal() {
    document.getElementById('clearLogConfirmModal').style.display = 'none';
}

function executeClearLog() {
    const btn = document.getElementById('clearLogConfirmBtn');
    btn.disabled = true;
    btn.textContent = '⏳ <?= __('loading') ?>';
    
    const fd = new FormData();
    fd.append('ajax', 'clear_log');
    fd.append('clearType', clearLogType);
    if (clearLogType === 'date_range') {
        fd.append('startDate', clearLogStartDate);
        fd.append('endDate', clearLogEndDate);
    }
    
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            closeClearLogModal();
            if (d.success) {
                showLogMessage(d.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showLogMessage(d.message, 'error');
                btn.disabled = false;
                btn.textContent = '✅ <?= __('security_clear') ?>';
            }
        })
        .catch(e => {
            closeClearLogModal();
            showLogMessage('<?= __('msg_save_error') ?>', 'error');
            btn.disabled = false;
            btn.textContent = '✅ <?= __('security_clear') ?>';
        });
}

// ============================================
// СООБЩЕНИЯ
// ============================================
function showLogMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('logOperationMessage');
    if (!msgDiv) return;
    if (logMessageTimer) clearTimeout(logMessageTimer);
    let bgColor = '', textColor = '';
    switch(type) {
        case 'error': bgColor = '<?= $themeCSS['danger'] ?>20'; textColor = '<?= $themeCSS['danger'] ?>'; break;
        case 'success': bgColor = '<?= $themeCSS['success'] ?>20'; textColor = '<?= $themeCSS['success'] ?>'; break;
        default: bgColor = '<?= $themeCSS['primary'] ?>20'; textColor = '<?= $themeCSS['primary'] ?>';
    }
    msgDiv.style.backgroundColor = bgColor;
    msgDiv.style.color = textColor;
    msgDiv.style.border = '1px solid ' + textColor;
    msgDiv.innerHTML = message;
    msgDiv.style.display = 'block';
    if (!permanent) {
        logMessageTimer = setTimeout(() => { if (msgDiv) { msgDiv.innerHTML = ''; msgDiv.style.display = 'none'; } logMessageTimer = null; }, 10000);
    }
}

function showExportMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('exportOperationMessage');
    if (!msgDiv) return;
    if (exportMessageTimer) clearTimeout(exportMessageTimer);
    let bgColor = '', textColor = '';
    switch(type) {
        case 'error': bgColor = '<?= $themeCSS['danger'] ?>20'; textColor = '<?= $themeCSS['danger'] ?>'; break;
        case 'success': bgColor = '<?= $themeCSS['success'] ?>20'; textColor = '<?= $themeCSS['success'] ?>'; break;
        default: bgColor = '<?= $themeCSS['primary'] ?>20'; textColor = '<?= $themeCSS['primary'] ?>';
    }
    msgDiv.style.backgroundColor = bgColor;
    msgDiv.style.color = textColor;
    msgDiv.style.border = '1px solid ' + textColor;
    msgDiv.innerHTML = message;
    msgDiv.style.display = 'block';
    if (!permanent) {
        exportMessageTimer = setTimeout(() => { if (msgDiv) { msgDiv.innerHTML = ''; msgDiv.style.display = 'none'; } exportMessageTimer = null; }, 10000);
    }
}

function showCacheMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('cacheOperationMessage');
    if (!msgDiv) return;
    if (cacheMessageTimer) clearTimeout(cacheMessageTimer);
    let bgColor = '', textColor = '';
    switch(type) {
        case 'error': bgColor = '<?= $themeCSS['danger'] ?>20'; textColor = '<?= $themeCSS['danger'] ?>'; break;
        case 'success': bgColor = '<?= $themeCSS['success'] ?>20'; textColor = '<?= $themeCSS['success'] ?>'; break;
        default: bgColor = '<?= $themeCSS['primary'] ?>20'; textColor = '<?= $themeCSS['primary'] ?>';
    }
    msgDiv.style.backgroundColor = bgColor;
    msgDiv.style.color = textColor;
    msgDiv.style.border = '1px solid ' + textColor;
    msgDiv.innerHTML = message;
    msgDiv.style.display = 'block';
    if (!permanent) {
        cacheMessageTimer = setTimeout(() => { if (msgDiv) { msgDiv.innerHTML = ''; msgDiv.style.display = 'none'; } cacheMessageTimer = null; }, 10000);
    }
}

function showEmojiCacheMessage(message, type = 'info') {
    const msgDiv = document.getElementById('emojiCacheMessage');
    if (!msgDiv) return;
    if (emojiMessageTimer) clearTimeout(emojiMessageTimer);
    let bgColor = '', textColor = '';
    switch(type) {
        case 'error': bgColor = '<?= $themeCSS['danger'] ?>20'; textColor = '<?= $themeCSS['danger'] ?>'; break;
        case 'success': bgColor = '<?= $themeCSS['success'] ?>20'; textColor = '<?= $themeCSS['success'] ?>'; break;
        default: bgColor = '<?= $themeCSS['primary'] ?>20'; textColor = '<?= $themeCSS['primary'] ?>';
    }
    msgDiv.style.backgroundColor = bgColor;
    msgDiv.style.color = textColor;
    msgDiv.style.border = '1px solid ' + textColor;
    msgDiv.innerHTML = message;
    msgDiv.style.display = 'block';
    emojiMessageTimer = setTimeout(() => { if (msgDiv) msgDiv.style.display = 'none'; emojiMessageTimer = null; }, 10000);
}

// ============================================
// КЭШ ГЕОЛОКАЦИИ
// ============================================
function showToolsConfirmModal() {
    if (isRefreshing) { showCacheMessage('⚠️ ' + '<?= __('tools_cache_already_running') ?>', 'error'); return; }
    document.getElementById('toolsConfirmModal').style.display = 'flex';
}
function closeToolsConfirmModal() { document.getElementById('toolsConfirmModal').style.display = 'none'; }
function showToolsResultModal(message, details) {
    document.getElementById('resultMessage').innerHTML = message;
    document.getElementById('resultDetails').innerHTML = details;
    document.getElementById('toolsResultModal').style.display = 'flex';
}
function closeToolsResultModal() { document.getElementById('toolsResultModal').style.display = 'none'; }
function startToolsRefreshCache() { closeToolsConfirmModal(); toolsRefreshCache(); }

function showBlockingOverlay(message) {
    const overlay = document.getElementById('toolsBlockingOverlay');
    document.getElementById('overlayMessage').innerHTML = message;
    overlay.style.display = 'flex';
}
function updateBlockingOverlayMessage(message) { document.getElementById('overlayMessage').innerHTML = message; }
function hideBlockingOverlay() { document.getElementById('toolsBlockingOverlay').style.display = 'none'; }

function disableAllInterface() {
    document.querySelectorAll('#section-tools .tools-action-btn').forEach(btn => btn.classList.add('disabled-global'));
    document.querySelectorAll('.settings-menu .menu-item').forEach(item => { if (!item.classList.contains('logout-btn')) { item.style.pointerEvents = 'none'; item.style.opacity = '0.5'; } });
    const backLink = document.querySelector('.back-link'); if (backLink) { backLink.style.pointerEvents = 'none'; backLink.style.opacity = '0.5'; }
    const logoutBtn = document.querySelector('.logout-btn'); if (logoutBtn) { logoutBtn.style.pointerEvents = 'none'; logoutBtn.style.opacity = '0.5'; }
}
function enableAllInterface() {
    document.querySelectorAll('#section-tools .tools-action-btn').forEach(btn => btn.classList.remove('disabled-global'));
    document.querySelectorAll('.settings-menu .menu-item').forEach(item => { item.style.pointerEvents = ''; item.style.opacity = ''; });
    const backLink = document.querySelector('.back-link'); if (backLink) { backLink.style.pointerEvents = ''; backLink.style.opacity = ''; }
    const logoutBtn = document.querySelector('.logout-btn'); if (logoutBtn) { logoutBtn.style.pointerEvents = ''; logoutBtn.style.opacity = ''; }
}
function setToolsButtonLoading(btn, isLoading) { if (!btn) return; if (isLoading) { btn.classList.add('loading'); btn.disabled = true; } else { btn.classList.remove('loading'); btn.disabled = false; } }

async function toolsRefreshCache() {
    const btn = document.getElementById('refreshCacheBtn');
    isRefreshing = true;
    currentOffset = 0;
    currentTotalUpdated = 0;
    currentTotalProcessed = 0;
    currentTotalAll = 0;
    disableAllInterface();
    setToolsButtonLoading(btn, true);
    showBlockingOverlay('⏳ ' + '<?= __('tools_cache_started') ?>');
    let hasMore = true;
    let errorOccurred = false;
    let errorMessage = '';
    while (hasMore && !errorOccurred) {
        try {
            const fd = new FormData();
            fd.append('ajax', 'refresh_geo_cache');
            fd.append('offset', currentOffset);
            fd.append('limit', BATCH_SIZE);
            const response = await fetch('', { method: 'POST', body: fd });
            const data = await response.json();
            if (data.success) {
                currentTotalProcessed += data.processed;
                currentTotalUpdated += data.updated;
                currentTotalAll = data.total_all;
                hasMore = data.has_more;
                currentOffset = data.next_offset;
                const processedPercent = Math.round((currentTotalProcessed / currentTotalAll) * 100);
                updateBlockingOverlayMessage('🔄 ' + '<?= __('tools_cache_in_progress') ?>: ' + currentTotalProcessed + '/' + currentTotalAll + ' (' + processedPercent + '%) | ' + '<?= __('tools_cache_updated') ?>: ' + currentTotalUpdated);
            } else { throw new Error(data.message); }
        } catch (error) { errorOccurred = true; errorMessage = error.message; }
    }
    hideBlockingOverlay();
    if (errorOccurred) { showToolsResultModal('❌ ' + '<?= __('tools_cache_error') ?>', errorMessage); }
    else { showToolsResultModal('✅ ' + '<?= __('tools_cache_complete') ?>', '📊 ' + '<?= __('tools_cache_files') ?>: ' + currentTotalUpdated + ' / ' + currentTotalAll + ' ' + '<?= __('tools_cache_ips') ?><br>🔄 ' + '<?= __('tools_cache_updated') ?>: ' + currentTotalUpdated); }
    enableAllInterface();
    setToolsButtonLoading(btn, false);
    isRefreshing = false;
    await toolsCheckCache(false);
}

async function toolsClearCache() {
    if (isRefreshing) { showCacheMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error'); return; }
    if (!confirm('<?= __('tools_cache_clear_confirm') ?>')) return;
    const btn = document.getElementById('clearCacheBtn');
    setToolsButtonLoading(btn, true);
    showCacheMessage('⏳ ' + '<?= __('tools_cache_clearing_cache') ?>', 'info', true);
    try {
        const fd = new FormData();
        fd.append('ajax', 'clear_geo_cache');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) {
            showCacheMessage(data.message, 'success');
            document.getElementById('cacheCount').innerText = '0';
            document.getElementById('cacheSize').innerText = '0 B';
        } else { showCacheMessage(data.message, 'error'); }
    } catch (error) { showCacheMessage('<?= __('msg_save_error') ?>', 'error'); }
    finally { setToolsButtonLoading(btn, false); }
}

async function toolsCheckCache(showMessage = true) {
    if (isRefreshing) { if (showMessage) showCacheMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error'); return; }
    const btn = document.getElementById('checkCacheBtn');
    if (showMessage) setToolsButtonLoading(btn, true);
    try {
        const fd = new FormData();
        fd.append('ajax', 'check_geo_cache');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) {
            document.getElementById('cacheCount').innerText = data.count;
            document.getElementById('cacheSize').innerText = data.size_formatted;
            if (showMessage) showCacheMessage('📊 ' + '<?= __('tools_cache_stats') ?>: ' + data.count + ' ' + '<?= __('tools_cache_files') ?>' + ', ' + data.size_formatted, 'success');
        } else if (showMessage) showCacheMessage(data.message, 'error');
    } catch (error) { if (showMessage) showCacheMessage('❌ ' + '<?= __('msg_save_error') ?>', 'error'); }
    finally { if (showMessage) setToolsButtonLoading(btn, false); }
}

// ============================================
// УПРАВЛЕНИЕ ЛОГОМ
// ============================================
async function toolsClearLog(type) {
    // Функция объединена с модальным окном, оставлена для обратной совместимости
    showClearLogModal(type);
}

async function toolsBackupLog() {
    if (isRefreshing) { showLogMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error'); return; }
    if (!confirm('<?= __('security_backup_confirm') ?>')) return;
    const btn = event.target;
    setToolsButtonLoading(btn, true);
    showLogMessage('⏳ ' + '<?= __('tools_cache_backup') ?>', 'info', true);
    try {
        const fd = new FormData();
        fd.append('ajax', 'backup_log');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        showLogMessage(data.message, data.success ? 'success' : 'error');
    } catch (error) { showLogMessage('<?= __('msg_backup_error') ?>', 'error'); }
    finally { setToolsButtonLoading(btn, false); }
}

// ============================================
// ЭКСПОРТ И СБРОС
// ============================================
async function toolsExportProject(type) {
    if (isRefreshing) { showExportMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error'); return; }
    let confirmMsg = '';
    switch(type) {
        case 'clean': confirmMsg = '<?= __('export_clean_confirm') ?>'; break;
        case 'config': confirmMsg = '<?= __('export_config_confirm') ?>'; break;
        case 'full': confirmMsg = '<?= __('export_full_confirm') ?>'; break;
    }
    if (!confirm(confirmMsg)) return;
    const btn = event.target;
    setToolsButtonLoading(btn, true);
    showExportMessage('⏳ ' + '<?= __('tools_cache_export') ?>', 'info', true);
    try {
        const fd = new FormData();
        fd.append('ajax', 'export_project');
        fd.append('export_type', type);
        const response = await fetch('', { method: 'POST', body: fd });
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kms_export_${type}_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.tar.gz`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showExportMessage('<?= __('export_success') ?>', 'success');
    } catch (error) { showExportMessage('<?= __('export_error') ?>: ' + error.message, 'error'); }
    finally { setToolsButtonLoading(btn, false); }
}

async function toolsResetConfig() {
    if (isRefreshing) { showExportMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error'); return; }
    if (!confirm('<?= __('info_reset_confirm') ?>')) return;
    const btn = document.querySelector('.reset-config-btn');
    setToolsButtonLoading(btn, true);
    showExportMessage('⏳ ' + '<?= __('tools_cache_resetting') ?>', 'info', true);
    try {
        const fd = new FormData();
        fd.append('action', 'reset_config');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) { showExportMessage(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showExportMessage(data.message, 'error'); }
    } catch (error) { showExportMessage('<?= __('msg_save_error') ?>', 'error'); }
    finally { setToolsButtonLoading(btn, false); }
}

// ============================================
// ЭМОДЗИ КЭШ
// ============================================
async function toolsRefreshEmojiCache() {
    const btn = document.getElementById('refreshEmojiCacheBtn');
    if (!btn) return;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ <?= __('loading') ?>';
    btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('ajax', 'refresh_emoji_cache');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) {
            showEmojiCacheMessage(data.message + ' (' + data.count + ' emoji)', 'success');
            await toolsCheckEmojiCache();
        } else { showEmojiCacheMessage(data.message, 'error'); }
    } catch (error) { showEmojiCacheMessage('<?= __('msg_save_error') ?>', 'error'); }
    finally { btn.innerHTML = originalText; btn.disabled = false; }
}

async function toolsClearEmojiCache() {
    if (!confirm('<?= __('tools_cache_clear_confirm') ?>')) return;
    const btn = document.getElementById('clearEmojiCacheBtn');
    if (btn) btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('ajax', 'clear_emoji_cache');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) { showEmojiCacheMessage(data.message, 'success'); await toolsCheckEmojiCache(); }
        else { showEmojiCacheMessage(data.message, 'error'); }
    } catch (error) { showEmojiCacheMessage('<?= __('msg_save_error') ?>', 'error'); }
    finally { if (btn) btn.disabled = false; }
}

async function toolsCheckEmojiCache() {
    try {
        const fd = new FormData();
        fd.append('ajax', 'check_emoji_cache');
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        if (data.success) {
            document.getElementById('emojiCacheExists').innerText = data.exists ? '<?= __('yes') ?>' : '<?= __('no') ?>';
            document.getElementById('emojiCacheSize').innerText = data.size_formatted || '0 B';
        }
    } catch (error) { console.error('Error checking emoji cache:', error); }
}

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('section-tools');
    if (section && section.classList.contains('active')) {
        setTimeout(function() { toolsCheckCache(false); toolsCheckEmojiCache(); }, 100);
    }
});
const toolsSectionObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const target = mutation.target;
            if (target.id === 'section-tools' && target.classList.contains('active')) {
                setTimeout(function() { toolsCheckCache(false); toolsCheckEmojiCache(); }, 100);
            }
        }
    });
});
const toolsSectionElement = document.getElementById('section-tools');
if (toolsSectionElement) toolsSectionObserver.observe(toolsSectionElement, { attributes: true });
</script>
<?php
?>