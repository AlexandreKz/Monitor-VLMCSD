<?php
// ============================================
// ФАЙЛ: sections/audit.php
// ВЕРСИЯ: 2.2.0
// ДАТА: 2026-11-17
// @description: Секция «Журнал действий»: фильтры, поиск, изменения «было → стало», сводка по пользователю, активность по часам и дням, срок хранения, очистка (админ), заготовка уведомлений
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
    'count'      => __('audit_chart_count')
];
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
        <div class="audit-f">
            <label><?= __('audit_date_from') ?></label>
            <input type="date" id="auditFrom" class="form-control">
        </div>
        <div class="audit-f">
            <label><?= __('audit_date_to') ?></label>
            <input type="date" id="auditTo" class="form-control">
        </div>
    </div>

    <div class="audit-bar">
        <div class="audit-bar-left">
            <button class="btn btn-primary" id="auditRefreshBtn" style="padding: 6px 14px; font-size: 12px;">🔄 <?= __('audit_refresh') ?></button>
            <button class="btn" id="auditResetBtn" style="padding: 6px 14px; font-size: 12px;">✖ <?= __('audit_reset') ?></button>
            <button class="btn" id="auditExportBtn" style="padding: 6px 14px; font-size: 12px;">📥 <?= __('audit_export') ?></button>
            <?php if ($canAuditAdmin): ?>
            <button class="btn btn-danger" id="auditClearBtn" style="padding: 6px 14px; font-size: 12px;">🗑️ <?= __('audit_clear') ?></button>
            <?php endif; ?>
            <label class="audit-check"><input type="checkbox" id="auditAuto"> <?= __('audit_auto') ?></label>
        </div>
        <div class="audit-bar-right">
            <span id="auditFound"></span>
            <select id="auditPerPage" class="form-control" style="width: 90px;" title="<?= htmlspecialchars(__('audit_per_page')) ?>">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
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

    <div class="audit-pager">
        <button class="btn" id="auditPrev" style="padding: 5px 12px;">←</button>
        <span id="auditPageInfo"></span>
        <button class="btn" id="auditNext" style="padding: 5px 12px;">→</button>
    </div>

    <!-- Статистика (внизу страницы, сворачивается): сводка по пользователю и активность по часам и дням -->
    <div class="audit-stats-box" id="auditStatsBox">
        <div class="audit-stats-head" id="auditStatsHead" role="button" tabindex="0" aria-expanded="true">
            <span>📈 <?= __('audit_stats_title') ?></span>
            <span id="auditStatsChevron" class="audit-chevron">▾</span>
        </div>
        <div id="auditStatsBody" class="audit-stats-body">
            <div id="auditSummary" class="audit-summary" style="display: none;"></div>
            <div class="audit-chart-head">
                <span><?= __('audit_chart_title') ?></span>
                <span>
                    <button class="audit-tab active" id="auditTabHours" type="button"><?= __('audit_chart_hours') ?></button>
                    <button class="audit-tab" id="auditTabDays" type="button"><?= __('audit_chart_days') ?></button>
                </span>
            </div>
            <div id="auditChart" class="audit-chart"></div>
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

    <!-- Заготовка уведомлений (пункт 27 бэклога) -->
    <div class="audit-panel" style="opacity: 0.55;">
        <div class="audit-panel-title">🔔 <?= __('audit_notify_title') ?> <span class="update-dev-badge">🚧 <?= __('update_in_dev') ?></span></div>
        <div style="display: flex; gap: 25px; flex-wrap: wrap;">
            <label class="audit-check"><input type="checkbox" disabled> <?= __('audit_notify_failed_logins') ?></label>
            <label class="audit-check"><input type="checkbox" disabled> <?= __('audit_notify_denied') ?></label>
            <label class="audit-check"><input type="checkbox" disabled> <?= __('audit_notify_user_delete') ?></label>
        </div>
    </div>
    </div>

    <div class="audit-hint"><?= __('audit_hint') ?></div>
</div>

<style>
.audit-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 12px 15px; margin-bottom: 12px; }
.audit-f label { display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px; }
.audit-f .form-control { width: 100%; box-sizing: border-box; }
.audit-summary { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-left: 3px solid <?= $themeCSS['primary'] ?>; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; font-size: 12px; line-height: 1.7; }
.audit-summary b { color: <?= $themeCSS['primary'] ?>; }
.audit-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 10px 15px; }
.audit-bar-left, .audit-bar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.audit-check { font-size: 12px; color: #8aa0bb; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }
#auditFound { font-size: 12px; color: #8aa0bb; }
.audit-stats-box { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 12px 15px; margin-bottom: 12px; }
.audit-stats-head { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none; }
.audit-stats-head:hover { color: <?= $themeCSS['primary'] ?>; }
.audit-chevron { font-size: 14px; color: #8aa0bb; }
.audit-stats-body { margin-top: 10px; padding-top: 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; }
.audit-stats-box.collapsed .audit-stats-body { display: none; }
.audit-chart-head { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #8aa0bb; margin-bottom: 8px; }
.audit-tab { background: transparent; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; border-radius: 12px; padding: 2px 10px; font-size: 11px; cursor: pointer; opacity: 0.7; }
.audit-tab.active { opacity: 1; color: <?= $themeCSS['primary'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.audit-chart { display: flex; align-items: flex-end; gap: 3px; height: 90px; }
.audit-col { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; }
.audit-colbar { width: 100%; background: <?= $themeCSS['primary'] ?>; border-radius: 2px 2px 0 0; min-height: 1px; opacity: 0.85; }
.audit-colbar.zero { opacity: 0.15; }
.audit-collabel { font-size: 9px; color: #8aa0bb; height: 12px; line-height: 12px; white-space: nowrap; }
.audit-table-wrap { overflow-x: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; background: <?= $themeCSS['input'] ?>; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.audit-table th { text-align: left; padding: 8px 10px; background: <?= $themeCSS['card'] ?>; color: #8aa0bb; font-weight: 600; white-space: nowrap; }
.audit-table td { padding: 7px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; vertical-align: top; }
.audit-table td.audit-time, .audit-table td.audit-ip { white-space: nowrap; font-family: monospace; }
.audit-table td.audit-details { word-break: break-word; color: #8aa0bb; }
.audit-user-link { cursor: pointer; color: <?= $themeCSS['primary'] ?>; }
.audit-user-link:hover { text-decoration: underline; }
.audit-change { font-family: monospace; font-size: 11px; color: <?= $themeCSS['text'] ?>; opacity: 0.85; margin-top: 2px; }
.audit-table tr:hover td { background: <?= $themeCSS['hover'] ?>; }
.audit-table tr.audit-bad td { background: <?= $themeCSS['danger'] ?>14; }
.audit-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; white-space: nowrap; }
.audit-badge.ok { color: #2ecc71; border: 1px solid #2ecc7155; }
.audit-badge.error { color: #f39c12; border: 1px solid #f39c1255; }
.audit-badge.denied { color: #e74c3c; border: 1px solid #e74c3c55; }
.audit-pager { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 12px 0; font-size: 12px; }
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
    const $ = function (id) { return document.getElementById(id); };
    let page = 1, pages = 1, timer = null, autoTimer = null, loadedOnce = false, lastData = null, chartMode = 'hours';

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

    function renderChart() {
        const box = $('auditChart');
        box.textContent = '';
        if (!lastData) return;
        let labels = [], values = [];
        if (chartMode === 'hours') {
            values = lastData.hours;
            labels = values.map(function (v, i) { return (i < 10 ? '0' : '') + i + ':00'; });
        } else {
            labels = Object.keys(lastData.days);
            values = labels.map(function (k) { return lastData.days[k]; });
        }
        const max = Math.max.apply(null, values.concat([1]));
        values.forEach(function (v, i) {
            const col = el('div', 'audit-col');
            const bar = el('div', 'audit-colbar' + (v === 0 ? ' zero' : ''));
            bar.style.height = Math.max(v === 0 ? 1 : 3, Math.round(v / max * 68)) + 'px';
            col.title = labels[i] + ' — ' + I18N.count + ': ' + v;
            col.appendChild(bar);
            const showLabel = chartMode === 'hours' ? (i % 3 === 0) : (i === 0 || i === values.length - 1);
            col.appendChild(el('div', 'audit-collabel', showLabel ? (chartMode === 'hours' ? labels[i].slice(0, 2) : labels[i].slice(5)) : ''));
            box.appendChild(col);
        });
    }

    function renderSummary(s) {
        const box = $('auditSummary');
        box.textContent = '';
        if (!s) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        const line = function (label, value) {
            const d = el('div');
            d.appendChild(el('b', '', label + ': '));
            d.appendChild(document.createTextNode(value));
            box.appendChild(d);
        };
        line(I18N.sumTotal, s.total + ' (✅ ' + s.ok + ' · ⚠️ ' + s.error + ' · ⛔ ' + s.denied + ')');
        line(I18N.sumPeriod, (s.first || '—') + ' → ' + (s.last || '—'));
        if (s.ips.length) line(I18N.sumIps, s.ips.join(', '));
        if (s.top.length) line(I18N.sumTop, s.top.map(function (t) { return t.label + ' ×' + t.count; }).join(' · '));
    }

    function render(data) {
        lastData = data;
        fillSelect($('auditUser'), I18N.allUsers, data.users.map(function (u) { return { value: u, label: u }; }));
        fillSelect($('auditAction'), I18N.allActions, data.actions.map(function (a) { return { value: a.code, label: a.label }; }));
        renderSummary(data.summary);
        renderChart();
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
    ['auditUser', 'auditAction', 'auditResult', 'auditFrom', 'auditTo', 'auditPerPage'].forEach(function (id) { $(id).addEventListener('change', reload); });
    $('auditRefreshBtn').onclick = load;
    $('auditResetBtn').onclick = function () {
        ['auditSearch', 'auditUser', 'auditAction', 'auditResult', 'auditFrom', 'auditTo'].forEach(function (id) { $(id).value = ''; });
        reload();
    };
    $('auditExportBtn').onclick = exportCsv;
    if ($('auditClearBtn')) $('auditClearBtn').onclick = clearAudit;
    if ($('auditRetSave')) $('auditRetSave').onclick = saveRetention;
    $('auditRetSel').addEventListener('change', function () {
        $('auditRetCustom').style.display = this.value === 'custom' ? '' : 'none';
    });
    $('auditTabHours').onclick = function () { chartMode = 'hours'; this.classList.add('active'); $('auditTabDays').classList.remove('active'); renderChart(); };
    $('auditTabDays').onclick = function () { chartMode = 'days'; this.classList.add('active'); $('auditTabHours').classList.remove('active'); renderChart(); };
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

    // Сворачивание блока статистики (состояние запоминается в браузере)
    function setStatsCollapsed(c) {
        $('auditStatsBox').classList.toggle('collapsed', c);
        $('auditStatsHead').setAttribute('aria-expanded', c ? 'false' : 'true');
        $('auditStatsChevron').textContent = c ? '▸' : '▾';
        try { localStorage.setItem('vlmcAuditStatsCollapsed', c ? '1' : '0'); } catch (e) {}
    }
    $('auditStatsHead').onclick = function () { setStatsCollapsed(!$('auditStatsBox').classList.contains('collapsed')); };
    $('auditStatsHead').onkeydown = function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); this.onclick(); }
    };
    try { if (localStorage.getItem('vlmcAuditStatsCollapsed') === '1') setStatsCollapsed(true); } catch (e) {}

    window.loadAudit = function () { if (!loadedOnce) { reload(); } else { load(); } };
    if ($('section-audit').classList.contains('active')) window.loadAudit();
})();
</script>
