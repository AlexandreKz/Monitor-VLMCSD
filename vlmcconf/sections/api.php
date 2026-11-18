<?php
// ============================================
// ФАЙЛ: sections/api.php
// ВЕРСИЯ: 6.7.1
// ДАТА: 2026-11-18
// @description: Раздел «API» (п. 25): ключи доступа, настройки и лимит запросов, документация (OpenAPI)
// @description: API section: access keys, settings and rate limit, documentation (OpenAPI)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../vlmcinc/section_ui.php';
require_once __DIR__ . '/../vlmcinc/api_core.php';
vlmcSectionUi($themeCSS);
?>

<div id="section-api" class="settings-section <?= $activeSection === 'api' ? 'active' : '' ?>">
    <div class="section-title sec-title">
        <span>🖥️ <?= __('api') ?></span>
        <div class="sec-tabs">
            <button class="sec-tab-btn active" data-for="keys" onclick="secTab('section-api','keys',this)">🔑 <?= __('api_tab_keys') ?></button>
            <button class="sec-tab-btn" data-for="settings" onclick="secTab('section-api','settings',this)">⚙️ <?= __('api_tab_settings') ?></button>
            <button class="sec-tab-btn" data-for="docs" onclick="secTab('section-api','docs',this); apiDocsLoad();">📖 <?= __('api_tab_docs') ?></button>
        </div>
    </div>

<?php
// Раздел «API» — только полный администратор (владелец и администраторы)
$apiIsAdmin = hasPermission($_SESSION['vlmc_permissions'] ?? 0, PERM_ADMIN_FULL);
if (!$apiIsAdmin) {
    echo '<div class="alert alert-danger">' . __('access_denied') . '</div>';
    echo '</div>';
    return;
}

$apiCanEdit = $apiIsAdmin;
$apiSet = apiSettings($config);
$apiKeys = apiKeysList();
$apiKeysView = apiKeysView();   // только безопасные поля (без хеша)
$apiOrigins = implode("\n", $apiSet['cors_origins']);
?>

    <!-- ===== Ключи ===== -->
    <div class="sec-tab-content active" data-tab="keys">
        <div class="settings-card">
            <div class="settings-card-title sec-th">🔑 <?= __('api_keys_title') ?>
                <span id="apiState" class="sec-badge <?= $apiSet['enabled'] ? 'ok' : 'err' ?>" style="margin-left: auto; text-transform: none; letter-spacing: 0;"><?= __($apiSet['enabled'] ? 'api_state_on' : 'api_state_off') ?></span></div>
            <div class="sec-row">
                <span><?= __('api_base_url') ?>:</span>
                <span class="sec-code" style="margin: 0; flex: 1 1 300px;" id="apiBaseUrl"></span>
                <button type="button" class="sec-btn sm" onclick="secCopy(document.getElementById('apiBaseUrl').textContent)">📋 <?= __('copy') ?></button>
            </div>
            <?php if ($apiCanEdit): ?>
            <div class="sec-row" style="margin-top: 8px;">
                <button type="button" class="sec-btn primary" onclick="apiNewOpen()">➕ <?= __('api_key_new') ?></button>
            </div>
            <div id="apiOverlay" class="api-ov" onclick="if (event.target === this && !apiResultShown) apiClose()">
                <div class="api-modal">
                    <div id="apiFormPane">
                        <h3>🔑 <?= __('api_key_new') ?></h3>
                        <div class="sec-row"><label for="apiKeyName"><?= __('api_key_name') ?></label>
                            <input type="text" id="apiKeyName" class="form-control" maxlength="60" placeholder="Zabbix, Grafana…" autocomplete="off"></div>
                        <div class="sec-row"><label><?= __('api_key_scopes') ?></label>
                            <span style="display: flex; gap: 14px; flex-wrap: wrap;">
                                <?php foreach (apiScopes() as $sc): ?>
                                <label class="sec-switch" style="margin: 0; font-weight: 400;"><input type="checkbox" class="apiScope" value="<?= $sc ?>" <?= $sc === 'security' ? '' : 'checked' ?>> <?= __('api_scope_' . $sc) ?></label>
                                <?php endforeach; ?>
                            </span></div>
                        <div class="sec-row"><label for="apiKeyIps"><?= __('api_key_ips') ?></label>
                            <input type="text" id="apiKeyIps" class="form-control" placeholder="203.0.113.5, 10.0.0.0/24" autocomplete="off"></div>
                        <div class="sec-row"><label for="apiKeyExpiresShow"><?= __('api_key_expires') ?></label>
                            <span class="api-datewrap">
                                <input type="text" id="apiKeyExpiresShow" class="form-control" readonly placeholder="<?= htmlspecialchars(__('api_never')) ?>" onclick="apiCalToggle()" style="cursor: pointer;">
                                <input type="hidden" id="apiKeyExpires" value="">
                                <div class="api-cal" id="apiCal" style="display: none;"></div>
                            </span></div>
                        <div class="api-foot">
                            <span id="apiModalMsg" class="sec-msg" style="margin-right: auto;"></span>
                            <button type="button" class="sec-btn" onclick="apiClose()"><?= __('cancel') ?></button>
                            <button type="button" class="sec-btn primary" onclick="apiCreateKey()">➕ <?= __('api_key_create') ?></button>
                        </div>
                    </div>
                    <div id="apiResultPane" style="display: none;">
                        <h3>✅ <?= __('api_key_created_title') ?></h3>
                        <div class="sec-code" id="apiNewKey" style="margin: 8px 0;"></div>
                        <p class="sec-hint"><?= __('api_key_created_hint') ?></p>
                        <div class="api-foot">
                            <button type="button" class="sec-btn" onclick="secCopy(document.getElementById('apiNewKey').textContent)">📋 <?= __('copy') ?></button>
                            <button type="button" class="sec-btn primary" onclick="apiClose()"><?= __('close') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div id="apiKeysMsg" class="sec-msg" style="margin: 0 0 8px;"></div>
            <div class="sec-scroll" style="max-height: 360px;">
                <table class="sec-table">
                    <thead><tr><th><?= __('api_col_name') ?></th><th><?= __('api_col_key') ?></th><th><?= __('api_col_scopes') ?></th><th><?= __('api_col_ips') ?></th><th><?= __('api_col_expires') ?></th><th><?= __('api_col_used') ?></th><th><?= __('api_col_status') ?></th><th></th></tr></thead>
                    <tbody id="apiKeyRows"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Настройки ===== -->
    <div class="sec-tab-content" data-tab="settings">
        <div class="sec-grid">
            <div class="sec-col" style="flex: 0.6;">
                <div class="settings-card">
                    <div class="settings-card-title sec-th">⚙️ <?= __('api_settings_title') ?> <span id="apiSetMsg" class="sec-msg" style="margin: 0 0 0 auto; text-transform: none; letter-spacing: 0; font-weight: 400;"></span></div>
                    <label class="sec-switch">
                        <input type="checkbox" id="apiEnabled" onchange="apiToggleEnabled()" <?= $apiSet['enabled'] ? 'checked' : '' ?> <?= $apiCanEdit ? '' : 'disabled' ?>>
                        <span><?= __('api_enable') ?></span>
                    </label>
                    <p class="sec-hint"><?= __('api_enable_hint') ?></p>
                    <div class="sec-row" style="margin-top: 12px;">
                        <label for="apiRate"><?= __('api_rate_limit') ?></label>
                        <input type="number" id="apiRate" class="form-control sec-narrow" min="0" max="100000" value="<?= (int)$apiSet['rate_limit'] ?>" <?= $apiCanEdit ? '' : 'disabled' ?>>
                        <span class="sec-hint" style="margin: 0;"><?= __('api_rate_limit_unit') ?></span>
                    </div>
                    <div class="sec-row">
                        <label for="apiCors"><?= __('api_cors') ?></label>
                        <textarea id="apiCors" class="form-control" rows="3" placeholder="*" <?= $apiCanEdit ? '' : 'disabled' ?>><?= htmlspecialchars($apiOrigins) ?></textarea>
                    </div>
                    <p class="sec-hint"><?= __('api_cors_hint') ?></p>
                    <?php if ($apiCanEdit): ?>
                    <div class="sec-row" style="margin: 10px 0 0;"><button type="button" class="sec-btn primary" onclick="apiSaveSettings()">💾 <?= __('save') ?></button></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sec-col" style="flex: 0.4;">
                <div class="settings-card">
                    <div class="settings-card-title">💡 <?= __('api_usage_title') ?></div>
                    <p class="sec-hint" style="margin-top: 0;"><?= __('api_usage_text') ?></p>
                    <div class="sec-code" id="apiCurl"></div>
                    <button type="button" class="sec-btn sm" onclick="secCopy(document.getElementById('apiCurl').textContent)">📋 <?= __('copy') ?></button>
                    <p class="sec-hint"><?= __('api_https_hint') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Документация ===== -->
    <div class="sec-tab-content" data-tab="docs">
        <div class="settings-card">
            <div class="settings-card-title">📖 <?= __('api_docs_title') ?></div>
            <div class="api-toolbar">
                <span class="api-search">
                    <input type="text" id="apiDocsSearch" class="form-control" placeholder="<?= htmlspecialchars(__('api_docs_search')) ?>" autocomplete="off" oninput="apiDocsFilter()">
                    <button type="button" class="api-search-x" id="apiDocsClear" title="<?= htmlspecialchars(__('api_docs_clear')) ?>" onclick="apiDocsClearSearch()" style="display: none;">✕</button>
                </span>
                <button type="button" class="sec-btn sm" id="apiDocsReset" onclick="apiDocsReset()" disabled>↺ <?= __('api_docs_reset') ?></button>
                <span class="sec-hint" style="margin: 0;" id="apiDocsCount"></span>
                <span class="api-toolbar-right">
                    <a class="sec-btn sm" id="apiSpecLink" href="#" target="_blank" rel="noopener">⬇️ openapi.json</a>
                    <span class="sec-hint" style="margin: 0;"><?= __('api_docs_hint') ?></span>
                </span>
            </div>
            <div id="apiDocsMethods" class="api-chips"></div>
            <div id="apiDocsChips" class="api-chips"></div>
            <div id="apiDocs"><div class="sec-empty"><?= __('api_docs_loading') ?></div></div>
            <div class="sec-empty" id="apiDocsNone" style="display: none;"><?= __('api_docs_none') ?></div>
        </div>
    </div>
</div>

<style>
.api-datewrap { position: relative; display: block; width: 180px; flex: 0 0 180px; }
.api-cal { position: absolute; top: calc(100% + 6px); left: 0; z-index: 60; width: 268px; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.35); color: <?= $themeCSS['text'] ?>; }
.api-cal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13px; font-weight: 600; }
.api-cal-nav { background: transparent; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; line-height: 1; }
.api-cal-nav:hover { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['accentText'] ?>; }
.api-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.api-cal-wd { font-size: 10px; color: <?= $themeCSS['muted'] ?>; text-align: center; padding: 2px 0; text-transform: uppercase; }
.api-cal-day { background: transparent; border: 1px solid transparent; color: <?= $themeCSS['text'] ?>; border-radius: 6px; height: 28px; font-size: 12px; cursor: pointer; padding: 0; }
.api-cal-day:hover:not(:disabled) { background: <?= $themeCSS['hover'] ?>; }
.api-cal-day.other { opacity: 0.35; }
.api-cal-day.today { border-color: <?= $themeCSS['primary'] ?>; }
.api-cal-day.sel { background: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; }
.api-cal-day:disabled { opacity: 0.2; cursor: not-allowed; }
.api-cal-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; padding-top: 8px; border-top: 1px solid <?= $themeCSS['border'] ?>; }
.api-cal-preset { background: transparent; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; border-radius: 12px; padding: 2px 10px; font-size: 11px; cursor: pointer; }
.api-cal-preset:hover { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['accentText'] ?>; }
.api-codebox { position: relative; }
.api-codebox .api-copy { position: absolute; top: 6px; right: 6px; z-index: 2; }
.api-codebox .sec-code { padding-right: 44px; }
.api-ov { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 10000; }
.api-ov.open { display: flex; }
.api-modal { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 10px; width: min(560px, 94vw); max-height: 86vh; overflow: visible; padding: 16px 18px; }
.api-modal h3 { margin: 0 0 12px; font-size: 15px; }
.api-foot { display: flex; align-items: center; gap: 8px; justify-content: flex-end; margin-top: 12px; }
.api-ep { border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
.api-ep-head { display: flex; align-items: center; gap: 14px; padding: 13px 16px; cursor: pointer; background: <?= $themeCSS['card'] ?>; }
.api-ep-head:hover { background: <?= $themeCSS['hover'] ?>; }
.api-m-post { background: #2e9e5b !important; color: #fff !important; }
.api-m-put, .api-m-patch { background: #d68a1f !important; color: #fff !important; }
.api-m-delete { background: #c0392b !important; color: #fff !important; }
.api-method { background: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 4px; min-width: 38px; text-align: center; }
.api-path { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 14px; }
.api-sum { color: <?= $themeCSS['muted'] ?>; font-size: 13.5px; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.api-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 8px 0 4px; }
.api-search { position: relative; width: 240px; max-width: 100%; }
.api-search .form-control { width: 100%; padding-right: 28px; }
.api-search-x { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: transparent; border: 0; color: <?= $themeCSS['muted'] ?>; cursor: pointer; font-size: 12px; line-height: 1; padding: 4px; border-radius: 50%; }
.api-search-x:hover { color: <?= $themeCSS['text'] ?>; background: <?= $themeCSS['hover'] ?>; }
.api-toolbar-right { margin-left: auto; flex: 1 1 280px; justify-content: flex-end; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.api-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
.api-chip-n { opacity: 0.7; font-size: 10px; margin-left: 2px; }
.api-desc { margin: 0 0 6px; font-size: 14px; line-height: 1.45; }
.api-ep-body { display: none; padding: 12px 16px; border-top: 1px solid <?= $themeCSS['border'] ?>; font-size: 12px; }
.api-ep.open .api-ep-body { display: block; }
.api-ep h4 { margin: 10px 0 4px; font-size: 11px; text-transform: uppercase; color: <?= $themeCSS['muted'] ?>; }
</style>

<script>
const apiKeys = <?= json_encode($apiKeysView, JSON_UNESCAPED_UNICODE) ?>;
const apiCanEdit = <?= $apiCanEdit ? 'true' : 'false' ?>;
const apiStr = { on: <?= json_encode(__('api_key_on')) ?>, off: <?= json_encode(__('api_key_off')) ?>, expired: <?= json_encode(__('api_key_expired')) ?>, never: <?= json_encode(__('api_never')) ?>,
    anyIp: <?= json_encode(__('api_any_ip')) ?>, none: <?= json_encode(__('api_keys_empty')) ?>, del: <?= json_encode(__('api_key_delete_confirm')) ?>,
    disable: <?= json_encode(__('api_key_disable')) ?>, enable: <?= json_encode(__('api_key_enable')) ?>, requests: <?= json_encode(__('api_requests')) ?> };
const apiEpText = <?php
$apiEpText = [];
foreach (array_keys(apiOpenApiSpec('')['paths']) as $apiPath) {
    $apiKey = 'api_ep_' . trim(preg_replace('/[^a-z]+/', '_', $apiPath), '_');
    $apiEpText[$apiPath] = __($apiKey) !== $apiKey ? __($apiKey) : '';
}
echo json_encode($apiEpText, JSON_UNESCAPED_UNICODE);
?>;
const apiScopeNames = <?= json_encode(array_combine(apiScopes(), array_map(function ($s) { return __('api_scope_' . $s); }, apiScopes())), JSON_UNESCAPED_UNICODE) ?>;
const apiBase = location.origin + location.pathname.replace(/[^\/]*$/, '') + 'api.php/v1';

function apiRenderKeys() {
    const body = document.getElementById('apiKeyRows');
    if (!apiKeys.length) { body.innerHTML = '<tr><td colspan="8" class="sec-empty">' + secEsc(apiStr.none) + '</td></tr>'; return; }
    const today = new Date().toISOString().slice(0, 10);
    body.innerHTML = apiKeys.map(k => {
        const expired = k.expires && k.expires < today;
        const state = !k.enabled ? ['warn', apiStr.off] : (expired ? ['err', apiStr.expired] : ['ok', apiStr.on]);
        const scopes = (k.scopes || []).map(s => '<span class="sec-badge">' + secEsc(apiScopeNames[s] || s) + '</span>').join(' ');
        const used = k.last_used ? secEsc(k.last_used) + '<br><span class="sec-hint">' + secEsc(k.last_ip) + ' · ' + secEsc(apiStr.requests) + ': ' + k.requests + '</span>' : '—';
        const acts = apiCanEdit
            ? '<div class="sec-actions"><button class="sec-btn sm" onclick="apiToggle(\'' + k.id + '\',' + (k.enabled ? 'false' : 'true') + ')">' + (k.enabled ? '⏸️ ' + secEsc(apiStr.disable) : '▶️ ' + secEsc(apiStr.enable)) + '</button>' +
              '<button class="sec-btn sm" onclick="apiDelete(\'' + k.id + '\')">🗑️</button></div>' : '';
        return '<tr><td><b>' + secEsc(k.name) + '</b><br><span class="sec-hint">' + secEsc(k.created) + ' · ' + secEsc(k.created_by) + '</span></td>' +
            '<td class="mono">' + secEsc(k.prefix) + '…</td><td>' + scopes + '</td>' +
            '<td class="mono">' + (k.allowed_ips && k.allowed_ips.length ? secEsc(k.allowed_ips.join(', ')) : secEsc(apiStr.anyIp)) + '</td>' +
            '<td>' + (k.expires ? secEsc(k.expires) : secEsc(apiStr.never)) + '</td><td>' + used + '</td>' +
            '<td><span class="sec-badge ' + state[0] + '">' + secEsc(state[1]) + '</span></td><td>' + acts + '</td></tr>';
    }).join('');
}
const apiStateStr = { on: <?= json_encode(__('api_state_on')) ?>, off: <?= json_encode(__('api_state_off')) ?> };
function apiSetState(on) {
    const b = document.getElementById('apiState');
    b.className = 'sec-badge ' + (on ? 'ok' : 'err');
    b.textContent = on ? apiStateStr.on : apiStateStr.off;
}
const apiLang = <?= json_encode(($config['language'] ?? 'ru') === 'en' ? 'en-US' : 'ru-RU') ?>;
const apiCalStr = { never: <?= json_encode(__('api_never')) ?>, d30: <?= json_encode(__('api_cal_30d')) ?>, d90: <?= json_encode(__('api_cal_90d')) ?>, y1: <?= json_encode(__('api_cal_1y')) ?> };
let apiCalView = new Date();
function apiIso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
function apiCalToggle() {
    const box = document.getElementById('apiCal');
    if (box.style.display !== 'none') { box.style.display = 'none'; return; }
    const v = document.getElementById('apiKeyExpires').value;
    apiCalView = v ? new Date(v + 'T00:00:00') : new Date();
    apiCalDraw(); box.style.display = 'block';
}
function apiCalSet(iso) {
    document.getElementById('apiKeyExpires').value = iso;
    document.getElementById('apiKeyExpiresShow').value = iso ? new Date(iso + 'T00:00:00').toLocaleDateString(apiLang) : '';
    document.getElementById('apiCal').style.display = 'none';
}
function apiCalAdd(days, years) {
    const d = new Date(); d.setDate(d.getDate() + days); d.setFullYear(d.getFullYear() + years); apiCalSet(apiIso(d));
}
function apiCalNav(n) { apiCalView = new Date(apiCalView.getFullYear(), apiCalView.getMonth() + n, 1); apiCalDraw(); }
function apiCalDraw() {
    const box = document.getElementById('apiCal'), y = apiCalView.getFullYear(), m = apiCalView.getMonth();
    const sel = document.getElementById('apiKeyExpires').value, today = apiIso(new Date());
    const first = new Date(y, m, 1), shift = (first.getDay() + 6) % 7, start = new Date(y, m, 1 - shift);
    let h = '<div class="api-cal-head"><button type="button" class="api-cal-nav" onclick="apiCalNav(-1)">‹</button><span>' +
        secEsc(first.toLocaleDateString(apiLang, { month: 'long', year: 'numeric' }).replace(/\s?г\.$/, '').replace(/^./, c => c.toUpperCase())) + '</span><button type="button" class="api-cal-nav" onclick="apiCalNav(1)">›</button></div><div class="api-cal-grid">';
    for (let i = 0; i < 7; i++) { h += '<div class="api-cal-wd">' + secEsc(new Date(2024, 0, 1 + i).toLocaleDateString(apiLang, { weekday: 'short' })) + '</div>'; }
    for (let i = 0; i < 42; i++) {
        const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i), iso = apiIso(d);
        h += '<button type="button" class="api-cal-day' + (d.getMonth() !== m ? ' other' : '') + (iso === today ? ' today' : '') + (iso === sel ? ' sel' : '') + '"' +
             (iso < today ? ' disabled' : '') + ' onclick="apiCalSet(\'' + iso + '\')">' + d.getDate() + '</button>';
    }
    h += '</div><div class="api-cal-presets"><button type="button" class="api-cal-preset" onclick="apiCalSet(\'\')">' + secEsc(apiCalStr.never) + '</button>' +
         '<button type="button" class="api-cal-preset" onclick="apiCalAdd(30,0)">' + secEsc(apiCalStr.d30) + '</button>' +
         '<button type="button" class="api-cal-preset" onclick="apiCalAdd(90,0)">' + secEsc(apiCalStr.d90) + '</button>' +
         '<button type="button" class="api-cal-preset" onclick="apiCalAdd(0,1)">' + secEsc(apiCalStr.y1) + '</button></div>';
    box.innerHTML = h;
}
document.addEventListener('click', function (e) {   // клик вне календаря закрывает его
    const box = document.getElementById('apiCal');
    if (box && box.style.display !== 'none' && !e.target.closest('.api-datewrap')) box.style.display = 'none';
});
function apiToggleEnabled() { // переключатель сохраняется сразу, без кнопки «Сохранить»
    const cb = document.getElementById('apiEnabled'), want = cb.checked;
    secPost({ ajax: 'api_save_settings', enabled: want ? '1' : '0' })
        .then(d => {
            secMsg('apiSetMsg', !!d.success, d.message || '');
            if (d.success) apiSetState(want); else cb.checked = !want;
        })
        .catch(() => { cb.checked = !want; secMsg('apiSetMsg', false, <?= json_encode(__('msg_save_error')) ?>); });
}
function apiCopyBtn(btn) {
    secCopy(btn.parentNode.querySelector('.sec-code').textContent, function () {
        const t = btn.textContent; btn.textContent = '✅'; setTimeout(function () { btn.textContent = t; }, 1200);
    });
}
let apiResultShown = false;
function apiNewOpen() {
    apiResultShown = false;
    document.getElementById('apiKeyName').value = '';
    document.getElementById('apiKeyIps').value = '';
    apiCalSet('');
    document.querySelectorAll('.apiScope').forEach(c => { c.checked = c.value !== 'security'; });
    const mm = document.getElementById('apiModalMsg'); mm.textContent = ''; mm.className = 'sec-msg'; mm.title = '';
    document.getElementById('apiFormPane').style.display = '';
    document.getElementById('apiResultPane').style.display = 'none';
    document.getElementById('apiOverlay').classList.add('open');
    document.getElementById('apiKeyName').focus();
}
function apiClose() { // закрытие стирает показанный ключ
    apiResultShown = false;
    document.getElementById('apiNewKey').textContent = '';
    document.getElementById('apiOverlay').classList.remove('open');
}
function apiCreateKey() {
    const scopes = Array.from(document.querySelectorAll('.apiScope:checked')).map(c => c.value).join(',');
    secPost({ ajax: 'api_key_create', name: document.getElementById('apiKeyName').value, scopes: scopes,
              allowed_ips: document.getElementById('apiKeyIps').value, expires: document.getElementById('apiKeyExpires').value })
        .then(d => {
            if (!d.success) { secMsg('apiModalMsg', false, d.message || ''); return; }
            apiResultShown = true;
            document.getElementById('apiNewKey').textContent = d.key;
            document.getElementById('apiFormPane').style.display = 'none';
            document.getElementById('apiResultPane').style.display = '';
            apiReloadKeysSoft();
        })
        .catch(() => secMsg('apiModalMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function apiReloadKeysSoft() { // обновляем таблицу ключей, не трогая показанный новый ключ
    secPost({ ajax: 'api_keys_list' }).then(d => { if (d.success) { apiKeys.length = 0; d.keys.forEach(k => apiKeys.push(k)); } apiRenderKeys(); });
}
function apiToggle(id, enable) {
    secPost({ ajax: 'api_key_toggle', id: id, enabled: enable ? '1' : '0' }).then(d => {
        secMsg('apiKeysMsg', !!d.success, d.message || '');
        if (d.success) { const k = apiKeys.find(x => x.id === id); if (k) k.enabled = enable; apiRenderKeys(); }
    });
}
function apiDelete(id) {
    if (!confirm(apiStr.del)) return;
    secPost({ ajax: 'api_key_delete', id: id }).then(d => {
        secMsg('apiKeysMsg', !!d.success, d.message || '');
        if (d.success) { const i = apiKeys.findIndex(x => x.id === id); if (i >= 0) apiKeys.splice(i, 1); apiRenderKeys(); }
    });
}
function apiSaveSettings() {
    secPost({ ajax: 'api_save_settings', enabled: document.getElementById('apiEnabled').checked ? '1' : '0',
              rate_limit: document.getElementById('apiRate').value, cors_origins: document.getElementById('apiCors').value })
        .then(d => {
            secMsg('apiSetMsg', !!d.success, d.message || '');
            if (d.success) apiSetState(document.getElementById('apiEnabled').checked);   // статус в шапке вкладки «Ключи»
        })
        .catch(() => secMsg('apiSetMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}

// Документация из openapi.json
let apiDocsSpec = null;
const apiHttpMethods = ['get', 'post', 'put', 'patch', 'delete'];
function apiEndpoints(spec) { // плоский список «адрес + HTTP-метод»; сейчас в API только GET
    const list = [];
    Object.keys(spec.paths).forEach(p => apiHttpMethods.forEach(m => { if (spec.paths[p][m]) list.push({ path: p, method: m, op: spec.paths[p][m] }); }));
    return list;
}
let apiDocsTag = '';
let apiDocsMethod = '';
function apiDocsChips(spec) { // кнопки-фильтры: тип запроса (GET, POST…) и область доступа, с числом методов
    const eps = apiEndpoints(spec), byTag = {}, byMethod = {};
    eps.forEach(e => { const t = (e.op.tags || [])[0] || ''; byTag[t] = (byTag[t] || 0) + 1; byMethod[e.method] = (byMethod[e.method] || 0) + 1; });
    const chip = (kind, val, label, n, active) => '<button type="button" class="sec-btn sm' + (active ? ' primary' : '') + '"' + (n === 0 ? ' disabled title="<?= htmlspecialchars(__('api_docs_no_methods')) ?>"' : '') +
        ' onclick="apiDocsSet(\'' + kind + '\', \'' + val + '\')">' + secEsc(label) + ' <span class="api-chip-n">' + n + '</span></button>';
    document.getElementById('apiDocsMethods').innerHTML = chip('m', '', <?= json_encode(__('api_docs_all')) ?>, eps.length, apiDocsMethod === '') +
        apiHttpMethods.map(m => chip('m', m, m.toUpperCase(), byMethod[m] || 0, apiDocsMethod === m)).join('');
    document.getElementById('apiDocsChips').innerHTML = chip('t', '', <?= json_encode(__('api_docs_all')) ?>, eps.length, apiDocsTag === '') +
        Object.keys(apiScopeNames).filter(t => byTag[t]).map(t => chip('t', t, apiScopeNames[t] || t, byTag[t], apiDocsTag === t)).join('');
}
function apiDocsClearSearch() { document.getElementById('apiDocsSearch').value = ''; apiDocsFilter(); document.getElementById('apiDocsSearch').focus(); }
function apiDocsReset() { // сбрасывает и поиск, и оба набора фильтров
    document.getElementById('apiDocsSearch').value = '';
    apiDocsTag = ''; apiDocsMethod = '';
    if (apiDocsSpec) apiDocsChips(apiDocsSpec);
    apiDocsFilter();
}
function apiDocsSet(kind, val) {
    if (kind === 'm') apiDocsMethod = apiDocsMethod === val ? '' : val; else apiDocsTag = apiDocsTag === val ? '' : val;
    apiDocsChips(apiDocsSpec);
    apiDocsFilter();
}
function apiDocsFilter() {
    const q = (document.getElementById('apiDocsSearch').value || '').trim().toLowerCase();
    const eps = document.querySelectorAll('#apiDocs .api-ep');
    let shown = 0;
    eps.forEach(e => {
        const ok = (!q || e.getAttribute('data-s').indexOf(q) !== -1) && (!apiDocsTag || e.getAttribute('data-t') === apiDocsTag) && (!apiDocsMethod || e.getAttribute('data-m') === apiDocsMethod);
        e.style.display = ok ? '' : 'none'; if (ok) shown++;
    });
    document.getElementById('apiDocsCount').textContent = eps.length ? ((q || apiDocsTag || apiDocsMethod) ? shown + ' / ' : '') + eps.length : '';
    document.getElementById('apiDocsNone').style.display = eps.length && !shown ? '' : 'none';
    document.getElementById('apiDocsClear').style.display = q ? '' : 'none';
    document.getElementById('apiDocsReset').disabled = !(q || apiDocsTag || apiDocsMethod);
}
let apiDocsLoaded = false;
function apiResolve(spec, node) {
    if (node && node.$ref) { const name = node.$ref.split('/').pop(); return spec.components.schemas[name] || {}; }
    return node || {};
}
function apiExample(spec, schema, depth) {
    schema = apiResolve(spec, schema); depth = depth || 0;
    if (schema.allOf) { let o = {}; schema.allOf.forEach(s => Object.assign(o, apiExample(spec, s, depth))); return o; }
    if (schema.type === 'array') return depth > 2 ? [] : [apiExample(spec, schema.items, depth + 1)];
    if (schema.type === 'object') { const o = {}; Object.keys(schema.properties || {}).forEach(k => o[k] = apiExample(spec, schema.properties[k], depth + 1)); return o; }
    if (schema.example !== undefined) return schema.example;
    if (schema.enum) return schema.enum[0];
    return ({ string: 'string', integer: 0, boolean: true, number: 0 })[schema.type] ?? null;
}
function apiDocsLoad() {
    if (apiDocsLoaded) return; apiDocsLoaded = true;
    const link = document.getElementById('apiSpecLink'); link.href = apiBase + '/openapi.json';
    fetch(apiBase + '/openapi.json').then(r => r.json()).then(spec => {
        apiDocsSpec = spec;
        let html = '<p class="sec-hint">' + secEsc(<?= json_encode(__('api_docs_intro')) ?>) + '</p>';
        apiEndpoints(spec).forEach((ep, i) => {
            const p = ep.path, op = ep.op, mth = ep.method.toUpperCase(), txt = apiEpText[p] || op.summary || '', scope = (op.tags || [])[0] || '';
            const params = (op.parameters || []).map(q => '<tr><td class="mono">' + secEsc(q.name) + '</td><td>' + secEsc(q.in) + '</td><td>' + secEsc((q.schema && q.schema.type) || '') + (q.required ? ' *' : '') + '</td><td>' + secEsc(q.description || '') + '</td></tr>').join('');
            const okSchema = op.responses['200'].content['application/json'].schema;
            const ex = JSON.stringify({ data: apiExample(spec, okSchema) }, null, 2);
            const curl = 'curl ' + (mth === 'GET' ? '' : '-X ' + mth + ' ') + '-H "Authorization: Bearer <?= 'kms_…' ?>" "' + apiBase + p.replace('{name}', 'DEVICE-01') + '"';
            html += '<div class="api-ep" id="apiEp' + i + '" data-m="' + ep.method + '" data-t="' + secEsc(scope) + '" data-s="' + secEsc((p + ' ' + txt + ' ' + (op.summary || '') + ' ' + (apiScopeNames[scope] || '') + ' ' + scope).toLowerCase()) + '"><div class="api-ep-head" onclick="document.getElementById(\'apiEp' + i + '\').classList.toggle(\'open\')">' +
                '<span class="api-method api-m-' + ep.method + '">' + mth + '</span><span class="api-path">/v1' + secEsc(p) + '</span><span class="api-sum">' + secEsc(txt) + '</span>' +
                '<span class="sec-badge"><?= htmlspecialchars(__('api_docs_scope')) ?>: ' + secEsc(apiScopeNames[scope] || scope) + '</span></div><div class="api-ep-body">' +
                '<p class="api-desc">' + secEsc(txt) + '</p>' +
                (params ? '<h4><?= __('api_docs_params') ?></h4><table class="sec-table"><tbody>' + params + '</tbody></table>' : '') +
                '<h4><?= __('api_docs_example') ?></h4><div class="api-codebox"><button type="button" class="sec-btn sm api-copy" title="<?= htmlspecialchars(__('copy')) ?>" onclick="apiCopyBtn(this)">📋</button><div class="sec-code">' + secEsc(curl) + '</div></div>' +
                '<h4><?= __('api_docs_response') ?></h4><div class="api-codebox"><button type="button" class="sec-btn sm api-copy" title="<?= htmlspecialchars(__('copy')) ?>" onclick="apiCopyBtn(this)">📋</button><div class="sec-code">' + secEsc(ex) + '</div></div></div></div>';
        });
        document.getElementById('apiDocs').innerHTML = html;
        apiDocsChips(spec);
        apiDocsFilter();
    }).catch(() => { document.getElementById('apiDocs').innerHTML = '<div class="sec-empty">' + secEsc(<?= json_encode(__('api_docs_error')) ?>) + '</div>'; });
}

document.getElementById('apiBaseUrl').textContent = apiBase;
document.getElementById('apiCurl').textContent = 'curl -H "Authorization: Bearer kms_…" ' + apiBase + '/status';
apiRenderKeys();
document.addEventListener('DOMContentLoaded', function () {
    secTabRestore('section-api', 'keys');
    const act = document.querySelector('#section-api .sec-tab-btn.active');
    if (act && act.getAttribute('data-for') === 'docs') apiDocsLoad();
});
</script>
