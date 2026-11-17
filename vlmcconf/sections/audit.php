<?php
// ============================================
// ФАЙЛ: sections/audit.php
// ВЕРСИЯ: 3.1.1
// ДАТА: 2026-11-17
// @description: Секция «Журнал действий»: фильтры, поиск, изменения «было → стало», сводка по пользователю, активность по часам и дням, срок хранения, очистка (админ), ссылка на уведомления
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'audit.php') {
    http_response_code(403);
    exit('Access denied');
}

$canAuditAdmin = hasPermission($currentUserPermissions, PERM_ADMIN_FULL);

$auditI18n = [
    'found'      => __('audit_found'),
    'empty'      => __('audit_empty'),
    'page'       => __('audit_page'),
    'loadError'  => __('audit_load_error'),
    'allUsers'   => __('audit_all_users'),
    'allActions' => __('audit_all_actions'),
    'ok'         => __('audit_result_ok'),
    'error'      => __('audit_result_error'),
    'denied'     => __('audit_result_denied'),
    'stats'      => __('audit_stats'),
    'days'       => __('audit_days_short'),
    'clearConfirm' => __('audit_clear_confirm'),
    'cleared'    => __('audit_cleared'),
    'saved'      => __('audit_retention_saved'),
    'badDays'    => __('audit_retention_bad'),
    'saveError'  => __('msg_save_error'),
    'sumTotal'   => __('audit_sum_total'),
    'sumPeriod'  => __('audit_sum_period'),
    'sumIps'     => __('audit_sum_ips'),
    'sumTop'     => __('audit_sum_top'),
    'filterUser' => __('audit_filter_by_user'),
    'count'      => __('audit_chart_count'),
    'today'      => __('audit_range_today'),
    'days7'      => __('audit_range_7'),
    'days30'     => __('audit_range_30'),
    'all'        => __('audit_range_all'),
    'placeholder' => __('audit_range_placeholder')
];
$auditLang = (($config['language'] ?? 'ru') === 'en') ? 'en' : 'ru';
?>

<div id="section-audit" class="settings-section <?= $activeSection === 'audit' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📜 <?= __('audit_title') ?></span>
    </div>

    <div class="audit-filters">
        <div class="audit-f">
            <label><?= __('audit_search_label') ?></label>
            <input type="text" id="auditSearch" class="form-control" placeholder="<?= htmlspecialchars(__('audit_search')) ?>" autocomplete="off">
        </div>
        <div class="audit-f">
            <label><?= __('audit_col_user') ?></label>
            <select id="auditUser" class="form-control"><option value=""><?= __('audit_all_users') ?></option></select>
        </div>
        <div class="audit-f">
            <label><?= __('audit_col_action') ?></label>
            <select id="auditAction" class="form-control"><option value=""><?= __('audit_all_actions') ?></option></select>
        </div>
        <div class="audit-f">
            <label><?= __('audit_col_result') ?></label>
            <select id="auditResult" class="form-control">
                <option value=""><?= __('audit_all_results') ?></option>
                <option value="ok"><?= __('audit_result_ok') ?></option>
                <option value="error"><?= __('audit_result_error') ?></option>
                <option value="denied"><?= __('audit_result_denied') ?></option>
            </select>
        </div>
        <div class="audit-f audit-range-wrap">
            <label><?= __('audit_range_label') ?></label>
            <div class="audit-range" id="auditRange">
                <input type="text" id="auditRangeInput" class="form-control" readonly autocomplete="off" placeholder="<?= htmlspecialchars(__('audit_range_placeholder')) ?>">
                <div class="audit-cal" id="auditCal" style="display: none;"></div>
            </div>
            <input type="hidden" id="auditFrom">
            <input type="hidden" id="auditTo">
        </div>
        <div class="audit-f audit-f-reset">
            <label style="visibility: hidden;">&nbsp;</label>
            <button class="audit-flat" id="auditResetBtn" type="button">✖ <?= __('audit_reset') ?></button>
        </div>
    </div>

    <div class="audit-bar">
        <div class="audit-bar-left">
            <button class="btn btn-primary" id="auditRefreshBtn" style="padding: 6px 14px; font-size: 12px;">🔄 <?= __('audit_refresh') ?></button>
            <label class="audit-check"><input type="checkbox" id="auditAuto"> <?= __('audit_auto') ?></label>
        </div>
        <div class="audit-bar-right">
            <span id="auditFound"></span>
            <?php if ($canAuditAdmin): ?>
            <button class="btn btn-danger" id="auditClearBtn" style="padding: 6px 14px; font-size: 12px;">🗑️ <?= __('audit_clear') ?></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th><?= __('audit_col_time') ?></th>
                    <th><?= __('audit_col_user') ?></th>
                    <th><?= __('audit_col_ip') ?></th>
                    <th><?= __('audit_col_action') ?></th>
                    <th><?= __('audit_col_details') ?></th>
                    <th><?= __('audit_col_result') ?></th>
                </tr>
            </thead>
            <tbody id="auditBody"></tbody>
        </table>
    </div>

    <div class="audit-pager-row">
        <div class="audit-pager-left">
            <button class="audit-flat" id="auditExportBtn" type="button">📥 <?= __('audit_export') ?></button>
        </div>
        <div class="audit-pager">
            <button class="btn" id="auditPrev" style="padding: 5px 12px;">←</button>
            <span id="auditPageInfo"></span>
            <button class="btn" id="auditNext" style="padding: 5px 12px;">→</button>
        </div>
        <div class="audit-pager-right">
            <select id="auditPerPage" class="form-control" style="width: 90px;" title="<?= htmlspecialchars(__('audit_per_page')) ?>">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
        </div>
    </div>

    <!-- Срок хранения и уведомления: в одну строку -->
    <div class="audit-row">
    <!-- Срок хранения -->
    <div class="audit-panel">
        <div class="audit-panel-title">🗓️ <?= __('audit_retention_title') ?></div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select id="auditRetSel" class="form-control" style="width: 190px;" <?= $canAuditAdmin ? '' : 'disabled' ?>>
                <option value="30"><?= sprintf(__('audit_retention_days'), 30) ?></option>
                <option value="90"><?= sprintf(__('audit_retention_days'), 90) ?></option>
                <option value="180"><?= sprintf(__('audit_retention_days'), 180) ?></option>
                <option value="custom"><?= __('audit_retention_custom') ?></option>
            </select>
            <input type="number" id="auditRetCustom" class="form-control" style="width: 110px; display: none;" min="1" max="365" step="1" placeholder="1–365" <?= $canAuditAdmin ? '' : 'disabled' ?>>
            <?php if ($canAuditAdmin): ?>
            <button class="btn btn-primary" id="auditRetSave" style="padding: 6px 14px; font-size: 12px;">💾 <?= __('save') ?></button>
            <?php endif; ?>
            <span id="auditRetMsg" style="font-size: 12px;"></span>
        </div>
        <div id="auditStatsLine" class="audit-hint" style="margin-top: 8px;"></div>
        <div class="audit-hint"><?= __('audit_retention_hint') ?></div>
    </div>

    <!-- Уведомления журнала: настройка в разделе «Уведомления» (пункт 27) -->
    <div class="audit-panel">
        <div class="audit-panel-title">🔔 <?= __('audit_notify_title') ?></div>
        <div class="audit-hint"><?= __('notify_open_hint') ?></div>
        <?php if ($canAuditAdmin): ?>
        <div style="margin-top: 8px;"><button type="button" class="btn" style="height: 30px; padding: 0 16px; font-size: 12px;" onclick="var m=document.querySelector('.menu-item[onclick*=&quot;notifications&quot;]'); if (m) m.click();">🔔 <?= __('notify_open_settings') ?></button></div>
        <?php endif; ?>
    </div>
    </div>

</div>

<style>
.audit-filters { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) auto; gap: 10px; align-items: end; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; }
.audit-f label { display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px; }
.audit-f .form-control { width: 100%; box-sizing: border-box; }
.audit-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 10px 15px; }
.audit-bar-left, .audit-bar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.audit-check { font-size: 12px; color: #8aa0bb; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }
#auditFound { font-size: 12px; color: #8aa0bb; }
.audit-table-wrap { overflow: auto; max-height: clamp(220px, 46vh, 560px); border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; background: <?= $themeCSS['input'] ?>; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.audit-table th { position: sticky; top: 0; z-index: 1; text-align: left; padding: 8px 10px; background: <?= $themeCSS['card'] ?>; color: #8aa0bb; font-weight: 600; white-space: nowrap; }
.audit-table td { padding: 7px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; vertical-align: top; }
.audit-table td.audit-time, .audit-table td.audit-ip { white-space: nowrap; font-family: monospace; }
.audit-table td.audit-details { word-break: break-word; overflow-wrap: anywhere; color: #8aa0bb; min-width: 260px; }
.audit-user-link { cursor: pointer; color: <?= $themeCSS['primary'] ?>; }
.audit-user-link:hover { text-decoration: underline; }
.audit-change { font-family: monospace; font-size: 11px; color: <?= $themeCSS['text'] ?>; opacity: 0.85; margin-top: 2px; }
.audit-table tr:hover td { background: <?= $themeCSS['hover'] ?>; }
.audit-table tr.audit-bad td { background: <?= $themeCSS['danger'] ?>14; }
.audit-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; white-space: nowrap; }
.audit-badge.ok { color: #2ecc71; border: 1px solid #2ecc7155; }
.audit-badge.error { color: #f39c12; border: 1px solid #f39c1255; }
.audit-badge.denied { color: #e74c3c; border: 1px solid #e74c3c55; }
.audit-pager-row { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 12px; margin: 10px 0 12px; }
.audit-pager-left { justify-self: start; }
.audit-pager-right { justify-self: end; }
.audit-pager { display: flex; justify-content: center; align-items: center; gap: 12px; font-size: 12px; }
.audit-f-reset { display: flex; flex-direction: column; justify-content: flex-end; }
.audit-flat { box-sizing: border-box; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 0 16px; height: 30px; font-size: 12px; line-height: 28px; cursor: pointer; white-space: nowrap; font-family: inherit; transition: border-color .15s, color .15s; }
.audit-flat:hover { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['primary'] ?>; }

.audit-range { position: relative; }
.audit-range .form-control { cursor: pointer; width: 100%; box-sizing: border-box; }
.audit-cal { position: absolute; top: calc(100% + 6px); left: 0; z-index: 60; width: 268px; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.35); color: <?= $themeCSS['text'] ?>; }
.audit-cal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13px; font-weight: 600; text-transform: capitalize; }
.audit-cal-nav { background: transparent; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; line-height: 1; }
.audit-cal-nav:hover { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['primary'] ?>; }
.audit-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.audit-cal-wd { font-size: 10px; color: #8aa0bb; text-align: center; padding: 2px 0; text-transform: uppercase; }
.audit-cal-day { background: transparent; border: 1px solid transparent; color: <?= $themeCSS['text'] ?>; border-radius: 6px; height: 28px; font-size: 12px; cursor: pointer; padding: 0; }
.audit-cal-day:hover { background: <?= $themeCSS['hover'] ?>; }
.audit-cal-day.other { opacity: 0.35; }
.audit-cal-day.today { border-color: <?= $themeCSS['primary'] ?>; }
.audit-cal-day.in-range { background: <?= $themeCSS['primary'] ?>33; border-radius: 0; }
.audit-cal-day.edge { background: <?= $themeCSS['primary'] ?>; color: #fff; border-radius: 6px; }
.audit-cal-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; padding-top: 8px; border-top: 1px solid <?= $themeCSS['border'] ?>; }
.audit-cal-preset { background: transparent; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; border-radius: 12px; padding: 2px 10px; font-size: 11px; cursor: pointer; }
.audit-cal-preset:hover { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['primary'] ?>; }
.audit-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; align-items: stretch; }
.audit-row .audit-panel { margin-bottom: 0; }
@media (max-width: 900px) { .audit-row { grid-template-columns: 1fr; } }
.audit-panel { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 12px 15px; margin-bottom: 12px; }
.audit-panel-title { font-size: 12px; font-weight: 600; margin-bottom: 8px; }
.audit-hint { font-size: 11px; color: #8aa0bb; }
.audit-empty { text-align: center; padding: 24px; color: #8aa0bb; }
</style>

<script>
(function () {
    const I18N = <?= json_encode($auditI18n, JSON_UNESCAPED_UNICODE) ?>;
    const LANG = <?= json_encode($auditLang) ?>;
    const $ = function (id) { return document.getElementById(id); };
    let page = 1, pages = 1, timer = null, autoTimer = null, loadedOnce = false;

    function post(params) {
        const fd = new FormData();
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }

    function filters() {
        return {
            q: $('auditSearch').value.trim(),
            user: $('auditUser').value,
            action: $('auditAction').value,
            result: $('auditResult').value,
            from: $('auditFrom').value,
            to: $('auditTo').value
        };
    }

    function fillSelect(sel, firstLabel, values) {
        const cur = sel.value;
        sel.textContent = '';
        sel.appendChild(Object.assign(el('option', '', firstLabel), { value: '' }));
        values.forEach(function (v) { sel.appendChild(Object.assign(el('option', '', v.label), { value: v.value })); });
        sel.value = cur;
    }

    // --- Выбор периода: один календарь на «с» и «по» (оформление берётся из темы панели) ---
    const cal = { view: new Date(), start: '', end: '', picking: false };
    const pad2 = function (n) { return (n < 10 ? '0' : '') + n; };
    const isoDate = function (d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); };
    const fromIso = function (s) { const p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };

    function setRange(a, b, apply) {
        cal.start = a; cal.end = b;
        $('auditFrom').value = a;
        $('auditTo').value = b;
        $('auditRangeInput').value = a ? (a + ' — ' + (b || '…')) : '';
        if (apply) reload();
    }

    function calOpen() { return $('auditCal').style.display !== 'none'; }
    function closeCal() {
        if (!calOpen()) return;
        if (cal.picking && cal.start) { setRange(cal.start, cal.start, true); }   // выбран один день
        cal.picking = false;
        $('auditCal').style.display = 'none';
    }
    function openCal() {
        cal.picking = false;
        cal.view = cal.start ? fromIso(cal.start) : new Date();
        cal.view.setDate(1);
        $('auditCal').style.display = 'block';
        renderCal();
    }

    function pickDay(d) {
        if (!cal.picking) {
            cal.picking = true;
            setRange(d, '', false);
            renderCal();
        } else {
            let a = cal.start, b = d;
            if (b < a) { const t = a; a = b; b = t; }
            cal.picking = false;
            setRange(a, b, true);
            $('auditCal').style.display = 'none';
        }
    }

    function preset(days) {
        const end = new Date(), start = new Date();
        start.setDate(start.getDate() - (days - 1));
        cal.picking = false;
        setRange(isoDate(start), isoDate(end), true);
        $('auditCal').style.display = 'none';
    }

    function renderCal() {
        const box = $('auditCal');
        box.textContent = '';
        const head = el('div', 'audit-cal-head');
        const prev = el('button', 'audit-cal-nav', '‹'); prev.type = 'button';
        const next = el('button', 'audit-cal-nav', '›'); next.type = 'button';
        prev.onclick = function (e) { e.stopPropagation(); cal.view.setMonth(cal.view.getMonth() - 1); renderCal(); };
        next.onclick = function (e) { e.stopPropagation(); cal.view.setMonth(cal.view.getMonth() + 1); renderCal(); };
        head.appendChild(prev);
        head.appendChild(el('span', '', new Intl.DateTimeFormat(LANG, { month: 'long', year: 'numeric' }).format(cal.view)));
        head.appendChild(next);
        box.appendChild(head);

        const grid = el('div', 'audit-cal-grid');
        for (let i = 0; i < 7; i++) {   // неделя с понедельника (1 января 2024 — понедельник)
            grid.appendChild(el('div', 'audit-cal-wd', new Intl.DateTimeFormat(LANG, { weekday: 'short' }).format(new Date(2024, 0, 1 + i))));
        }
        const first = new Date(cal.view.getFullYear(), cal.view.getMonth(), 1);
        const offset = (first.getDay() + 6) % 7;
        const today = isoDate(new Date());
        for (let i = 0; i < 42; i++) {
            const d = new Date(first.getFullYear(), first.getMonth(), 1 - offset + i);
            const iso = isoDate(d);
            let cls = 'audit-cal-day';
            if (d.getMonth() !== first.getMonth()) cls += ' other';
            if (iso === today) cls += ' today';
            const hi = cal.end || cal.start;
            if (cal.start && iso === cal.start || hi && iso === hi) cls += ' edge';
            else if (cal.start && cal.end && iso > cal.start && iso < cal.end) cls += ' in-range';
            const b = el('button', cls, String(d.getDate()));
            b.type = 'button';
            b.onclick = function (e) { e.stopPropagation(); pickDay(iso); };
            grid.appendChild(b);
        }
        box.appendChild(grid);

        const presets = el('div', 'audit-cal-presets');
        [[I18N.today, 1], [I18N.days7, 7], [I18N.days30, 30]].forEach(function (p) {
            const b = el('button', 'audit-cal-preset', p[0]); b.type = 'button';
            b.onclick = function (e) { e.stopPropagation(); preset(p[1]); };
            presets.appendChild(b);
        });
        const all = el('button', 'audit-cal-preset', I18N.all); all.type = 'button';
        all.onclick = function (e) { e.stopPropagation(); cal.picking = false; setRange('', '', true); $('auditCal').style.display = 'none'; };
        presets.appendChild(all);
        box.appendChild(presets);
    }

    $('auditRangeInput').addEventListener('click', function (e) { e.stopPropagation(); calOpen() ? closeCal() : openCal(); });
    $('auditCal').addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', closeCal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCal(); });

    // Окно записей занимает всё свободное место: весь раздел (таблица, страницы, срок хранения,
    // уведомления) помещается в видимую область .settings-content без общей прокрутки
    function fitTable() {
        const wrap = document.querySelector('#section-audit .audit-table-wrap');
        const sec = $('section-audit');
        if (!wrap || !sec || wrap.offsetParent === null) return;
        const cont = sec.closest('.settings-content');
        if (!cont) return;
        const wr = wrap.getBoundingClientRect();
        const top = wr.top - cont.getBoundingClientRect().top + cont.scrollTop;
        const below = sec.lastElementChild.getBoundingClientRect().bottom - wr.bottom;   // всё, что ниже таблицы
        const pad = parseFloat(getComputedStyle(cont).paddingBottom) || 0;
        const h = cont.clientHeight - top - below - pad - 4;
        wrap.style.maxHeight = 'none';
        wrap.style.height = Math.max(140, Math.floor(h)) + 'px';
    }
    window.addEventListener('resize', fitTable);
    window.fitAuditTable = fitTable;

    function render(data) {
        fillSelect($('auditUser'), I18N.allUsers, data.users.map(function (u) { return { value: u, label: u }; }));
        fillSelect($('auditAction'), I18N.allActions, data.actions.map(function (a) { return { value: a.code, label: a.label }; }));
        const body = $('auditBody');
        body.textContent = '';
        if (!data.items.length) {
            const tr = el('tr'); const td = el('td', 'audit-empty', I18N.empty);
            td.colSpan = 6; tr.appendChild(td); body.appendChild(tr);
        }
        data.items.forEach(function (it) {
            const tr = el('tr');
            if (it.result !== 'ok' || it.action === 'login_failed') tr.className = 'audit-bad';
            tr.appendChild(el('td', 'audit-time', it.time));
            const tdu = el('td');
            if (it.user) {
                const a = el('span', 'audit-user-link', it.user);
                a.title = I18N.filterUser;
                a.onclick = function () { $('auditUser').value = it.user; reload(); };
                tdu.appendChild(a);
            } else tdu.textContent = '—';
            tr.appendChild(tdu);
            tr.appendChild(el('td', 'audit-ip', it.ip));
            tr.appendChild(el('td', '', it.label));
            const tdd = el('td', 'audit-details');
            if (it.details) tdd.appendChild(el('div', '', it.details));
            it.changes.forEach(function (c) { tdd.appendChild(el('div', 'audit-change', c)); });
            tr.appendChild(tdd);
            const tdr = el('td');
            tdr.appendChild(el('span', 'audit-badge ' + it.result, I18N[it.result] || it.result));
            tr.appendChild(tdr);
            body.appendChild(tr);
        });
        pages = data.pages; page = data.page;
        $('auditFound').textContent = I18N.found.replace('%d', data.total);
        $('auditPageInfo').textContent = I18N.page.replace('%d', data.page).replace('%d', data.pages);
        $('auditPrev').disabled = page <= 1;
        $('auditNext').disabled = page >= pages;
        renderStats(data.stats);
        setTimeout(fitTable, 0);
    }

    function renderStats(st) {
        if (!st) return;
        const kb = st.size < 1048576 ? Math.max(1, Math.round(st.size / 1024)) + ' KB' : (st.size / 1048576).toFixed(1) + ' MB';
        $('auditStatsLine').textContent = I18N.stats.replace('%d', st.records).replace('%s', kb).replace('%d', st.retention_days);
        const sel = $('auditRetSel'), inp = $('auditRetCustom');
        if (document.activeElement === sel || document.activeElement === inp) return;
        if ([30, 90, 180].indexOf(st.retention_days) >= 0) {
            sel.value = String(st.retention_days);
            inp.style.display = 'none';
        } else {
            sel.value = 'custom';
            inp.value = st.retention_days;
            inp.style.display = '';
        }
    }

    function load() {
        const f = filters();
        f.ajax = 'get_audit';
        f.page = page;
        f.per_page = $('auditPerPage').value;
        loadedOnce = true;
        post(f).then(function (data) {
            if (!data.success) throw new Error('fail');
            render(data);
        }).catch(function () { $('auditFound').textContent = I18N.loadError; });
    }

    function reload() { page = 1; load(); }

    function exportCsv() {
        const f = filters();
        f.ajax = 'export_audit';
        post(f).then(function (data) {
            if (!data.success) throw new Error('fail');
            const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'audit-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        }).catch(function () { $('auditFound').textContent = I18N.loadError; });
    }

    function retMsg(text, ok) {
        const m = $('auditRetMsg');
        m.style.color = ok ? '#2ecc71' : '#e74c3c';
        m.textContent = text;
        setTimeout(function () { m.textContent = ''; }, 4000);
    }

    function saveRetention() {
        const sel = $('auditRetSel').value;
        const days = sel === 'custom' ? parseInt($('auditRetCustom').value, 10) : parseInt(sel, 10);
        if (!(days >= 1 && days <= 365)) { retMsg(I18N.badDays, false); return; }
        post({ ajax: 'save_audit_settings', retention_days: days }).then(function (d) {
            retMsg(d.success ? I18N.saved : (d.message || I18N.saveError), !!d.success);
            if (d.success) load();
        }).catch(function () { retMsg(I18N.saveError, false); });
    }

    function clearAudit() {
        if (!window.confirm(I18N.clearConfirm)) return;
        post({ ajax: 'clear_audit' }).then(function (d) {
            $('auditFound').textContent = d.success ? I18N.cleared : (d.message || I18N.saveError);
            if (d.success) reload();
        }).catch(function () { $('auditFound').textContent = I18N.saveError; });
    }

    $('auditSearch').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(reload, 300); });
    ['auditUser', 'auditAction', 'auditResult', 'auditPerPage'].forEach(function (id) { $(id).addEventListener('change', reload); });
    $('auditRefreshBtn').onclick = load;
    $('auditResetBtn').onclick = function () {
        ['auditSearch', 'auditUser', 'auditAction', 'auditResult'].forEach(function (id) { $(id).value = ''; });
        cal.picking = false;
        setRange('', '', false);
        reload();
    };
    $('auditExportBtn').onclick = exportCsv;
    if ($('auditClearBtn')) $('auditClearBtn').onclick = clearAudit;
    if ($('auditRetSave')) $('auditRetSave').onclick = saveRetention;
    $('auditRetSel').addEventListener('change', function () {
        $('auditRetCustom').style.display = this.value === 'custom' ? '' : 'none';
    });
    $('auditPrev').onclick = function () { if (page > 1) { page--; load(); } };
    $('auditNext').onclick = function () { if (page < pages) { page++; load(); } };
    $('auditAuto').addEventListener('change', function () {
        clearInterval(autoTimer);
        if (this.checked) {
            autoTimer = setInterval(function () {
                if ($('section-audit').classList.contains('active')) load();
            }, 15000);
        }
    });

    window.loadAudit = function () {
        setTimeout(fitTable, 0); if (!loadedOnce) { reload(); } else { load(); } };
    // Раздел может стать активным уже после выполнения этого скрипта (например, при восстановлении вкладки после загрузки страницы)
    function autoLoad() { if ($('section-audit').classList.contains('active') && !loadedOnce) window.loadAudit(); }
    autoLoad();
    new MutationObserver(autoLoad).observe($('section-audit'), { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('load', autoLoad);
})();
</script>
