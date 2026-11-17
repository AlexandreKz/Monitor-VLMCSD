<?php
// ============================================
// ФАЙЛ: sections/notifications.php
// ВЕРСИЯ: 3.3.1
// ДАТА: 2026-11-17
// @description: Раздел «Уведомления» (пункт 27): вкладки «Правила», «Каналы», «История»; правила (события, каналы, фильтр по группе/устройству, пороги, частота, тихие часы) и общие каналы доставки (Email/SMTP, Telegram) создаются в модальных окнах; доступ — право «Уведомления: изменение»
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}
if (!canEditNotifications($currentUserPermissions)) {
    return;
}
require_once __DIR__ . '/../vlmcinc/notify.php';

$ntfEv = [];
foreach (notifyCatalog() as $id => $e) {
    $ntfEv[$id] = __('notify_ev_' . $id);
}
$ntfGrp = [];
foreach (notifyEventGroups() as $g) {
    $ntfGrp[$g] = __('notify_grp_' . $g);
}
$ntfPar = [];
foreach (notifyCatalog() as $e) {
    foreach (($e['params'] ?? []) as $k => $_) { $ntfPar[$k] = __('notify_p_' . $k); }
}
$ntfKeys = ['rule_new', 'channel_new', 'rules_empty', 'channels_empty', 'col_name', 'col_events', 'col_channels', 'col_freq', 'col_quiet', 'col_status',
    'col_type', 'col_target', 'col_time', 'col_rule', 'col_event', 'col_result', 'no_channels', 'unlimited', 'every_min', 'st_on', 'st_off',
    'rule_title_new', 'rule_title_edit', 'ch_title_new', 'ch_title_edit', 'f_name', 'f_events', 'f_channels', 'f_channels_none', 'f_filter', 'f_filter_groups',
    'f_filter_devices', 'f_filter_hint', 'f_params', 'f_throttle', 'f_throttle_hint', 'f_quiet', 'f_quiet_from', 'f_quiet_to', 'f_active', 'soon',
    'f_type', 'f_host', 'f_port', 'f_secure', 'f_secure_tls', 'f_secure_ssl', 'f_secure_none', 'f_user', 'f_pass', 'f_from', 'f_to', 'f_to_hint',
    'f_token', 'f_chat', 'f_webhook', 'f_webhook_hint', 'f_dialog', 'f_dialog_hint', 'f_schedule', 'err_no_channels', 'f_chat_hint', 'f_ch_enabled', 'secret_set', 'btn_save', 'btn_cancel', 'btn_delete', 'btn_test', 'confirm_delete_rule',
    'confirm_delete_channel', 'hist_empty', 'hist_ok', 'hist_fail', 'hist_quiet', 'sev_high', 'sev_mid', 'sev_low', 'ch_off', 'more'];
$ntfT = [];
foreach ($ntfKeys as $k) {
    $ntfT[$k] = __('notify_' . $k);
}
$ntfT['saveError'] = __('msg_save_error');
?>
<div id="section-notifications" class="settings-section <?= $activeSection === 'notifications' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🔔 <?= __('notify_title') ?></span>
        <div class="ntf-tabs">
            <button type="button" class="ntf-tab active" data-p="ntfChannels">📡 <?= __('notify_tab_channels') ?></button>
            <button type="button" class="ntf-tab" data-p="ntfRules">📋 <?= __('notify_tab_rules') ?></button>
            <button type="button" class="ntf-tab" data-p="ntfHistory">🕘 <?= __('notify_tab_history') ?></button>
            <button type="button" class="ntf-tab" data-p="ntfTemplates">🧩 <?= __('notify_tab_templates') ?></button>
        </div>
    </div>

    <div id="ntfRules" class="ntf-page">
        <div class="ntf-row ntf-top"><button type="button" class="ntf-flat" id="ntfNewRule">➕ <?= __('notify_rule_new') ?></button><span class="ntf-msg"></span></div>
        <div class="ntf-wrap"><table class="ntf-table" id="ntfRulesTable"></table></div>
    </div>
    <div id="ntfChannels" class="ntf-page active">
        <div class="ntf-row ntf-top"><button type="button" class="ntf-flat" id="ntfNewChannel">➕ <?= __('notify_channel_new') ?></button><span class="ntf-msg"></span></div>
        <div class="ntf-wrap"><table class="ntf-table" id="ntfChannelsTable"></table></div>
    </div>
    <div id="ntfHistory" class="ntf-page">
        <div class="ntf-wrap"><table class="ntf-table" id="ntfHistTable"></table></div>
    </div>
    <div id="ntfTemplates" class="ntf-page">
        <div class="ntf-hint">🚧 <?= __('notify_templates_soon') ?></div>
    </div>
</div>

<style>
.ntf-tabs { display: flex; gap: 10px; margin-left: auto; flex-wrap: wrap; }
.ntf-tab { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; }
.ntf-tab:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.ntf-tab.active { opacity: 1; color: <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.ntf-page { display: none; }
.ntf-page.active { display: block; }
.ntf-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ntf-top { margin: 0 0 12px; }
.ntf-hint { font-size: 12px; color: #8aa0bb; }
.ntf-wrap { overflow: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; background: <?= $themeCSS['input'] ?>; }
.ntf-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ntf-table th { text-align: left; padding: 8px 10px; background: <?= $themeCSS['card'] ?>; color: #8aa0bb; font-weight: 600; white-space: nowrap; }
.ntf-table td { padding: 6px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; vertical-align: middle; }
.ntf-table tr.ntf-click { cursor: pointer; }
.ntf-table tr.ntf-click:hover td { background: <?= $themeCSS['hover'] ?>; }
.ntf-none { color: <?= $themeCSS['danger'] ?>; }
.ntf-badge { font-size: 10px; line-height: 16px; padding: 1px 9px; border-radius: 9px; font-weight: 500; white-space: nowrap; background: <?= $themeCSS['border'] ?>; display: inline-block; }
.ntf-badge.on { background: <?= $themeCSS['success'] ?>; color: #fff; }
.ntf-badge.ntf-tg { cursor: pointer; transition: filter .15s; }
.ntf-badge.ntf-tg:hover { filter: brightness(1.15); }
.ntf-sec.ntf-bad { border-color: <?= $themeCSS['danger'] ?>; box-shadow: 0 0 0 2px <?= $themeCSS['danger'] ?>33; animation: ntfShake .35s; }
@keyframes ntfShake { 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
.ntf-sev { font-size: 11px; padding: 1px 8px; border-radius: 4px; }
.ntf-sev.high { background: <?= $themeCSS['danger'] ?>33; color: <?= $themeCSS['danger'] ?>; }
.ntf-sev.mid { background: <?= $themeCSS['warning'] ?>33; color: <?= $themeCSS['warning'] ?>; }
.ntf-sev.low { background: <?= $themeCSS['primary'] ?>33; color: <?= $themeCSS['primary'] ?>; }
.ntf-msg { font-size: 13px; }
.ntf-msg.ok { color: <?= $themeCSS['success'] ?>; }
.ntf-msg.err { color: <?= $themeCSS['danger'] ?>; }
.ntf-flat { box-sizing: border-box; background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 0 16px; height: 30px; font-size: 12px; line-height: 28px; cursor: pointer; white-space: nowrap; font-family: inherit; transition: border-color .15s, color .15s; }
.ntf-flat:hover:not(:disabled) { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['primary'] ?>; }
.ntf-flat:disabled { opacity: 0.5; cursor: default; }
.ntf-flat.danger:hover:not(:disabled) { border-color: <?= $themeCSS['danger'] ?>; color: <?= $themeCSS['danger'] ?>; }
.ntf-prim { box-sizing: border-box; background: <?= $themeCSS['primary'] ?>; color: #fff; border: 1px solid <?= $themeCSS['primary'] ?>; border-radius: 6px; padding: 0 18px; height: 30px; font-size: 12px; cursor: pointer; font-family: inherit; }
.ntf-prim:disabled { opacity: 0.5; cursor: default; }
.ntf-num { -moz-appearance: textfield; appearance: textfield; }
.ntf-num::-webkit-outer-spin-button, .ntf-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.ntf-ov { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.ntf-mod { background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 10px; width: 720px; max-width: 100%; max-height: 92vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,.4); }
.ntf-mh { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; font-size: 15px; font-weight: 600; }
.ntf-x { background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; opacity: .7; }
.ntf-x:hover { opacity: 1; }
.ntf-mb { padding: 16px 18px; overflow-y: auto; }
.ntf-mf { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border-top: 1px solid <?= $themeCSS['border'] ?>; flex-wrap: wrap; }
.ntf-mf .ntf-msg { flex: 1 1 160px; min-width: 0; }
.ntf-mod .form-control { background: <?= $themeCSS['bg'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; }
.ntf-mb .ntf-hint { margin: 4px 0 12px; }
.ntf-mod .form-control:focus { border-color: <?= $themeCSS['primary'] ?>; outline: none; }
.ntf-sec { border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; }
.ntf-sec > .ntf-st { font-size: 13px; font-weight: 600; margin: 0 0 10px; }
.ntf-f { margin-bottom: 14px; }
.ntf-sec .ntf-f:last-child { margin-bottom: 0; }
.ntf-l { display: block; font-size: 12px; color: #8aa0bb; margin-bottom: 5px; }
.ntf-gh { font-size: 12px; font-weight: 600; margin: 10px 0 4px; color: #8aa0bb; }
.ntf-evg { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 4px 14px; }
.ntf-chk { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; margin: 0; }
.ntf-chk.dis { opacity: .5; cursor: default; }
.ntf-box { border: 1px solid <?= $themeCSS['border'] ?>; background: <?= $themeCSS['bg'] ?>; border-radius: 6px; padding: 8px 10px; max-height: 150px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 12px; }
.ntf-grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.ntf-inl { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ntf-time { width: 96px; cursor: pointer; }
.ntf-w { width: 120px; }
</style>
<script>
(function () {
    const T = <?= json_encode($ntfT, JSON_UNESCAPED_UNICODE) ?>;
    const EV = <?= json_encode($ntfEv, JSON_UNESCAPED_UNICODE) ?>;
    const GRP = <?= json_encode($ntfGrp, JSON_UNESCAPED_UNICODE) ?>;
    const PAR = <?= json_encode($ntfPar, JSON_UNESCAPED_UNICODE) ?>;
    const $ = function (id) { return document.getElementById(id); };
    let D = { channels: [], rules: [], catalog: [], groups: [], devices: [], history: [] };
    let loaded = false;

    function post(params) {
        const fd = new FormData();
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }
    function el(tag, cls, text) { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
    function btn(cls, text, fn) { const b = el('button', cls, text); b.type = 'button'; b.onclick = fn; return b; }
    function evName(id) { return EV[id] || id; }
    function chName(id) { const c = D.channels.filter(function (x) { return x.id === id; })[0]; return c ? c.name : ''; }

    function say(node, text, ok) {
        if (!node) return;
        node.textContent = text || ''; node.className = 'ntf-msg ' + (ok ? 'ok' : 'err');
        setTimeout(function () { if (node.textContent === text) node.textContent = ''; }, ok ? 4000 : 8000);
    }
    function pageMsg() { return document.querySelector('#section-notifications .ntf-page.active .ntf-msg'); }
    function apply(d) { ['channels', 'rules', 'catalog', 'groups', 'devices', 'history'].forEach(function (k) { if (d[k]) D[k] = d[k]; }); renderAll(); }
    function failMsg(node, e) { say(node, T.saveError + ' (' + (e && e.message ? e.message : '?') + ')', false); }

    // ---------- вкладки ----------
    document.querySelectorAll('#section-notifications .ntf-tab').forEach(function (b) {
        b.onclick = function () {
            document.querySelectorAll('#section-notifications .ntf-tab, #section-notifications .ntf-page').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active'); $(b.dataset.p).classList.add('active');
            document.querySelectorAll('#section-notifications .ntf-msg').forEach(function (m) { m.textContent = ''; });
        };
    });

    // ---------- таблицы ----------
    function head(t, cols) { const tr = el('tr'); cols.forEach(function (c) { tr.appendChild(el('th', '', c)); }); t.appendChild(tr); }
    function emptyRow(t, text, span) { const tr = el('tr'); const td = el('td', 'ntf-hint', text); td.colSpan = span; tr.appendChild(td); t.appendChild(tr); }

    function renderRules() {
        const t = $('ntfRulesTable'); t.textContent = '';
        head(t, [T.col_name, T.col_events, T.col_channels, T.col_freq, T.col_quiet, T.col_status]);
        if (!D.rules.length) { emptyRow(t, T.rules_empty, 6); return; }
        D.rules.forEach(function (r) {
            const tr = el('tr', 'ntf-click'); tr.onclick = function () { openRule(r); };
            tr.appendChild(el('td', '', r.name));
            const names = (r.events || []).map(evName);
            const te = el('td', '', names.slice(0, 2).join(', ') + (names.length > 2 ? ' +' + (names.length - 2) : '')); te.title = names.join(', '); tr.appendChild(te);
            const chs = (r.channels || []).map(chName).filter(Boolean);
            tr.appendChild(chs.length ? el('td', '', chs.join(', ')) : el('td', 'ntf-none', T.no_channels));
            tr.appendChild(el('td', '', r.throttle > 0 ? T.every_min.replace('%d', r.throttle) : T.unlimited));
            tr.appendChild(el('td', '', r.quiet_enabled ? r.quiet_from + '–' + r.quiet_to : '—'));
            const ts = el('td'); const b = el('span', 'ntf-badge ntf-tg' + (r.active ? ' on' : ''), r.active ? T.st_on : T.st_off);
            b.onclick = function (ev) {
                ev.stopPropagation();
                post({ ajax: 'notify_rule_toggle', id: r.id, active: r.active ? '' : '1' }).then(function (d) {
                    if (d.success) apply(d); say(pageMsg(), d.message, !!d.success);
                }).catch(function (e) { failMsg(pageMsg(), e); });
            };
            ts.appendChild(b); tr.appendChild(ts); t.appendChild(tr);
        });
    }

    function chTarget(c) {
        if (c.type === 'email') return c.email.to;
        if (c.type === 'bitrix24') return (c.bitrix24.host ? c.bitrix24.host + ' → ' : '') + c.bitrix24.dialog;
        return c.telegram.chat;
    }
    function renderChannels() {
        const t = $('ntfChannelsTable'); t.textContent = '';
        head(t, [T.col_name, T.col_type, T.col_target, T.col_status]);
        if (!D.channels.length) { emptyRow(t, T.channels_empty, 4); return; }
        D.channels.forEach(function (c) {
            const tr = el('tr', 'ntf-click'); tr.onclick = function () { openChannel(c); };
            tr.appendChild(el('td', '', c.name));
            tr.appendChild(el('td', '', c.type === 'email' ? '✉️ Email' : (c.type === 'bitrix24' ? '🏢 Битрикс24' : '✈️ Telegram')));
            tr.appendChild(el('td', '', chTarget(c)));
            const ts = el('td'); ts.appendChild(el('span', 'ntf-badge' + (c.enabled ? ' on' : ''), c.enabled ? T.st_on : T.st_off)); tr.appendChild(ts);
            t.appendChild(tr);
        });
    }

    function renderHistory() {
        const t = $('ntfHistTable'); t.textContent = '';
        head(t, [T.col_time, T.col_rule, T.col_event, T.col_channels, T.col_result]);
        if (!D.history.length) { emptyRow(t, T.hist_empty, 5); return; }
        D.history.forEach(function (h) {
            const tr = el('tr'); tr.appendChild(el('td', '', new Date(h.t * 1000).toLocaleString()));
            tr.appendChild(el('td', '', h.rule || ''));
            tr.appendChild(el('td', '', h.title || ''));
            tr.appendChild(el('td', '', (h.ch || []).join(', ')));
            const st = el('td', '', h.skip === 'quiet' ? T.hist_quiet : (h.ok ? T.hist_ok : (T.hist_fail + (h.err ? ': ' + h.err : ''))));
            if (!h.ok) st.className = 'ntf-none';
            tr.appendChild(st); t.appendChild(tr);
        });
    }
    function renderAll() { renderRules(); renderChannels(); renderHistory(); }

    // ---------- модальное окно ----------
    function openModal(title, body, footer) {
        const ov = el('div', 'ntf-ov'), md = el('div', 'ntf-mod');
        const h = el('div', 'ntf-mh'); h.appendChild(el('span', '', title));
        const close = function () { document.removeEventListener('keydown', onKey); ov.remove(); };
        function onKey(e) { if (e.key === 'Escape') close(); }
        document.addEventListener('keydown', onKey);
        h.appendChild(btn('ntf-x', '✕', close));
        const b = el('div', 'ntf-mb'); b.appendChild(body);
        const f = el('div', 'ntf-mf'); footer.forEach(function (n) { f.appendChild(n); });
        md.appendChild(h); md.appendChild(b); md.appendChild(f); ov.appendChild(md);
        ov.addEventListener('mousedown', function (e) { if (e.target === ov) close(); });
        document.body.appendChild(ov);
        return close;
    }
    function field(label, node) { const f = el('div', 'ntf-f'); if (label) f.appendChild(el('label', 'ntf-l', label)); f.appendChild(node); return f; }
    function input(type, val, cls) { const i = el('input', 'form-control' + (cls ? ' ' + cls : '')); i.type = type; i.value = val === undefined ? '' : val; i.autocomplete = type === 'password' ? 'new-password' : 'off'; return i; }
    function check(text, on, dis) {
        const l = el('label', 'ntf-chk' + (dis ? ' dis' : '')); const c = document.createElement('input'); c.type = 'checkbox'; c.checked = !!on; c.disabled = !!dis;
        l.appendChild(c); l.appendChild(el('span', '', text)); return { label: l, box: c };
    }
    function timeSelect(v) {
        const s = el('select', 'form-control ntf-time');
        for (let h = 0; h < 24; h++) for (let m = 0; m < 60; m += 15) { const x = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'); s.appendChild(new Option(x, x)); }
        v = v || '00:00';
        if (!Array.prototype.some.call(s.options, function (o) { return o.value === v; })) s.appendChild(new Option(v, v));
        s.value = v; return s;
    }
    function catOf(id) { return D.catalog.filter(function (c) { return c.id === id; })[0]; }

    // ---------- правило ----------
    function openRule(rule) {
        const isNew = !rule;
        const st = rule ? JSON.parse(JSON.stringify(rule)) : { name: '', events: [], channels: [], groups: [], devices: [], throttle: 30, quiet_enabled: false, quiet_from: '23:00', quiet_to: '07:00', active: true, params: {} };
        const body = el('div');
        const name = input('text', st.name); name.maxLength = 80;
        body.appendChild(field(T.f_name, name));

        // события по группам
        const evBox = el('div', 'ntf-sec'); evBox.appendChild(el('div', 'ntf-st', T.f_events)); body.appendChild(evBox);
        const order = <?= json_encode(notifyEventGroups()) ?>;
        order.forEach(function (g) {
            const items = D.catalog.filter(function (c) { return c.group === g; });
            if (!items.length) return;
            evBox.appendChild(el('div', 'ntf-gh', GRP[g] || g));
            const grid = el('div', 'ntf-evg');
            items.forEach(function (c) {
                const k = check(evName(c.id), st.events.indexOf(c.id) >= 0, c.soon);
                if (c.soon) { k.label.appendChild(el('span', 'ntf-badge', T.soon)); }
                else {
                    k.box.onchange = function () {
                        const i = st.events.indexOf(c.id);
                        if (k.box.checked && i < 0) st.events.push(c.id); else if (!k.box.checked && i >= 0) st.events.splice(i, 1);
                        dyn();
                    };
                }
                grid.appendChild(k.label);
            });
            evBox.appendChild(grid);
        });

        // каналы
        const chBox = el('div', 'ntf-box');
        const chSec = el('div', 'ntf-sec');
        if (!D.channels.length) { chBox.appendChild(el('span', 'ntf-hint', T.f_channels_none)); }
        D.channels.forEach(function (c) {
            const k = check((c.type === 'email' ? '✉️ ' : (c.type === 'bitrix24' ? '🏢 ' : '✈️ ')) + c.name + (c.enabled ? '' : ' (' + T.ch_off + ')'), st.channels.indexOf(c.id) >= 0);
            k.box.onchange = function () {
                chSec.classList.remove('ntf-bad');
                const i = st.channels.indexOf(c.id);
                if (k.box.checked && i < 0) st.channels.push(c.id); else if (!k.box.checked && i >= 0) st.channels.splice(i, 1);
            };
            chBox.appendChild(k.label);
        });
        chSec.appendChild(el('div', 'ntf-st', T.f_channels)); chSec.appendChild(chBox); body.appendChild(chSec);

        // фильтр и пороги — зависят от выбранных событий
        const dynBox = el('div'); body.appendChild(dynBox);
        function dyn() {
            dynBox.textContent = '';
            const sel = st.events.map(catOf).filter(Boolean);
            const needGroup = sel.some(function (c) { return c.scope === 'group' || c.scope === 'device'; });
            const needDev = sel.some(function (c) { return c.scope === 'device'; });
            if (needGroup) {
                const wrap = el('div');
                const gb = el('div', 'ntf-box');
                D.groups.forEach(function (g) {
                    const k = check(g, st.groups.indexOf(g) >= 0);
                    k.box.onchange = function () { const i = st.groups.indexOf(g); if (k.box.checked && i < 0) st.groups.push(g); else if (!k.box.checked && i >= 0) st.groups.splice(i, 1); };
                    gb.appendChild(k.label);
                });
                wrap.appendChild(field(T.f_filter_groups, gb));
                if (needDev) {
                    const db = el('div', 'ntf-box');
                    D.devices.forEach(function (d) {
                        const k = check(d.name + ' (' + d.group + ')', st.devices.indexOf(d.name) >= 0);
                        k.box.onchange = function () { const i = st.devices.indexOf(d.name); if (k.box.checked && i < 0) st.devices.push(d.name); else if (!k.box.checked && i >= 0) st.devices.splice(i, 1); };
                        db.appendChild(k.label);
                    });
                    wrap.appendChild(field(T.f_filter_devices, db));
                }
                wrap.appendChild(el('div', 'ntf-hint', T.f_filter_hint));
                const f = el('div', 'ntf-sec'); f.appendChild(el('div', 'ntf-st', T.f_filter)); f.appendChild(wrap); dynBox.appendChild(f);
            }
            const seen = {}; const pf = el('div', 'ntf-inl'); let any = false;
            sel.forEach(function (c) {
                Object.keys(c.params || {}).forEach(function (k) {
                    if (seen[k]) return; seen[k] = true; any = true;
                    const p = c.params[k]; const v = st.params[k] !== undefined ? st.params[k] : p.def;
                    const i = input('number', v, 'ntf-num ntf-w'); i.min = p.min; i.max = p.max;
                    i.oninput = function () { st.params[k] = i.value; };
                    st.params[k] = v;
                    const w = el('div'); w.appendChild(el('label', 'ntf-l', PAR[k] || k)); w.appendChild(i); pf.appendChild(w);
                });
            });
            if (any) { const ps = el('div', 'ntf-sec'); ps.appendChild(el('div', 'ntf-st', T.f_params)); ps.appendChild(pf); dynBox.appendChild(ps); }
        }
        dyn();

        // частота, тихие часы, активность
        const thr = input('number', st.throttle, 'ntf-num ntf-w'); thr.min = 0; thr.max = 1440;
        const tw = el('div', 'ntf-inl'); tw.appendChild(thr); tw.appendChild(el('span', 'ntf-hint', T.f_throttle_hint));
        const schSec = el('div', 'ntf-sec'); schSec.appendChild(el('div', 'ntf-st', T.f_schedule)); schSec.appendChild(field(T.f_throttle, tw)); body.appendChild(schSec);
        const qk = check(T.f_quiet, st.quiet_enabled);
        const qf = timeSelect(st.quiet_from), qt = timeSelect(st.quiet_to);
        const qw = el('div', 'ntf-inl'); qw.appendChild(qk.label); qw.appendChild(el('span', '', T.f_quiet_from)); qw.appendChild(qf); qw.appendChild(el('span', '', T.f_quiet_to)); qw.appendChild(qt);
        schSec.appendChild(field('', qw));
        const ak = check(T.f_active, st.active); body.appendChild(field('', ak.label));

        const msg = el('span', 'ntf-msg');
        let close;
        const save = btn('ntf-prim', '💾 ' + T.btn_save, function () {
            const data = { events: st.events, channels: st.channels, groups: st.groups, devices: st.devices, throttle: thr.value, quiet_enabled: qk.box.checked,
                quiet_from: qf.value, quiet_to: qt.value, active: ak.box.checked, params: st.params };
            if (data.active && !data.channels.length) {
                chSec.classList.remove('ntf-bad'); void chSec.offsetWidth; chSec.classList.add('ntf-bad');
                chSec.scrollIntoView({ block: 'center', behavior: 'smooth' });
                say(msg, T.err_no_channels, false); return;
            }
            save.disabled = true;
            post({ ajax: 'notify_rule_save', id: isNew ? '' : rule.id, name: name.value, data: JSON.stringify(data) }).then(function (d) {
                if (d.success) { close(); apply(d); say(pageMsg(), d.message, true); } else { say(msg, d.message, false); }
            }).catch(function (e) { failMsg(msg, e); }).then(function () { save.disabled = false; });
        });
        const foot = [save, btn('ntf-flat', T.btn_cancel, function () { close(); })];
        if (!isNew) {
            foot.push(btn('ntf-flat danger', '🗑️ ' + T.btn_delete, function () {
                if (!confirm(T.confirm_delete_rule)) return;
                post({ ajax: 'notify_rule_delete', id: rule.id }).then(function (d) {
                    if (d.success) { close(); apply(d); say(pageMsg(), d.message, true); } else { say(msg, d.message, false); }
                }).catch(function (e) { failMsg(msg, e); });
            }));
        }
        foot.push(msg);
        close = openModal(isNew ? T.rule_title_new : T.rule_title_edit, body, foot);
    }

    // ---------- канал ----------
    function openChannel(ch) {
        const isNew = !ch;
        const c = ch || { name: '', type: 'email', enabled: true, email: { host: '', port: 587, secure: 'tls', user: '', from: '', to: '', has_pass: false }, telegram: { chat: '', has_token: false } };
        const body = el('div');
        const name = input('text', c.name); name.maxLength = 60;
        body.appendChild(field(T.f_name, name));
        const type = el('select', 'form-control');
        type.appendChild(new Option('✉️ Email', 'email')); type.appendChild(new Option('✈️ Telegram', 'telegram')); type.appendChild(new Option('🏢 Битрикс24', 'bitrix24'));
        type.value = c.type; type.disabled = !isNew;
        body.appendChild(field(T.f_type, type));

        const em = el('div'), tg = el('div'), bx = el('div');
        const e = c.email || { host: '', port: 587, secure: 'tls', user: '', from: '', to: '', has_pass: false };
        const host = input('text', e.host), port = input('number', e.port, 'ntf-num'), sec = el('select', 'form-control');
        sec.appendChild(new Option(T.f_secure_tls, 'tls')); sec.appendChild(new Option(T.f_secure_ssl, 'ssl')); sec.appendChild(new Option(T.f_secure_none, 'none')); sec.value = e.secure || 'tls';
        port.min = 1; port.max = 65535;
        const g1 = el('div', 'ntf-grid2'); g1.appendChild(field(T.f_host, host)); g1.appendChild(field(T.f_port, port)); g1.appendChild(field(T.f_secure, sec));
        const user = input('text', e.user), pass = input('password', ''); pass.placeholder = e.has_pass ? T.secret_set : '';
        const g2 = el('div', 'ntf-grid2'); g2.appendChild(field(T.f_user, user)); g2.appendChild(field(T.f_pass, pass));
        const from = input('text', e.from); from.placeholder = 'KMS Monitor <monitor@example.com>';
        const to = input('text', e.to); to.placeholder = 'name@example.com, other@example.com';
        em.appendChild(g1); em.appendChild(g2); em.appendChild(field(T.f_from, from)); em.appendChild(field(T.f_to, to)); em.appendChild(el('div', 'ntf-hint', T.f_to_hint));

        const t = c.telegram || { chat: '', has_token: false };
        const token = input('password', ''); token.placeholder = t.has_token ? T.secret_set : '123456:ABC…';
        const chat = input('text', t.chat); chat.placeholder = '123456789';
        tg.appendChild(field(T.f_token, token)); tg.appendChild(field(T.f_chat, chat)); tg.appendChild(el('div', 'ntf-hint', T.f_chat_hint));
        const bt = c.bitrix24 || { dialog: '', has_hook: false };
        const hook = input('password', ''); hook.placeholder = bt.has_hook ? T.secret_set : 'https://portal.bitrix24.ru/rest/1/xxxxxxxxxxxxxxxx/';
        const dialog = input('text', bt.dialog); dialog.placeholder = '1  /  chat123';
        bx.appendChild(field(T.f_webhook, hook)); bx.appendChild(el('div', 'ntf-hint', T.f_webhook_hint)); bx.appendChild(field(T.f_dialog, dialog)); bx.appendChild(el('div', 'ntf-hint', T.f_dialog_hint));
        body.appendChild(em); body.appendChild(tg); body.appendChild(bx);
        function showType() { em.style.display = type.value === 'email' ? '' : 'none'; tg.style.display = type.value === 'telegram' ? '' : 'none'; bx.style.display = type.value === 'bitrix24' ? '' : 'none'; }
        type.onchange = showType; showType();
        const en = check(T.f_ch_enabled, c.enabled); const enw = el('div', '', ''); enw.style.marginTop = '14px'; enw.appendChild(en.label); body.appendChild(enw);

        function form() {
            const data = type.value === 'email'
                ? { host: host.value, port: port.value, secure: sec.value, user: user.value, pass: pass.value, from: from.value, to: to.value, enabled: en.box.checked }
                : (type.value === 'bitrix24' ? { hook: hook.value, dialog: dialog.value, enabled: en.box.checked } : { token: token.value, chat: chat.value, enabled: en.box.checked });
            return { id: isNew ? '' : ch.id, name: name.value, type: type.value, data: JSON.stringify(data) };
        }
        const msg = el('span', 'ntf-msg');
        let close;
        const save = btn('ntf-prim', '💾 ' + T.btn_save, function () {
            const p = form(); p.ajax = 'notify_channel_save'; save.disabled = true;
            post(p).then(function (d) {
                if (d.success) { close(); apply(d); say(pageMsg(), d.message, true); } else { say(msg, d.message, false); }
            }).catch(function (x) { failMsg(msg, x); }).then(function () { save.disabled = false; });
        });
        const test = btn('ntf-flat', '🧪 ' + T.btn_test, function () {
            const p = form(); p.ajax = 'notify_channel_test'; test.disabled = true;
            post(p).then(function (d) {
                say(msg, d.message, !!d.success);
                post({ ajax: 'notify_get' }).then(function (v) { if (v.success) { D.history = v.history; renderHistory(); } });
            }).catch(function (x) { failMsg(msg, x); }).then(function () { test.disabled = false; });
        });
        const foot = [save, test, btn('ntf-flat', T.btn_cancel, function () { close(); })];
        if (!isNew) {
            foot.push(btn('ntf-flat danger', '🗑️ ' + T.btn_delete, function () {
                if (!confirm(T.confirm_delete_channel)) return;
                post({ ajax: 'notify_channel_delete', id: ch.id }).then(function (d) {
                    if (d.success) { close(); apply(d); say(pageMsg(), d.message, true); } else { say(msg, d.message, false); }
                }).catch(function (x) { failMsg(msg, x); });
            }));
        }
        foot.push(msg);
        close = openModal(isNew ? T.ch_title_new : T.ch_title_edit, body, foot);
    }

    $('ntfNewRule').onclick = function () { openRule(null); };
    $('ntfNewChannel').onclick = function () { openChannel(null); };

    function load() {
        post({ ajax: 'notify_get' }).then(function (d) { if (d.success) { apply(d); loaded = true; } }).catch(function () {});
    }
    window.loadNotify = function () { load(); };
    function auto() { if ($('section-notifications').classList.contains('active') && !loaded) load(); }
    auto();
    new MutationObserver(auto).observe($('section-notifications'), { attributes: true, attributeFilter: ['class'] });
})();
</script>
