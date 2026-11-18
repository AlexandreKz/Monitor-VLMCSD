<?php
// ============================================
// ФАЙЛ: vlmcinc/section_ui.php
// ВЕРСИЯ: 1.3.0
// ДАТА: 2026-11-18
// @description: Общие стили и скрипты новых разделов («Доступ и защита», «API», «Интеграции»): вкладки, таблицы, сообщения
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'section_ui.php') {
    http_response_code(403);
    exit('Access denied');
}

/** Выводит общие стили и скрипт один раз за страницу. */
function vlmcSectionUi($themeCSS) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
<style>
/* Вкладки в строке заголовка раздела (как в «Инструментах») */
.sec-title { justify-content: flex-start !important; }
.sec-title > span:first-child { flex: 1 1 0; white-space: nowrap; }
.sec-tabs { display: flex; gap: 10px; flex: 1 1 0; justify-content: flex-end; flex-wrap: nowrap; }
.sec-tab-btn { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 4px 16px; font-size: 12px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; white-space: nowrap; }
.sec-tab-btn:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.sec-tab-btn.active { opacity: 1; color: <?= $themeCSS['accentText'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.sec-tab-content { display: none; animation: fadeIn 0.3s; }
.sec-tab-content.active { display: block; }

.sec-grid { display: flex; gap: 15px; align-items: flex-start; }
.sec-grid > .sec-col { min-width: 0; }
.sec-grid .settings-card { height: auto; }
.sec-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; font-size: 13px; }
.sec-row > label { min-width: 150px; color: <?= $themeCSS['muted'] ?>; font-size: 12px; }
.sec-row .form-control { flex: 1 1 200px; min-width: 120px; }
.sec-row .form-control.sec-narrow { flex: 0 0 110px; min-width: 90px; width: 110px; }
.sec-hint { margin: 6px 0 0; font-size: 11px; color: <?= $themeCSS['muted'] ?>; line-height: 1.45; }
.sec-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; border: 1px solid <?= $themeCSS['border'] ?>; white-space: nowrap; }
.sec-badge.ok { color: <?= $themeCSS['okText'] ?>; border-color: <?= $themeCSS['okText'] ?>; }
.sec-badge.err { color: <?= $themeCSS['dangerText'] ?>; border-color: <?= $themeCSS['dangerText'] ?>; }
.sec-badge.warn { color: <?= $themeCSS['warning'] ?>; border-color: <?= $themeCSS['warning'] ?>; }
.sec-msg { margin-top: 8px; font-size: 11px; padding: 3px 10px; border-radius: 12px; border: 1px solid transparent; display: none; }
.sec-msg.ok { display: inline-block; color: <?= $themeCSS['okText'] ?>; border-color: <?= $themeCSS['okText'] ?>; }
.sec-msg.err { display: inline-block; color: <?= $themeCSS['dangerText'] ?>; border-color: <?= $themeCSS['dangerText'] ?>; }
.sec-switch { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 500; margin-bottom: 4px; }
.sec-switch input { width: 16px; height: 16px; accent-color: <?= $themeCSS['primary'] ?>; cursor: pointer; }
.sec-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.sec-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; color: <?= $themeCSS['muted'] ?>; padding: 6px 8px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; white-space: nowrap; }
.sec-table td { padding: 6px 8px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; vertical-align: middle; }
.sec-table tr:last-child td { border-bottom: none; }
.sec-table .mono, .sec-mono { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; }
.sec-empty { text-align: center; padding: 18px; color: <?= $themeCSS['muted'] ?>; font-size: 12px; }
.sec-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; }
.sec-table .btn { white-space: nowrap; }
.sec-table td.mono { white-space: nowrap; }
.sec-sub-tabs { display: flex; gap: 6px; margin-bottom: 14px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; }
.sec-sub-btn { background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -1px; padding: 6px 14px; font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.65; transition: all 0.2s; }
.sec-sub-btn:hover { opacity: 1; }
.sec-sub-btn.active { opacity: 1; color: <?= $themeCSS['accentText'] ?>; border-bottom-color: <?= $themeCSS['primary'] ?>; }
.sec-sub { display: none; }
.sec-sub.active { display: block; animation: fadeIn 0.3s; }
.sec-btn { display: inline-flex; align-items: center; gap: 6px; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 500; font-family: inherit; cursor: pointer; white-space: nowrap; transition: all 0.2s; }
.sec-btn:hover { background: <?= $themeCSS['hover'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.sec-btn.primary { background: <?= $themeCSS['primary'] ?>; border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; }
.sec-btn.primary:hover { filter: brightness(1.12); transform: translateY(-1px); }
.sec-btn.danger { background: <?= $themeCSS['danger'] ?>; color: <?= $themeCSS['onDanger'] ?>; border-color: <?= $themeCSS['danger'] ?>; }
.sec-btn.danger:hover { opacity: 0.9; background: <?= $themeCSS['danger'] ?>; }
.sec-btn.sm { padding: 3px 10px; font-size: 11px; border-radius: 14px; }
.sec-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; filter: none; }
.sec-code { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 8px 10px; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all; position: relative; margin: 6px 0; }
.sec-scroll { max-height: 320px; overflow-y: auto; }
.sec-scroll.sec-tall { max-height: 360px; }   /* точная высота выставляется скриптом раздела по размеру окна */
.sec-note { border-left: 4px solid <?= $themeCSS['primary'] ?>; background: <?= $themeCSS['card'] ?>; border-radius: 6px; padding: 8px 12px; font-size: 12px; margin-bottom: 12px; line-height: 1.45; }
.sec-note.warn { border-left-color: <?= $themeCSS['warning'] ?>; }
@media (max-width: 980px) { .sec-grid { flex-direction: column; } .sec-grid > .sec-col { width: 100% !important; flex: none !important; } }
</style>
<script>
function secTab(sectionId, tab, btn) {
    var root = document.getElementById(sectionId);
    if (!root) return;
    root.querySelectorAll('.sec-tab-btn').forEach(function (b) { b.classList.remove('active'); });
    root.querySelectorAll('.sec-tab-content').forEach(function (c) { c.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    var el = root.querySelector('[data-tab="' + tab + '"]');
    if (el) el.classList.add('active');
    try { sessionStorage.setItem('vlmc_tab_' + sectionId, tab); } catch (e) {}
}
function secTabRestore(sectionId, fallback) {
    var tab = fallback;
    try { tab = sessionStorage.getItem('vlmc_tab_' + sectionId) || fallback; } catch (e) {}
    var root = document.getElementById(sectionId);
    if (!root) return;
    var btn = root.querySelector('.sec-tab-btn[data-for="' + tab + '"]') || root.querySelector('.sec-tab-btn[data-for="' + fallback + '"]');
    if (btn) secTab(sectionId, btn.getAttribute('data-for'), btn);
}
function secMsg(id, ok, text) {
    var m = document.getElementById(id);
    if (!m) return;
    text = String(text || '').replace(/^[^\p{L}\p{N}]+/u, '');   // у части сообщений уже есть значок
    m.textContent = (ok ? '✅ ' : '❌ ') + text;
    m.className = 'sec-msg ' + (ok ? 'ok' : 'err');
    clearTimeout(m._t);
    m._t = setTimeout(function () { m.className = 'sec-msg'; }, 8000);
}
function secPost(params) {
    var fd = new FormData();
    Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
    return fetch('', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
}
function secEsc(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
}
function secCopy(text, done) {
    var fin = function () { if (done) done(); };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(fin, fin);
    } else {
        var t = document.createElement('textarea');
        t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(t); fin();
    }
}
</script>
    <?php
}
