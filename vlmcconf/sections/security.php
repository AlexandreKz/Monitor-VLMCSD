<?php
// ============================================
// ФАЙЛ: sections/security.php
// ВЕРСИЯ: 6.0.6
// ДАТА: 2026-11-18
// @description: Секция "Безопасность" (вкладки «Пользователи», «Доступ и IP», Firewall) — управление пользователями, не подозрительные IP, ограничение доступа к панели, блокировка IP, скрытие IP от неавторизованных и таймаут входа (только администратор)
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'security.php') {
    http_response_code(403);
    exit('Access denied');
}

// Проверяем права доступа к секциям
$userPerms = $_SESSION['vlmc_permissions'] ?? 0;
$canViewUsers = hasPermission($userPerms, PERM_USERS_VIEW);
$canEditUsers = hasPermission($userPerms, PERM_USERS_EDIT);
$canManageWhitelist = hasPermission($userPerms, PERM_IP_WHITELIST);
$isFullAdmin = hasPermission($userPerms, PERM_ADMIN_FULL);
$hideIps = !empty($config['privacy']['hide_ips']);
$sessionTimeoutMin = max(5, min(1440, (int)($config['security']['session_timeout_min'] ?? 30)));

// Получаем список белых IP из конфига
$whitelistIps = $config['whitelist_ips'] ?? [];

require_once __DIR__ . '/../vlmcinc/section_ui.php';
require_once __DIR__ . '/../vlmcinc/panel_access.php';
require_once __DIR__ . '/../vlmcinc/firewall.php';

// Ограничение доступа к панели и Firewall: менять может только полный администратор
$pwSettings = vlmcPanelAccessSettings($config);
$pwMyIp = vlmcPanelClientIp();
$pwEmergency = vlmcPanelAccessEmergencyOff();
$pwOffFile = vlmcDataDir() . DIRECTORY_SEPARATOR . 'panel_whitelist.off';
$fwSet = fwSettings($config);
$fwBlocked = fwList();
$fwIsWin = fwIsWindows();
$fwHelperSrc = realpath(__DIR__ . '/../vlmcinc/vlmc-firewall.sh') ?: (__DIR__ . '/../vlmcinc/vlmc-firewall.sh');
$fwHelperDst = FW_HELPER_DEFAULT;
$webUser = 'www-data';

vlmcSectionUi($themeCSS);
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
const PERM_TOOLS_VIEW = <?= PERM_TOOLS_VIEW ?>;
const PERM_TOOLS_EDIT = <?= PERM_TOOLS_EDIT ?>;
const PERM_IP_WHITELIST = <?= PERM_IP_WHITELIST ?>;
const PERM_NOTIFY_EDIT = <?= PERM_NOTIFY_EDIT ?>;
</script>

<div id="section-security" class="settings-section <?= $activeSection === 'security' ? 'active' : '' ?>">
    <div class="section-title sec-title">
        <span>🔒 <?= __('security_title') ?></span>
        <?php if ($activeSection === 'security' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="sec-tabs">
            <button class="sec-tab-btn active" data-for="users" onclick="secTab('section-security','users',this)">👥 <?= __('sec_tab_users') ?></button>
            <button class="sec-tab-btn" data-for="access" onclick="secTab('section-security','access',this)">🌐 <?= __('sec_tab_access') ?></button>
            <button class="sec-tab-btn" data-for="firewall" onclick="secTab('section-security','firewall',this); if (window.fwFirstCheck) { fwFirstCheck(); }">🚫 <?= __('sec_tab_firewall') ?></button>
        </div>
    </div>

    <!-- ===== Вкладка «Пользователи» ===== -->
    <div class="sec-tab-content active" data-tab="users">
    <div class="security-two-columns">

        <!-- ЛЕВАЯ КОЛОНКА: Управление пользователями (65%) -->
        <div class="security-users-column">
            <div class="settings-card">
                <div class="settings-card-title">👥 <?= __('users_management') ?></div>
                
                <!-- Кнопка добавления пользователя (только если есть права на редактирование) -->
                <?php if ($canEditUsers): ?>
                <div style="margin-bottom: 15px;">
                    <button class="sec-btn primary" onclick="showAddUserModal()">➕ <?= __('users_add') ?></button>
                </div>
                <?php endif; ?>
                
                <!-- Список пользователей -->
                <div id="usersList" style="max-height: 400px; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 0.8fr; gap: 10px; padding: 8px 0; border-bottom: 2px solid <?= $themeCSS['border'] ?>; font-weight: 600; font-size: 11px;">
                        <span><?= __('users_username') ?></span>
                        <span><?= __('users_role') ?></span>
                        <span><?= __('users_created') ?></span>
                        <span><?= __('actions') ?></span>
                    </div>
                    <div id="usersListContainer">⏳ <?= __('loading') ?></div>
                </div>
            </div>
        </div>
        
        <!-- ПРАВАЯ КОЛОНКА: таймаут входа и скрытие IP (только администратор) -->
        <div class="security-side-column">
			<?php if ($isFullAdmin): ?>
			<!-- Таймаут входа (только администратор) -->
			<div class="settings-card privacy-card">
				<div class="settings-card-title">⏱️ <?= __('session_timeout_title') ?></div>
				<div class="session-row">
					<input type="number" id="sessionTimeoutMin" class="form-control" min="5" max="1440" step="1" value="<?= (int)$sessionTimeoutMin ?>">
					<span><?= __('session_timeout_unit') ?></span>
					<button type="button" class="sec-btn primary" onclick="saveSessionSettings()">💾 <?= __('save') ?></button>
				</div>
				<p class="privacy-hint"><?= __('session_timeout_hint') ?></p>
				<div id="sessionMessage" class="privacy-message" style="display: none;"></div>
			</div>

			<!-- Скрытие IP от неавторизованных (только администратор) -->
			<div class="settings-card privacy-card">
				<div class="settings-card-title">🙈 <?= __('privacy_title') ?></div>
				<label class="privacy-switch">
					<input type="checkbox" id="hideIps" <?= $hideIps ? 'checked' : '' ?> onchange="savePrivacySettings(this)">
					<span><?= __('privacy_hide_ips_label') ?></span>
				</label>
				<p class="privacy-hint"><?= __('privacy_hide_ips_hint') ?></p>
				<div id="privacyMessage" class="privacy-message" style="display: none;"></div>
			</div>
			<?php endif; ?>
        </div>

    </div>
    </div>

    <!-- ===== Вкладка «Доступ и IP» ===== -->
    <div class="sec-tab-content" data-tab="access">
        <div class="sec-grid">
            <div class="sec-col" style="flex: 0.5;">
			<div class="settings-card">
				<div class="settings-card-title"><?= __('whitelist_title') ?></div>  <!-- иконка уже в переводе -->
				
				<!-- Строка с кнопкой и описанием -->
				<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
					<?php if ($canManageWhitelist): ?>
					<button class="sec-btn primary" onclick="showAddWhitelistIpModal()"><?= __('whitelist_add_ip') ?></button>
					<?php endif; ?>
					<p style="margin: 0; flex: 1; font-size: 11px; color: <?= $themeCSS['muted'] ?>; line-height: 1.4;"><?= __('whitelist_description') ?></p>
				</div>
				
				<!-- Таблица белых IP -->
				<div id="whitelistList" style="max-height: 300px; overflow-y: auto;">
					<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; padding: 8px 0; border-bottom: 2px solid <?= $themeCSS['border'] ?>; font-weight: 600; font-size: 11px;">
						<span><?= __('whitelist_ip') ?></span>
						<span><?= __('actions') ?></span>
					</div>
					<div id="whitelistListContainer">
					<?php if (empty($whitelistIps)): ?>
						<div style="text-align: center; padding: 20px; color: <?= $themeCSS['muted'] ?>;"><?= __('whitelist_empty') ?></div>
					<?php else: ?>
						<?php foreach ($whitelistIps as $ip): ?>
						<div class="whitelist-item" data-ip="<?= htmlspecialchars($ip) ?>">
							<span><?= htmlspecialchars($ip) ?></span>
							<span>
								<?php if ($canManageWhitelist): ?>
								<button class="sec-ico danger" onclick="removeWhitelistIp('<?= htmlspecialchars($ip) ?>')" title="<?= __('delete') ?>">🗑️</button>
								<?php else: ?>
								<span class="lock-icon" title="<?= __('access_denied') ?>">🔒</span>
								<?php endif; ?>
							</span>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				</div>
			</div>

                <?php if ($isFullAdmin): ?>
                <div class="settings-card">
                    <div class="settings-card-title sec-th">🌐 <?= __('panel_wl_title') ?> <span id="pwMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
                    <label class="sec-switch">
                        <input type="checkbox" id="pwEnabled" <?= $pwSettings['enabled'] ? 'checked' : '' ?>>
                        <span><?= __('panel_wl_enable') ?></span>
                    </label>
                    <p class="sec-hint"><?= __('panel_wl_hint') ?></p>

                    <div class="sec-row" style="margin-top: 12px;">
                        <span><?= __('panel_wl_your_ip') ?>: <b class="sec-mono" id="pwMyIp"><?= htmlspecialchars($pwMyIp ?: '—') ?></b></span>
                        <span id="pwMyState" class="sec-badge"></span>
                    </div>

                    <div class="sec-row">
                        <input type="text" id="pwNewIp" class="form-control" placeholder="203.0.113.10 / 192.168.1.0/24 / 2001:db8::/32" autocomplete="off">
                        <input type="text" id="pwNewComment" class="form-control" placeholder="<?= __('panel_wl_comment_ph') ?>" maxlength="100" autocomplete="off">
                        <button type="button" class="sec-btn primary" onclick="pwAdd()">➕ <?= __('panel_wl_add') ?></button>
                    </div>
                    <div class="sec-row" style="margin-bottom: 0;">
                        <button type="button" class="sec-btn" onclick="pwAddMine()" <?= $pwMyIp === '' ? 'disabled' : '' ?>>📍 <?= __('panel_wl_add_mine') ?></button>
                        <button type="button" class="sec-btn primary" onclick="pwSave()">💾 <?= __('save') ?></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="settings-card"><div class="sec-note"><?= __('access_admin_only') ?></div></div>
                <?php endif; ?>
            </div>
            <div class="sec-col" style="flex: 0.5;">
                <?php if ($isFullAdmin): ?>
                <div class="settings-card">
                    <div class="settings-card-title">📋 <?= __('panel_wl_list') ?></div>
                    <div class="sec-scroll">
                        <table class="sec-table">
                            <thead><tr><th><?= __('panel_wl_col_ip') ?></th><th><?= __('panel_wl_col_comment') ?></th><th></th></tr></thead>
                            <tbody id="pwRows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="settings-card">
                    <div class="settings-card-title">🆘 <?= __('panel_wl_emergency_title') ?></div>
                    <?php if ($pwEmergency): ?>
                    <div class="sec-note warn"><?= __('panel_wl_emergency_active') ?></div>
                    <?php endif; ?>
                    <?php
                    $pwFileName = basename($pwOffFile);
                    $pwCmdMake = $fwIsWin ? 'type nul > "' . $pwOffFile . '"' : 'touch ' . $pwOffFile;
                    $pwCmdDel = $fwIsWin ? 'del "' . $pwOffFile . '"' : 'rm ' . $pwOffFile;
                    ?>
                    <p class="sec-hint" style="margin-top:0;"><?= sprintf(htmlspecialchars(__('panel_wl_emergency_text')), '<b>' . htmlspecialchars($pwFileName) . '</b>') ?></p>
                    <div class="sec-code pw-cmd" title="<?= htmlspecialchars(__('panel_wl_click_copy')) ?>" onclick="pwCopyCmd(this)"><?= htmlspecialchars($pwCmdMake) ?></div>
                    <p class="sec-hint"><?= htmlspecialchars(__('panel_wl_emergency_text2')) ?></p>
                    <div class="sec-code pw-cmd" title="<?= htmlspecialchars(__('panel_wl_click_copy')) ?>" onclick="pwCopyCmd(this)"><?= htmlspecialchars($pwCmdDel) ?></div>
                    <p class="sec-hint"><?= __('panel_wl_scope_note') ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Вкладка Firewall ===== -->
    <div class="sec-tab-content" data-tab="firewall">
        <?php if ($isFullAdmin): ?>
        <div class="sec-grid">
            <div class="sec-col" style="flex: 0.42;">
                <div class="settings-card">
                    <div class="settings-card-title sec-th">⚙️ <?= __('fw_title') ?> <span id="fwSetMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
                    <div class="sec-row">
                        <span><?= __('fw_status') ?>:</span>
                        <span id="fwState" class="sec-badge"><?= __('fw_checking') ?></span>
                        <button type="button" class="sec-btn sm" onclick="fwCheck()">🔄 <?= __('fw_check') ?></button>
                    </div>
                    <p class="sec-hint" id="fwStateText" style="margin-top: 0;"></p>
                    <hr style="border: none; border-top: 1px solid <?= $themeCSS['border'] ?>; margin: 12px 0;">
                    <div class="sec-row">
                        <label for="fwBackend"><?= __('fw_backend') ?></label>
                        <select id="fwBackend" class="form-control">
                            <?php if ($fwIsWin): ?>
                            <option value="auto" <?= $fwSet['backend'] !== 'none' ? 'selected' : '' ?>>Windows Firewall (netsh)</option>
                            <?php else: ?>
                            <option value="auto" <?= $fwSet['backend'] === 'auto' ? 'selected' : '' ?>><?= __('fw_backend_auto') ?></option>
                            <option value="ufw" <?= $fwSet['backend'] === 'ufw' ? 'selected' : '' ?>>ufw</option>
                            <option value="iptables" <?= $fwSet['backend'] === 'iptables' ? 'selected' : '' ?>>iptables</option>
                            <option value="nftables" <?= $fwSet['backend'] === 'nftables' ? 'selected' : '' ?>>nftables</option>
                            <?php endif; ?>
                            <option value="none" <?= $fwSet['backend'] === 'none' ? 'selected' : '' ?>><?= __('fw_backend_none') ?></option>
                        </select>
                    </div>
                    <div class="sec-row">
                        <label for="fwScope"><?= __('fw_scope') ?></label>
                        <select id="fwScope" class="form-control" onchange="fwScopeChange()">
                            <option value="all" <?= $fwSet['scope'] === 'all' ? 'selected' : '' ?>><?= __('fw_scope_all') ?></option>
                            <option value="port" <?= $fwSet['scope'] === 'port' ? 'selected' : '' ?>><?= __('fw_scope_port') ?></option>
                        </select>
                        <input type="number" id="fwPort" class="form-control sec-narrow" min="1" max="65535" value="<?= (int)$fwSet['port'] ?>" <?= $fwSet['scope'] === 'port' ? '' : 'disabled' ?>>
                    </div>
                    <div class="sec-row" style="margin-bottom: 0;">
                        <button type="button" class="sec-btn primary" onclick="fwSaveSettings()">💾 <?= __('save') ?></button>
                    </div>
                </div>

                <?php if (!$fwIsWin): ?>
                <div class="settings-card" id="fwSetupCard">
                    <div class="settings-card-title">🔧 <?= __('fw_setup_title') ?></div>
                    <p class="sec-hint" style="margin-top:0;"><?= __('fw_setup_text') ?></p>
                    <div class="sec-code pw-cmd" id="fwSetupCode" title="<?= htmlspecialchars(__('panel_wl_click_copy')) ?>" onclick="pwCopyCmd(this)">sudo install -m 750 -o root -g root <?= htmlspecialchars($fwHelperSrc) ?> <?= htmlspecialchars($fwHelperDst) ?>

echo '<?= htmlspecialchars($webUser) ?> ALL=(root) NOPASSWD: <?= htmlspecialchars($fwHelperDst) ?>' | sudo tee /etc/sudoers.d/vlmc-firewall
sudo chmod 440 /etc/sudoers.d/vlmc-firewall</div>
                    <p class="sec-hint"><?= __('fw_setup_note') ?></p>
                </div>
                <?php else: ?>
                <div class="settings-card">
                    <div class="settings-card-title">🔧 <?= __('fw_setup_title') ?></div>
                    <p class="sec-hint" style="margin-top:0;"><?= __('fw_setup_windows') ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="sec-col" style="flex: 0.58;">
                <div class="settings-card">
                    <div class="settings-card-title sec-th">🚫 <?= __('fw_list_title') ?> <span class="sec-badge" id="fwCount"><?= count($fwBlocked) ?></span> <span id="fwMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
                    <div class="sec-row">
                        <input type="text" id="fwNewIp" class="form-control" placeholder="203.0.113.7" autocomplete="off">
                        <input type="text" id="fwNewReason" class="form-control" placeholder="<?= __('fw_reason_ph') ?>" maxlength="200" autocomplete="off">
                        <button type="button" class="sec-btn danger" onclick="fwBlockNew()">🚫 <?= __('fw_block') ?></button>
                    </div>
                    <div class="sec-scroll">
                        <table class="sec-table">
                            <thead><tr><th>IP</th><th><?= __('fw_col_reason') ?></th><th><?= __('fw_col_by') ?></th><th><?= __('fw_col_time') ?></th><th><?= __('fw_col_status') ?></th><th></th></tr></thead>
                            <tbody id="fwRows"></tbody>
                        </table>
                    </div>
                    <div class="sec-row" style="margin: 10px 0 0;">
                        <button type="button" class="sec-btn sm" onclick="fwApply(false)">▶️ <?= __('fw_apply_pending') ?></button>
                        <button type="button" class="sec-btn sm" onclick="fwApply(true)">🔁 <?= __('fw_apply_all') ?></button>
                    </div>
                    <p class="sec-hint"><?= __('fw_list_hint') ?></p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="settings-card"><div class="sec-note"><?= __('access_admin_only') ?></div></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isFullAdmin): ?>
<script>
// ===== Доступ к панели (п. 29) =====
let pwEntries = <?= json_encode($pwSettings['entries'], JSON_UNESCAPED_UNICODE) ?>;
const pwMyIp = <?= json_encode($pwMyIp) ?>;
const pwStrings = { remove: <?= json_encode(__('delete')) ?>, empty: <?= json_encode(__('panel_wl_empty')) ?>,
    allowed: <?= json_encode(__('panel_wl_state_allowed')) ?>, denied: <?= json_encode(__('panel_wl_state_denied')) ?>, loop: <?= json_encode(__('panel_wl_state_loop')) ?> };

function pwIpToBytes(ip) {
    if (ip.indexOf(':') === -1) {
        const p = ip.split('.').map(Number);
        return p.length === 4 && p.every(n => n >= 0 && n <= 255) ? p : null;
    }
    let parts = ip.split('::');
    if (parts.length > 2) return null;
    const head = parts[0] ? parts[0].split(':') : [];
    const tail = parts.length === 2 && parts[1] ? parts[1].split(':') : [];
    const fill = parts.length === 2 ? 8 - head.length - tail.length : 0;
    if (fill < 0 || (parts.length === 1 && head.length !== 8)) return null;
    const groups = head.concat(Array(fill).fill('0'), tail);
    const out = [];
    for (const g of groups) { if (!/^[0-9a-fA-F]{1,4}$/.test(g)) return null; const v = parseInt(g, 16); out.push(v >> 8, v & 255); }
    return out.length === 16 ? out : null;
}
function pwMatch(ip, entry) {
    const a = pwIpToBytes(ip); if (!a) return false;
    let net = entry, bits = null;
    if (entry.indexOf('/') !== -1) { const s = entry.split('/'); net = s[0]; bits = parseInt(s[1], 10); }
    const b = pwIpToBytes(net); if (!b || a.length !== b.length) return false;
    if (bits === null) bits = a.length * 8;
    for (let i = 0; i < a.length && bits > 0; i++, bits -= 8) {
        const mask = bits >= 8 ? 255 : (255 << (8 - bits)) & 255;
        if ((a[i] & mask) !== (b[i] & mask)) return false;
    }
    return true;
}
function pwRender() {
    const body = document.getElementById('pwRows');
    if (!pwEntries.length) { body.innerHTML = '<tr><td colspan="3" class="sec-empty">' + secEsc(pwStrings.empty) + '</td></tr>'; }
    else body.innerHTML = pwEntries.map((e, i) =>
        '<tr><td class="mono">' + secEsc(e.ip) + '</td><td>' + secEsc(e.comment) + '</td>' +
        '<td><div class="sec-actions"><button class="sec-ico danger" title="' + secEsc(pwStrings.remove) + '" onclick="pwRemove(' + i + ')">🗑️</button></div></td></tr>').join('');
    pwState();
}
function pwState() {
    const el = document.getElementById('pwMyState');
    const on = document.getElementById('pwEnabled').checked;
    if (!on) { el.className = 'sec-badge'; el.textContent = ''; return; }
    const loop = pwMyIp === '::1' || pwMyIp.indexOf('127.') === 0;
    const ok = loop || pwEntries.some(e => pwMatch(pwMyIp, e.ip));
    el.className = 'sec-badge ' + (ok ? 'ok' : 'err');
    el.textContent = loop ? pwStrings.loop : (ok ? pwStrings.allowed : pwStrings.denied);
}
function pwAdd() {
    const ip = document.getElementById('pwNewIp').value.trim();
    const comment = document.getElementById('pwNewComment').value.trim();
    if (!ip) return;
    if (!pwIpToBytes(ip.split('/')[0]) || (ip.indexOf('/') !== -1 && !/^\d{1,3}$/.test(ip.split('/')[1]))) { secMsg('pwMsg', false, <?= json_encode(__('panel_wl_msg_bad_entry_short')) ?>); return; }
    if (!pwEntries.some(e => e.ip === ip)) pwEntries.push({ ip: ip, comment: comment });
    document.getElementById('pwNewIp').value = ''; document.getElementById('pwNewComment').value = '';
    pwRender();
}
function pwAddMine() { if (pwMyIp && !pwEntries.some(e => e.ip === pwMyIp)) { pwEntries.push({ ip: pwMyIp, comment: <?= json_encode(__('panel_wl_my_comment')) ?> }); pwRender(); } }
function pwRemove(i) { pwEntries.splice(i, 1); pwRender(); }
function pwSave() {
    secPost({ ajax: 'panel_wl_save', enabled: document.getElementById('pwEnabled').checked ? '1' : '0', entries: JSON.stringify(pwEntries) })
        .then(d => secMsg('pwMsg', !!d.success, d.message || ''))
        .catch(() => secMsg('pwMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
if (document.getElementById('pwEnabled')) { document.getElementById('pwEnabled').addEventListener('change', pwState); }

// Команда аварийного отключения: по нажатию копируется в буфер
function pwCopyCmd(el) {
    secCopy(el.textContent, function () {
        const t = el.textContent; el.textContent = '✅ ' + <?= json_encode(__('panel_wl_copied')) ?>;
        setTimeout(() => { el.textContent = t; }, 1200);
    });
}

// ===== Блокировка IP (п. 30) =====
let fwRows = <?= json_encode($fwBlocked, JSON_UNESCAPED_UNICODE) ?>;
const fwStr = { applied: <?= json_encode(__('fw_st_applied')) ?>, pending: <?= json_encode(__('fw_st_pending')) ?>, empty: <?= json_encode(__('fw_empty')) ?>,
    unblock: <?= json_encode(__('fw_unblock')) ?>, confirm: <?= json_encode(__('fw_unblock_confirm')) ?>, ready: <?= json_encode(__('fw_state_ready')) ?>, notReady: <?= json_encode(__('fw_state_not_ready')) ?> };
function fwRender() {
    const body = document.getElementById('fwRows');
    document.getElementById('fwCount').textContent = fwRows.length;
    if (!fwRows.length) { body.innerHTML = '<tr><td colspan="6" class="sec-empty">' + secEsc(fwStr.empty) + '</td></tr>'; return; }
    body.innerHTML = fwRows.map(r =>
        '<tr><td class="mono">' + secEsc(r.ip) + '</td><td>' + secEsc(r.reason) + '</td><td>' + secEsc(r.user) + '</td><td class="mono">' + secEsc(r.time) + '</td>' +
        '<td><span class="sec-badge ' + (r.status === 'applied' ? 'ok' : 'warn') + '" title="' + secEsc(r.note || '') + '">' + secEsc(r.status === 'applied' ? fwStr.applied : fwStr.pending) + '</span></td>' +
        '<td><div class="sec-actions"><button class="sec-btn sm" onclick="fwUnblock(\'' + secEsc(r.ip) + '\')">♻️ ' + secEsc(fwStr.unblock) + '</button></div></td></tr>').join('');
}
function fwReload(done) {
    secPost({ ajax: 'fw_list' }).then(d => { if (d.success) { fwRows = d.rows; } fwRender(); if (done) done(); });
}
let fwChecked = false;
function fwFirstCheck() { if (!fwChecked) { fwChecked = true; fwCheck(); } }
function fwCheck() {
    const st = document.getElementById('fwState'), tx = document.getElementById('fwStateText');
    st.className = 'sec-badge'; st.textContent = <?= json_encode(__('fw_checking')) ?>; tx.textContent = '';
    secPost({ ajax: 'fw_status' }).then(d => {
        st.className = 'sec-badge ' + (d.ready ? 'ok' : 'warn');
        st.textContent = d.ready ? fwStr.ready : fwStr.notReady;
        tx.textContent = d.message || '';
        const card = document.getElementById('fwSetupCard'); if (card) card.style.display = d.problem === 'helper_missing' || d.problem === 'sudo_denied' || !d.ready ? '' : 'none';
    }).catch(() => { st.className = 'sec-badge err'; st.textContent = '?'; });
}
function fwScopeChange() { document.getElementById('fwPort').disabled = document.getElementById('fwScope').value !== 'port'; }
function fwSaveSettings() {
    secPost({ ajax: 'fw_save_settings', backend: document.getElementById('fwBackend').value, scope: document.getElementById('fwScope').value, port: document.getElementById('fwPort').value })
        .then(d => { secMsg('fwSetMsg', !!d.success, d.message || ''); if (d.success) fwCheck(); })
        .catch(() => secMsg('fwSetMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function fwBlockNew() {
    const ip = document.getElementById('fwNewIp').value.trim(); if (!ip) return;
    secPost({ ajax: 'fw_block', ip: ip, reason: document.getElementById('fwNewReason').value })
        .then(d => { secMsg('fwMsg', !!d.success, d.message || ''); if (d.success) { document.getElementById('fwNewIp').value = ''; document.getElementById('fwNewReason').value = ''; fwReload(); } })
        .catch(() => secMsg('fwMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function fwUnblock(ip) {
    if (!confirm(fwStr.confirm + ' ' + ip + '?')) return;
    secPost({ ajax: 'fw_unblock', ip: ip }).then(d => { secMsg('fwMsg', !!d.success, d.message || ''); if (d.success) fwReload(); });
}
function fwApply(all) {
    secPost({ ajax: 'fw_apply', all: all ? '1' : '0' }).then(d => { secMsg('fwMsg', !!d.success, d.message || ''); fwReload(); });
}
if (document.getElementById('pwRows')) { pwRender(); } if (document.getElementById('fwRows')) { fwRender(); }
document.addEventListener('DOMContentLoaded', function () {
    secTabRestore('section-security', 'users');
    const act = document.querySelector('#section-security .sec-tab-btn.active');
    if (act && act.getAttribute('data-for') === 'firewall') fwFirstCheck();
});
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function () { secTabRestore('section-security', 'users'); });
</script>
<?php endif; ?>

<!-- Модальное окно для выбора прав при добавлении/редактировании пользователя -->
<div id="permissionsModal" class="modal" style="display: none; z-index: 100000;">
    <div class="modal-content" style="width: 650px; max-width: 95%; z-index: 100001;">
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
                
                <div class="form-group">
                    <label><?= __('permissions_tools') ?></label>
                    <select id="permTools" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_view') ?></option>
                        <option value="2"><?= __('permissions_edit') ?></option>
                    </select>
                    <small class="form-hint"><?= __('permissions_tools_hint') ?></small>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_whitelist') ?></label>
                    <select id="permWhitelist" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_enabled') ?></option>
                    </select>
                    <small class="form-hint"><?= __('permissions_whitelist_hint') ?></small>
                </div>
                
                <div class="form-group">
                    <label><?= __('permissions_notify') ?></label>
                    <select id="permNotify" class="form-control">
                        <option value="0"><?= __('permissions_none') ?></option>
                        <option value="1"><?= __('permissions_enabled') ?></option>
                    </select>
                    <small class="form-hint"><?= __('permissions_notify_hint') ?></small>
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
            
            <button class="sec-btn primary" onclick="saveUserPermissions()" style="margin: 12px auto 0;"><?= __('permissions_save') ?></button>
        </div>
    </div>
</div>

<!-- Модальное окно добавления IP в белый список -->
<div id="addWhitelistIpModal" class="modal" style="display: none; z-index: 100000;">
    <div class="modal-content" style="width: 450px; max-width: 90%;">
        <div class="modal-header">
            <h2><?= __('whitelist_add_ip_title') ?></h2>
            <span class="modal-close" onclick="closeAddWhitelistIpModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="whitelistMessage" class="modal-message" style="display: none;"></div>
            <div class="form-group">
                <label><?= __('whitelist_ip_address') ?></label>
                <input type="text" id="whitelistIpInput" class="form-control" placeholder="192.168.1.100" autocomplete="off">
            </div>
            <div class="form-group">
                <label><?= __('whitelist_ip_comment') ?></label>
                <input type="text" id="whitelistCommentInput" class="form-control" placeholder="<?= __('whitelist_ip_comment_placeholder') ?>" autocomplete="off">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button class="sec-btn" onclick="closeAddWhitelistIpModal()"><?= __('cancel') ?></button>
                <button class="sec-btn primary" onclick="addWhitelistIp()"><?= __('whitelist_add_btn') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.pw-cmd { cursor: pointer; }
.pw-cmd:hover { border-color: <?= $themeCSS['primary'] ?>; }
.security-two-columns {
    display: flex;
    gap: 20px;
    align-items: stretch;
}

.security-users-column {
    flex: 0.65;
    min-width: 0;
}

.security-side-column {
    flex: 0.35;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.security-side-column > .privacy-card { flex: 0 0 auto; height: auto; }
.session-row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 13px; }
.session-row input { width: 80px; flex: 0 0 auto; }
.session-row input[type=number] { -moz-appearance: textfield; appearance: textfield; }
.session-row input[type=number]::-webkit-outer-spin-button, .session-row input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.session-row span { white-space: nowrap; }
.session-row .btn { white-space: nowrap; }
.privacy-switch { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 500; }
.privacy-switch input { width: 16px; height: 16px; accent-color: <?= $themeCSS['primary'] ?>; cursor: pointer; }
.privacy-hint { margin: 8px 0 0; font-size: 11px; color: <?= $themeCSS['muted'] ?>; line-height: 1.4; }
.privacy-message { margin-top: 8px; font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid transparent; display: inline-block; }
.privacy-message.ok { color: <?= $themeCSS['okText'] ?>; border-color: <?= $themeCSS['okText'] ?>; }
.privacy-message.err { color: <?= $themeCSS['dangerText'] ?>; border-color: <?= $themeCSS['dangerText'] ?>; }

.settings-card {
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 0;
    height: 100%;
}

.settings-card-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: <?= $themeCSS['muted'] ?>;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.whitelist-description {
    margin: 0;
    flex: 1;
    font-size: 11px;
    color: <?= $themeCSS['muted'] ?>;
    line-height: 1.4;
}

.whitelist-item {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
    align-items: center;
    font-size: 12px;
}

.whitelist-item:last-child {
    border-bottom: none;
}

.btn-whitelist-delete {
    background: none;
    border: none;
    color: <?= $themeCSS['danger'] ?>;
    cursor: pointer;
    font-size: 14px;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.2s;
}

.btn-whitelist-delete:hover {
    background: <?= $themeCSS['danger'] ?>20;
    transform: scale(1.05);
}

.lock-icon {
    color: <?= $themeCSS['muted'] ?>;
    font-size: 12px;
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 4px;
    color: <?= $themeCSS['muted'] ?>;
}

.form-hint {
    display: block;
    font-size: 10px;
    color: <?= $themeCSS['muted'] ?>;
    margin-top: 3px;
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
    color: <?= $themeCSS['onPrimary'] ?>;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-small {
    padding: 4px 10px;
    font-size: 11px;
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
    width: 650px;
    max-width: 95%;
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
    color: <?= $themeCSS['accentText'] ?>;
}

.modal-close {
    color: <?= $themeCSS['muted'] ?>;
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
    background: <?= $themeCSS['field'] ?>;
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
    color: <?= $themeCSS['muted'] ?>;
    padding: 0;
    margin: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-password-btn:hover {
    color: <?= $themeCSS['accentText'] ?>;
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
    if (groupsLevel == 2) perms |= PERM_GROUPS_VIEW | PERM_GROUPS_EDIT;
    else if (groupsLevel == 1) perms |= PERM_GROUPS_VIEW;
    
    const devicesLevel = parseInt(document.getElementById('permDevices').value);
    if (devicesLevel == 2) perms |= PERM_DEVICES_VIEW | PERM_DEVICES_EDIT;
    else if (devicesLevel == 1) perms |= PERM_DEVICES_VIEW;
    
    const logsLevel = parseInt(document.getElementById('permLogs').value);
    if (logsLevel == 2) perms |= PERM_LOGS_VIEW | PERM_LOGS_EDIT;
    else if (logsLevel == 1) perms |= PERM_LOGS_VIEW;
    
    const usersLevel = parseInt(document.getElementById('permUsers').value);
    if (usersLevel == 2) perms |= PERM_USERS_VIEW | PERM_USERS_EDIT;
    else if (usersLevel == 1) perms |= PERM_USERS_VIEW;
    
    const infoLevel = parseInt(document.getElementById('permInfo').value);
    if (infoLevel == 1) perms |= PERM_INFO_VIEW;
    
    const toolsLevel = parseInt(document.getElementById('permTools').value);
    if (toolsLevel == 2) perms |= PERM_TOOLS_VIEW | PERM_TOOLS_EDIT;
    else if (toolsLevel == 1) perms |= PERM_TOOLS_VIEW;
    
    const whitelistLevel = parseInt(document.getElementById('permWhitelist').value);
    if (whitelistLevel == 1) perms |= PERM_IP_WHITELIST;
    
    if (parseInt(document.getElementById('permNotify').value) == 1) perms |= PERM_NOTIFY_EDIT;
    
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
    
    if (permissions & PERM_TOOLS_EDIT) document.getElementById('permTools').value = 2;
    else if (permissions & PERM_TOOLS_VIEW) document.getElementById('permTools').value = 1;
    else document.getElementById('permTools').value = 0;
    
    if (permissions & PERM_IP_WHITELIST) document.getElementById('permWhitelist').value = 1;
    else document.getElementById('permWhitelist').value = 0;
    
    document.getElementById('permNotify').value = (permissions & PERM_NOTIFY_EDIT) ? 1 : 0;
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
    const tools = getPermissionLevelText(permissions, PERM_TOOLS_VIEW, PERM_TOOLS_EDIT);
    const whitelist = (permissions & PERM_IP_WHITELIST) ? '✅' : '❌';
    const notify = (permissions & PERM_NOTIFY_EDIT) ? '✅' : '❌';
    
    return `👥 ${groups} • 📱 ${devices} • 📝 ${logs} • 👑 ${users} • ℹ️ ${info} • 🛠️ ${tools} • ⚪ ${whitelist} • 🔔 ${notify}`;
}

function renderUsers(users) {
    const container = document.getElementById('usersListContainer');
    if (!users || users.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: <?= $themeCSS['muted'] ?>;"><?= __('users_empty') ?></div>';
        return;
    }
    
    let html = '';
    users.forEach(user => {
        const isCurrent = user.id === <?= $_SESSION['vlmc_user_id'] ?? 1 ?>;
        const roleDisplay = getRoleDisplay(user.permissions);
        
        html += `
            <div style="display: grid; grid-template-columns: 1.5fr 1.5fr 1fr 0.8fr; gap: 10px; padding: 8px 0; border-bottom: 1px dashed <?= $themeCSS['border'] ?>; align-items: center; font-size: 12px;">
                <span><strong>${escapeHtml(user.username)}</strong></span>
                <span style="font-size: 10px;">${escapeHtml(roleDisplay)}</span>
                <span style="font-size: 10px;">${user.created || '—'}</span>
                <span>
                    ${isCurrent ? '<span style="color: <?= $themeCSS['muted'] ?>; font-size: 10px;"><?= __('current_user') ?></span>' : `
                        <?php if ($canEditUsers): ?>
                        <button class="sec-ico blue" onclick="editUser(${user.id}, '${escapeHtml(user.username)}', ${user.permissions})" title="<?= __('edit') ?>">✎</button>
                        <button class="sec-ico warn" onclick="showChangePasswordModal(${user.id}, '${escapeHtml(user.username)}')" title="<?= __('change_password') ?>">🔑</button>
                        <button class="sec-ico danger" onclick="deleteUser(${user.id})" title="<?= __('delete') ?>">✕</button>
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
// УПРАВЛЕНИЕ БЕЛЫМИ IP
// ============================================

function showAddWhitelistIpModal() {
    document.getElementById('whitelistIpInput').value = '';
    document.getElementById('whitelistCommentInput').value = '';
    document.getElementById('whitelistMessage').style.display = 'none';
    document.getElementById('addWhitelistIpModal').style.display = 'flex';
}

function closeAddWhitelistIpModal() {
    document.getElementById('addWhitelistIpModal').style.display = 'none';
}

function addWhitelistIp() {
    const ip = document.getElementById('whitelistIpInput').value.trim();
    const comment = document.getElementById('whitelistCommentInput').value.trim();
    const messageDiv = document.getElementById('whitelistMessage');
    
    if (!ip) {
        messageDiv.className = 'modal-message error';
        messageDiv.innerHTML = '❌ <?= __('whitelist_ip_required') ?>';
        messageDiv.style.display = 'block';
        return;
    }
    
    const ipRegex = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
    if (!ipRegex.test(ip)) {
        messageDiv.className = 'modal-message error';
        messageDiv.innerHTML = '❌ <?= __('whitelist_ip_invalid') ?>';
        messageDiv.style.display = 'block';
        return;
    }
    
    messageDiv.className = 'modal-message';
    messageDiv.innerHTML = '⏳ <?= __('whitelist_adding') ?>';
    messageDiv.style.display = 'block';
    
    const fd = new FormData();
    fd.append('ajax', 'add_whitelist_ip');
    fd.append('ip', ip);
    fd.append('comment', comment);
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.className = 'modal-message success';
                messageDiv.innerHTML = withIcon('✅', data.message);
                setTimeout(() => {
                    closeAddWhitelistIpModal();
                    location.reload();
                }, 1500);
            } else {
                messageDiv.className = 'modal-message error';
                messageDiv.innerHTML = withIcon('❌', data.message);
            }
        })
        .catch(error => {
            messageDiv.className = 'modal-message error';
            messageDiv.innerHTML = '❌ <?= __('whitelist_add_error') ?>';
        });
}

function removeWhitelistIp(ip) {
    if (!confirm('<?= __('whitelist_remove_confirm') ?> ' + ip + '?')) return;
    
    const fd = new FormData();
    fd.append('ajax', 'remove_whitelist_ip');
    fd.append('ip', ip);
    
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModalMessage(data.message, 'success');
                location.reload();
            } else {
                showModalMessage(data.message, 'error');
            }
        })
        .catch(error => {
            showModalMessage('<?= __('whitelist_remove_error') ?>', 'error');
        });
}

// Скрытие IP-адресов добавленных устройств от неавторизованных (только администратор)
function savePrivacySettings(box) {
    const msg = document.getElementById('privacyMessage');
    const fd = new FormData();
    fd.append('ajax', 'save_privacy_settings');
    fd.append('hide_ips', box.checked ? '1' : '0');
    box.disabled = true;
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (!data.success) { box.checked = !box.checked; }
            msg.textContent = (data.success ? '✅ ' : '❌ ') + (data.message || '');
            msg.className = 'privacy-message ' + (data.success ? 'ok' : 'err');
            msg.style.display = 'inline-block';
        })
        .catch(() => {
            box.checked = !box.checked;
            msg.textContent = '❌ <?= __('msg_save_error') ?>';
            msg.className = 'privacy-message err';
            msg.style.display = 'inline-block';
        })
        .then(() => {
            box.disabled = false;
            setTimeout(() => { msg.style.display = 'none'; }, 6000);
        });
}

// Таймаут входа по бездействию (только администратор)
function saveSessionSettings() {
    const input = document.getElementById('sessionTimeoutMin');
    const msg = document.getElementById('sessionMessage');
    const fd = new FormData();
    fd.append('ajax', 'save_session_settings');
    fd.append('session_timeout_min', String(input.value).trim());
    const show = (ok, text) => {
        msg.textContent = (ok ? '✅ ' : '❌ ') + text;
        msg.className = 'privacy-message ' + (ok ? 'ok' : 'err');
        msg.style.display = 'inline-block';
        setTimeout(() => { msg.style.display = 'none'; }, 6000);
    };
    fetch('', { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => show(!!data.success, data.message || ''))
        .catch(() => show(false, '<?= __('msg_save_error') ?>'));
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