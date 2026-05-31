<?php
// ============================================
// ФАЙЛ: sections/info.php
// ВЕРСИЯ: 1.5.0
// ДАТА: 2026-05-31
// @description: Секция "Информация" (доступ по праву PERM_INFO_VIEW)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'info.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права на просмотр информации
$canViewInfo = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_INFO_VIEW);

if (!$canViewInfo) {
    echo '<div class="settings-section" id="section-info"><div class="section-title"><span>ℹ️ ' . __('info_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-info" class="settings-section <?= $activeSection === 'info' ? 'active' : '' ?>">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span>ℹ️ <?= __('info_title') ?></span>
        <button class="btn btn-primary btn-small" onclick="refreshFileInfo()" style="font-size: 11px; padding: 4px 10px;">🔄 <?= __('refresh') ?></button>
        <?php if ($activeSection === 'info' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Блок с версией, темой, конфигом -->
    <div style="background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 10px; margin-bottom: 20px; font-size: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; flex: 1;">
                <div><strong style="color: #8aa0bb;"><?= __('info_version') ?>:</strong> <?= CONFIG_VERSION ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_theme') ?>:</strong> <?= __($themeCSS['name']) ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_config') ?>:</strong> <?= htmlspecialchars(basename($configFile)) ?></div>
                <div><strong style="color: #8aa0bb;"><?= __('info_log') ?>:</strong> <span style="color: <?= $logFileExists ? $themeCSS['success'] : $themeCSS['danger'] ?>;"><?= $logFileExists ? '✓' : '✗' ?></span></div>
            </div>
        </div>
    </div>
    
    <!-- Информация о сервере -->
    <div class="settings-card">
        <div class="settings-card-title">🖥️ <?= __('info_server') ?></div>
        <div class="server-info-grid">
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_os') ?>:</span><span><?= htmlspecialchars($osName) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_kernel') ?>:</span><span><?= htmlspecialchars(php_uname('r')) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_host') ?>:</span><span><?= htmlspecialchars($hostname) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_ip') ?>:</span><span><?= htmlspecialchars($serverIp) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_web_server') ?>:</span><span><?= htmlspecialchars($serverSoftware) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_php') ?>:</span><span><?= htmlspecialchars($phpVersion) ?></span></div>
            <div class="server-info-item"><span style="color: #8aa0bb;"><?= __('info_uptime') ?>:</span><span><?= $uptimeFormatted ?></span></div>
        </div>
    </div>
    
    <!-- Структура проекта -->
    <?php renderFileStructure($themeCSS, $fileVersions, $configFile); ?>
</div>

<style>
.server-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 30px;
    font-size: 12px;
}

.server-info-item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
}

.settings-card {
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.settings-card-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: #8aa0bb;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
}
.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-small {
    padding: 4px 10px;
    font-size: 11px;
}
</style>

<script>
function refreshFileInfo() {
    document.querySelectorAll('.file-row').forEach(row => {
        const path = row.dataset.path;
        const sizeCell = row.querySelector('.file-size');
        const mtimeCell = row.querySelector('.file-mtime');
        if (!path) return;
        
        const fd = new FormData();
        fd.append('ajax', 'file_info');
        fd.append('path', path);
        
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if (d.success) { 
                    if (sizeCell && sizeCell.textContent !== d.size) sizeCell.textContent = d.size; 
                    if (mtimeCell && mtimeCell.textContent !== d.date) mtimeCell.textContent = d.date; 
                } 
            })
            .catch(e => console.error('File info error:', e));
    });
}

// Инициализация при активации секции
document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('section-info');
    if (section && section.classList.contains('active')) {
        setTimeout(refreshFileInfo, 500);
    }
});

const infoObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const target = mutation.target;
            if (target.id === 'section-info' && target.classList.contains('active')) {
                setTimeout(refreshFileInfo, 500);
            }
        }
    });
});

const infoSection = document.getElementById('section-info');
if (infoSection) {
    infoObserver.observe(infoSection, { attributes: true });
}
</script>
<?php
?>