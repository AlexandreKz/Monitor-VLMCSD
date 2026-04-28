<?php
// ============================================
// ФАЙЛ: sections/security.php
// ВЕРСИЯ: 4.0.0
// ДАТА: 2026-04-27
// @description: Секция "Безопасность" — управление пользователями
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'security.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права доступа к секциям
$canViewUsers = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_USERS_VIEW);
$canEditUsers = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_USERS_EDIT);

// Если нет прав на просмотр — не показываем секцию
if (!$canViewUsers) {
    echo '<div class="settings-section" id="section-security"><div class="section-title"><span>🔒 ' . __('security_title') . '</span></div><div style="text-align: center; padding: 40px; color: #8aa0bb;">🔒 ' . __('access_denied') . '</div></div>';
    return;
}
?>

<script>
// Передаем константы прав из PHP в JavaScript
const PERM_GROUPS_VIEW = <?= PERM_GROUPS_VIEW ?>;
const PERM_GROUPS_EDIT = <?= PERM_GROUPS_EDIT ?>;
const PERM_DEVICES_VIEW = <?= PERM_DEVICES_VIEW ?>;
const PERM_DEVICES_EDIT = <?= PERM_DEVICES_EDIT ?>;
const PERM_LOGS_VIEW = <?= PERM_LOGS_VIEW ?>;
const PERM_LOGS_EDIT = <?= PERM_LOGS_EDIT ?>;
const PERM_USERS_VIEW = <?= PERM_USERS_VIEW ?>;
const PERM_USERS_EDIT = <?= PERM_USERS_EDIT ?>;
const PERM_INFO_VIEW = <?= PERM_INFO_VIEW ?>;
</script>

<div id="section-security" class="settings-section <?= $activeSection === 'security' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🔒 <?= __('security_title') ?></span>
        <?php if ($activeSection === 'security' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Управление пользователями -->
    <div class="settings-card">
        <div class="settings-card-title">👥 <?= __('users_management') ?></div>
        
        <!-- Кнопка добавления пользователя (только если есть права на редактирование) -->
        <?php if ($canEditUsers): ?>
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" onclick="showAddUserModal()">➕ <?= __('users_add') ?></button>
        </div>
        <?php endif; ?>
        
        <!-- Список пользователей -->
        <div id="usersList" style="max-height: 400px; overflow-y: auto;">
            <div style="display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 1fr 0.8fr; gap: 10px; padding: 8px 0; border-bottom: 2px solid <?= $themeCSS['border'] ?>; font-weight: 600; font-size: 11px;">
                <span><?= __('users_username') ?></span>
                <span><?= __('users_role') ?></span>
                <span><?= __('users_created') ?></span>
                <span><?= __('users_last_login') ?></span>
                <span><?= __('actions') ?></span>
            </div>
            <div id="usersListContainer">⏳ <?= __('loading') ?></div>
        </div>
    </div>
</div>

<!-- Модальное окно для выбора прав при добавлении/редактировании пользователя -->
<div id="permissionsModal" class="modal" style="display: none; z-index: 100000;">
    <div class="modal-content" style="width: 550px; z-index: 100001;">
        <div class="modal-header">
            <h2 id="permissionsModalTitle"><?= __('permissions_title_add') ?></h2>
            <span class="modal-close" onclick="closePermissionsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="modalErrorMessage" class="modal-message error" style="display: none; margin-bottom: 15px;"></div>
            <input type="hidden" id="editUserId" value="">
            
            <form id="userPermissionsForm" onsubmit="return false;">
                <div class="form-group" id="usernameField">
                    <label><?= __('permissions_username') ?></label>
                    <input type="text" id="modalUsername" class="form-control" placeholder="<?= __('users_username') ?>" autocomplete="username">
                </div>
                <div class="form-group" id="passwordField">
                    <label><?= __('permissions_password') ?></label>
                    <div class="password-wrapper">
                        <input type="password" id="modalPassword" class="form-control" placeholder="<?= __('users_password') ?>" autocomplete="new-password">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordField('modalPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_groups') ?></label>
                    <select id="permGroups" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                        <option value="2"><?= __('permissions_edit') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_devices') ?></label>
                    <select id="permDevices" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                        <option value="2"><?= __('permissions_edit') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_logs') ?></label>
                    <select id="permLogs" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                        <option value="2"><?= __('permissions_edit') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_users') ?></label>
                    <select id="permUsers" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                        <option value="2"><?= __('permissions_edit') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_info') ?></label>
                    <select id="permInfo" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                    </select>
                </div>
                
                <div id="changePasswordBlock" style="display: none;">
                    <div class="form-group">
                        <label><?= __('permissions_new_password') ?></label>
                        <div class="password-wrapper">
                            <input type="password" id="newUserPassword" class="form-control" placeholder="<?= __('new_password') ?>" autocomplete="new-password">
                            <button type="button" class="toggle-password-btn" onclick="togglePasswordField('newUserPassword')">👁️</button>
                        </div>
                    </div>
                </div>
            </form>
            
            <button class="btn btn-primary" onclick="saveUserPermissions()" style="margin-top: 10px; display: block; margin-left: auto; margin-right: auto; width: 50%;"><?= __('permissions_save') ?></button>
        </div>
    </div>
</div>

<style>
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

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 4px;
    color: #8aa0bb;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 6px;
    color: <?= $themeCSS['text'] ?>;
    font-size: 13px;
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

.modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(3px);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: <?= $themeCSS['card'] ?>;
    padding: 25px;
    border-radius: 12px;
    width: 550px;
    max-width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    color: <?= $themeCSS['text'] ?>;
    animation: modalFadeIn 0.3s;
    border: 2px solid <?= $themeCSS['primary'] ?>;
    position: relative;
    z-index: 100001;
}

.modal-message {
    padding: 10px;
    border-radius: 6px;
    font-size: 12px;
    text-align: center;
    margin-bottom: 15px;
    position: relative;
    z-index: 100002;
}

.modal-message.error {
    background: <?= $themeCSS['danger'] ?>20;
    color: <?= $themeCSS['danger'] ?>;
    border: 1px solid <?= $themeCSS['danger'] ?>;
}

.modal-message.success {
    background: <?= $themeCSS['success'] ?>20;
    color: <?= $themeCSS['success'] ?>;
    border: 1px solid <?= $themeCSS['success'] ?>;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
}

.modal-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: <?= $themeCSS['primary'] ?>;
}

.modal-close {
    color: #8aa0bb;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.modal-close:hover {
    color: <?= $themeCSS['text'] ?>;
}

.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    width: 100%;
    padding: 8px 35px 8px 12px;
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 6px;
    color: <?= $themeCSS['text'] ?>;
    font-size: 13px;
    box-sizing: border-box;
}

.password-wrapper input:focus {
    outline: none;
    border-color: <?= $themeCSS['primary'] ?>;
}

.toggle-password-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #8aa0bb;
    padding: 0;
    margin: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-password-btn:hover {
    color: <?= $themeCSS['primary'] ?>;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// ============================================
// УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ
// ============================================

let currentEditUserId = null;
let currentEditMode = 'add';

function togglePasswordField(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.type = field.type === 'password' ? 'text' : 'password';
    }
}

function showModalMessage(message, type) {
    const msgDiv = document.getElementById('modalErrorMessage');
    if (msgDiv) {
        msgDiv.innerHTML = message;
        msgDiv.className = 'modal-message ' + type;
        msgDiv.style.display = 'block';
        setTimeout(() => {
            msgDiv.style.display = 'none';
        }, 5000);
    }
}

function getPermissionsFromSelects() {
    let perms = 0;
    const groupsLevel = parseInt(document.getElementById('permGroups').value);
    const devicesLevel = parseInt(document.getElementById('permDevices').value);
    const logsLevel = parseInt(document.getElementById('permLogs').value);
    const usersLevel = parseInt(document.getElementById('permUsers').value);
    const infoLevel = parseInt(document.getElementById('permInfo').value);
    
    if (groupsLevel == 2) perms |= PERM_GROUPS_VIEW | PERM_GROUPS_EDIT;
    else if (groupsLevel == 1) perms |= PERM_GROUPS_VIEW;
    
    if (devicesLevel == 2) perms |= PERM_DEVICES_VIEW | PERM_DEVICES_EDIT;
    else if (devicesLevel == 1) perms |= PERM_DEVICES_VIEW;
    
    if (logsLevel == 2) perms |= PERM_LOGS_VIEW | PERM_LOGS_EDIT;
    else if (logsLevel == 1) perms |= PERM_LOGS_VIEW;
    
    if (usersLevel == 2) perms |= PERM_USERS_VIEW | PERM_USERS_EDIT;
    else if (usersLevel == 1) perms |= PERM_USERS_VIEW;
    
    if (infoLevel == 1) perms |= PERM_INFO_VIEW;
    
    return perms;
}

function setPermissionsSelects(permissions) {
    if (permissions & PERM_GROUPS_EDIT) document.getElementById('permGroups').value = 2;
    else if (permissions & PERM_GROUPS_VIEW) document.getElementById('permGroups').value = 1;
    else document.getElementById('permGroups').value = 0;
    
    if (permissions & PERM_DEVICES_EDIT) document.getElementById('permDevices').value = 2;
    else if (permissions & PERM_DEVICES_VIEW) document.getElementById('permDevices').value = 1;
    else document.getElementById('permDevices').value = 0;
    
    if (permissions & PERM_LOGS_EDIT) document.getElementById('permLogs').value = 2;
    else if (permissions & PERM_LOGS_VIEW) document.getElementById('permLogs').value = 1;
    else document.getElementById('permLogs').value = 0;
    
    if (permissions & PERM_USERS_EDIT) document.getElementById('permUsers').value = 2;
    else if (permissions & PERM_USERS_VIEW) document.getElementById('permUsers').value = 1;
    else document.getElementById('permUsers').value = 0;
    
    if (permissions & PERM_INFO_VIEW) document.getElementById('permInfo').value = 1;
    else document.getElementById('permInfo').value = 0;
}

function showAddUserModal() {
    currentEditMode = 'add';
    currentEditUserId = null;
    document.getElementById('permissionsModalTitle').innerHTML = '<?= __('permissions_title_add') ?>';
    document.getElementById('usernameField').style.display = 'block';
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('changePasswordBlock').style.display = 'none';
    document.getElementById('modalUsername').value = '';
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalErrorMessage').style.display = 'none';
    
    setPermissionsSelects(PERM_DEVICES_VIEW);
    
    document.getElementById('permissionsModal').style.display = 'flex';
}

function editUser(userId, username, permissions) {
    currentEditMode = 'edit';
    currentEditUserId = userId;
    document.getElementById('permissionsModalTitle').innerHTML = '<?= __('permissions_title_edit') ?> ' + username;
    document.getElementById('usernameField').style.display = 'block';
    document.getElementById('passwordField').style.display = 'none';
    document.getElementById('changePasswordBlock').style.display = 'block';
    document.getElementById('modalUsername').value = username;
    document.getElementById('newUserPassword').value = '';
    document.getElementById('modalErrorMessage').style.display = 'none';
    
    setPermissionsSelects(permissions);
    
    document.getElementById('permissionsModal').style.display = 'flex';
}

function showChangePasswordModal(userId, username) {
    currentEditUserId = userId;
    document.getElementById('permissionsModalTitle').innerHTML = '<?= __('permissions_title_password') ?> ' + username;
    document.getElementById('usernameField').style.display = 'none';
    document.getElementById('passwordField').style.display = 'none';
    document.getElementById('changePasswordBlock').style.display = 'block';
    document.getElementById('newUserPassword').value = '';
    document.getElementById('modalErrorMessage').style.display = 'none';
    
    document.getElementById('permissionsModal').style.display = 'flex';
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').style.display = 'none';
}

function saveUserPermissions() {
    const perms = getPermissionsFromSelects();
    
    if (currentEditMode === 'add') {
        const username = document.getElementById('modalUsername').value.trim();
        const password = document.getElementById('modalPassword').value;
        
        if (!username || !password) {
            showModalMessage('<?= __('users_username_required') ?>', 'error');
            return;
        }
        
        const fd = new FormData();
        fd.append('ajax', 'add_user');
        fd.append('username', username);
        fd.append('password', password);
        fd.append('permissions', perms);
        
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showModalMessage(data.message, 'success');
                    setTimeout(() => {
                        closePermissionsModal();
                        loadUsers();
                    }, 1500);
                } else {
                    showModalMessage(data.message, 'error');
                }
            })
            .catch(e => showModalMessage('<?= __('msg_save_error') ?>', 'error'));
    } else {
        const fd = new FormData();
        fd.append('ajax', 'update_user');
        fd.append('id', currentEditUserId);
        fd.append('username', document.getElementById('modalUsername').value);
        fd.append('permissions', perms);
        
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const newPassword = document.getElementById('newUserPassword').value;
                    if (newPassword) {
                        const fd2 = new FormData();
                        fd2.append('ajax', 'change_user_password');
                        fd2.append('user_id', currentEditUserId);
                        fd2.append('new_password', newPassword);
                        
                        fetch('', { method: 'POST', body: fd2 })
                            .then(r2 => r2.json())
                            .then(data2 => {
                                showModalMessage(data2.message, data2.success ? 'success' : 'error');
                                if (data2.success) {
                                    setTimeout(() => {
                                        closePermissionsModal();
                                        loadUsers();
                                    }, 1500);
                                }
                            });
                    } else {
                        showModalMessage(data.message, 'success');
                        setTimeout(() => {
                            closePermissionsModal();
                            loadUsers();
                        }, 1500);
                    }
                } else {
                    showModalMessage(data.message, 'error');
                }
            })
            .catch(e => showModalMessage('<?= __('msg_save_error') ?>', 'error'));
    }
}

function deleteUser(id) {
    if (!confirm('<?= __('users_delete_confirm') ?>')) return;
    
    const fd = new FormData();
    fd.append('ajax', 'delete_user');
    fd.append('id', id);
    
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showModalMessage(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => loadUsers(), 1000);
        })
        .catch(e => showModalMessage('<?= __('msg_save_error') ?>', 'error'));
}

function loadUsers() {
    const fd = new FormData();
    fd.append('ajax', 'get_users');
    
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderUsers(data.users);
            } else {
                document.getElementById('usersListContainer').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;">❌ <?= __('error_loading') ?>: ' + (data.error || 'unknown error') + '</div>';
            }
        })
        .catch(e => {
            console.error('Fetch error:', e);
            document.getElementById('usersListContainer').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;">❌ <?= __('error_loading') ?>: ' + e.message + '</div>';
        });
}

function getPermissionLevelText(permissions, viewMask, editMask) {
    if (permissions & editMask) return '✏️';
    if (permissions & viewMask) return '👁️';
    return '🚫';
}

function getRoleDisplay(permissions) {
    const groups = getPermissionLevelText(permissions, PERM_GROUPS_VIEW, PERM_GROUPS_EDIT);
    const devices = getPermissionLevelText(permissions, PERM_DEVICES_VIEW, PERM_DEVICES_EDIT);
    const logs = getPermissionLevelText(permissions, PERM_LOGS_VIEW, PERM_LOGS_EDIT);
    const users = getPermissionLevelText(permissions, PERM_USERS_VIEW, PERM_USERS_EDIT);
    const info = getPermissionLevelText(permissions, PERM_INFO_VIEW, PERM_INFO_VIEW);
    
    return `👥 ${groups} • 📱 ${devices} • 📝 ${logs} • 👑 ${users} • ℹ️ ${info}`;
}

function renderUsers(users) {
    const container = document.getElementById('usersListContainer');
    if (!users || users.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #8aa0bb;"><?= __('users_empty') ?></div>';
        return;
    }
    
    let html = '';
    users.forEach(user => {
        const isCurrent = user.id === <?= $_SESSION['vlmc_user_id'] ?? 1 ?>;
        const roleDisplay = getRoleDisplay(user.permissions);
        
        html += `
            <div style="display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 1fr 0.8fr; gap: 10px; padding: 8px 0; border-bottom: 1px dashed <?= $themeCSS['border'] ?>; align-items: center; font-size: 12px;">
                <span><strong>${escapeHtml(user.username)}</strong></span>
                <span style="font-size: 10px;">${escapeHtml(roleDisplay)}</span>
                <span style="font-size: 10px;">${user.created || '—'}</span>
                <span style="font-size: 10px;">${user.last_login || '—'}</span>
                <span>
                    ${isCurrent ? '<span style="color: #8aa0bb; font-size: 10px;"><?= __('current_user') ?></span>' : `
                        <?php if ($canEditUsers): ?>
                        <button onclick="editUser(${user.id}, '${escapeHtml(user.username)}', ${user.permissions})" style="background: none; border: none; color: #3b82f6; cursor: pointer; margin-right: 8px;" title="<?= __('edit') ?>">✎</button>
                        <button onclick="showChangePasswordModal(${user.id}, '${escapeHtml(user.username)}')" style="background: none; border: none; color: #f39c12; cursor: pointer; margin-right: 8px;" title="<?= __('change_password') ?>">🔑</button>
                        <button onclick="deleteUser(${user.id})" style="background: none; border: none; color: #e74c3c; cursor: pointer;" title="<?= __('delete') ?>">✕</button>
                        <?php endif; ?>
                    `}
                </span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('section-security');
    if (section && section.classList.contains('active')) {
        loadUsers();
    }
});

const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const target = mutation.target;
            if (target.id === 'section-security' && target.classList.contains('active')) {
                loadUsers();
            }
        }
    });
});

const section = document.getElementById('section-security');
if (section) observer.observe(section, { attributes: true });
</script>
<?php
?>