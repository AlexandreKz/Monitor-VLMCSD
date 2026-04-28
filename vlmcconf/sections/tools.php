<?php
// ============================================
// ФАЙЛ: sections/tools.php
// ВЕРСИЯ: 3.3.0
// ДАТА: 2026-04-27
// @description: Секция "Инструменты"
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'tools.php') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/../vlmcinc/geo_cache.php';

$canViewLogs = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_LOGS_VIEW);
$canEditLogs = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_LOGS_EDIT);
$canManageCache = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_DEVICES_EDIT) || $canViewLogs;

if (!$canViewLogs && !$canManageCache) {
    echo '<div class="settings-section" id="section-tools"><div class="section-title"><span>🛠️ ' . __('tools_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}

$cacheStats = getGeoCacheStats();
?>

<div id="section-tools" class="settings-section <?= $activeSection === 'tools' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🛠️ <?= __('tools_title') ?></span>
        <?php if ($activeSection === 'tools' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="tools-layout">
        
        <!-- Левая колонка: Управление логом -->
        <?php if ($canViewLogs): ?>
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
                    <button class="btn btn-warning btn-small tools-action-btn log-action-btn" onclick="toolsClearLog('date_range')">🗑️ <?= __('security_clear') ?></button>
                </div>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">🗑️ <?= __('security_full_clear') ?></div>
                <div class="two-buttons">
                    <button class="btn btn-danger btn-small tools-action-btn log-action-btn" onclick="toolsClearLog('all')" <?= !$logFileExists ? 'disabled' : '' ?>>🗑️ <?= __('security_clear_all') ?></button>
                    <button class="btn btn-success btn-small tools-action-btn log-action-btn" onclick="toolsBackupLog()" <?= !$logFileExists ? 'disabled' : '' ?>>💾 <?= __('security_log_backup') ?></button>
                </div>
            </div>
            
            <!-- Сообщения для блока управления логом (внизу) -->
            <div id="logOperationMessage" class="block-message" style="display: none;"></div>
        </div>
        <?php endif; ?>
        
        <!-- Центральная колонка: Экспорт проекта -->
        <?php if ($isAdmin): ?>
        <div class="tools-card">
            <div class="tools-card-title">💾 <?= __('export_title') ?></div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">🧹 <?= __('export_clean') ?></div>
                <p class="tools-desc"><?= __('export_clean_desc') ?></p>
                <button class="btn btn-primary btn-small tools-action-btn export-action-btn" onclick="toolsExportProject('clean')" style="width: 100%;">📦 <?= __('export_clean') ?></button>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">⚙️ <?= __('export_with_config') ?></div>
                <p class="tools-desc"><?= __('export_config_desc') ?></p>
                <button class="btn btn-primary btn-small tools-action-btn export-action-btn" onclick="toolsExportProject('config')" style="width: 100%;">⚙️ <?= __('export_with_config') ?></button>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">💾 <?= __('export_full') ?></div>
                <p class="tools-desc"><?= __('export_full_desc') ?></p>
                <button class="btn btn-primary btn-small tools-action-btn export-action-btn" onclick="toolsExportProject('full')" style="width: 100%;">🗃️ <?= __('export_full') ?></button>
            </div>
            
            <!-- Сообщения для блока экспорта (внизу) -->
            <div id="exportOperationMessage" class="block-message" style="display: none;"></div>
        </div>
        <?php endif; ?>
        
        <!-- Правая колонка: Кэш геолокации -->
        <?php if ($canManageCache): ?>
        <div class="tools-card">
            <div class="tools-card-title">🗺️ <?= __('tools_geo_cache') ?></div>
            
            <div class="cache-stats" id="cacheStats">
                <div><strong><?= __('tools_cache_files') ?>:</strong> <span id="cacheCount"><?= $cacheStats['count'] ?></span></div>
                <div><strong><?= __('tools_cache_size') ?>:</strong> <span id="cacheSize"><?= $cacheStats['size_formatted'] ?></span></div>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">🧹 <?= __('tools_cache_clear') ?></div>
                <p class="tools-desc"><?= __('tools_cache_clear_desc') ?></p>
                <button class="btn btn-warning btn-small tools-action-btn cache-action-btn" id="clearCacheBtn" onclick="toolsClearCache()" style="width: 100%;">🗑️ <?= __('tools_cache_clear_btn') ?></button>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">🔄 <?= __('tools_cache_refresh') ?></div>
                <p class="tools-desc"><?= __('tools_cache_refresh_desc') ?></p>
                <button class="btn btn-primary btn-small tools-action-btn cache-action-btn" id="refreshCacheBtn" onclick="showToolsConfirmModal()" style="width: 100%;">🔄 <?= __('tools_cache_refresh_btn') ?></button>
            </div>
            
            <div class="tools-subsection">
                <div class="tools-subsection-title">📊 <?= __('tools_cache_check') ?></div>
                <p class="tools-desc"><?= __('tools_cache_check_desc') ?></p>
                <button class="btn btn-info btn-small tools-action-btn cache-action-btn" id="checkCacheBtn" onclick="toolsCheckCache()" style="width: 100%;">📊 <?= __('tools_cache_check_btn') ?></button>
            </div>
            
            <!-- Сообщения для блока кэша (внизу) -->
            <div id="cacheOperationMessage" class="block-message" style="display: none;"></div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Модальное окно подтверждения -->
<div id="toolsConfirmModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header">
            <h2>⚠️ <?= __('tools_cache_confirm_title') ?></h2>
            <span class="tools-modal-close" onclick="closeToolsConfirmModal()">&times;</span>
        </div>
        <div class="tools-modal-body">
            <p><?= __('tools_cache_confirm_warning') ?></p>
            <p class="tools-modal-estimate"><?= __('tools_cache_estimate') ?></p>
        </div>
        <div class="tools-modal-footer">
            <button class="btn btn-secondary" onclick="closeToolsConfirmModal()">❌ <?= __('tools_cache_confirm_no') ?></button>
            <button class="btn btn-primary" onclick="startToolsRefreshCache()">✅ <?= __('tools_cache_confirm_yes') ?></button>
        </div>
    </div>
</div>

<!-- Модальное окно результатов обновления кэша -->
<div id="toolsResultModal" class="tools-modal" style="display: none;">
    <div class="tools-modal-content">
        <div class="tools-modal-header">
            <h2>✅ <?= __('tools_cache_result_title') ?></h2>
            <span class="tools-modal-close" onclick="closeToolsResultModal()">&times;</span>
        </div>
        <div class="tools-modal-body">
            <p id="resultMessage"><?= __('tools_cache_complete') ?></p>
            <p class="tools-modal-estimate" id="resultDetails"></p>
        </div>
        <div class="tools-modal-footer">
            <button class="btn btn-primary" onclick="closeToolsResultModal()"><?= __('tools_cache_result_ok') ?></button>
        </div>
    </div>
</div>

<!-- Блокирующий оверлей -->
<div id="toolsBlockingOverlay" class="tools-blocking-overlay" style="display: none;">
    <div class="overlay-content">
        <div class="spinner"></div>
        <h3>🔄 <?= __('tools_cache_in_progress_title') ?></h3>
        <p id="overlayMessage"><?= __('tools_cache_started') ?></p>
    </div>
</div>

<style>
.tools-layout {
    display: flex;
    gap: 15px;
    align-items: stretch;
    flex-wrap: wrap;
}

.tools-card {
    flex: 1;
    min-width: 260px;
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 10px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tools-card-title {
    font-size: 14px;
    font-weight: 600;
    padding-bottom: 6px;
    border-bottom: 2px solid <?= $themeCSS['primary'] ?>;
    color: <?= $themeCSS['primary'] ?>;
}

.tools-subsection {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 6px;
    padding: 8px 10px;
}

.tools-subsection-title {
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 6px;
    color: #8aa0bb;
}

.tools-desc {
    font-size: 10px;
    color: #8aa0bb;
    margin-bottom: 6px;
}

.log-info-compact {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px;
}

.cache-stats {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 12px;
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.cache-stats div {
    flex: 1;
}

.block-message {
    margin-top: 8px;
    padding: 6px 8px;
    font-size: 11px;
    text-align: center;
    border-radius: 6px;
    background: <?= $themeCSS['card'] ?>;
}

.date-range-row {
    display: flex;
    gap: 6px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.date-field {
    flex: 1;
}

.date-field label {
    display: block;
    font-size: 9px;
    margin-bottom: 2px;
    color: #8aa0bb;
}

.date-field input {
    padding: 4px 6px;
    font-size: 11px;
}

.two-buttons {
    display: flex;
    gap: 8px;
}

.two-buttons button {
    flex: 1;
}

.tools-action-btn {
    transition: all 0.2s ease;
    padding: 5px 10px;
    font-size: 11px;
}

.tools-action-btn.loading {
    opacity: 0.6;
    cursor: wait;
}

.tools-action-btn.loading::after {
    content: " ⏳";
}

.tools-action-btn.disabled-global {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.btn-info {
    background: #17a2b8;
    color: white;
}
.btn-info:hover {
    background: #138496;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #5a6268;
}

.form-control {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 4px;
    color: <?= $themeCSS['text'] ?>;
}

/* Модальные окна */
.tools-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.tools-modal-content {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
    width: 450px;
    max-width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    color: <?= $themeCSS['text'] ?>;
    animation: modalFadeIn 0.3s;
}

.tools-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
}

.tools-modal-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.tools-modal-close {
    color: #8aa0bb;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
    line-height: 1;
}

.tools-modal-close:hover {
    color: <?= $themeCSS['text'] ?>;
}

.tools-modal-body {
    padding: 20px;
}

.tools-modal-body p {
    margin-bottom: 12px;
    line-height: 1.5;
}

.tools-modal-estimate {
    font-size: 12px;
    color: #8aa0bb;
    background: <?= $themeCSS['input'] ?>;
    padding: 8px;
    border-radius: 6px;
    margin-top: 10px;
}

.tools-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid <?= $themeCSS['border'] ?>;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Блокирующий оверлей */
.tools-blocking-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 100001;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
}

.tools-blocking-overlay .overlay-content {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    border: 2px solid <?= $themeCSS['primary'] ?>;
    min-width: 300px;
}

.tools-blocking-overlay .overlay-content h3 {
    margin-bottom: 15px;
    color: <?= $themeCSS['primary'] ?>;
}

.tools-blocking-overlay .overlay-content p {
    margin-bottom: 0;
    color: <?= $themeCSS['text'] ?>;
    font-size: 13px;
}

.tools-blocking-overlay .spinner {
    width: 40px;
    height: 40px;
    border: 3px solid <?= $themeCSS['border'] ?>;
    border-top-color: <?= $themeCSS['primary'] ?>;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
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
const BATCH_SIZE = 30;

// ============================================
// ФУНКЦИИ ДЛЯ СООБЩЕНИЙ В КАЖДОМ БЛОКЕ
// ============================================

function showLogMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('logOperationMessage');
    if (!msgDiv) return;
    
    if (logMessageTimer) {
        clearTimeout(logMessageTimer);
        logMessageTimer = null;
    }
    
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
        logMessageTimer = setTimeout(() => {
            const div = document.getElementById('logOperationMessage');
            if (div) { div.innerHTML = ''; div.style.display = 'none'; }
            logMessageTimer = null;
        }, 10000);
    }
}

function showExportMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('exportOperationMessage');
    if (!msgDiv) return;
    
    if (exportMessageTimer) {
        clearTimeout(exportMessageTimer);
        exportMessageTimer = null;
    }
    
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
        exportMessageTimer = setTimeout(() => {
            const div = document.getElementById('exportOperationMessage');
            if (div) { div.innerHTML = ''; div.style.display = 'none'; }
            exportMessageTimer = null;
        }, 10000);
    }
}

function showCacheMessage(message, type = 'info', permanent = false) {
    const msgDiv = document.getElementById('cacheOperationMessage');
    if (!msgDiv) return;
    
    if (cacheMessageTimer) {
        clearTimeout(cacheMessageTimer);
        cacheMessageTimer = null;
    }
    
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
        cacheMessageTimer = setTimeout(() => {
            const div = document.getElementById('cacheOperationMessage');
            if (div) { div.innerHTML = ''; div.style.display = 'none'; }
            cacheMessageTimer = null;
        }, 10000);
    }
}

// ============================================
// МОДАЛЬНЫЕ ОКНА
// ============================================

function showToolsConfirmModal() {
    if (isRefreshing) {
        showCacheMessage('⚠️ ' + '<?= __('tools_cache_already_running') ?>', 'error');
        return;
    }
    document.getElementById('toolsConfirmModal').style.display = 'flex';
}

function closeToolsConfirmModal() {
    document.getElementById('toolsConfirmModal').style.display = 'none';
}

function showToolsResultModal(message, details) {
    const msgEl = document.getElementById('resultMessage');
    const detailsEl = document.getElementById('resultDetails');
    if (msgEl) msgEl.innerHTML = message;
    if (detailsEl) detailsEl.innerHTML = details;
    document.getElementById('toolsResultModal').style.display = 'flex';
}

function closeToolsResultModal() {
    document.getElementById('toolsResultModal').style.display = 'none';
}

function startToolsRefreshCache() {
    closeToolsConfirmModal();
    toolsRefreshCache();
}

// ============================================
// ФУНКЦИИ ДЛЯ БЛОКИРОВКИ/РАЗБЛОКИРОВКИ
// ============================================

function showBlockingOverlay(message) {
    const overlay = document.getElementById('toolsBlockingOverlay');
    const msgSpan = document.getElementById('overlayMessage');
    if (msgSpan) msgSpan.innerHTML = message;
    overlay.style.display = 'flex';
}

function updateBlockingOverlayMessage(message) {
    const msgSpan = document.getElementById('overlayMessage');
    if (msgSpan) msgSpan.innerHTML = message;
}

function hideBlockingOverlay() {
    const overlay = document.getElementById('toolsBlockingOverlay');
    overlay.style.display = 'none';
}

function disableAllInterface() {
    document.querySelectorAll('#section-tools .tools-action-btn').forEach(btn => {
        btn.classList.add('disabled-global');
    });
    
    document.querySelectorAll('.settings-menu .menu-item').forEach(item => {
        if (!item.classList.contains('logout-btn')) {
            item.style.pointerEvents = 'none';
            item.style.opacity = '0.5';
        }
    });
    
    const backLink = document.querySelector('.back-link');
    if (backLink) {
        backLink.style.pointerEvents = 'none';
        backLink.style.opacity = '0.5';
    }
    
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.style.pointerEvents = 'none';
        logoutBtn.style.opacity = '0.5';
    }
    
    const settingsBtn = document.querySelector('.settings-button');
    if (settingsBtn) {
        settingsBtn.style.pointerEvents = 'none';
        settingsBtn.style.opacity = '0.5';
    }
}

function enableAllInterface() {
    document.querySelectorAll('#section-tools .tools-action-btn').forEach(btn => {
        btn.classList.remove('disabled-global');
    });
    
    document.querySelectorAll('.settings-menu .menu-item').forEach(item => {
        item.style.pointerEvents = '';
        item.style.opacity = '';
    });
    
    const backLink = document.querySelector('.back-link');
    if (backLink) {
        backLink.style.pointerEvents = '';
        backLink.style.opacity = '';
    }
    
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.style.pointerEvents = '';
        logoutBtn.style.opacity = '';
    }
    
    const settingsBtn = document.querySelector('.settings-button');
    if (settingsBtn) {
        settingsBtn.style.pointerEvents = '';
        settingsBtn.style.opacity = '';
    }
}

function setToolsButtonLoading(btn, isLoading) {
    if (!btn) return;
    if (isLoading) {
        btn.classList.add('loading');
        btn.disabled = true;
    } else {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// ============================================
// УПРАВЛЕНИЕ КЭШЕМ ГЕОЛОКАЦИИ (ОСНОВНАЯ ФУНКЦИЯ)
// ============================================

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
                
                const remaining = currentTotalAll - currentOffset;
                const processedPercent = Math.round((currentTotalProcessed / currentTotalAll) * 100);
                
                const progressMsg = '🔄 ' + '<?= __('tools_cache_in_progress') ?>: ' + currentTotalProcessed + '/' + currentTotalAll + ' (' + processedPercent + '%) | ' + '<?= __('tools_cache_updated') ?>: ' + currentTotalUpdated;
                updateBlockingOverlayMessage(progressMsg);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            errorOccurred = true;
            errorMessage = error.message;
        }
    }
    
    hideBlockingOverlay();
    
    if (errorOccurred) {
        showToolsResultModal(
            '❌ ' + '<?= __('tools_cache_error') ?>',
            errorMessage
        );
    } else {
        const successMessage = '✅ ' + '<?= __('tools_cache_complete') ?>';
        const details = '📊 ' + '<?= __('tools_cache_files') ?>: ' + currentTotalUpdated + ' / ' + currentTotalAll + ' ' + '<?= __('tools_cache_ips') ?><br>' +
                        '🔄 ' + '<?= __('tools_cache_updated') ?>: ' + currentTotalUpdated;
        showToolsResultModal(successMessage, details);
    }
    
    enableAllInterface();
    setToolsButtonLoading(btn, false);
    isRefreshing = false;
    
    await toolsCheckCache(false);
}

// ============================================
// ОСТАЛЬНЫЕ ФУНКЦИИ
// ============================================

async function toolsClearLog(type) {
    if (isRefreshing) {
        showLogMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error');
        return;
    }
    
    const sd = document.getElementById('toolsStartDate')?.value;
    const ed = document.getElementById('toolsEndDate')?.value;
    
    if (type === 'date_range' && (!sd || !ed)) {
        showLogMessage('<?= __('date_range_required') ?>', 'error');
        return;
    }
    if (type === 'date_range' && sd > ed) {
        showLogMessage('<?= __('date_range_invalid') ?>', 'error');
        return;
    }
    
    const confirmMsg = type === 'all' 
        ? '<?= __('security_clear_all_confirm') ?>' 
        : `<?= __('security_clear_date_confirm') ?> ${sd} ${ed}?`;
    
    if (!confirm(confirmMsg)) return;
    
    const btn = event.target;
    setToolsButtonLoading(btn, true);
    showLogMessage('⏳ ' + '<?= __('tools_cache_clearing') ?>', 'info', true);
    
    try {
        const fd = new FormData();
        fd.append('ajax', 'clear_log');
        fd.append('clearType', type);
        if (type === 'date_range') {
            fd.append('startDate', sd);
            fd.append('endDate', ed);
        }
        
        const response = await fetch('', { method: 'POST', body: fd });
        const data = await response.json();
        
        if (data.success) {
            showLogMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showLogMessage(data.message, 'error');
        }
    } catch (error) {
        showLogMessage('<?= __('msg_save_error') ?>', 'error');
    } finally {
        setToolsButtonLoading(btn, false);
    }
}

async function toolsBackupLog() {
    if (isRefreshing) {
        showLogMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error');
        return;
    }
    
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
    } catch (error) {
        showLogMessage('<?= __('msg_backup_error') ?>', 'error');
    } finally {
        setToolsButtonLoading(btn, false);
    }
}

async function toolsExportProject(type) {
    if (isRefreshing) {
        showExportMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error');
        return;
    }
    
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
    } catch (error) {
        showExportMessage('<?= __('export_error') ?>: ' + error.message, 'error');
    } finally {
        setToolsButtonLoading(btn, false);
    }
}

async function toolsClearCache() {
    if (isRefreshing) {
        showCacheMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error');
        return;
    }
    
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
        } else {
            showCacheMessage(data.message, 'error');
        }
    } catch (error) {
        showCacheMessage('<?= __('msg_save_error') ?>', 'error');
    } finally {
        setToolsButtonLoading(btn, false);
    }
}

async function toolsCheckCache(showMessage = true) {
    if (isRefreshing) {
        if (showMessage) {
            showCacheMessage('⚠️ ' + '<?= __('tools_cache_wait') ?>', 'error');
        }
        return;
    }
    
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
            if (showMessage) {
                showCacheMessage('📊 ' + '<?= __('tools_cache_stats') ?>' + ': ' + data.count + ' ' + '<?= __('tools_cache_files') ?>' + ', ' + data.size_formatted, 'success');
            }
        } else if (showMessage) {
            showCacheMessage(data.message, 'error');
        }
    } catch (error) {
        if (showMessage) {
            showCacheMessage('❌ ' + '<?= __('msg_save_error') ?>', 'error');
        }
    } finally {
        if (showMessage) setToolsButtonLoading(btn, false);
    }
}

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('section-tools');
    if (section && section.classList.contains('active')) {
        setTimeout(function() {
            toolsCheckCache(false);
        }, 100);
    }
});

const toolsObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const target = mutation.target;
            if (target.id === 'section-tools' && target.classList.contains('active')) {
                setTimeout(function() {
                    toolsCheckCache(false);
                }, 100);
            }
        }
    });
});

const toolsSection = document.getElementById('section-tools');
if (toolsSection) {
    toolsObserver.observe(toolsSection, { attributes: true });
}
</script>
<?php
?>