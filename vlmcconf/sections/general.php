<?php
// ============================================
// ФАЙЛ: sections/general.php
// ВЕРСИЯ: 1.6.0
// ДАТА: 2026-03-28
// @description: Секция "Общие настройки" (доступна всем)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'general.php') {
    http_response_code(403);
    exit('Access denied');
}

// Права на редактирование общих настроек (пока все могут редактировать)
$canEditGeneral = true;
?>

<div id="section-general" class="settings-section <?= $activeSection === 'general' ? 'active' : '' ?>">
    <div class="section-title">
        <span>⚙️ <?= __('general_title') ?></span>
        <?php if ($activeSection === 'general' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Тема оформления -->
    <div class="settings-card">
        <div class="settings-card-title">🎨 <?= __('theme_title') ?></div>
        <form method="POST" id="themeForm">
            <input type="hidden" name="action" value="save_theme">
            
            <div class="theme-selector">
                <div class="theme-options">
                    <?php foreach ($availableThemes as $key => $name): ?>
                    <div class="theme-option <?= $key ?> <?= $config['theme'] === $key ? 'selected' : '' ?>" onclick="selectThemeAndPreview('<?= $key ?>')">
                        <?= __($name) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="theme-preview" id="themePreview">
                    <div class="theme-preview-title">🎨 <?= __('theme_preview') ?></div>
                    <div class="theme-preview-content" id="themePreviewContent">
                        <div class="preview-buttons">
                            <span class="preview-btn" id="previewBtnPrimary" style="background: <?= $themeCSS['primary'] ?>;"><?= __('theme_preview_button') ?></span>
                            <span class="preview-btn" id="previewBtnSuccess" style="background: <?= $themeCSS['success'] ?>;"><?= __('theme_preview_success') ?></span>
                            <span class="preview-btn" id="previewBtnDanger" style="background: <?= $themeCSS['danger'] ?>;"><?= __('theme_preview_error') ?></span>
                        </div>
                        <div class="preview-card" id="previewCard">
                            <?= __('theme_preview_card') ?>
                        </div>
                        <div class="preview-muted" id="previewMuted">
                            <?= __('theme_preview_muted') ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="theme" id="selectedTheme" value="<?= $config['theme'] ?>">
            <button type="submit" class="btn btn-primary">💾 <?= __('theme_apply') ?></button>
        </form>
    </div>
    
    <!-- Язык интерфейса и путь к логу в одной строке -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <!-- Блок: Язык -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-title">🌐 <?= __('language_title') ?></div>
            <form method="POST">
                <input type="hidden" name="action" value="save_language">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label><?= __('label_language') ?></label>
                    <div style="display: flex; gap: 20px; align-items: center; margin-top: 5px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="language" value="ru" <?= ($config['language'] ?? 'ru') === 'ru' ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            <span style="font-size: 13px;">🇷🇺 Русский</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="language" value="en" <?= ($config['language'] ?? 'ru') === 'en' ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            <span style="font-size: 13px;">🇬🇧 English</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-small">💾 <?= __('language_save') ?></button>
            </form>
        </div>
        
        <!-- Блок: Путь к логу -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-title">📁 <?= __('log_title') ?></div>
            <form method="POST">
                <input type="hidden" name="action" value="save_log_path">
                <div class="form-group" style="margin-bottom: 8px;">
                    <label><?= __('label_log_path') ?></label>
                    <input type="text" name="logPath" class="form-control" value="<?= htmlspecialchars($config['logPath']) ?>" style="font-size: 12px;">
                </div>
                <div class="path-preview" style="margin: 0 0 8px 0; padding: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 11px;">
                        <strong><?= __('security_status') ?>:</strong>
                        <?php if ($logFileExists): ?>
                            <span style="color: <?= $themeCSS['success'] ?>;">✓ <?= __('log_status_found') ?></span>
                        <?php else: ?>
                            <span style="color: <?= $themeCSS['danger'] ?>;">✗ <?= __('log_status_not_found') ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 4px; font-size: 10px; color: #8aa0bb; word-break: break-all;"><?= htmlspecialchars($fullLogPath) ?></div>
                </div>
                <button type="submit" class="btn btn-primary btn-small">💾 <?= __('log_save') ?></button>
            </form>
        </div>
    </div>
</div>

<style>
.theme-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
</style>