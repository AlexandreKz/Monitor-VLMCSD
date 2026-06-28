<?php
// ============================================
// ФАЙЛ: sections/groups.php
// ВЕРСИЯ: 3.6.0
// ДАТА: 2026-06-05
// @description: Секция "Группы" с модальным окном (редактирование имени, иконки, цвета, удаление)
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

// Подключаем менеджер emoji для получения списка
require_once __DIR__ . '/../vlmcinc/emoji_manager.php';
$fullEmojiList = get_emoji_list();
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
        <form method="POST" action="vlmcconf.php?section=groups" style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add_group">
            <div class="form-group" style="flex: 2; margin-bottom: 0;">
                <label><?= __('label_group_name') ?></label>
                <input type="text" name="groupName" class="form-control" placeholder="<?= __('groups_name_placeholder') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label><?= __('label_group_icon') ?></label>
                <button type="button" class="btn-emoji-picker" id="emojiPickerBtnAdd">📁</button>
                <input type="hidden" name="groupIcon" id="selectedIconAdd" value="📁">
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
            <?php foreach ($config['groupColors'] as $group => $data): 
                // Пропускаем скрытую группу-заглушку
                if ($group === '__orphaned__') continue;
                
                // Поддержка старого и нового формата
                $color = is_array($data) ? $data['color'] : $data;
                $icon = (is_array($data) && isset($data['icon'])) ? $data['icon'] : '📁';
            ?>
            <div class="group-card" data-group="<?= htmlspecialchars($group) ?>">
                <div class="group-color" style="background: <?= htmlspecialchars($color) ?>;"></div>
                <div class="group-info">
                    <div class="group-name">
                        <span class="group-name-text"><?= htmlspecialchars($icon) ?> <?= __($group) ?></span>
                        <?php if ($canEditGroups): ?>
                        <button type="button" class="btn-edit-group" data-group="<?= htmlspecialchars($group) ?>" data-icon="<?= htmlspecialchars($icon) ?>" data-color="<?= htmlspecialchars($color) ?>" title="<?= __('edit_group') ?>">✎</button>
                        <?php endif; ?>
                    </div>
                    <div class="group-devices-count"><?= count($config['devices'][$group] ?? []) ?> <?= __('groups_devices_count') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования группы -->
<div id="editGroupModal" class="modal" style="display: none;">
    <div class="modal-content" style="width: 470px; max-width: 90%;">
        <div class="modal-header">
            <h2>✎ <?= __('edit_group') ?></h2>
            <span class="modal-close" onclick="closeEditGroupModal()">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Название группы с иконкой слева -->
            <div class="form-group">
                <label><?= __('label_group_name') ?></label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span id="editGroupIconPreview" style="font-size: 20px;">📁</span>
                    <input type="text" id="editGroupInput" class="form-control" style="flex: 1;">
                </div>
                <input type="hidden" id="editGroupOldName">
                <input type="hidden" id="editGroupIconValue" value="📁">
            </div>
            
            <!-- Иконка и цвет на одной строке, раздельные подписи -->
            <div class="form-group">
                <div style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label><?= __('label_group_icon') ?></label>
                        <button type="button" class="btn-emoji-picker-modal" id="editGroupIconBtn" title="<?= __('select_emoji') ?>">📁</button>
                    </div>
                    <div style="flex: 1;">
                        <label><?= __('label_group_color') ?></label>
                        <input type="color" id="editGroupColor" class="color-picker-modal" value="#3498db">
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button class="btn btn-primary" onclick="saveGroupChanges()">💾 <?= __('save') ?></button>
                <button class="btn btn-danger" onclick="deleteGroup()">🗑️ <?= __('delete') ?></button>
                <button class="btn btn-secondary" onclick="closeEditGroupModal()">❌ <?= __('cancel') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно выбора emoji -->
<div id="emojiModal" class="emoji-modal" style="display: none;">
    <div class="emoji-modal-content">
        <div class="emoji-modal-header">
            <h3>😀 <?= __('select_emoji') ?></h3>
            <span class="emoji-modal-close" onclick="closeEmojiModal()">&times;</span>
        </div>
        <div class="emoji-grid" id="emojiGrid"></div>
    </div>
</div>

<style>
.groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
    max-height: 450px;
    overflow-y: auto;
    padding: 5px;
}

.group-card {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.group-color {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    flex-shrink: 0;
}

.group-info {
    flex: 1;
}

.group-name {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 2px;
    color: <?= $themeCSS['text'] ?>;
    display: flex;
    align-items: center;
    gap: 6px;
}

.group-name-text {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-edit-group {
    background: none;
    border: none;
    color: #8aa0bb;
    cursor: pointer;
    font-size: 12px;
    padding: 2px 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.btn-edit-group:hover {
    background: <?= $themeCSS['hover'] ?>;
    color: <?= $themeCSS['text'] ?>;
}

.group-devices-count {
    font-size: 11px;
    color: #8aa0bb;
}

.btn-emoji-picker,
.color-picker {
    height: 32px;
    padding: 0;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 4px;
    background: <?= $themeCSS['input'] ?>;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    box-sizing: border-box;
}

.btn-emoji-picker {
    width: 40px;
    font-size: 16px;
    overflow: hidden;
    white-space: nowrap;
}

.color-picker {
    width: 32px;
    height: 32px;
    padding: 2px;
}

.btn-emoji-picker:hover,
.color-picker:hover {
    background: <?= $themeCSS['hover'] ?>;
}

/* Модальное окно выбора emoji */
.emoji-modal {
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

.emoji-modal-content {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
    width: 500px;
    max-width: 90%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    border: 1px solid <?= $themeCSS['border'] ?>;
}

.emoji-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
}

.emoji-modal-header h3 {
    margin: 0;
    font-size: 16px;
    color: <?= $themeCSS['text'] ?>;
}

.emoji-modal-close {
    color: #8aa0bb;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}

.emoji-modal-close:hover {
    color: <?= $themeCSS['text'] ?>;
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(9, 1fr);
    gap: 4px;
    padding: 16px;
    overflow-y: auto;
    max-height: 400px;
}

.emoji-item {
    font-size: 18px;
    padding: 4px;
    text-align: center;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.emoji-item:hover {
    background: <?= $themeCSS['hover'] ?>;
    transform: scale(1.1);
}

/* Модальное окно редактирования группы */
.modal {
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

.modal-content {
    background: <?= $themeCSS['card'] ?>;
    padding: 20px;
    border-radius: 12px;
    width: 470px;
    max-width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    color: <?= $themeCSS['text'] ?>;
    animation: modalFadeIn 0.3s;
    border: 2px solid <?= $themeCSS['primary'] ?>;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
}

.modal-header h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: <?= $themeCSS['primary'] ?>;
}

.modal-close {
    color: #8aa0bb;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
}

.modal-close:hover {
    color: <?= $themeCSS['text'] ?>;
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 4px;
    color: #8aa0bb;
}

.form-control {
    width: 100%;
    padding: 6px 10px;
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 4px;
    color: <?= $themeCSS['text'] ?>;
    font-size: 12px;
}

.form-control:focus {
    outline: none;
    border-color: <?= $themeCSS['primary'] ?>;
}

.btn-emoji-picker-modal {
    width: 36px;
    height: 32px;
    font-size: 18px;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 4px;
    background: <?= $themeCSS['input'] ?>;
    cursor: pointer;
}

.color-picker-modal {
    width: 50px;
    height: 32px;
    padding: 2px;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 4px;
    background: <?= $themeCSS['input'] ?>;
    cursor: pointer;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-danger {
    background: <?= $themeCSS['danger'] ?>;
    color: white;
}

.btn-danger:hover {
    opacity: 0.8;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Переменные для модального окна выбора emoji
let currentPickerTarget = null; // 'add' or 'editGroup'

// Переменные для модального окна редактирования группы
let currentEditGroup = null;
let originalName = '';
let originalIcon = '';
let originalColor = '';

// Открыть модальное окно выбора emoji
function openEmojiModal(target, currentIcon) {
    currentPickerTarget = target;
    
    const emojiList = <?= json_encode($fullEmojiList) ?>;
    const grid = document.getElementById('emojiGrid');
    
    let html = '';
    for (let i = 0; i < emojiList.length; i++) {
        let selectedStyle = '';
        if (emojiList[i] === currentIcon) {
            selectedStyle = 'style="background: ' + '<?= $themeCSS['primary'] ?>' + '20; border: 1px solid ' + '<?= $themeCSS['primary'] ?>' + ';"';
        }
        html += `<div class="emoji-item" onclick="selectEmoji('${emojiList[i].replace(/'/g, "\\'")}')" ${selectedStyle}>${emojiList[i]}</div>`;
    }
    grid.innerHTML = html;
    
    document.getElementById('emojiModal').style.display = 'flex';
}

// Закрыть модальное окно выбора emoji
function closeEmojiModal() {
    document.getElementById('emojiModal').style.display = 'none';
    currentPickerTarget = null;
}

// Выбрать emoji
function selectEmoji(emoji) {
    if (currentPickerTarget === 'add') {
        const btn = document.getElementById('emojiPickerBtnAdd');
        if (btn) btn.innerHTML = emoji;
        const hiddenInput = document.getElementById('selectedIconAdd');
        if (hiddenInput) hiddenInput.value = emoji;
    } else if (currentPickerTarget === 'editGroup') {
        const btn = document.getElementById('editGroupIconBtn');
        const preview = document.getElementById('editGroupIconPreview');
        const hiddenInput = document.getElementById('editGroupIconValue');
        if (btn) btn.innerHTML = emoji;
        if (preview) preview.innerHTML = emoji;
        if (hiddenInput) hiddenInput.value = emoji;
    }
    closeEmojiModal();
}

// Открыть модальное окно редактирования группы
function openEditGroupModal(groupName, icon, color) {
    currentEditGroup = groupName;
    originalName = groupName;
    originalIcon = icon;
    originalColor = color;
    
    document.getElementById('editGroupInput').value = groupName;
    document.getElementById('editGroupOldName').value = groupName;
    document.getElementById('editGroupIconPreview').innerHTML = icon;
    document.getElementById('editGroupIconValue').value = icon;
    document.getElementById('editGroupIconBtn').innerHTML = icon;
    document.getElementById('editGroupColor').value = color;
    document.getElementById('editGroupModal').style.display = 'flex';
    document.getElementById('editGroupInput').focus();
}

// Закрыть модальное окно редактирования группы
function closeEditGroupModal() {
    document.getElementById('editGroupModal').style.display = 'none';
    currentEditGroup = null;
}

// Сохранить изменения группы (имя, иконка, цвет)
function saveGroupChanges() {
    const newName = document.getElementById('editGroupInput').value.trim();
    const oldName = document.getElementById('editGroupOldName').value;
    const newIcon = document.getElementById('editGroupIconValue').value;
    const newColor = document.getElementById('editGroupColor').value;
    
    if (newName === '') {
        alert('<?= __('name_required') ?>');
        return;
    }
    
    // Проверяем, есть ли изменения
    if (newName === oldName && newIcon === originalIcon && newColor === originalColor) {
        closeEditGroupModal();
        return;
    }
    
    // Отправляем AJAX запрос для сохранения всех изменений
    const fd = new FormData();
    fd.append('ajax', 'save_group_changes');
    fd.append('old_name', oldName);
    fd.append('new_name', newName);
    fd.append('new_icon', newIcon);
    fd.append('new_color', newColor);
    
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

// Удалить группу
function deleteGroup() {
    const groupName = document.getElementById('editGroupOldName').value;
    
    if (!confirm('<?= __('groups_delete_confirm') ?> ' + groupName + '?')) {
        return;
    }
    
    const fd = new FormData();
    fd.append('ajax', 'delete_group');
    fd.append('group_name', groupName);
    
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

// Назначить обработчики
document.addEventListener('DOMContentLoaded', function() {
    // Кнопка для добавления новой группы (выбор emoji)
    const addBtn = document.getElementById('emojiPickerBtnAdd');
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentIcon = document.getElementById('selectedIconAdd').value || '📁';
            openEmojiModal('add', currentIcon);
        });
    }
    
    // Кнопка выбора emoji в модальном окне редактирования
    const editIconBtn = document.getElementById('editGroupIconBtn');
    if (editIconBtn) {
        editIconBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentIcon = document.getElementById('editGroupIconValue').value || '📁';
            openEmojiModal('editGroup', currentIcon);
        });
    }
    
    // Кнопки редактирования группы (карандаш)
    document.querySelectorAll('.btn-edit-group').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const groupName = this.getAttribute('data-group');
            const icon = this.getAttribute('data-icon') || '📁';
            const color = this.getAttribute('data-color') || '#3498db';
            openEditGroupModal(groupName, icon, color);
        });
    });
    
    // Закрытие модальных окон по клику вне их
    window.onclick = function(event) {
        const emojiModal = document.getElementById('emojiModal');
        const editModal = document.getElementById('editGroupModal');
        if (event.target === emojiModal) closeEmojiModal();
        if (event.target === editModal) closeEditGroupModal();
    };
});
</script>