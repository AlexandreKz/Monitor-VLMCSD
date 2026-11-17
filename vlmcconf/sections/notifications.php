<?php
// ============================================
// ФАЙЛ: sections/notifications.php
// ВЕРСИЯ: 1.1.0
// ДАТА: 2026-11-17
// @description: Раздел «Уведомления» (пункт 27): каналы Email/Telegram, события журнала и обновлений, правила (пороги, антиспам, тихие часы), тест и история отправок
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}
require_once __DIR__ . '/../vlmcinc/notify.php';

$ntfCatalog = [];
foreach (notifyCatalog() as $id => $e) {
    $ntfCatalog[] = ['id' => $id, 'sev' => $e['sev'], 'src' => $e['src'], 'name' => __('notify_ev_' . $id)];
}
$ntfI18n = [
    'sevHigh' => __('notify_sev_high'), 'sevMid' => __('notify_sev_mid'), 'sevLow' => __('notify_sev_low'),
    'srcAudit' => __('notify_src_audit'), 'srcUpdate' => __('notify_src_update'),
    'ok' => __('notify_ok'), 'fail' => __('notify_fail'), 'empty' => __('notify_history_empty'),
    'secretSet' => __('notify_secret_set'), 'saveError' => __('msg_save_error'),
    'on' => __('notify_enabled'), 'off' => __('notify_disabled')
];
?>
<div id="section-notifications" class="settings-section <?= $activeSection === 'notifications' ? 'active' : '' ?>">
    <div class="section-title">
        <span>🔔 <?= __('notify_title') ?></span>
        <div class="ntf-tabs">
            <button type="button" class="ntf-tab active" data-p="ntfCh">📡 <?= __('notify_tab_channels') ?></button>
            <button type="button" class="ntf-tab" data-p="ntfEv">📋 <?= __('notify_tab_events') ?></button>
            <button type="button" class="ntf-tab" data-p="ntfRl">⚙️ <?= __('notify_tab_rules') ?></button>
        </div>
    </div>

    <div id="ntfCh" class="ntf-page active">
        <div class="ntf-blk">
            <h3>✉️ <?= __('notify_email') ?> <span id="ntfMailBadge" class="ntf-badge"></span></h3>
            <label class="ntf-chk"><input type="checkbox" id="mailOn"> <?= __('update_notify_enable') ?></label>
            <div class="ntf-grid">
                <div><label><?= __('notify_host') ?></label><input type="text" id="mailHost" class="form-control" placeholder="smtp.example.com" autocomplete="off"></div>
                <div><label><?= __('notify_port') ?></label><input type="number" id="mailPort" class="form-control ntf-num" min="1" max="65535"></div>
                <div><label><?= __('notify_secure') ?></label>
                    <select id="mailSecure" class="form-control">
                        <option value="tls"><?= __('notify_secure_tls') ?></option>
                        <option value="ssl"><?= __('notify_secure_ssl') ?></option>
                        <option value="none"><?= __('notify_secure_none') ?></option>
                    </select></div>
                <div><label><?= __('notify_user') ?></label><input type="text" id="mailUser" class="form-control" autocomplete="off"></div>
                <div><label><?= __('notify_pass') ?></label><input type="password" id="mailPass" class="form-control" autocomplete="new-password"></div>
                <div><label><?= __('notify_from') ?></label><input type="text" id="mailFrom" class="form-control" placeholder="KMS Monitor &lt;monitor@example.com&gt;" autocomplete="off"></div>
            </div>
            <div style="margin-top: 10px;"><label><?= __('notify_to') ?></label><input type="text" id="mailTo" class="form-control" placeholder="admin@example.com, security@example.com" autocomplete="off"></div>
            <div class="ntf-row">
                <button type="button" class="btn btn-primary ntf-btn" data-save>💾 <?= __('save') ?></button>
                <button type="button" class="ntf-flat" data-test="mail">✉️ <?= __('notify_test_mail') ?></button>
                <span class="ntf-msg"></span>
                <span class="ntf-hint"><?= __('notify_secret_hint') ?></span>
            </div>
        </div>

        <div class="ntf-blk">
            <h3>✈️ <?= __('notify_telegram') ?> <span id="ntfTgBadge" class="ntf-badge"></span></h3>
            <label class="ntf-chk"><input type="checkbox" id="tgOn"> <?= __('update_notify_enable') ?></label>
            <div class="ntf-grid">
                <div><label><?= __('notify_token') ?></label><input type="password" id="tgToken" class="form-control" placeholder="123456:ABC…" autocomplete="new-password"></div>
                <div><label><?= __('notify_chat') ?></label><input type="text" id="tgChat" class="form-control" placeholder="-1001234567890" autocomplete="off"></div>
            </div>
            <div class="ntf-row">
                <button type="button" class="btn btn-primary ntf-btn" data-save>💾 <?= __('save') ?></button>
                <button type="button" class="ntf-flat" data-test="tg">✈️ <?= __('notify_test_tg') ?></button>
                <span class="ntf-msg"></span>
            </div>
        </div>
    </div>

    <div id="ntfEv" class="ntf-page">
        <div class="ntf-blk">
            <table class="ntf-table" id="ntfEvTable"></table>
            <div class="ntf-hint" style="margin-top: 10px;">ℹ️ <?= __('notify_future') ?></div>
            <div class="ntf-row"><button type="button" class="btn btn-primary ntf-btn" data-save>💾 <?= __('save') ?></button><span class="ntf-msg"></span></div>
        </div>
    </div>

    <div id="ntfRl" class="ntf-page">
        <div class="ntf-two">
            <div class="ntf-blk">
                <h3>🔐 <?= __('notify_rules_thresholds') ?></h3>
                <div class="ntf-fields">
                    <div><label><?= __('notify_fail_count') ?></label><input type="number" id="rFailN" class="form-control ntf-num ntf-w" min="2" max="100"></div>
                    <div><label><?= __('notify_fail_minutes') ?></label><input type="number" id="rFailM" class="form-control ntf-num ntf-w" min="1" max="1440"></div>
                </div>
            </div>
            <div class="ntf-blk">
                <h3>🛡️ <?= __('notify_rules_spam') ?></h3>
                <div class="ntf-fields">
                    <div><label><?= __('notify_antispam') ?></label><input type="number" id="rSpam" class="form-control ntf-num ntf-w" min="0" max="1440"></div>
                </div>
                <label class="ntf-chk" style="margin-top: 12px;"><input type="checkbox" id="rQuiet"> <?= __('notify_quiet') ?></label>
                <div class="ntf-row" style="margin-top: 6px;">
                    <span><?= __('notify_quiet_from') ?></span>
                    <select id="rQFromH" class="form-control ntf-time"></select><span>:</span><select id="rQFromM" class="form-control ntf-time"></select>
                    <span style="margin-left: 8px;"><?= __('notify_quiet_to') ?></span>
                    <select id="rQToH" class="form-control ntf-time"></select><span>:</span><select id="rQToM" class="form-control ntf-time"></select>
                </div>
            </div>
        </div>
        <div class="ntf-row" style="margin: 0 0 12px;"><button type="button" class="btn btn-primary ntf-btn" data-save>💾 <?= __('save') ?></button><span class="ntf-msg"></span></div>
        <div class="ntf-blk">
            <h3>🕘 <?= __('notify_history') ?></h3>
            <table class="ntf-table" id="ntfHist"></table>
        </div>
    </div>

</div>

<style>
.ntf-tabs { display: flex; gap: 10px; margin-left: auto; flex-wrap: wrap; }
.ntf-tab { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; }
.ntf-tab:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.ntf-tab.active { opacity: 1; color: <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.ntf-page { display: none; }
.ntf-page.active { display: block; }
.ntf-blk { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 14px; margin-bottom: 12px; }
.ntf-blk h3 { margin: 0 0 10px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.ntf-badge { font-size: 11px; padding: 2px 9px; border-radius: 10px; font-weight: 500; background: <?= $themeCSS['border'] ?>; opacity: 0.85; }
.ntf-badge.on { background: <?= $themeCSS['success'] ?>; color: #fff; opacity: 1; }
.ntf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; margin-top: 10px; }
.ntf-grid label, #section-notifications .ntf-blk > div > label { display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px; }
.ntf-chk { display: inline-flex !important; align-items: center; gap: 8px; font-size: 13px !important; color: <?= $themeCSS['text'] ?> !important; cursor: pointer; margin: 0 !important; }
.ntf-row { display: flex; gap: 10px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
.ntf-btn { height: 30px; padding: 0 16px; font-size: 12px; }
.ntf-hint { font-size: 12px; color: #8aa0bb; }
.ntf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ntf-table th, .ntf-table td { padding: 8px 10px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; text-align: left; }
.ntf-table th { font-size: 12px; font-weight: 500; color: #8aa0bb; }
.ntf-table .c { text-align: center; width: 90px; }
.ntf-table .grp td { background: <?= $themeCSS['hover'] ?>; font-weight: 600; font-size: 12px; }
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
.ntf-num { -moz-appearance: textfield; appearance: textfield; }
.ntf-num::-webkit-outer-spin-button, .ntf-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.ntf-w { width: 120px; }
.ntf-fields { display: flex; gap: 16px; flex-wrap: wrap; }
.ntf-fields label { display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px; }
.ntf-time { width: 68px; padding-right: 4px; cursor: pointer; }
.ntf-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: stretch; margin-bottom: 12px; }
.ntf-two .ntf-blk { margin-bottom: 0; height: 100%; box-sizing: border-box; }
@media (max-width: 1000px) { .ntf-two { grid-template-columns: 1fr; } }
</style>

<script>
(function () {
    const CAT = <?= json_encode($ntfCatalog, JSON_UNESCAPED_UNICODE) ?>;
    const T = <?= json_encode($ntfI18n, JSON_UNESCAPED_UNICODE) ?>;
    const $ = function (id) { return document.getElementById(id); };
    let S = null, events = {}, loaded = false;

    function post(params) {
        const fd = new FormData();
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }
    // Сообщение показывается рядом с нажатой кнопкой и только на своей вкладке; само исчезает
    function clearMsgs() { document.querySelectorAll('#section-notifications .ntf-msg').forEach(function (m) { m.textContent = ''; }); }
    function msg(btn, text, ok) {
        clearMsgs();
        const row = btn && btn.parentNode, m = row && row.querySelector('.ntf-msg');
        if (!m) return;
        m.textContent = text || ''; m.className = 'ntf-msg ' + (ok ? 'ok' : 'err');
        setTimeout(function () { if (m.textContent === text) m.textContent = ''; }, ok ? 4000 : 8000);
    }
    function fillTime(hId, mId) {
        const h = $(hId), m = $(mId);
        for (let i = 0; i < 24; i++) h.appendChild(new Option(String(i).padStart(2, '0'), String(i).padStart(2, '0')));
        for (let i = 0; i < 60; i++) m.appendChild(new Option(String(i).padStart(2, '0'), String(i).padStart(2, '0')));
    }
    function setTime(hId, mId, v) { const p = String(v || '00:00').split(':'); $(hId).value = p[0]; $(mId).value = p[1]; }
    function getTime(hId, mId) { return $(hId).value + ':' + $(mId).value; }
    fillTime('rQFromH', 'rQFromM'); fillTime('rQToH', 'rQToM');
    function el(tag, cls, text) { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

    document.querySelectorAll('#section-notifications .ntf-tab').forEach(function (b) {
        b.onclick = function () {
            document.querySelectorAll('#section-notifications .ntf-tab, #section-notifications .ntf-page').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active'); $(b.dataset.p).classList.add('active'); clearMsgs();
        };
    });

    function badge(id, on) { const b = $(id); b.textContent = on ? T.on : T.off; b.className = 'ntf-badge' + (on ? ' on' : ''); }

    function fill(s) {
        S = s;
        $('mailOn').checked = !!s.email.enabled; $('mailHost').value = s.email.host; $('mailPort').value = s.email.port;
        $('mailSecure').value = s.email.secure; $('mailUser').value = s.email.user; $('mailPass').value = '';
        $('mailPass').placeholder = s.email.has_pass ? T.secretSet : '';
        $('mailFrom').value = s.email.from; $('mailTo').value = s.email.to;
        $('tgOn').checked = !!s.telegram.enabled; $('tgToken').value = ''; $('tgToken').placeholder = s.telegram.has_token ? T.secretSet : '123456:ABC…';
        $('tgChat').value = s.telegram.chat;
        badge('ntfMailBadge', s.email.enabled); badge('ntfTgBadge', s.telegram.enabled);
        const r = s.rules;
        $('rFailN').value = r.fail_count; $('rFailM').value = r.fail_minutes; $('rSpam').value = r.antispam_minutes;
        $('rQuiet').checked = !!r.quiet_enabled; setTime('rQFromH', 'rQFromM', r.quiet_from); setTime('rQToH', 'rQToM', r.quiet_to);
        events = JSON.parse(JSON.stringify(s.events));
        renderEvents();
    }

    function renderEvents() {
        const t = $('ntfEvTable'); t.textContent = '';
        const head = el('tr'); ['<?= addslashes(__('notify_event')) ?>', '<?= addslashes(__('notify_severity')) ?>', '✉️ Email', '✈️ Telegram'].forEach(function (h, i) { const th = el('th', i ? 'c' : '', h); head.appendChild(th); });
        t.appendChild(head);
        [['audit', T.srcAudit], ['update', T.srcUpdate]].forEach(function (g) {
            const rows = CAT.filter(function (c) { return c.src === g[0]; });
            if (!rows.length) return;
            const gr = el('tr', 'grp'); const gt = el('td', '', g[1]); gt.colSpan = 4; gr.appendChild(gt); t.appendChild(gr);
            rows.forEach(function (c) {
                const tr = el('tr'); tr.appendChild(el('td', '', c.name));
                const sv = el('td', 'c'); sv.appendChild(el('span', 'ntf-sev ' + c.sev, c.sev === 'high' ? T.sevHigh : (c.sev === 'mid' ? T.sevMid : T.sevLow))); tr.appendChild(sv);
                ['mail', 'tg'].forEach(function (ch) {
                    const td = el('td', 'c'); const cb = document.createElement('input'); cb.type = 'checkbox';
                    cb.checked = !!(events[c.id] && events[c.id][ch]);
                    cb.onchange = function () { events[c.id][ch] = cb.checked; };
                    td.appendChild(cb); tr.appendChild(td);
                });
                t.appendChild(tr);
            });
        });
    }

    function renderHistory(list) {
        const t = $('ntfHist'); t.textContent = '';
        if (!list || !list.length) { const tr = el('tr'); const td = el('td', 'ntf-hint', T.empty); tr.appendChild(td); t.appendChild(tr); return; }
        list.forEach(function (h) {
            const tr = el('tr'); const d = new Date(h.t * 1000);
            tr.appendChild(el('td', '', d.toLocaleString()));
            tr.appendChild(el('td', '', h.title));
            tr.appendChild(el('td', '', (h.ch || []).map(function (c) { return c === 'tg' ? '✈️' : '✉️'; }).join(' ')));
            const st = el('td', '', h.ok ? T.ok : (T.fail + (h.err ? ': ' + h.err : ''))); st.style.color = h.ok ? '' : 'var(--danger, #ef4444)';
            tr.appendChild(st); t.appendChild(tr);
        });
    }

    function form() {
        return {
            mail_on: $('mailOn').checked ? 1 : '', mail_host: $('mailHost').value, mail_port: $('mailPort').value,
            mail_secure: $('mailSecure').value, mail_user: $('mailUser').value, mail_pass: $('mailPass').value,
            mail_from: $('mailFrom').value, mail_to: $('mailTo').value,
            tg_on: $('tgOn').checked ? 1 : '', tg_token: $('tgToken').value, tg_chat: $('tgChat').value,
            events: JSON.stringify(events),
            rules: JSON.stringify({ fail_count: $('rFailN').value, fail_minutes: $('rFailM').value, antispam_minutes: $('rSpam').value,
                quiet_enabled: $('rQuiet').checked, quiet_from: getTime('rQFromH', 'rQFromM'), quiet_to: getTime('rQToH', 'rQToM') })
        };
    }

    function load() {
        post({ ajax: 'get_notify' }).then(function (d) {
            if (d.success) { fill(d.settings); renderHistory(d.history); loaded = true; }
        }).catch(function () {});
    }

    document.querySelectorAll('#section-notifications [data-save]').forEach(function (b) {
        b.onclick = function () {
            const p = form(); p.ajax = 'save_notify';
            post(p).then(function (d) {
                msg(b, d.message || (d.success ? '' : T.saveError), !!d.success);
                if (d.success) { fill(d.settings); }
            }).catch(function (e) { msg(b, T.saveError + ' (' + (e && e.message ? e.message : '?') + ')', false); });
        };
    });
    document.querySelectorAll('#section-notifications [data-test]').forEach(function (b) {
        b.onclick = function () {
            const p = form(); p.ajax = 'test_notify'; p.channel = b.dataset.test; b.disabled = true;
            post(p).then(function (d) { msg(b, d.message, !!d.success); load(); })
                .catch(function (e) { msg(b, T.saveError + ' (' + (e && e.message ? e.message : '?') + ')', false); })
                .then(function () { b.disabled = false; });
        };
    });

    window.loadNotify = function () { if (!loaded) load(); else load(); };
    function auto() { if ($('section-notifications').classList.contains('active') && !loaded) load(); }
    auto();
    new MutationObserver(auto).observe($('section-notifications'), { attributes: true, attributeFilter: ['class'] });
})();
</script>
