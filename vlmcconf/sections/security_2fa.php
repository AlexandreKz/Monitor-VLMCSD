<?php
// ============================================
// ФАЙЛ: sections/security_2fa.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Карточка «Двухфакторная аутентификация» во вкладке «Пользователи» раздела «Безопасность» (пункт 34):
//               включение (QR-код + код подтверждения), коды восстановления, отключение по паролю.
//               QR-код строится прямо в браузере (без внешних библиотек и без отправки секрета на чужие сайты).
// @description: "Two-factor authentication" card of the Security section (item 34)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../vlmcinc/totp.php';
$tfMe = isset($_SESSION['vlmc_user_id']) ? getUserById((int)$_SESSION['vlmc_user_id']) : null;
$tfLocal = $tfMe && (($tfMe['source'] ?? 'local') === 'local');
$tfOn = $tfLocal && totpIsEnabled($tfMe);
$tfLeft = $tfOn ? totpRecoveryLeft($tfMe) : 0;
$tfLang = [];
foreach (['tfa_setup_title', 'tfa_codes_title', 'tfa_pass_enter', 'tfa_code_enter', 'tfa_err_code', 'tfa_copied', 'tfa_confirm_disable', 'tfa_confirm_regen', 'tfa_confirm_reset', 'tfa_secret_copy', 'tfa_dl_name', 'msg_save_error'] as $tfK) {
    $tfLang[$tfK] = __($tfK);
}
?>
<div class="settings-card privacy-card" id="tfaCard">
    <div class="settings-card-title">🔐 <?= __('tfa_title') ?></div>
    <?php if (!$tfLocal): ?>
        <p class="privacy-hint"><?= __('tfa_err_local_only') ?></p>
    <?php else: ?>
        <div class="tf-status">
            <span class="sec-badge <?= $tfOn ? 'ok' : '' ?>" id="tfaBadge"><?= $tfOn ? '✅ ' . __('tfa_status_on') : '⚪ ' . __('tfa_status_off') ?></span>
            <?php if ($tfOn): ?><span class="tf-left"><?= sprintf(__('tfa_codes_left'), $tfLeft) ?></span><?php endif; ?>
        </div>
        <p class="privacy-hint"><?= $tfOn ? __('tfa_hint_on') : __('tfa_hint_off') ?></p>
        <div class="tf-actions">
            <?php if (!$tfOn): ?>
            <button type="button" class="sec-btn primary" id="tfaEnableBtn"<?= vlmcTip('tip_tfa_enable') ?>>🔐 <?= __('tfa_enable') ?></button>
            <?php else: ?>
            <button type="button" class="sec-btn" id="tfaRecoveryBtn"<?= vlmcTip('tip_tfa_recovery') ?>>🗝️ <?= __('tfa_new_codes') ?></button>
            <button type="button" class="sec-btn danger" id="tfaDisableBtn"<?= vlmcTip('tip_tfa_disable') ?>>🔓 <?= __('tfa_disable') ?></button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Окно: включение (QR-код и подтверждение) -->
<div id="tfaSetupModal" class="tf-modal" style="display: none;">
    <div class="tf-box" style="--mc: #14b8a6; --mt: #14b8a6; width: 520px;">
        <div class="tf-head"><h2 id="tfaSetupTitle">🔐 <?= __('tfa_setup_title') ?></h2><span class="tf-x" data-tfclose="tfaSetupModal">&times;</span></div>
        <div class="tf-body" id="tfaStepScan">
            <p><?= __('tfa_setup_step1') ?></p>
            <div class="tf-scan">
                <div class="tf-qr" id="tfaQr"></div>
                <div class="tf-manual">
                    <div class="tf-lbl"><?= __('tfa_manual') ?></div>
                    <code class="tf-secret" id="tfaSecret"></code>
                    <button type="button" class="sec-btn sm" id="tfaSecretCopy"><?= __('tfa_secret_copy') ?></button>
                </div>
            </div>
            <p><?= __('tfa_setup_step2') ?></p>
            <input type="text" id="tfaSetupCode" class="form-control tf-code" maxlength="7" inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
            <div class="sec-msg" id="tfaSetupErr"></div>
        </div>
        <div class="tf-body" id="tfaStepCodes" style="display: none;">
            <p class="tf-warn">⚠️ <?= __('tfa_codes_warn') ?></p>
            <div class="tf-codes" id="tfaCodes"></div>
            <div class="tf-codes-btns">
                <button type="button" class="sec-btn" id="tfaCodesCopy">📋 <?= __('tfa_copy_all') ?></button>
                <button type="button" class="sec-btn" id="tfaCodesDl">💾 <?= __('tfa_download') ?></button>
            </div>
        </div>
        <div class="tf-foot">
            <button type="button" class="sec-btn" data-tfclose="tfaSetupModal" id="tfaSetupCancel"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" id="tfaSetupGo">✅ <?= __('tfa_confirm_enable') ?></button>
            <button type="button" class="sec-btn primary" id="tfaSetupDone" style="display: none;"><?= __('tfa_done') ?></button>
        </div>
    </div>
</div>

<!-- Окно: подтверждение паролем / сброс -->
<div id="tfaAskModal" class="tf-modal" style="display: none;">
    <div class="tf-box" id="tfaAskBox" style="--mc: #f59e0b; --mt: #f59e0b;">
        <div class="tf-head"><h2 id="tfaAskTitle"></h2><span class="tf-x" data-tfclose="tfaAskModal">&times;</span></div>
        <div class="tf-body">
            <p id="tfaAskText"></p>
            <input type="password" id="tfaAskPass" class="form-control" autocomplete="current-password" style="display: none;" placeholder="<?= htmlspecialchars(__('tfa_pass_ph')) ?>">
            <div class="sec-msg" id="tfaAskErr"></div>
        </div>
        <div class="tf-foot">
            <button type="button" class="sec-btn" data-tfclose="tfaAskModal"><?= __('cancel') ?></button>
            <button type="button" class="sec-btn primary" id="tfaAskGo"></button>
        </div>
    </div>
</div>

<style>
.tf-status { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; flex-wrap: wrap; }
.tf-left { font-size: 11px; color: <?= $themeCSS['muted'] ?>; }
.tf-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.tf-modal { position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; }
.tf-box { background: <?= $themeCSS['card'] ?>; border: 2px solid var(--mc, <?= $themeCSS['primary'] ?>); border-radius: 12px; width: 460px; max-width: 94%; max-height: 94vh; overflow-y: auto; color: <?= $themeCSS['text'] ?>; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.tf-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; }
.tf-head h2 { margin: 0; font-size: 17px; font-weight: 600; color: var(--mt, <?= $themeCSS['accentText'] ?>); }
.tf-x { color: <?= $themeCSS['muted'] ?>; font-size: 24px; font-weight: bold; cursor: pointer; line-height: 1; }
.tf-x:hover { color: <?= $themeCSS['text'] ?>; }
.tf-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
.tf-body p { margin: 0; line-height: 1.5; }
.tf-foot { padding: 12px 20px; border-top: 1px solid <?= $themeCSS['border'] ?>; display: flex; justify-content: flex-end; gap: 10px; }
.tf-scan { display: flex; gap: 16px; align-items: center; }
.tf-qr { flex: none; width: 190px; height: 190px; background: #fff; border-radius: 8px; padding: 6px; box-sizing: border-box; }
.tf-qr svg { width: 100%; height: 100%; display: block; }
.tf-manual { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.tf-lbl { font-size: 11px; color: <?= $themeCSS['muted'] ?>; text-transform: uppercase; letter-spacing: .3px; }
.tf-secret { display: block; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 13px; line-height: 1.6; word-break: break-all; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 8px 10px; user-select: all; }
.tf-code { text-align: center; font-size: 20px; letter-spacing: 6px; font-family: 'JetBrains Mono', Consolas, monospace; height: 40px; }
.tf-warn { color: <?= $themeCSS['warning'] ?>; }
.tf-codes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.tf-codes span { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 14px; text-align: center; background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 7px 4px; user-select: all; }
.tf-codes-btns { display: flex; gap: 8px; flex-wrap: wrap; }
@media (max-width: 560px) { .tf-scan { flex-direction: column; } }
</style>

<script>
(function () {
    const L = <?= json_encode($tfLang, JSON_UNESCAPED_UNICODE) ?>;
    const $ = id => document.getElementById(id);
    function post(action, extra) {
        const fd = new FormData();
        fd.append('ajax', action);
        Object.keys(extra || {}).forEach(k => fd.append(k, extra[k]));
        return fetch('', { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    }
    function open(id) { $(id).style.display = 'flex'; }
    function close(id) { $(id).style.display = 'none'; }
    function err(id, text) { const e = $(id); e.textContent = text || ''; e.className = 'sec-msg' + (text ? ' err' : ''); }
    function copy(text, btn) {
        const done = () => { if (btn) { const o = btn.textContent; btn.textContent = L.tfa_copied; setTimeout(() => { btn.textContent = o; }, 1500); } };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, () => fallback()); return; }
        fallback();
        function fallback() {
            const t = document.createElement('textarea');
            t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* пользователь скопирует вручную: текст выделяется кликом */ }
            t.remove();
        }
    }
    document.querySelectorAll('[data-tfclose]').forEach(el => el.addEventListener('click', () => close(el.dataset.tfclose)));
    ['tfaSetupModal', 'tfaAskModal'].forEach(id => $(id).addEventListener('mousedown', e => { if (e.target === $(id)) { if (id !== 'tfaSetupModal' || $('tfaStepCodes').style.display === 'none') close(id); } }));

    // ================= QR-код (ISO 18004, байтовый режим, коррекция M, версии 1–10) =================
    const EC = { 1: [10, [[1, 16]]], 2: [16, [[1, 28]]], 3: [26, [[1, 44]]], 4: [18, [[2, 32]]], 5: [24, [[2, 43]]], 6: [16, [[4, 27]]], 7: [18, [[4, 31]]],
                 8: [22, [[2, 38], [2, 39]]], 9: [22, [[3, 36], [2, 37]]], 10: [26, [[4, 43], [1, 44]]] };
    const ALIGN = { 1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50] };
    function gfMul(x, y) { let z = 0; for (let i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; } return z; }
    function rsDivisor(n) {
        const r = new Array(n).fill(0); r[n - 1] = 1; let root = 1;
        for (let i = 0; i < n; i++) { for (let j = 0; j < n; j++) { r[j] = gfMul(r[j], root); if (j + 1 < n) r[j] ^= r[j + 1]; } root = gfMul(root, 2); }
        return r;
    }
    function rsRemainder(data, div) {
        const r = new Array(div.length).fill(0);
        data.forEach(b => { const f = b ^ r.shift(); r.push(0); div.forEach((c, i) => { r[i] ^= gfMul(c, f); }); });
        return r;
    }
    function bit(x, i) { return ((x >>> i) & 1) !== 0; }
    function qrMatrix(text) {
        const bytes = Array.from(new TextEncoder().encode(text));
        let ver = 0, dataCw = 0;
        for (let v = 1; v <= 10; v++) {
            const cw = EC[v][1].reduce((a, g) => a + g[0] * g[1], 0);
            if (4 + (v < 10 ? 8 : 16) + 8 * bytes.length <= cw * 8) { ver = v; dataCw = cw; break; }
        }
        if (!ver) return null;
        // биты данных
        const bits = [];
        const put = (val, len) => { for (let i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); };
        put(4, 4); put(bytes.length, ver < 10 ? 8 : 16); bytes.forEach(b => put(b, 8));
        put(0, Math.min(4, dataCw * 8 - bits.length));
        while (bits.length % 8) bits.push(0);
        for (let pad = 0xEC; bits.length < dataCw * 8; pad ^= 0xEC ^ 0x11) put(pad, 8);
        const data = [];
        for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.slice(i, i + 8).join(''), 2));
        // блоки и коды Рида — Соломона
        const blocks = []; let pos = 0;
        EC[ver][1].forEach(g => { for (let i = 0; i < g[0]; i++) { blocks.push(data.slice(pos, pos + g[1])); pos += g[1]; } });
        const div = rsDivisor(EC[ver][0]);
        const ecs = blocks.map(b => rsRemainder(b, div));
        const all = [];
        const maxD = Math.max.apply(null, blocks.map(b => b.length));
        for (let i = 0; i < maxD; i++) blocks.forEach(b => { if (i < b.length) all.push(b[i]); });
        for (let i = 0; i < EC[ver][0]; i++) ecs.forEach(e => all.push(e[i]));
        // матрица
        const size = ver * 4 + 17;
        const mod = Array.from({ length: size }, () => new Array(size).fill(false));
        const fn = Array.from({ length: size }, () => new Array(size).fill(false));
        const setFn = (x, y, d) => { mod[y][x] = d; fn[y][x] = true; };
        for (let i = 0; i < size; i++) { setFn(6, i, i % 2 === 0); setFn(i, 6, i % 2 === 0); }
        const finder = (cx, cy) => {
            for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) {
                const d = Math.max(Math.abs(dx), Math.abs(dy)), x = cx + dx, y = cy + dy;
                if (x >= 0 && x < size && y >= 0 && y < size) setFn(x, y, d !== 2 && d !== 4);
            }
        };
        finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
        const ap = ALIGN[ver], n = ap.length;
        for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) {
            if ((i === 0 && j === 0) || (i === 0 && j === n - 1) || (i === n - 1 && j === 0)) continue;
            for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) setFn(ap[i] + dx, ap[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
        const drawFormat = mask => {
            const d = (0 << 3) | mask; let rem = d;   // уровень M = 00
            for (let i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
            const b = ((d << 10) | rem) ^ 0x5412;
            for (let i = 0; i <= 5; i++) setFn(8, i, bit(b, i));
            setFn(8, 7, bit(b, 6)); setFn(8, 8, bit(b, 7)); setFn(7, 8, bit(b, 8));
            for (let i = 9; i < 15; i++) setFn(14 - i, 8, bit(b, i));
            for (let i = 0; i < 8; i++) setFn(size - 1 - i, 8, bit(b, i));
            for (let i = 8; i < 15; i++) setFn(8, size - 15 + i, bit(b, i));
            setFn(8, size - 8, true);
        };
        drawFormat(0);
        if (ver >= 7) {
            let rem = ver; for (let i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
            const b = (ver << 12) | rem;
            for (let i = 0; i < 18; i++) { const c = bit(b, i), a = size - 11 + i % 3, bb = Math.floor(i / 3); setFn(a, bb, c); setFn(bb, a, c); }
        }
        // данные зигзагом
        let k = 0;
        for (let right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (let vert = 0; vert < size; vert++) for (let j = 0; j < 2; j++) {
                const x = right - j, up = ((right + 1) & 2) === 0, y = up ? size - 1 - vert : vert;
                if (!fn[y][x] && k < all.length * 8) { mod[y][x] = bit(all[k >>> 3], 7 - (k & 7)); k++; }
            }
        }
        const maskFn = [(x, y) => (x + y) % 2 === 0, (x, y) => y % 2 === 0, (x, y) => x % 3 === 0, (x, y) => (x + y) % 3 === 0,
            (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0, (x, y) => x * y % 2 + x * y % 3 === 0,
            (x, y) => (x * y % 2 + x * y % 3) % 2 === 0, (x, y) => ((x + y) % 2 + x * y % 3) % 2 === 0];
        const applyMask = m => { for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) if (!fn[y][x] && maskFn[m](x, y)) mod[y][x] = !mod[y][x]; };
        const penalty = () => {
            let p = 0;
            const line = get => {
                let run = 1;
                for (let i = 1; i < size; i++) { if (get(i) === get(i - 1)) { run++; } else { if (run >= 5) p += 3 + run - 5; run = 1; } }
                if (run >= 5) p += 3 + run - 5;
            };
            for (let y = 0; y < size; y++) line(i => mod[y][i]);
            for (let x = 0; x < size; x++) line(i => mod[i][x]);
            for (let y = 0; y < size - 1; y++) for (let x = 0; x < size - 1; x++) { const c = mod[y][x]; if (c === mod[y][x + 1] && c === mod[y + 1][x] && c === mod[y + 1][x + 1]) p += 3; }
            let dark = 0; mod.forEach(r => r.forEach(c => { if (c) dark++; }));
            p += Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10;
            return p;
        };
        let best = 0, bestP = Infinity;
        for (let m = 0; m < 8; m++) {
            applyMask(m); drawFormat(m);
            const p = penalty();
            if (p < bestP) { bestP = p; best = m; }
            applyMask(m);
        }
        applyMask(best); drawFormat(best);
        return mod;
    }
    function qrSvg(text) {
        const m = qrMatrix(text);
        if (!m) return '';
        const n = m.length, q = 2;
        let d = '';
        m.forEach((row, y) => { let x = 0; while (x < n) { if (row[x]) { let e = x; while (e < n && row[e]) e++; d += 'M' + (x + q) + ',' + (y + q) + 'h' + (e - x) + 'v1h-' + (e - x) + 'z'; x = e; } else x++; } });
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + (n + 2 * q) + ' ' + (n + 2 * q) + '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
    }
    window.tfaQrSvg = qrSvg;   // для проверки

    // ================= включение =================
    let curCodes = [];
    function showCodes(codes) {
        curCodes = codes;
        $('tfaStepScan').style.display = 'none';
        $('tfaStepCodes').style.display = '';
        $('tfaCodes').innerHTML = codes.map(c => '<span>' + c + '</span>').join('');
        $('tfaSetupTitle').textContent = '🗝️ ' + L.tfa_codes_title;
        $('tfaSetupGo').style.display = 'none';
        $('tfaSetupCancel').style.display = 'none';
        $('tfaSetupDone').style.display = '';
    }
    const enBtn = $('tfaEnableBtn');
    if (enBtn) enBtn.addEventListener('click', () => {
        err('tfaSetupErr', '');
        post('tfa_begin').then(d => {
            if (!d.success) { alert(d.message || L.msg_save_error); return; }
            $('tfaQr').innerHTML = qrSvg(d.uri);
            $('tfaSecret').textContent = d.secret.replace(/(.{4})/g, '$1 ').trim();
            $('tfaSecret').dataset.raw = d.secret;
            $('tfaSetupCode').value = '';
            $('tfaStepScan').style.display = ''; $('tfaStepCodes').style.display = 'none';
            $('tfaSetupTitle').textContent = '🔐 ' + L.tfa_setup_title;
            $('tfaSetupGo').style.display = ''; $('tfaSetupCancel').style.display = ''; $('tfaSetupDone').style.display = 'none';
            open('tfaSetupModal');
            $('tfaSetupCode').focus();
        }).catch(() => alert(L.msg_save_error));
    });
    $('tfaSecretCopy').addEventListener('click', function () { copy($('tfaSecret').dataset.raw || '', this); });
    function enable() {
        const code = $('tfaSetupCode').value.trim();
        if (!/^\d{6}$/.test(code.replace(/\s/g, ''))) { err('tfaSetupErr', L.tfa_code_enter); return; }
        post('tfa_enable', { tfa_token: code }).then(d => {
            if (d.success) { err('tfaSetupErr', ''); showCodes(d.recovery || []); }
            else err('tfaSetupErr', d.message || L.tfa_err_code);
        }).catch(() => err('tfaSetupErr', L.msg_save_error));
    }
    $('tfaSetupGo').addEventListener('click', enable);
    $('tfaSetupCode').addEventListener('keydown', e => { if (e.key === 'Enter') enable(); });
    $('tfaCodesCopy').addEventListener('click', function () { copy(curCodes.join('\n'), this); });
    $('tfaCodesDl').addEventListener('click', () => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([L.tfa_dl_name + '\n\n' + curCodes.join('\n') + '\n'], { type: 'text/plain;charset=utf-8' }));
        a.download = 'kms-monitor-recovery-codes.txt';
        document.body.appendChild(a); a.click(); a.remove();
    });
    $('tfaSetupDone').addEventListener('click', () => location.reload());

    // ================= отключение / новые коды / сброс чужой =================
    let askAction = null, askExtra = {};
    function ask(opts) {
        askAction = opts.action; askExtra = opts.extra || {};
        $('tfaAskBox').style.setProperty('--mc', opts.color); $('tfaAskBox').style.setProperty('--mt', opts.color);
        $('tfaAskTitle').textContent = opts.title;
        $('tfaAskText').textContent = opts.text;
        $('tfaAskPass').style.display = opts.password ? '' : 'none';
        $('tfaAskPass').value = '';
        const go = $('tfaAskGo'); go.textContent = opts.button; go.className = 'sec-btn ' + (opts.danger ? 'danger' : 'primary');
        err('tfaAskErr', '');
        open('tfaAskModal');
        if (opts.password) $('tfaAskPass').focus();
    }
    function askGo() {
        const extra = Object.assign({}, askExtra);
        if ($('tfaAskPass').style.display !== 'none') {
            if ($('tfaAskPass').value === '') { err('tfaAskErr', L.tfa_pass_enter); return; }
            extra.tfa_pass = $('tfaAskPass').value;
        }
        post(askAction, extra).then(d => {
            if (!d.success) { err('tfaAskErr', d.message || L.msg_save_error); return; }
            if (askAction === 'tfa_recovery') { close('tfaAskModal'); $('tfaStepScan').style.display = 'none'; showCodes(d.recovery || []); open('tfaSetupModal'); return; }
            location.reload();
        }).catch(() => err('tfaAskErr', L.msg_save_error));
    }
    $('tfaAskGo').addEventListener('click', askGo);
    $('tfaAskPass').addEventListener('keydown', e => { if (e.key === 'Enter') askGo(); });
    const dis = $('tfaDisableBtn');
    if (dis) dis.addEventListener('click', () => ask({ action: 'tfa_disable', color: '<?= $themeCSS['danger'] ?>', title: '🔓 ' + L.tfa_confirm_disable.split('|')[0], text: L.tfa_confirm_disable.split('|')[1], button: L.tfa_confirm_disable.split('|')[2], password: true, danger: true }));
    const rec = $('tfaRecoveryBtn');
    if (rec) rec.addEventListener('click', () => ask({ action: 'tfa_recovery', color: '#f59e0b', title: '🗝️ ' + L.tfa_confirm_regen.split('|')[0], text: L.tfa_confirm_regen.split('|')[1], button: L.tfa_confirm_regen.split('|')[2], password: true }));
    window.tfaAskReset = function (userId, name) {
        const p = L.tfa_confirm_reset.split('|');
        ask({ action: 'tfa_reset', extra: { user_id: userId }, color: '#f59e0b', title: '♻️ ' + p[0], text: p[1].replace('{user}', name), button: p[2], password: false });
    };
})();
</script>
