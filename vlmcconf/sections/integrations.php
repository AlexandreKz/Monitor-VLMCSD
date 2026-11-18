<?php
// ============================================
// ФАЙЛ: sections/integrations.php
// ВЕРСИЯ: 6.3.0
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
            <button class="sec-tab-btn active" data-for="ad" onclick="secTab('section-integrations','ad',this); ldFit();">🏢 <?= __('ldap_tab_ad') ?></button>
            <button class="sec-tab-btn" data-for="cloud" onclick="secTab('section-integrations','cloud',this)">☁️ <?= __('int_tab_cloud') ?></button>
            <button class="sec-tab-btn" data-for="webhook" onclick="secTab('section-integrations','webhook',this)">🔔 <?= __('int_tab_webhook') ?></button>
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
        <div class="sec-sub-tabs">
            <button type="button" class="sec-sub-btn active" data-sub-for="conn" onclick="ldSub('conn', this)">🔗 <?= __('ldap_sub_conn') ?></button>
            <button type="button" class="sec-sub-btn" data-sub-for="users" onclick="ldSub('users', this)">👥 <?= __('ldap_tab_users') ?></button>
            <button type="button" class="sec-sub-btn" data-sub-for="log" onclick="ldSub('log', this)">📜 <?= __('ldap_tab_log') ?></button>
        </div>
        <div class="sec-sub active" data-sub="conn">
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
                    <div class="sec-row" style="align-items: flex-start;"><label><?= __('ldap_backup_accounts') ?></label>
                        <div style="flex: 1 1 200px; min-width: 0;">
                            <div id="ldBackups"></div>
                            <?php if ($ldCanEdit): ?>
                            <button type="button" class="sec-btn sm" id="ldBkAddBtn" onclick="ldBkAdd()">➕ <?= __('ldap_backup_add') ?></button>
                            <?php endif; ?>
                        </div></div>
                    <p class="sec-hint"><?= __('ldap_service_hint') ?></p>
                    <p class="sec-hint"><?= __('ldap_backup_hint') ?></p>
                    <div class="sec-row" style="margin: 10px 0 0;">
                        <?php if ($ldCanEdit): ?>
                        <button type="button" class="sec-btn primary" onclick="ldSave()">💾 <?= __('save') ?></button>
                        <button type="button" class="sec-btn" onclick="ldTest()">🔌 <?= __('ldap_test') ?></button>
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
                    <div class="sec-row" style="align-items: flex-start;"><label><?= __('ldap_required_group') ?></label>
                        <div style="flex: 1 1 200px; min-width: 0;">
                            <div id="ldGroupChips" class="ld-chips"></div>
                            <?php if ($ldCanEdit): ?>
                            <button type="button" class="sec-btn sm" onclick="ldGroupsOpen()">📂 <?= __('ldap_groups_pick') ?></button>
                            <?php endif; ?>
                        </div></div>
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
        <div class="sec-sub" data-sub="users">
        <div class="settings-card">
            <div class="settings-card-title">👥 <?= __('ldap_users_title') ?> <span class="sec-badge" id="ldUsersCount">0</span></div>
            <div class="sec-row">
                <input type="text" id="ldQuery" class="form-control" placeholder="<?= __('ldap_search_ph') ?>" autocomplete="off" oninput="ldSearchLive()">
                <?php if ($ldCanEdit): ?>
                <button type="button" class="sec-btn primary" onclick="ldImport()">⬇️ <?= __('ldap_import_selected') ?></button>
                <button type="button" class="sec-btn" onclick="ldSync()">🔄 <?= __('ldap_sync_now') ?></button>
                <?php endif; ?>
            </div>
            <div id="ldUsersMsg" class="sec-msg" style="margin: 0 0 8px;"></div>
            <div class="sec-scroll sec-tall">
                <table class="sec-table">
                    <thead><tr><th style="width: 28px;"><input type="checkbox" id="ldAll" onchange="ldToggleAll(this)"></th><th><?= __('ldap_col_login') ?></th><th><?= __('ldap_col_name') ?></th><th><?= __('ldap_col_email') ?></th><th><?= __('ldap_col_groups') ?></th><th><?= __('ldap_col_state') ?></th></tr></thead>
                    <tbody id="ldUserRows"><tr><td colspan="6" class="sec-empty"><?= __('ldap_users_hint') ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Журнал ===== -->
        <div class="sec-sub" data-sub="log">
        <div class="settings-card">
            <div class="settings-card-title">📜 <?= __('ldap_log_title') ?></div>
            <div class="sec-scroll sec-tall">
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

    <!-- ===== Cloud Storage ===== -->
    <div class="sec-tab-content" data-tab="cloud">
        <div class="settings-card">
            <div class="settings-card-title">☁️ <?= __('int_tab_cloud') ?></div>
            <div class="sec-note"><?= __('int_cloud_soon') ?></div>
        </div>
    </div>

    <!-- ===== Webhook ===== -->
    <div class="sec-tab-content" data-tab="webhook">
        <div class="settings-card">
            <div class="settings-card-title">🔔 <?= __('int_tab_webhook') ?></div>
            <div class="sec-note"><?= __('int_webhook_soon') ?></div>
        </div>
    </div>
</div>

<style>
.ld-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.ld-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 6px 3px 12px; border-radius: 14px; font-size: 12px; border: 1px solid <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; max-width: 100%; }
.ld-chip button { background: none; border: none; cursor: pointer; color: <?= $themeCSS['muted'] ?>; font-size: 13px; padding: 0 4px; line-height: 1; }
.ld-chip button:hover { color: <?= $themeCSS['dangerText'] ?>; }
.ld-chips .ld-none { font-size: 12px; color: <?= $themeCSS['muted'] ?>; }
.ld-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 10000; }
.ld-overlay.open { display: flex; }
.ld-modal { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 10px; width: min(640px, 94vw); max-height: 86vh; display: flex; flex-direction: column; padding: 16px 18px; }
.ld-modal h3 { margin: 0 0 10px; font-size: 15px; }
.ld-list { overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; margin: 10px 0; min-height: 120px; flex: 1 1 auto; }
.ld-item { display: flex; gap: 10px; align-items: flex-start; padding: 7px 10px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; cursor: pointer; font-size: 13px; }
.ld-item:last-child { border-bottom: none; }
.ld-item:hover { background: <?= $themeCSS['hover'] ?>; }
.ld-item input { margin-top: 3px; accent-color: <?= $themeCSS['primary'] ?>; }
.ld-item small { display: block; color: <?= $themeCSS['muted'] ?>; font-size: 11px; }
.ld-foot { display: flex; gap: 8px; justify-content: flex-end; }
.ld-bk { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
.ld-bk input { flex: 1 1 0; min-width: 0; }
.ld-bk button { background: none; border: none; cursor: pointer; color: <?= $themeCSS['muted'] ?>; font-size: 14px; padding: 0 4px; }
.ld-bk button:hover { color: <?= $themeCSS['dangerText'] ?>; }
</style>
<div id="ldGroupsOverlay" class="ld-overlay" onclick="if (event.target === this) ldGroupsClose()">
    <div class="ld-modal">
        <h3>📂 <?= __('ldap_groups_title') ?></h3>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="ldGroupsQuery" class="form-control" placeholder="<?= htmlspecialchars(__('ldap_groups_search')) ?>" onkeydown="if (event.key === 'Enter') ldGroupsLoad()">
            <button type="button" class="sec-btn" onclick="ldGroupsLoad()">🔍</button>
        </div>
        <div id="ldGroupsList" class="ld-list"></div>
        <div style="display: flex; gap: 8px; margin-bottom: 10px;">
            <input type="text" id="ldGroupsManual" class="form-control" placeholder="<?= htmlspecialchars(__('ldap_groups_manual')) ?>" onkeydown="if (event.key === 'Enter') ldGroupsAddManual()">
            <button type="button" class="sec-btn" onclick="ldGroupsAddManual()"><?= __('ldap_groups_add') ?></button>
        </div>
        <div class="ld-foot">
            <button type="button" class="sec-btn" onclick="ldGroupsClose()"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" onclick="ldGroupsApply()"><?= __('ldap_groups_apply') ?></button>
        </div>
    </div>
</div>

<script>
const ldCanEdit = <?= $ldCanEdit ? 'true' : 'false' ?>;
const ldStr = { new: <?= json_encode(__('ldap_state_new')) ?>, imported: <?= json_encode(__('ldap_state_imported')) ?>, conflict: <?= json_encode(__('ldap_state_conflict')) ?>,
    empty: <?= json_encode(__('ldap_users_none')) ?>, confirmSync: <?= json_encode(__('ldap_sync_confirm')) ?>, none: <?= json_encode(__('ldap_nothing_selected')) ?>, searching: <?= json_encode(__('ldap_searching')) ?> };
let ldFound = false;

// ---- Резервные служебные записи
const LD_BK_MAX = <?= (int)LDAP_BACKUP_MAX ?>;
let ldBackups = <?= json_encode(array_map(function ($b) { return ['user' => $b['user'], 'password' => $b['has_password'] ? '********' : '']; }, $ld['backup_accounts']), JSON_UNESCAPED_UNICODE) ?>;
const ldBkStr = { user: <?= json_encode(__('ldap_backup_user_ph')) ?>, pass: <?= json_encode(__('ldap_service_password')) ?>, remove: <?= json_encode(__('ldap_groups_remove')) ?> };
function ldBkRender() {
    const box = document.getElementById('ldBackups');
    box.innerHTML = ldBackups.map((b, i) => '<div class="ld-bk"><input type="text" class="form-control" placeholder="' + secEsc(ldBkStr.user) + '" value="' + secEsc(b.user) + '" ' + (ldCanEdit ? '' : 'disabled') + ' oninput="ldBackups[' + i + '].user = this.value">' +
        '<input type="password" class="form-control" autocomplete="new-password" placeholder="' + secEsc(ldBkStr.pass) + '" value="' + secEsc(b.password) + '" ' + (ldCanEdit ? '' : 'disabled') + ' oninput="ldBackups[' + i + '].password = this.value">' +
        (ldCanEdit ? '<button type="button" title="' + secEsc(ldBkStr.remove) + '" onclick="ldBkDel(' + i + ')">✕</button>' : '') + '</div>').join('');
    const add = document.getElementById('ldBkAddBtn');
    if (add) add.style.display = ldBackups.length >= LD_BK_MAX ? 'none' : '';
}
function ldBkAdd() {
    if (ldBackups.length >= LD_BK_MAX) return;
    ldBackups.push({ user: '', password: '' });
    ldBkRender();
    const rows = document.querySelectorAll('#ldBackups .ld-bk input[type=text]');
    if (rows.length) rows[rows.length - 1].focus();
}
function ldBkDel(i) { ldBackups.splice(i, 1); ldBkRender(); }

// ---- Высота списков: по высоте окна, без второй прокрутки страницы
function ldFit() {
    document.querySelectorAll('#section-integrations .sec-tall').forEach(function (el) {
        if (!el.offsetParent) return;
        const top = el.getBoundingClientRect().top;
        el.style.maxHeight = Math.max(200, window.innerHeight - top - 110) + 'px';
    });
}
window.addEventListener('resize', ldFit);
document.addEventListener('DOMContentLoaded', ldBkRender);

// ---- Обязательные группы: выбор из каталога
let ldGroups = <?= json_encode(array_values($ld['required_groups']), JSON_UNESCAPED_UNICODE) ?>;
let ldGroupsDraft = [];
let ldGroupsKnown = {};
const ldGStr = { none: <?= json_encode(__('ldap_groups_none')) ?>, remove: <?= json_encode(__('ldap_groups_remove')) ?>, loading: <?= json_encode(__('ldap_groups_loading')) ?>,
    empty: <?= json_encode(__('ldap_groups_empty')) ?>, missing: <?= json_encode(__('ldap_groups_missing')) ?>, manual: <?= json_encode(__('ldap_groups_manual_tag')) ?> };
function ldGroupLabel(v) { const m = /^CN=((?:\\.|[^,])+)/i.exec(v); return m ? m[1].replace(/\\(.)/g, '$1') : v; }
function ldGroupIndex(list, v) { return list.findIndex(x => x.toLowerCase() === String(v).toLowerCase()); }
function ldChipsRender() {
    const box = document.getElementById('ldGroupChips');
    if (!ldGroups.length) { box.innerHTML = '<span class="ld-none">' + secEsc(ldGStr.none) + '</span>'; return; }
    box.innerHTML = ldGroups.map((g, i) => '<span class="ld-chip" title="' + secEsc(g) + '">' + secEsc(ldGroupLabel(g)) +
        (ldCanEdit ? '<button type="button" title="' + secEsc(ldGStr.remove) + '" onclick="ldGroupRemove(' + i + ')">✕</button>' : '') + '</span>').join('');
}
function ldGroupRemove(i) { ldGroups.splice(i, 1); ldChipsRender(); }
function ldGroupsOpen() {
    ldGroupsDraft = ldGroups.slice();
    document.getElementById('ldGroupsQuery').value = '';
    document.getElementById('ldGroupsManual').value = '';
    document.getElementById('ldGroupsOverlay').classList.add('open');
    ldGroupsLoad();
}
function ldGroupsClose() { document.getElementById('ldGroupsOverlay').classList.remove('open'); }
function ldGroupsApply() { ldGroups = ldGroupsDraft.slice(); ldChipsRender(); ldGroupsClose(); }
function ldGroupsToggle(dn, on) {
    const i = ldGroupIndex(ldGroupsDraft, dn);
    if (on && i < 0) ldGroupsDraft.push(dn);
    if (!on && i >= 0) ldGroupsDraft.splice(i, 1);
}
function ldGroupsAddManual() {
    const inp = document.getElementById('ldGroupsManual'), v = inp.value.trim();
    if (!v) return;
    if (ldGroupIndex(ldGroupsDraft, v) < 0) ldGroupsDraft.push(v);
    inp.value = '';
    ldGroupsDrawList(ldGroupsLast);
}
let ldGroupsLast = [];
function ldGroupsDrawList(groups) {
    ldGroupsLast = groups;
    const list = document.getElementById('ldGroupsList');
    const known = groups.map(g => g.dn.toLowerCase());
    // выбранные, которых нет в ответе каталога (введены вручную, удалены или не попали под поиск)
    const extra = ldGroupsDraft.filter(v => known.indexOf(v.toLowerCase()) < 0 && !document.getElementById('ldGroupsQuery').value.trim());
    let html = '';
    extra.forEach(v => { html += '<label class="ld-item"><input type="checkbox" checked data-dn="' + secEsc(v) + '"><span>' + secEsc(ldGroupLabel(v)) +
        '<small>' + secEsc(v) + ' · <span style="color:var(--danger,#e55)">' + secEsc(ldGStr.missing) + '</span></small></span></label>'; });
    groups.forEach(g => { html += '<label class="ld-item"><input type="checkbox" ' + (ldGroupIndex(ldGroupsDraft, g.dn) >= 0 || ldGroupIndex(ldGroupsDraft, g.name) >= 0 ? 'checked' : '') +
        ' data-dn="' + secEsc(g.dn) + '"><span>' + secEsc(g.name) + '<small>' + secEsc(g.description || g.dn) + '</small></span></label>'; });
    list.innerHTML = html || '<div class="sec-empty">' + secEsc(ldGStr.empty) + '</div>';
    list.querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', () => ldGroupsToggle(cb.getAttribute('data-dn'), cb.checked)));
}
function ldGroupsLoad() {
    const list = document.getElementById('ldGroupsList');
    list.innerHTML = '<div class="sec-empty">' + secEsc(ldGStr.loading) + '</div>';
    // список берётся по сохранённым настройкам подключения
    secPost({ ajax: 'ldap_groups', q: document.getElementById('ldGroupsQuery').value }).then(d => {
        if (!d.success) { list.innerHTML = '<div class="sec-empty">' + secEsc(d.message || '') + '</div>'; ldGroupsLast = []; ldGroupsDrawExtraOnly(); return; }
        ldGroupsDrawList(d.groups || []);
    }).catch(() => { list.innerHTML = '<div class="sec-empty">?</div>'; });
}
function ldGroupsDrawExtraOnly() {
    const list = document.getElementById('ldGroupsList');
    ldGroupsDraft.forEach(v => { list.insertAdjacentHTML('beforeend', '<label class="ld-item"><input type="checkbox" checked data-dn="' + secEsc(v) + '"><span>' + secEsc(ldGroupLabel(v)) + '<small>' + secEsc(v) + '</small></span></label>'); });
    list.querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', () => ldGroupsToggle(cb.getAttribute('data-dn'), cb.checked)));
}
document.addEventListener('DOMContentLoaded', ldChipsRender);

function ldSecurityChange() {
    const sec = document.getElementById('ldSecurity').value, port = document.getElementById('ldPort');
    if (port.value === '389' || port.value === '636' || port.value === '') port.value = sec === 'ldaps' ? '636' : '389';
}
function ldCollect() {
    const v = id => document.getElementById(id).value;
    const c = id => document.getElementById(id).checked ? '1' : '0';
    return { enabled: c('ldEnabled'), auth_enabled: c('ldAuth'), auto_create: c('ldAutoCreate'), server: v('ldServer'), port: v('ldPort'), security: v('ldSecurity'),
        verify_cert: c('ldVerify'), base_dn: v('ldBase'), domain: v('ldDomain'), service_user: v('ldSvcUser'), service_password: v('ldSvcPass'), backup_accounts: JSON.stringify(ldBackups.filter(b => String(b.user).trim() !== '')),
        login_attr: v('ldAttr'), user_filter: v('ldFilter'), required_groups: JSON.stringify(ldGroups), default_permissions: v('ldPerms'), timeout: v('ldTimeout') };
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
let ldSearchTimer = null, ldSearchSeq = 0;
function ldSearchLive() { clearTimeout(ldSearchTimer); ldSearchTimer = setTimeout(ldSearch, 250); }
function ldSearch() {
    const seq = ++ldSearchSeq;
    const body = document.getElementById('ldUserRows');
    body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(ldStr.searching) + '</td></tr>';
    secPost({ ajax: 'ldap_search', q: document.getElementById('ldQuery').value }).then(d => {
        if (seq !== ldSearchSeq) return;   // пришёл ответ на устаревший запрос
        if (!d.success) { body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(d.message || '') + '</td></tr>'; document.getElementById('ldUsersCount').textContent = 0; return; }
        document.getElementById('ldUsersCount').textContent = d.users.length;
        setTimeout(ldFit, 0);
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
function ldSub(name, btn) {
    const root = document.getElementById('section-integrations');
    root.querySelectorAll('.sec-sub-btn').forEach(b => b.classList.remove('active'));
    root.querySelectorAll('.sec-sub').forEach(c => c.classList.remove('active'));
    const b = btn || root.querySelector('.sec-sub-btn[data-sub-for="' + name + '"]');
    if (b) b.classList.add('active');
    const pane = root.querySelector('.sec-sub[data-sub="' + name + '"]');
    if (pane) pane.classList.add('active');
    try { sessionStorage.setItem('vlmc_sub_integrations', name); } catch (e) {}
    if (name === 'users') ldFirstSearch();
    ldFit();
}
document.addEventListener('DOMContentLoaded', function () {
    secTabRestore('section-integrations', 'ad');
    let sub = 'conn';
    try { sub = sessionStorage.getItem('vlmc_sub_integrations') || 'conn'; } catch (e) {}
    if (['conn', 'users', 'log'].indexOf(sub) < 0) sub = 'conn';
    ldSub(sub);
});
</script>
