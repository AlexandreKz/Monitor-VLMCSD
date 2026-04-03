<?php
// ============================================
// ФАЙЛ: sections/groups.php
// ВЕРСИЯ: 1.3.0
// ДАТА: 2026-03-27
// @description: Секция "Группы" с проверкой прав
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'groups.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права
$canViewGroups = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_GROUPS_VIEW);
$canEditGroups = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_GROUPS_EDIT);

// Если нет прав на просмотр — не показываем секцию
if (!$canViewGroups) {
    echo '<div class="settings-section" id="section-groups"><div class="section-title"><span>👥 ' . __('groups_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<div id="section-groups" class="settings-section <?= $activeSection === 'groups' ? 'active' : '' ?>">
    <div class="section-title">
        <span>👥 <?= __('groups_title') ?></span>
        <?php if ($activeSection === 'groups' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Добавление группы (только если есть права на редактирование) -->
    <?php if ($canEditGroups): ?>
    <div class="settings-card">
        <div class="settings-card-title">➕ <?= __('groups_add') ?></div>
        <form method="POST" action="vlmcconf.php?section=groups" style="display: flex; gap: 8px; align-items: flex-end;">
            <input type="hidden" name="action" value="add_group">
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label><?= __('label_group_name') ?></label>
                <input type="text" name="groupName" class="form-control" placeholder="<?= __('groups_name_placeholder') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label><?= __('label_group_color') ?></label>
                <input type="color" name="groupColor" value="#3498db" class="color-picker">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom: 0;">➕ <?= __('add') ?></button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Список групп -->
    <div class="settings-card">
        <div class="settings-card-title">🎨 <?= __('groups_existing') ?></div>
        <div class="groups-grid">
            <?php foreach ($config['groupColors'] as $group => $color): ?>
            <div class="group-card">
                <div class="group-color" style="background: <?= htmlspecialchars($color) ?>;"></div>
                <div class="group-info">
                    <div class="group-name"><?= __($group) ?></div>
                    <div class="group-devices-count"><?= count($config['devices'][$group] ?? []) ?> <?= __('groups_devices_count') ?></div>
                </div>
                <div class="group-actions">
                    <?php if ($canEditGroups): ?>
                    <form method="POST" action="vlmcconf.php?section=groups" style="display: inline;">
                        <input type="hidden" name="action" value="save_group_colors">
                        <input type="color" name="color_<?= htmlspecialchars($group) ?>" value="<?= htmlspecialchars($color) ?>" class="color-picker" onchange="this.form.submit()" title="<?= __('edit') ?>">
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($canEditGroups && !isset($defaultGroupColors[$group])): ?>
                    <form method="POST" action="vlmcconf.php?section=groups" style="display: inline;" onsubmit="return confirm('<?= __('groups_delete_confirm') ?>');">
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="groupName" value="<?= htmlspecialchars($group) ?>">
                        <button type="submit" class="btn btn-danger btn-small" title="<?= __('delete') ?>">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>