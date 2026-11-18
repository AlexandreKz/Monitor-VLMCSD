<?php
// ============================================
// ФАЙЛ: sections/integrations.php
// ВЕРСИЯ: 6.4.8
// ДАТА: 2026-11-18
// @description: Раздел «Интеграции» (п. 26): Active Directory / LDAP, Cloud Storage, Webhook — настройки, проверка, журнал событий
// @description: Integrations section: Active Directory / LDAP, Cloud Storage, Webhook — settings, tests, event log
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
            <button class="sec-tab-btn" data-for="webhook" onclick="secTab('section-integrations','webhook',this)">🔗 <?= __('int_tab_webhook') ?></button>
            <button class="sec-tab-btn" data-for="multiserver" onclick="secTab('section-integrations','multiserver',this)">🛰️ <?= __('int_tab_ms') ?></button>
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
                    <div class="settings-card-title sec-th">🔗 <?= __('ldap_conn_title') ?> <span id="ldStatus" class="sec-badge" style="margin-left: auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
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
                    <div class="sec-row"><label for="ldSvcUser"><?= __('ldap_service_user') ?></label>
                        <div style="flex: 1 1 200px; min-width: 0;"><div class="ld-bk" style="margin: 0;">
                            <input type="text" id="ldSvcUser" class="form-control" value="<?= htmlspecialchars($ld['service_user']) ?>" placeholder="CN=svc-kms,OU=Service,DC=corp,DC=example,DC=com" <?= $ldDis ?>>
                            <input type="password" id="ldSvcPass" class="form-control" value="<?= $ld['has_password'] ? '********' : '' ?>" placeholder="<?= htmlspecialchars(__('ldap_service_password')) ?>" autocomplete="new-password" <?= $ldDis ?>>
                            <span style="width: 22px; flex: none;"></span>
                        </div></div></div>
                    <div class="sec-row" style="align-items: flex-start;"><label><?= __('ldap_backup_accounts') ?></label>
                        <div style="flex: 1 1 200px; min-width: 0;">
                            <div id="ldBackups"></div>
                            <?php if ($ldCanEdit): ?>
                            <button type="button" class="sec-btn sm" id="ldBkAddBtn" onclick="ldBkAdd()">➕ <?= __('ldap_backup_add') ?></button>
                            <?php endif; ?>
                        </div></div>
                    <div class="sec-row"><label for="ldDomain"><?= __('ldap_domain') ?></label>
                        <input type="text" id="ldDomain" class="form-control" value="<?= htmlspecialchars($ld['domain']) ?>" placeholder="corp.example.com" <?= $ldDis ?>></div>
                    <p class="sec-hint"><?= __('ldap_domain_hint') ?></p>
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
            <div class="settings-card-title" style="display: flex; align-items: center; gap: 10px; min-height: 28px;">👥 <?= __('ldap_users_title') ?> <span class="sec-badge" id="ldUsersCount">0</span>
                <span id="ldUsersMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
            <div class="sec-row">
                <input type="text" id="ldQuery" class="form-control" placeholder="<?= __('ldap_search_ph') ?>" autocomplete="off" oninput="ldSearchLive()">
                <?php if ($ldCanEdit): ?>
                <button type="button" class="sec-btn primary" onclick="ldImport()">⬇️ <?= __('ldap_import_selected') ?></button>
                <button type="button" class="sec-btn" onclick="ldSync()">🔄 <?= __('ldap_sync_now') ?></button>
                <?php endif; ?>
            </div>
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
            <div class="settings-card-title sec-th">☁️ <?= __('int_cl_title') ?>
                <span id="clMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
            <div class="sec-note"><?= __('int_cl_intro') ?></div>
            <?php if ($ldCanEdit): ?><div style="margin: 8px 0;"><button type="button" class="sec-btn primary" onclick="clEdit('')">➕ <?= __('int_cl_add') ?></button></div><?php endif; ?>
            <div class="sec-scroll">
                <table class="sec-table">
                    <thead><tr><th><?= __('cl_col_name') ?></th><th><?= __('cl_col_type') ?></th><th><?= __('cl_col_host') ?></th><th><?= __('cl_col_what') ?></th><th><?= __('cl_col_schedule') ?></th><th><?= __('cl_col_on') ?></th><th></th></tr></thead>
                    <tbody id="clRows"><tr><td colspan="7" class="sec-empty"><?= __('int_cl_empty') ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Webhook ===== -->
    <div class="sec-tab-content" data-tab="webhook">
        <div class="settings-card">
            <div class="settings-card-title sec-th">🔗 <?= __('int_wh_title') ?>
                <span id="whMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
            <div class="sec-note"><?= __('int_wh_intro') ?></div>
            <?php if ($ldCanEdit): ?><div style="margin: 8px 0;"><button type="button" class="sec-btn primary" onclick="whEdit('')">➕ <?= __('int_wh_add') ?></button></div><?php endif; ?>
            <div class="sec-scroll">
                <table class="sec-table">
                    <thead><tr><th><?= __('wh_col_name') ?></th><th><?= __('wh_col_host') ?></th><th><?= __('wh_col_format') ?></th><th><?= __('wh_col_events') ?></th><th><?= __('wh_col_last') ?></th><th><?= __('wh_col_on') ?></th><th></th></tr></thead>
                    <tbody id="whRows"><tr><td colspan="7" class="sec-empty"><?= __('int_wh_empty') ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Мультисервер (набросок) ===== -->
    <div class="sec-tab-content" data-tab="multiserver">
        <div class="settings-card ms-wip-wrap">
            <div class="ms-wip"><div class="ms-wip-box">🚧 <?= __('int_ms_wip') ?><div class="ix-sub"><?= __('int_ms_wip_sub') ?></div></div></div>
            <div class="settings-card-title sec-th">🛰️ <?= __('int_ms_title') ?>
                <span id="msMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
            <div class="sec-note"><?= __('int_ms_intro') ?></div>
            <?php if ($ldCanEdit): ?><div style="margin: 8px 0;"><button type="button" class="sec-btn primary" onclick="msEdit('')">➕ <?= __('int_ms_add') ?></button></div><?php endif; ?>
            <div class="sec-scroll">
                <table class="sec-table">
                    <thead><tr><th><?= __('ms_col_name') ?></th><th><?= __('ms_col_url') ?></th><th><?= __('ms_col_user') ?></th><th><?= __('ms_col_version') ?></th><th><?= __('ms_col_status') ?></th><th></th></tr></thead>
                    <tbody id="msRows"><tr><td colspan="6" class="sec-empty"><?= __('int_ms_empty') ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.ms-wip-wrap { position: relative; overflow: hidden; }
.ms-wip { position: absolute; inset: 0; z-index: 5; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, <?= $themeCSS['card'] ?> 82%, transparent); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); cursor: not-allowed; }
.ms-wip-box { text-align: center; font-size: 18px; font-weight: 600; color: <?= $themeCSS['text'] ?>; padding: 16px 24px; border: 1px dashed <?= $themeCSS['border'] ?>; border-radius: 10px; background: <?= $themeCSS['card'] ?>; }
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

<style>
.ix-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; overflow-y: auto; padding: 2px; }
.ix-grid label { display: block; font-size: 11px; color: <?= $themeCSS['muted'] ?>; margin-bottom: 3px; }
.ix-grid .full { grid-column: 1 / -1; }
.ix-checks { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 13px; }
.ix-checks label { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: <?= $themeCSS['text'] ?>; margin: 0; cursor: pointer; }
.ix-checks input, .ix-grid input[type=checkbox] { accent-color: <?= $themeCSS['primary'] ?>; }
.ix-sub { color: <?= $themeCSS['muted'] ?>; font-size: 11px; }
@media (max-width: 560px) { .ix-grid { grid-template-columns: 1fr; } }
</style>
<div id="whOverlay" class="ld-overlay" onclick="if (event.target === this) ixClose('whOverlay')">
    <div class="ld-modal">
        <h3>🔗 <?= __('int_wh_edit') ?></h3>
        <div class="ix-grid">
            <div><label><?= __('wh_field_name') ?></label><input type="text" id="whName" class="form-control" maxlength="60"></div>
            <div><label><?= __('wh_field_format') ?></label><select id="whFormat" class="form-control">
                <option value="json"><?= __('wh_fmt_json') ?></option><option value="slack"><?= __('wh_fmt_slack') ?></option><option value="text"><?= __('wh_fmt_text') ?></option></select></div>
            <div class="full"><label><?= __('wh_field_url') ?></label><input type="text" id="whUrl" class="form-control" autocomplete="off" placeholder="https://"><div class="ix-sub" id="whUrlHint"></div></div>
            <div><label><?= __('wh_field_secret') ?></label><input type="password" id="whSecret" class="form-control" autocomplete="new-password"><div class="ix-sub" id="whSecretHint"></div>
                <label id="whSecretClearBox" style="display: none; margin-top: 4px;"><input type="checkbox" id="whSecretClear"> <?= __('wh_field_secret_clear') ?></label></div>
            <div><label><?= __('wh_field_timeout') ?></label><input type="number" id="whTimeout" class="form-control" min="2" max="30" value="5"></div>
            <div class="full"><label><?= __('wh_field_events') ?></label><div class="ix-checks" id="whEvents"></div></div>
            <div class="full"><label style="display: inline-flex; gap: 6px; align-items: center; cursor: pointer;"><input type="checkbox" id="whEnabled" checked> <?= __('wh_field_enabled') ?></label></div>
        </div>
        <div class="ld-foot" style="margin-top: 12px;">
            <span id="whModalMsg" class="sec-msg" style="margin-right: auto;"></span>
            <button type="button" class="sec-btn" onclick="ixClose('whOverlay')"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" onclick="whSave()"><?= __('save') ?></button>
        </div>
    </div>
</div>
<div id="msOverlay" class="ld-overlay" onclick="if (event.target === this) ixClose('msOverlay')">
    <div class="ld-modal">
        <h3>🛰️ <?= __('int_ms_edit') ?></h3>
        <div class="ix-grid">
            <div class="full"><label><?= __('ms_field_name') ?></label><input type="text" id="msName" class="form-control" maxlength="60" autocomplete="off"></div>
            <div class="full"><label><?= __('ms_field_url') ?> *</label><input type="text" id="msUrl" class="form-control" autocomplete="off" placeholder="https://"><div class="ix-sub"><?= __('ms_field_url_hint') ?></div></div>
            <div><label><?= __('ms_field_user') ?> *</label><input type="text" id="msUser" class="form-control" autocomplete="off"></div>
            <div><label><?= __('ms_field_pass') ?> *</label><input type="password" id="msPass" class="form-control" autocomplete="new-password"><div class="ix-sub" id="msPassHint"></div></div>
            <div class="full ix-sub"><?= __('ms_hint_check') ?></div>
        </div>
        <div class="ld-foot" style="margin-top: 12px;">
            <span id="msModalMsg" class="sec-msg" style="margin-right: auto;"></span>
            <button type="button" class="sec-btn" onclick="ixClose('msOverlay')"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" id="msSaveBtn" onclick="msSave()"><?= __('ms_btn_save') ?></button>
        </div>
    </div>
</div>

<div id="clOverlay" class="ld-overlay" onclick="if (event.target === this) ixClose('clOverlay')">
    <div class="ld-modal">
        <h3>☁️ <?= __('int_tab_cloud') ?></h3>
        <div class="ix-grid">
            <div><label><?= __('cl_field_name') ?></label><input type="text" id="clName" class="form-control" maxlength="60"></div>
            <div><label><?= __('cl_field_type') ?></label><select id="clType" class="form-control" onchange="clTypeChanged(true)">
                <option value="sftp">SFTP</option><option value="ftp">FTP</option><option value="ftps">FTPS</option><option value="webdav">WebDAV</option><option value="s3">S3</option></select></div>
            <div id="clHostBox"><label id="clHostLbl"><?= __('cl_field_host') ?></label><input type="text" id="clHost" class="form-control" autocomplete="off"></div>
            <div id="clPortBox"><label><?= __('cl_field_port') ?></label><input type="number" id="clPort" class="form-control" min="1" max="65535"></div>
            <div id="clBucketBox"><label><?= __('cl_field_bucket') ?></label><input type="text" id="clBucket" class="form-control" autocomplete="off"></div>
            <div id="clRegionBox"><label><?= __('cl_field_region') ?></label><input type="text" id="clRegion" class="form-control" autocomplete="off" placeholder="us-east-1"></div>
            <div><label><?= __('cl_field_user') ?></label><input type="text" id="clUser" class="form-control" autocomplete="off"></div>
            <div><label><?= __('cl_field_pass') ?></label><input type="password" id="clPass" class="form-control" autocomplete="new-password"><div class="ix-sub" id="clPassHint"></div>
                <label id="clPassClearBox" style="display: none; margin-top: 4px;"><input type="checkbox" id="clPassClear"> <?= __('cl_field_pass_clear') ?></label></div>
            <div class="full"><label><?= __('cl_field_path') ?></label><input type="text" id="clPath" class="form-control" autocomplete="off" placeholder="/backups/kms"></div>
            <div class="full"><label><?= __('cl_field_what') ?></label><div class="ix-checks" id="clWhat">
                <?php foreach (['config', 'users', 'log', 'audit'] as $w): ?><label><input type="checkbox" value="<?= $w ?>"> <?= __('cl_what_' . $w) ?></label><?php endforeach; ?></div></div>
            <div><label><?= __('cl_field_schedule') ?></label><select id="clSchedule" class="form-control">
                <option value="off"><?= __('cl_sched_off') ?></option><option value="daily"><?= __('cl_sched_daily') ?></option><option value="weekly"><?= __('cl_sched_weekly') ?></option></select></div>
            <div><label><?= __('cl_field_time') ?></label><input type="time" id="clTime" class="form-control" value="03:00"></div>
            <div><label><?= __('cl_field_keep') ?></label><input type="number" id="clKeep" class="form-control" min="1" max="60" value="7"></div>
            <div><label style="display: inline-flex; gap: 6px; align-items: center; cursor: pointer; margin-top: 18px;"><input type="checkbox" id="clEnabled" checked> <?= __('cl_field_enabled') ?></label></div>
        </div>
        <div class="ld-foot" style="margin-top: 12px;">
            <span id="clModalMsg" class="sec-msg" style="margin-right: auto;"></span>
            <button type="button" class="sec-btn" onclick="ixClose('clOverlay')"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" onclick="clSave()"><?= __('save') ?></button>
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
// Статус подключения: «Активен» / «Не подключен»; устаревший (12 ч) результат сервер перепроверяет сам
function ldStatusShow(st) {
    const el = document.getElementById('ldStatus');
    if (!el || !st) { return; }
    const map = { active: ['ok', <?= json_encode('● ' . __('ldap_status_active')) ?>], down: ['err', <?= json_encode('● ' . __('ldap_status_down')) ?>], off: ['', <?= json_encode('○ ' . __('ldap_status_off')) ?>], unknown: ['', <?= json_encode('○ ' . __('ldap_status_unknown')) ?>] };
    const m = map[st.state] || map.unknown;
    el.className = 'sec-badge' + (m[0] ? ' ' + m[0] : '');
    el.textContent = m[1];
    el.title = (st.time ? <?= json_encode(__('ldap_status_checked')) ?> + ' ' + st.time : '') + (st.text ? ' — ' + st.text : '');
}
function ldStatusLoad() { secPost({ ajax: 'ldap_status' }).then(d => { if (d.success) { ldStatusShow(d.status); } }).catch(() => {}); }
function ldTest() {
    secMsg('ldMsg', true, <?= json_encode(__('ldap_testing')) ?>);
    // проверяется то, что сейчас в форме: сначала сохраняем поля, затем проверяем связь
    secPost(Object.assign({ ajax: 'ldap_save_settings' }, ldCollect())).then(function (sv) {
        if (!sv.success) { secMsg('ldMsg', false, sv.message || ''); return null; }
        return secPost({ ajax: 'ldap_test' }).then(d => { secMsg('ldMsg', !!d.success, d.message || ''); ldStatusLoad(); window.addEventListener('vlmc-sched-done', ldStatusLoad); });
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

// ===== Webhook =====
var whList = [], whEventList = [], whCur = '';
function ixClose(id) { document.getElementById(id).classList.remove('open'); }
function ixTime(t) { return t ? t.replace('T', ' ').slice(0, 16) : ''; }
function whLoad() {
    secPost({ ajax: 'webhook_list' }).then(function (d) {
        if (!d.success) { return; }
        whList = d.hooks || []; whEventList = d.events || [];
        var tb = document.getElementById('whRows');
        if (!whList.length) { tb.innerHTML = '<tr><td colspan="7" class="sec-empty">' + secEsc(<?= json_encode(__('int_wh_empty')) ?>) + '</td></tr>'; return; }
        tb.innerHTML = whList.map(function (h) {
            var last = h.last_time ? '<span class="sec-badge ' + (h.last_ok ? 'ok' : 'err') + '">' + secEsc(h.last_ok ? 'OK' : 'ERR') + '</span> <span class="sec-mono">' + secEsc(ixTime(h.last_time)) + '</span>' + (h.last_ok ? '' : '<div class="ix-sub">' + secEsc(h.last_text) + '</div>') : '<span class="ix-sub">' + secEsc(<?= json_encode(__('wh_never')) ?>) + '</span>';
            var act = ldCanEdit ? '<button type="button" class="sec-btn sm" onclick="whTest(\'' + h.id + '\')">🧪 ' + secEsc(<?= json_encode(__('wh_test')) ?>) + '</button> <button type="button" class="sec-btn sm" onclick="whEdit(\'' + h.id + '\')">✏️</button> <button type="button" class="sec-btn sm" onclick="whDelete(\'' + h.id + '\')">🗑️</button>' : '';
            return '<tr><td>' + secEsc(h.name) + '</td><td class="sec-mono">' + secEsc(h.scheme + '://' + h.host) + '</td><td>' + secEsc(h.format) + '</td><td>' + secEsc(<?= json_encode(__('wh_events_n')) ?>.replace('%d', (h.events || []).length)) + '</td><td>' + last
                + '</td><td><input type="checkbox" ' + (h.enabled ? 'checked ' : '') + (ldCanEdit ? '' : 'disabled ') + 'onchange="whToggle(\'' + h.id + '\', this.checked)"></td><td style="white-space: nowrap;">' + act + '</td></tr>';
        }).join('');
    });
}
function whEdit(id) {
    var h = whList.filter(function (x) { return x.id === id; })[0] || { name: '', format: 'json', timeout: 5, events: [], enabled: true, host: '', has_secret: false };
    whCur = id;
    document.getElementById('whName').value = h.name; document.getElementById('whFormat').value = h.format;
    document.getElementById('whUrl').value = ''; document.getElementById('whUrlHint').textContent = id ? (h.scheme + '://' + h.host + ' — ' + <?= json_encode(__('wh_field_url_keep')) ?>) : '';
    document.getElementById('whSecret').value = ''; document.getElementById('whSecretHint').textContent = h.has_secret ? <?= json_encode(__('wh_field_secret_keep')) ?> : '';
    document.getElementById('whSecretClearBox').style.display = h.has_secret ? '' : 'none'; document.getElementById('whSecretClear').checked = false;
    document.getElementById('whTimeout').value = h.timeout || 5; document.getElementById('whEnabled').checked = !!h.enabled;
    document.getElementById('whEvents').innerHTML = whEventList.map(function (e) {
        return '<label><input type="checkbox" value="' + secEsc(e.code) + '"' + ((h.events || []).indexOf(e.code) >= 0 ? ' checked' : '') + '> ' + secEsc(e.label) + '</label>';
    }).join('');
    document.getElementById('whModalMsg').className = 'sec-msg';
    document.getElementById('whOverlay').classList.add('open');
}
function whSave() {
    var data = { name: document.getElementById('whName').value, format: document.getElementById('whFormat').value, url: document.getElementById('whUrl').value,
        secret: document.getElementById('whSecret').value, clear_secret: document.getElementById('whSecretClear').checked, timeout: document.getElementById('whTimeout').value,
        enabled: document.getElementById('whEnabled').checked,
        events: Array.prototype.map.call(document.querySelectorAll('#whEvents input:checked'), function (i) { return i.value; }) };
    secPost({ ajax: 'webhook_save', id: whCur, data: JSON.stringify(data) }).then(function (d) {
        if (d.success) { ixClose('whOverlay'); secMsg('whMsg', true, d.message); whLoad(); } else { secMsg('whModalMsg', false, d.message); }
    });
}
function whToggle(id, on) { secPost({ ajax: 'webhook_toggle', id: id, enabled: on ? '1' : '0' }).then(function (d) { secMsg('whMsg', d.success, d.message); whLoad(); }); }
function whDelete(id) {
    var h = whList.filter(function (x) { return x.id === id; })[0];
    if (!h || !confirm(<?= json_encode(__('wh_confirm_delete')) ?>.replace('%s', h.name))) { return; }
    secPost({ ajax: 'webhook_delete', id: id }).then(function (d) { secMsg('whMsg', d.success, d.message); whLoad(); });
}
function whTest(id) { secPost({ ajax: 'webhook_test', id: id }).then(function (d) { secMsg('whMsg', d.success, d.message); whLoad(); }); }

// ===== Cloud Storage =====
var clList = [], clCur = '';
var clPorts = { sftp: 22, ftp: 21, ftps: 21, webdav: '', s3: '' };
var clWhatStr = { config: <?= json_encode(__('cl_what_config')) ?>, users: <?= json_encode(__('cl_what_users')) ?>, log: <?= json_encode(__('cl_what_log')) ?>, audit: <?= json_encode(__('cl_what_audit')) ?> };
var clSchedStr = { off: <?= json_encode(__('cl_sched_off')) ?>, daily: <?= json_encode(__('cl_sched_daily')) ?>, weekly: <?= json_encode(__('cl_sched_weekly')) ?> };
function clLoad() {
    secPost({ ajax: 'cloud_list' }).then(function (d) {
        if (!d.success) { return; }
        clList = d.targets || [];
        var tb = document.getElementById('clRows');
        if (!clList.length) { tb.innerHTML = '<tr><td colspan="7" class="sec-empty">' + secEsc(<?= json_encode(__('int_cl_empty')) ?>) + '</td></tr>'; return; }
        tb.innerHTML = clList.map(function (t) {
            var act = ldCanEdit ? '<button type="button" class="sec-btn sm" onclick="clTest(\'' + t.id + '\')">🧪 ' + secEsc(<?= json_encode(__('cl_test')) ?>) + '</button> <button type="button" class="sec-btn sm" onclick="clEdit(\'' + t.id + '\')">✏️</button> <button type="button" class="sec-btn sm" onclick="clDelete(\'' + t.id + '\')">🗑️</button>' : '';
            var sched = clSchedStr[t.schedule] + (t.schedule !== 'off' ? ' ' + t.time : '');
            return '<tr><td>' + secEsc(t.name) + '</td><td>' + secEsc(t.type.toUpperCase()) + '</td><td class="sec-mono">' + secEsc(t.host + (t.port ? ':' + t.port : '') + (t.bucket ? ' / ' + t.bucket : '')) + '</td><td>'
                + secEsc((t.what || []).map(function (w) { return clWhatStr[w]; }).join(', ')) + '</td><td>' + secEsc(sched)
                + '</td><td><input type="checkbox" ' + (t.enabled ? 'checked ' : '') + (ldCanEdit ? '' : 'disabled ') + 'onchange="clToggle(\'' + t.id + '\', this.checked)"></td><td style="white-space: nowrap;">' + act + '</td></tr>';
        }).join('');
    });
}
function clTypeChanged(resetPort) {
    var ty = document.getElementById('clType').value, web = (ty === 'webdav' || ty === 's3');
    document.getElementById('clHostLbl').textContent = web ? <?= json_encode(__('cl_field_url')) ?> : <?= json_encode(__('cl_field_host')) ?>;
    document.getElementById('clPortBox').style.display = web ? 'none' : '';
    document.getElementById('clBucketBox').style.display = ty === 's3' ? '' : 'none';
    document.getElementById('clRegionBox').style.display = ty === 's3' ? '' : 'none';
    if (resetPort) { document.getElementById('clPort').value = clPorts[ty]; }
}
function clEdit(id) {
    var t = clList.filter(function (x) { return x.id === id; })[0] || { name: '', type: 'sftp', host: '', port: 22, user: '', has_pass: false, path: '', bucket: '', region: '', what: ['config'], schedule: 'off', time: '03:00', keep: 7, enabled: true };
    clCur = id;
    ['Name:name', 'Type:type', 'Host:host', 'Port:port', 'User:user', 'Path:path', 'Bucket:bucket', 'Region:region', 'Schedule:schedule', 'Time:time', 'Keep:keep'].forEach(function (p) {
        var kv = p.split(':'); document.getElementById('cl' + kv[0]).value = t[kv[1]] == null ? '' : t[kv[1]];
    });
    document.getElementById('clEnabled').checked = !!t.enabled;
    document.getElementById('clPass').value = ''; document.getElementById('clPassHint').textContent = t.has_pass ? <?= json_encode(__('cl_field_pass_keep')) ?> : '';
    document.getElementById('clPassClearBox').style.display = t.has_pass ? '' : 'none'; document.getElementById('clPassClear').checked = false;
    Array.prototype.forEach.call(document.querySelectorAll('#clWhat input'), function (i) { i.checked = (t.what || []).indexOf(i.value) >= 0; });
    clTypeChanged(false);
    document.getElementById('clModalMsg').className = 'sec-msg';
    document.getElementById('clOverlay').classList.add('open');
}
function clSave() {
    var g = function (n) { return document.getElementById('cl' + n).value; };
    var data = { name: g('Name'), type: g('Type'), host: g('Host'), port: g('Port'), user: g('User'), pass: g('Pass'), clear_pass: document.getElementById('clPassClear').checked,
        path: g('Path'), bucket: g('Bucket'), region: g('Region'), schedule: g('Schedule'), time: g('Time'), keep: g('Keep'), enabled: document.getElementById('clEnabled').checked,
        what: Array.prototype.map.call(document.querySelectorAll('#clWhat input:checked'), function (i) { return i.value; }) };
    secPost({ ajax: 'cloud_save', id: clCur, data: JSON.stringify(data) }).then(function (d) {
        if (d.success) { ixClose('clOverlay'); secMsg('clMsg', true, d.message); clLoad(); } else { secMsg('clModalMsg', false, d.message); }
    });
}
function clToggle(id, on) { secPost({ ajax: 'cloud_toggle', id: id, enabled: on ? '1' : '0' }).then(function (d) { secMsg('clMsg', d.success, d.message); clLoad(); }); }
function clDelete(id) {
    var t = clList.filter(function (x) { return x.id === id; })[0];
    if (!t || !confirm(<?= json_encode(__('cl_confirm_delete')) ?>.replace('%s', t.name))) { return; }
    secPost({ ajax: 'cloud_delete', id: id }).then(function (d) { secMsg('clMsg', d.success, d.message); clLoad(); });
}
function clTest(id) { secPost({ ajax: 'cloud_test', id: id }).then(function (d) { secMsg('clMsg', d.success, d.message); }); }
// ===== Мультисервер (набросок) =====
var msList = [], msCur = '';
function msLoad() {
    secPost({ ajax: 'ms_list' }).then(function (d) {
        if (!d.success) { return; }
        msList = d.servers || [];
        var tb = document.getElementById('msRows');
        if (!msList.length) { tb.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(<?= json_encode(__('int_ms_empty')) ?>) + '</td></tr>'; return; }
        tb.innerHTML = msList.map(function (s) {
            var st = s.last_time ? '<span class="sec-badge ' + (s.last_ok ? 'ok' : 'err') + '" title="' + secEsc(s.last_text) + '">' + (s.last_ok ? 'OK' : 'ERR') + '</span> <span class="sec-mono">' + secEsc(s.last_time.slice(0, 16)) + '</span>' : '<span class="ix-sub">' + secEsc(<?= json_encode(__('ms_never')) ?>) + '</span>';
            var act = ldCanEdit ? '<button type="button" class="sec-btn sm" onclick="msTest(\'' + s.id + '\', this)">🧪 ' + secEsc(<?= json_encode(__('ms_test')) ?>) + '</button> <button type="button" class="sec-ico blue" onclick="msEdit(\'' + s.id + '\')">✏️</button> <button type="button" class="sec-ico danger" onclick="msDelete(\'' + s.id + '\')">🗑️</button>' : '';
            return '<tr><td>' + secEsc(s.name) + '</td><td class="sec-mono">' + secEsc(s.url) + '</td><td>' + secEsc(s.user) + '</td><td>' + secEsc(s.version || '—') + '</td><td>' + st + '</td><td style="white-space: nowrap;">' + act + '</td></tr>';
        }).join('');
    });
}
function msEdit(id) {
    var s = msList.filter(function (x) { return x.id === id; })[0] || { name: '', url: '', user: '' };
    msCur = id;
    document.getElementById('msName').value = s.name; document.getElementById('msUrl').value = s.url; document.getElementById('msUser').value = s.user;
    document.getElementById('msPass').value = ''; document.getElementById('msPassHint').textContent = id ? <?= json_encode(__('ms_field_pass_keep')) ?> : '';
    document.getElementById('msModalMsg').className = 'sec-msg';
    document.getElementById('msOverlay').classList.add('open');
}
function msSave() {
    var btn = document.getElementById('msSaveBtn'), label = btn.textContent;
    var data = { name: document.getElementById('msName').value, url: document.getElementById('msUrl').value, user: document.getElementById('msUser').value, pass: document.getElementById('msPass').value };
    btn.disabled = true; btn.textContent = <?= json_encode(__('ms_checking')) ?>;
    secPost({ ajax: 'ms_save', id: msCur, data: JSON.stringify(data) }).then(function (d) {
        btn.disabled = false; btn.textContent = label;
        if (d.success) { ixClose('msOverlay'); secMsg('msMsg', true, d.message); msLoad(); } else { secMsg('msModalMsg', false, d.message); }
    }).catch(function () { btn.disabled = false; btn.textContent = label; secMsg('msModalMsg', false, <?= json_encode(__('msg_save_error')) ?>); });
}
function msTest(id, btn) {
    btn.disabled = true;
    secPost({ ajax: 'ms_test', id: id }).then(function (d) { secMsg('msMsg', d.success, d.message); msLoad(); }).catch(function () { btn.disabled = false; });
}
function msDelete(id) {
    var s = msList.filter(function (x) { return x.id === id; })[0];
    if (!s || !confirm(<?= json_encode(__('ms_confirm_delete')) ?>.replace('%s', s.name))) { return; }
    secPost({ ajax: 'ms_delete', id: id }).then(function (d) { secMsg('msMsg', d.success, d.message); msLoad(); });
}
document.addEventListener('DOMContentLoaded', function () { whLoad(); clLoad(); msLoad(); ldStatusLoad(); });
</script>
