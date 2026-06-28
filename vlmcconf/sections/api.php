<?php
// ============================================
// ФАЙЛ: sections/api.php
// ВЕРСИЯ: 5.1.0
// ДАТА: 2026-06-05
// @description: Раздел API (в разработке)
// @description: API section (under development)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}
?>

<div id="section-api" class="settings-section <?= $activeSection === 'api' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🖥️ <?= __('api') ?></span>
    </div>

<?php
// Проверка прав
if (!check_permission(PERM_API_VIEW)) {
    echo '<div class="alert alert-danger">' . __('access_denied') . '</div>';
    echo '</div>';
    return;
}

// Стилизованный баннер "Раздел в разработке"
?>
<div class="under-development-banner" style="background: linear-gradient(135deg, <?= $themeCSS['primary'] ?>10 0%, <?= $themeCSS['primary'] ?>05 100%); border-left: 4px solid <?= $themeCSS['primary'] ?>; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
    <i class="fas fa-code fa-2x" style="color: <?= $themeCSS['primary'] ?>;"></i>
    <div style="flex: 1;">
        <strong style="font-size: 14px;"><?= __('section_under_development') ?></strong>
        <p style="margin: 4px 0 0 0; font-size: 12px; color: #8aa0bb;"><?= __('api_coming_soon') ?></p>
    </div>
    <span class="badge" style="background: <?= $themeCSS['primary'] ?>20; color: <?= $themeCSS['primary'] ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px;">Coming soon</span>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo __('api'); ?></h5>
    </div>
    <div class="card-body">
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-key fa-2x mb-2 text-muted"></i>
                        <h6><?php echo __('api_keys_management'); ?></h6>
                        <p class="small text-muted"><?php echo __('api_keys_management_desc'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-book fa-2x mb-2 text-muted"></i>
                        <h6><?php echo __('api_documentation'); ?></h6>
                        <p class="small text-muted"><?php echo __('api_documentation_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>