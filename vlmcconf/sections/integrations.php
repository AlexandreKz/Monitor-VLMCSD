<?php
// ============================================
// ФАЙЛ: sections/integrations.php
// ВЕРСИЯ: 6.0.2
// ДАТА: 2026-11-18
// @description: Раздел «Интеграции» (п. 26): Active Directory / LDAP — настройки, проверка связи, вход доменных пользователей, импорт, журнал
// @description: Integrations section: Active Directory / LDAP — settings, connection test, domain login, user import, sync log
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../vlmcinc/section_ui.php';
require_once __DIR__ . '/../vlmcinc/ldap.php';
vlmcSectionUi($themeCSS);
?>

<div id="section-integrations" class="settings-section <?= $activeSection === 'integrations' ? 'active' : '' ?>">
    <div class="section-title sec-title">
        <span>🔌 <?= __('integrations') ?></span>
        <div class="sec-tabs">
            <button class="sec-tab-btn active" data-for="ad" onclick="secTab('section-integrations','ad',this)">🏢 <?= __('ldap_tab_ad') ?></button>
            <button class="sec-tab-btn" data-for="users" onclick="secTab('section-integrations','users',this); ldFirstSearch();">👥 <?= __('ldap_tab_users') ?></button>
            <button class="sec-tab-btn" data-for="log" onclick="secTab('section-integrations','log',this)">📜 <?= __('ldap_tab_log') ?></button>
        </div>
    </div>

<?php
if (!check_permission(PERM_INTEGRATIONS_VIEW)) {
    echo '<div class="alert alert-danger">' . __('access_denied') . '</div>';
    echo '</div>';
    return;
}

$ldCanEdit = check_permission(PERM_INTEGRATIONS_EDIT);
$ld = ldapSettings($config);
$ldAvail = ldapAvailable();
$ldLog = ldapLogRead(100);
$ldDis = $ldCanEdit ? '' : 'disabled';
$permOptions = [
    (PERM_LOGS_VIEW | PERM_DEVICES_VIEW) => 'ldap_perm_view',
    (PERM_LOGS_VIEW | PERM_DEVICES_VIEW | PERM_GROUPS_VIEW | PERM_INFO_VIEW) => 'ldap_perm_view_ext',
    (PERM_LOGS_VIEW | PERM_DEVICES_VIEW | PERM_DEVICES_EDIT | PERM_GROUPS_VIEW | PERM_GROUPS_EDIT) => 'ldap_perm_edit',
];
?>

    <!-- ===== Active Directory / LDAP ===== -->
    <div class="sec-tab-content active" data-tab="ad">
        <?php if (!$ldAvail): ?>
        <div class="sec-note warn">
            <b><?= __('ldap_ext_missing_title') ?></b><br><?= __('ldap_ext_missing_text') ?>
            <div class="sec-code">sudo apt install php-ldap &amp;&amp; sudo systemctl restart apache2</div>
        </div>
        <?php endif; ?>
        <div class="sec-grid">
            <div class="sec-col" style="flex: 0.55;">
                <div class="settings-card">
                    <div class="settings-card-title">🔗 <?= __('ldap_conn_title') ?></div>
                    <label class="sec-switch">
                        <input type="checkbox" id="ldEnabled" <?= $ld['enabled'] ? 'checked' : '' ?> <?= $ldDis ?>>
                        <span><?= __('ldap_enable') ?></span>
                    </label>
                    <p class="sec-hint" style="margin-bottom: 12px;"><?= __('ldap_enable_hint') ?></p>
                    <div class="sec-row"><label for="ldServer"><?= __('ldap_server') ?></label>
                        <input type="text" id="ldServer" class="form-control" value="<?= htmlspecialchars($ld['server']) ?>" placeholder="dc.example.com" <?= $ldDis ?>></div>
                    <div class="sec-row"><label for="ldSecurity"><?= __('ldap_security') ?></label>
                        <select id="ldSecurity" class="form-control" onchange="ldSecurityChange()" <?= $ldDis ?>>
                            <option value="none" <?= $ld['security'] === 'none' ? 'selected' : '' ?>><?= __('ldap_sec_none') ?></option>
                            <option value="starttls" <?= $ld['security'] === 'starttls' ? 'selected' : '' ?>>StartTLS</option>
                            <option value="ldaps" <?= $ld['security'] === 'ldaps' ? 'selected' : '' ?>>LDAPS (SSL/TLS)</option>
                        </select>
                        <input type="number" id="ldPort" class="form-control sec-narrow" min="1" max="65535" value="<?= (int)$ld['port'] ?>" <?= $ldDis ?>></div>
                    <div class="sec-row"><label></label>
                        <label class="sec-switch" style="margin: 0; font-weight: 400;"><input type="checkbox" id="ldVerify" <?= $ld['verify_cert'] ? 'checked' : '' ?> <?= $ldDis ?>> <?= __('ldap_verify_cert') ?></label></div>
                    <div class="sec-row"><label for="ldBase"><?= __('ldap_base_dn') ?></label>
                        <input type="text" id="ldBase" class="form-control" value="<?= htmlspecialchars($ld['base_dn']) ?>" placeholder="DC=corp,DC=example,DC=com" <?= $ldDis ?>></div>
                    <div class="sec-row"><label for="ldDomain"><?= __('ldap_domain') ?></label>
                        <input type="text" id="ldDomain" class="form-control" value="<?= htmlspecialchars($ld['domain']) ?>" placeholder="corp.example.com" <?= $ldDis ?>></div>
                    <div class="sec-row"><label for="ldSvcUser"><?= __('ldap_service_user') ?></label>
                        <input type="text" id="ldSvcUser" class="form-control" value="<?= htmlspecialchars($ld['service_user']) ?>" placeholder="CN=svc-kms,OU=Service,DC=corp,DC=example,DC=com" <?= $ldDis ?>></div>
                    <div class="sec-row"><label for="ldSvcPass"><?= __('ldap_service_password') ?></label>
                        <input type="password" id="ldSvcPass" class="form-control" value="<?= $ld['has_password'] ? '********' : '' ?>" autocomplete="new-password" <?= $ldDis ?>></div>
                    <p class="sec-hint"><?= __('ldap_service_hint') ?></p>
                    <div class="sec-row" style="margin: 10px 0 0;">
                        <?php if ($ldCanEdit): ?>
                        <button type="button" class="btn btn-primary" onclick="ldSave()">💾 <?= __('save') ?></button>
                        <button type="button" class="btn" onclick="ldTest()">🔌 <?= __('ldap_test') ?></button>
                        <?php endif; ?>
                    </div>
                    <div id="ldMsg" class="sec-msg"></div>
                </div>
            </div>

            <div class="sec-col" style="flex: 0.45;">
                <div class="settings-card">
                    <div class="settings-card-title">🔐 <?= __('ldap_login_title') ?></div>
                    <label class="sec-switch">
                        <input type="checkbox" id="ldAuth" <?= $ld['auth_enabled'] ? 'checked' : '' ?> <?= $ldDis ?>>
                        <span><?= __('ldap_auth_enable') ?></span>
                    </label>
                    <p class="sec-hint" style="margin-bottom: 10px;"><?= __('ldap_auth_hint') ?></p>
                    <label class="sec-switch">
                        <input type="checkbox" id="ldAutoCreate" <?= $ld['auto_create'] ? 'checked' : '' ?> <?= $ldDis ?>>
                        <span><?= __('ldap_auto_create') ?></span>
                    </label>
                    <p class="sec-hint" style="margin-bottom: 12px;"><?= __('ldap_auto_create_hint') ?></p>
                    <div class="sec-row"><label for="ldPerms"><?= __('ldap_default_perms') ?></label>
                        <select id="ldPerms" class="form-control" <?= $ldDis ?>>
                            <?php foreach ($permOptions as $mask => $key): ?>
                            <option value="<?= $mask ?>" <?= $ld['default_permissions'] === $mask ? 'selected' : '' ?>><?= __($key) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="sec-row"><label for="ldAttr"><?= __('ldap_login_attr') ?></label>
                        <select id="ldAttr" class="form-control" <?= $ldDis ?>>
                            <?php foreach (['sAMAccountName', 'userPrincipalName', 'uid', 'mail'] as $a): ?>
                            <option value="<?= $a ?>" <?= $ld['login_attr'] === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="sec-row"><label for="ldGroup"><?= __('ldap_required_group') ?></label>
                        <input type="text" id="ldGroup" class="form-control" value="<?= htmlspecialchars($ld['required_group']) ?>" placeholder="KMS Admins" <?= $ldDis ?>></div>
                    <p class="sec-hint" style="margin: -4px 0 10px;"><?= __('ldap_required_group_hint') ?></p>
                    <div class="sec-row"><label for="ldFilter"><?= __('ldap_user_filter') ?></label>
                        <input type="text" id="ldFilter" class="form-control" value="<?= htmlspecialchars($ld['user_filter']) ?>" placeholder="(department=IT)" <?= $ldDis ?>></div>
                    <p class="sec-hint" style="margin: -4px 0 10px;"><?= __('ldap_user_filter_hint') ?></p>
                    <div class="sec-row"><label for="ldTimeout"><?= __('ldap_timeout') ?></label>
                        <input type="number" id="ldTimeout" class="form-control sec-narrow" min="2" max="30" value="<?= (int)$ld['timeout'] ?>" <?= $ldDis ?>>
                        <span class="sec-hint" style="margin: 0;"><?= __('ldap_timeout_unit') ?></span></div>
                    <p class="sec-hint"><?= __('ldap_security_note') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Пользователи каталога ===== -->
    <div class="sec-tab-content" data-tab="users">
        <div class="settings-card">
            <div class="settings-card-title">👥 <?= __('ldap_users_title') ?> <span class="sec-badge" id="ldUsersCount">0</span></div>
            <div class="sec-row">
                <input type="text" id="ldQuery" class="form-control" placeholder="<?= __('ldap_search_ph') ?>" autocomplete="off" onkeydown="if(event.key==='Enter') ldSearch()">
                <button type="button" class="btn btn-primary" onclick="ldSearch()">🔍 <?= __('ldap_search') ?></button>
                <?php if ($ldCanEdit): ?>
                <button type="button" class="btn" onclick="ldImport()">⬇️ <?= __('ldap_import_selected') ?></button>
                <button type="button" class="btn" onclick="ldSync()">🔄 <?= __('ldap_sync_now') ?></button>
                <?php endif; ?>
            </div>
            <div id="ldUsersMsg" class="sec-msg" style="margin: 0 0 8px;"></div>
            <div class="sec-scroll" style="max-height: 380px;">
                <table class="sec-table">
                    <thead><tr><th style="width: 28px;"><input type="checkbox" id="ldAll" onchange="ldToggleAll(this)"></th><th><?= __('ldap_col_login') ?></th><th><?= __('ldap_col_name') ?></th><th><?= __('ldap_col_email') ?></th><th><?= __('ldap_col_groups') ?></th><th><?= __('ldap_col_state') ?></th></tr></thead>
                    <tbody id="ldUserRows"><tr><td colspan="6" class="sec-empty"><?= __('ldap_users_hint') ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Журнал ===== -->
    <div class="sec-tab-content" data-tab="log">
        <div class="settings-card">
            <div class="settings-card-title">📜 <?= __('ldap_log_title') ?></div>
            <div class="sec-scroll" style="max-height: 420px;">
                <table class="sec-table">
                    <thead><tr><th><?= __('ldap_log_time') ?></th><th><?= __('ldap_log_type') ?></th><th><?= __('ldap_log_result') ?></th><th><?= __('ldap_log_text') ?></th></tr></thead>
                    <tbody>
                    <?php if (!$ldLog): ?>
                        <tr><td colspan="4" class="sec-empty"><?= __('ldap_log_empty') ?></td></tr>
                    <?php else: foreach ($ldLog as $row): ?>
                        <tr><td class="mono"><?= htmlspecialchars($row['time']) ?></td>
                            <td><?= htmlspecialchars(__('ldap_log_type_' . $row['type'])) ?></td>
                            <td><span class="sec-badge <?= !empty($row['ok']) ? 'ok' : 'err' ?>"><?= __(!empty($row['ok']) ? 'ldap_log_ok' : 'ldap_log_fail') ?></span></td>
                            <td><?= htmlspecialchars($row['text']) ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const ldCanEdit = <?= $ldCanEdit ? 'true' : 'false' ?>;
const ldStr = { new: <?= json_encode(__('ldap_state_new')) ?>, imported: <?= json_encode(__('ldap_state_imported')) ?>, conflict: <?= json_encode(__('ldap_state_conflict')) ?>,
    empty: <?= json_encode(__('ldap_users_none')) ?>, confirmSync: <?= json_encode(__('ldap_sync_confirm')) ?>, none: <?= json_encode(__('ldap_nothing_selected')) ?>, searching: <?= json_encode(__('ldap_searching')) ?> };
let ldFound = false;

function ldSecurityChange() {
    const sec = document.getElementById('ldSecurity').value, port = document.getElementById('ldPort');
    if (port.value === '389' || port.value === '636' || port.value === '') port.value = sec === 'ldaps' ? '636' : '389';
}
function ldCollect() {
    const v = id => document.getElementById(id).value;
    const c = id => document.getElementById(id).checked ? '1' : '0';
    return { enabled: c('ldEnabled'), auth_enabled: c('ldAuth'), auto_create: c('ldAutoCreate'), server: v('ldServer'), port: v('ldPort'), security: v('ldSecurity'),
        verify_cert: c('ldVerify'), base_dn: v('ldBase'), domain: v('ldDomain'), service_user: v('ldSvcUser'), service_password: v('ldSvcPass'),
        login_attr: v('ldAttr'), user_filter: v('ldFilter'), required_group: v('ldGroup'), default_permissions: v('ldPerms'), timeout: v('ldTimeout') };
}
function ldSave() {
    secPost(Object.assign({ ajax: 'ldap_save_settings' }, ldCollect()))
        .then(d => secMsg('ldMsg', !!d.success, d.message || ''))
        .catch(() => secMsg('ldMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function ldTest() {
    secMsg('ldMsg', true, <?= json_encode(__('ldap_testing')) ?>);
    // проверяется то, что сейчас в форме: сначала сохраняем поля, затем проверяем связь
    secPost(Object.assign({ ajax: 'ldap_save_settings' }, ldCollect())).then(function (sv) {
        if (!sv.success) { secMsg('ldMsg', false, sv.message || ''); return null; }
        return secPost({ ajax: 'ldap_test' }).then(d => secMsg('ldMsg', !!d.success, d.message || ''));
    }).catch(() => secMsg('ldMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function ldFirstSearch() { if (!ldFound) { ldFound = true; ldSearch(); } }
function ldSearch() {
    const body = document.getElementById('ldUserRows');
    body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(ldStr.searching) + '</td></tr>';
    secPost({ ajax: 'ldap_search', q: document.getElementById('ldQuery').value }).then(d => {
        if (!d.success) { body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(d.message || '') + '</td></tr>'; document.getElementById('ldUsersCount').textContent = 0; return; }
        document.getElementById('ldUsersCount').textContent = d.users.length;
        if (!d.users.length) { body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(ldStr.empty) + '</td></tr>'; return; }
        body.innerHTML = d.users.map(u => {
            const st = u.state === 'new' ? ['', ldStr.new] : (u.state === 'imported' ? ['ok', ldStr.imported] : ['err', ldStr.conflict]);
            const can = ldCanEdit && u.state !== 'local_conflict';
            return '<tr><td>' + (can ? '<input type="checkbox" class="ldPick" value="' + secEsc(u.username) + '">' : '') + '</td><td class="mono">' + secEsc(u.username) + '</td><td>' + secEsc(u.fullname) +
                '</td><td>' + secEsc(u.email) + '</td><td>' + secEsc((u.groups || []).join(', ')) + '</td><td><span class="sec-badge ' + st[0] + '">' + secEsc(st[1]) + '</span></td></tr>';
        }).join('');
    }).catch(() => { body.innerHTML = '<tr><td colspan="6" class="sec-empty">?</td></tr>'; });
}
function ldToggleAll(box) { document.querySelectorAll('.ldPick').forEach(c => { c.checked = box.checked; }); }
function ldImport() {
    const names = Array.from(document.querySelectorAll('.ldPick:checked')).map(c => c.value);
    if (!names.length) { secMsg('ldUsersMsg', false, ldStr.none); return; }
    secPost({ ajax: 'ldap_import', users: JSON.stringify(names) }).then(d => { secMsg('ldUsersMsg', !!d.success, d.message || ''); if (d.success) ldSearch(); });
}
function ldSync() {
    if (!confirm(ldStr.confirmSync)) return;
    secPost({ ajax: 'ldap_sync' }).then(d => { secMsg('ldUsersMsg', !!d.success, d.message || ''); if (d.success) ldSearch(); });
}
document.addEventListener('DOMContentLoaded', function () {
    secTabRestore('section-integrations', 'ad');
    const act = document.querySelector('#section-integrations .sec-tab-btn.active');
    if (act && act.getAttribute('data-for') === 'users') ldFirstSearch();
});
</script>
