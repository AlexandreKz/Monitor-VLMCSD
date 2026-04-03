<?php
// ============================================
// ФАЙЛ: sections/info.php
// ВЕРСИЯ: 1.4.0
// ДАТА: 2026-03-26
// @description: Секция "Информация" (доступ по праву PERM_INFO_VIEW)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'info.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права на просмотр информации (используем глобальную функцию hasPermission)
$canViewInfo = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_INFO_VIEW);

if (!$canViewInfo) {
    echo '<div class="settings-section" id="section-info"><div class="section-title"><span>ℹ️ ' . __('info_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-info" class="settings-section <?= $activeSection === 'info' ? 'active' : '' ?>">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
        <span>ℹ️ <?= __('info_title') ?></span>
        <?php if ($activeSection === 'info' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <button class="btn btn-small btn-primary" onclick="refreshFileInfo()" style="font-size: 11px; padding: 4px 10px; margin-left: auto;">🔄 <?= __('refresh') ?></button>
    </div>
    
    <!-- Блок с версией, темой, конфигом и кнопкой сброса -->
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
</style>