<?php
// ============================================
// ФАЙЛ: sections/api.php
// ВЕРСИЯ: 6.0.3
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
            <div class="settings-card-title">🔑 <?= __('api_keys_title') ?> <span class="sec-badge" id="apiKeyCount"><?= count($apiKeys) ?></span></div>
            <div class="sec-note warn" id="apiDisabledNote" style="<?= $apiSet['enabled'] ? 'display: none;' : '' ?>"><?= __('api_disabled_note') ?></div>
            <div class="sec-row">
                <span><?= __('api_base_url') ?>:</span>
                <span class="sec-code" style="margin: 0; flex: 1 1 300px;" id="apiBaseUrl"></span>
                <button type="button" class="sec-btn sm" onclick="secCopy(document.getElementById('apiBaseUrl').textContent)">📋 <?= __('copy') ?></button>
            </div>
            <?php if ($apiCanEdit): ?>
            <div id="apiCreateBox" style="display: none; margin: 10px 0; padding: 12px; border: 1px dashed <?= $themeCSS['border'] ?>; border-radius: 8px;">
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
                <div class="sec-row"><label for="apiKeyExpires"><?= __('api_key_expires') ?></label>
                    <input type="date" id="apiKeyExpires" class="form-control sec-narrow" style="width: 160px; flex: 0 0 160px;"></div>
                <div class="sec-row" style="margin-bottom: 0;">
                    <button type="button" class="sec-btn primary" onclick="apiCreateKey()">➕ <?= __('api_key_create') ?></button>
                    <button type="button" class="sec-btn" onclick="document.getElementById('apiCreateBox').style.display='none'"><?= __('cancel') ?></button>
                </div>
            </div>
            <div id="apiNewKeyBox" class="sec-note" style="display: none;">
                <b><?= __('api_key_created_title') ?></b>
                <div class="sec-code" id="apiNewKey" style="margin: 8px 0;"></div>
                <button type="button" class="sec-btn sm" onclick="secCopy(document.getElementById('apiNewKey').textContent)">📋 <?= __('copy') ?></button>
                <span class="sec-hint"><?= __('api_key_created_hint') ?></span>
            </div>
            <div class="sec-row" style="margin-top: 8px;">
                <button type="button" class="sec-btn primary" onclick="document.getElementById('apiCreateBox').style.display='block'">➕ <?= __('api_key_new') ?></button>
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
                    <div class="settings-card-title">⚙️ <?= __('api_settings_title') ?></div>
                    <label class="sec-switch">
                        <input type="checkbox" id="apiEnabled" <?= $apiSet['enabled'] ? 'checked' : '' ?> <?= $apiCanEdit ? '' : 'disabled' ?>>
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
                    <div id="apiSetMsg" class="sec-msg"></div>
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
            <div class="sec-row">
                <a class="sec-btn sm" id="apiSpecLink" href="#" target="_blank" rel="noopener">⬇️ openapi.json</a>
                <span class="sec-hint" style="margin: 0;"><?= __('api_docs_hint') ?></span>
            </div>
            <div id="apiDocs"><div class="sec-empty"><?= __('api_docs_loading') ?></div></div>
        </div>
    </div>
</div>

<style>
.api-ep { border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
.api-ep-head { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; background: <?= $themeCSS['card'] ?>; }
.api-ep-head:hover { background: <?= $themeCSS['hover'] ?>; }
.api-method { background: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.api-path { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12px; }
.api-sum { color: <?= $themeCSS['muted'] ?>; font-size: 12px; flex: 1; }
.api-ep-body { display: none; padding: 10px 12px; border-top: 1px solid <?= $themeCSS['border'] ?>; font-size: 12px; }
.api-ep.open .api-ep-body { display: block; }
.api-ep h4 { margin: 10px 0 4px; font-size: 11px; text-transform: uppercase; color: <?= $themeCSS['muted'] ?>; }
</style>

<script>
const apiKeys = <?= json_encode($apiKeysView, JSON_UNESCAPED_UNICODE) ?>;
const apiCanEdit = <?= $apiCanEdit ? 'true' : 'false' ?>;
const apiStr = { on: <?= json_encode(__('api_key_on')) ?>, off: <?= json_encode(__('api_key_off')) ?>, expired: <?= json_encode(__('api_key_expired')) ?>, never: <?= json_encode(__('api_never')) ?>,
    anyIp: <?= json_encode(__('api_any_ip')) ?>, none: <?= json_encode(__('api_keys_empty')) ?>, del: <?= json_encode(__('api_key_delete_confirm')) ?>,
    disable: <?= json_encode(__('api_key_disable')) ?>, enable: <?= json_encode(__('api_key_enable')) ?>, requests: <?= json_encode(__('api_requests')) ?> };
const apiScopeNames = <?= json_encode(array_combine(apiScopes(), array_map(function ($s) { return __('api_scope_' . $s); }, apiScopes())), JSON_UNESCAPED_UNICODE) ?>;
const apiBase = location.origin + location.pathname.replace(/[^\/]*$/, '') + 'api.php/v1';

function apiRenderKeys() {
    const body = document.getElementById('apiKeyRows');
    document.getElementById('apiKeyCount').textContent = apiKeys.length;
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
function apiCreateKey() {
    const scopes = Array.from(document.querySelectorAll('.apiScope:checked')).map(c => c.value).join(',');
    secPost({ ajax: 'api_key_create', name: document.getElementById('apiKeyName').value, scopes: scopes,
              allowed_ips: document.getElementById('apiKeyIps').value, expires: document.getElementById('apiKeyExpires').value })
        .then(d => {
            secMsg('apiKeysMsg', !!d.success, d.message || '');
            if (d.success) {
                document.getElementById('apiNewKey').textContent = d.key;
                document.getElementById('apiNewKeyBox').style.display = 'block';
                document.getElementById('apiCreateBox').style.display = 'none';
                document.getElementById('apiKeyName').value = '';
                apiReloadKeysSoft();
            }
        })
        .catch(() => secMsg('apiKeysMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}
function apiHideNewKey() { // удалили ключ — показанное значение нового ключа больше не нужно
    document.getElementById('apiNewKey').textContent = '';
    document.getElementById('apiNewKeyBox').style.display = 'none';
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
        if (d.success) { const i = apiKeys.findIndex(x => x.id === id); if (i >= 0) apiKeys.splice(i, 1); apiRenderKeys(); apiHideNewKey(); }
    });
}
function apiSaveSettings() {
    secPost({ ajax: 'api_save_settings', enabled: document.getElementById('apiEnabled').checked ? '1' : '0',
              rate_limit: document.getElementById('apiRate').value, cors_origins: document.getElementById('apiCors').value })
        .then(d => {
            secMsg('apiSetMsg', !!d.success, d.message || '');
            if (d.success) document.getElementById('apiDisabledNote').style.display = document.getElementById('apiEnabled').checked ? 'none' : '';   // предупреждение на вкладке «Ключи»
        })
        .catch(() => secMsg('apiSetMsg', false, <?= json_encode(__('msg_save_error')) ?>));
}

// Документация из openapi.json
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
        let html = '<p class="sec-hint">' + secEsc(spec.info.description) + '</p>';
        Object.keys(spec.paths).forEach((p, i) => {
            const op = spec.paths[p].get;
            const params = (op.parameters || []).map(q => '<tr><td class="mono">' + secEsc(q.name) + '</td><td>' + secEsc(q.in) + '</td><td>' + secEsc((q.schema && q.schema.type) || '') + (q.required ? ' *' : '') + '</td><td>' + secEsc(q.description || '') + '</td></tr>').join('');
            const okSchema = op.responses['200'].content['application/json'].schema;
            const ex = JSON.stringify({ data: apiExample(spec, okSchema) }, null, 2);
            const curl = 'curl -H "Authorization: Bearer <?= 'kms_…' ?>" "' + apiBase + p.replace('{name}', 'DEVICE-01') + '"';
            html += '<div class="api-ep" id="apiEp' + i + '"><div class="api-ep-head" onclick="document.getElementById(\'apiEp' + i + '\').classList.toggle(\'open\')">' +
                '<span class="api-method">GET</span><span class="api-path">/v1' + secEsc(p) + '</span><span class="api-sum">' + secEsc(op.summary) + '</span>' +
                '<span class="sec-badge">' + secEsc(op.description || '') + '</span></div><div class="api-ep-body">' +
                (params ? '<h4><?= __('api_docs_params') ?></h4><table class="sec-table"><tbody>' + params + '</tbody></table>' : '') +
                '<h4><?= __('api_docs_example') ?></h4><div class="sec-code">' + secEsc(curl) + '</div>' +
                '<h4><?= __('api_docs_response') ?></h4><div class="sec-code">' + secEsc(ex) + '</div></div></div>';
        });
        document.getElementById('apiDocs').innerHTML = html;
        document.getElementById('apiEp0').classList.add('open');
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
