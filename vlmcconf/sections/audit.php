<?php
// ============================================
// ФАЙЛ: sections/audit.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Секция «Журнал действий»: кто, что и когда делал в панели; фильтры по пользователю, действию, результату, датам; поиск; экспорт CSV
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'audit.php') {
    http_response_code(403);
    exit('Access denied');
}

$auditI18n = [
    'found'      => __('audit_found'),
    'empty'      => __('audit_empty'),
    'page'       => __('audit_page'),
    'loadError'  => __('audit_load_error'),
    'allUsers'   => __('audit_all_users'),
    'allActions' => __('audit_all_actions'),
    'ok'         => __('audit_result_ok'),
    'error'      => __('audit_result_error'),
    'denied'     => __('audit_result_denied')
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

    <div class="audit-hint"><?= __('audit_hint') ?></div>
</div>

<style>
.audit-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; background: <?= $themeCSS['input'] ?>; border-radius: 6px; padding: 12px 15px; margin-bottom: 12px; }
.audit-f label { display: block; font-size: 11px; color: #8aa0bb; margin-bottom: 4px; }
.audit-f .form-control { width: 100%; box-sizing: border-box; }
.audit-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
.audit-bar-left, .audit-bar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.audit-check { font-size: 12px; color: #8aa0bb; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }
#auditFound { font-size: 12px; color: #8aa0bb; }
.audit-table-wrap { overflow-x: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.audit-table th { text-align: left; padding: 8px 10px; background: <?= $themeCSS['input'] ?>; color: #8aa0bb; font-weight: 600; white-space: nowrap; }
.audit-table td { padding: 7px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; vertical-align: top; }
.audit-table td.audit-time, .audit-table td.audit-ip { white-space: nowrap; font-family: monospace; }
.audit-table td.audit-details { word-break: break-word; color: #8aa0bb; }
.audit-table tr:hover td { background: <?= $themeCSS['hover'] ?>; }
.audit-table tr.audit-bad td { background: <?= $themeCSS['danger'] ?>14; }
.audit-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; white-space: nowrap; }
.audit-badge.ok { color: #2ecc71; border: 1px solid #2ecc7155; }
.audit-badge.error { color: #f39c12; border: 1px solid #f39c1255; }
.audit-badge.denied { color: #e74c3c; border: 1px solid #e74c3c55; }
.audit-pager { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 12px 0; font-size: 12px; }
.audit-hint { font-size: 11px; color: #8aa0bb; }
.audit-empty { text-align: center; padding: 24px; color: #8aa0bb; }
</style>

<script>
(function () {
    const I18N = <?= json_encode($auditI18n, JSON_UNESCAPED_UNICODE) ?>;
    const $ = function (id) { return document.getElementById(id); };
    let page = 1, pages = 1, timer = null, autoTimer = null, loadedOnce = false;

    function post(params) {
        const fd = new FormData();
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
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
        const o0 = document.createElement('option');
        o0.value = ''; o0.textContent = firstLabel;
        sel.appendChild(o0);
        values.forEach(function (v) {
            const o = document.createElement('option');
            o.value = v.value; o.textContent = v.label;
            sel.appendChild(o);
        });
        sel.value = cur;
    }

    function render(data) {
        fillSelect($('auditUser'), I18N.allUsers, data.users.map(function (u) { return { value: u, label: u }; }));
        fillSelect($('auditAction'), I18N.allActions, data.actions.map(function (a) { return { value: a.code, label: a.label }; }));
        const body = $('auditBody');
        body.textContent = '';
        if (!data.items.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6; td.className = 'audit-empty'; td.textContent = I18N.empty;
            tr.appendChild(td); body.appendChild(tr);
        }
        data.items.forEach(function (it) {
            const tr = document.createElement('tr');
            if (it.result !== 'ok' || it.action === 'login_failed') tr.className = 'audit-bad';
            [['audit-time', it.time], ['', it.user || '—'], ['audit-ip', it.ip], ['', it.label], ['audit-details', it.details]].forEach(function (c) {
                const td = document.createElement('td');
                if (c[0]) td.className = c[0];
                td.textContent = c[1];
                tr.appendChild(td);
            });
            const tdr = document.createElement('td');
            const b = document.createElement('span');
            b.className = 'audit-badge ' + it.result;
            b.textContent = I18N[it.result] || it.result;
            tdr.appendChild(b); tr.appendChild(tdr);
            body.appendChild(tr);
        });
        pages = data.pages; page = data.page;
        $('auditFound').textContent = I18N.found.replace('%d', data.total);
        $('auditPageInfo').textContent = I18N.page.replace('%d', data.page).replace('%d', data.pages);
        $('auditPrev').disabled = page <= 1;
        $('auditNext').disabled = page >= pages;
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
        }).catch(function () {
            $('auditFound').textContent = I18N.loadError;
        });
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

    $('auditSearch').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(reload, 300); });
    ['auditUser', 'auditAction', 'auditResult', 'auditFrom', 'auditTo', 'auditPerPage'].forEach(function (id) { $(id).addEventListener('change', reload); });
    $('auditRefreshBtn').onclick = load;
    $('auditResetBtn').onclick = function () {
        ['auditSearch', 'auditUser', 'auditAction', 'auditResult', 'auditFrom', 'auditTo'].forEach(function (id) { $(id).value = ''; });
        reload();
    };
    $('auditExportBtn').onclick = exportCsv;
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

    window.loadAudit = function () { if (!loadedOnce) { reload(); } else { load(); } };
    if ($('section-audit').classList.contains('active')) window.loadAudit();
})();
</script>
