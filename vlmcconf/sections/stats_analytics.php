<?php
// ============================================
// ФАЙЛ: sections/stats_analytics.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-19
// @description: Вкладка «Аналитика» раздела «Статистика и отчёты» (пункт 33): графики по часам, дням и месяцам,
//               топ-10 устройств, карта мира по странам, экспорт CSV / XLSX / PDF. Графики рисуются в SVG без внешних библиотек.
// @description: "Analytics" tab of the statistics section (item 33)
// ============================================

if (!defined('VLMCS_CONF')) {
    die('Direct access not permitted');
}

$anT = [];
foreach ([
    'an_sum_total', 'an_sum_devices', 'an_sum_ips', 'an_sum_avg', 'an_sum_peak', 'an_sum_busiest',
    'an_hours', 'an_weekdays', 'an_days', 'an_months', 'an_top', 'an_geo', 'an_requests', 'an_requests_one', 'an_no_data', 'an_loading', 'an_error',
    'an_unknown', 'an_ips', 'an_other', 'an_local', 'an_wd', 'an_period_label', 'an_export_title', 'an_export_error', 'an_last', 'an_map_note',
    'an_h_section', 'an_h_item', 'an_h_requests', 'an_h_extra', 'an_sec_summary', 'an_sec_hours', 'an_sec_weekdays', 'an_sec_days', 'an_sec_months', 'an_sec_devices', 'an_sec_countries',
    'an_p7', 'an_p30', 'an_p90', 'an_p365', 'an_p0', 'an_period_name', 'an_dev_last', 'an_x_ips', 'an_x_dev', 'an_per_day',
] as $anK) {
    $anT[$anK] = __($anK);
}
?>
    <div id="statsPageAnalytics" class="stats-page">
        <div class="an-bar">
            <div class="an-periods" id="anPeriods">
                <button type="button" class="an-p" data-p="7"<?= vlmcTip('tip_an_period') ?>><?= __('an_p7') ?></button>
                <button type="button" class="an-p active" data-p="30"<?= vlmcTip('tip_an_period') ?>><?= __('an_p30') ?></button>
                <button type="button" class="an-p" data-p="90"<?= vlmcTip('tip_an_period') ?>><?= __('an_p90') ?></button>
                <button type="button" class="an-p" data-p="365"<?= vlmcTip('tip_an_period') ?>><?= __('an_p365') ?></button>
                <button type="button" class="an-p" data-p="0"<?= vlmcTip('tip_an_period') ?>><?= __('an_p0') ?></button>
            </div>
            <div class="an-export">
                <span class="sec-msg" id="anExportMsg"></span>
                <button type="button" class="sec-btn an-exp" data-fmt="csv"<?= vlmcTip('tip_an_csv') ?>>CSV</button>
                <button type="button" class="sec-btn an-exp" data-fmt="xlsx"<?= vlmcTip('tip_an_xlsx') ?>>XLSX</button>
                <button type="button" class="sec-btn an-exp" data-fmt="pdf"<?= vlmcTip('tip_an_pdf') ?>>PDF</button>
            </div>
        </div>

        <div class="an-kpis" id="anKpis"></div>

        <div class="an-grid">
            <div class="stat-block an-wide">
                <div class="stat-block-title">📈 <?= __('an_days') ?></div>
                <div id="anDays" class="an-chart"></div>
            </div>
            <div class="stat-block">
                <div class="stat-block-title">🕒 <?= __('an_hours') ?></div>
                <div id="anHours" class="an-chart"></div>
            </div>
            <div class="stat-block">
                <div class="stat-block-title">📅 <?= __('an_weekdays') ?></div>
                <div id="anWeek" class="an-chart"></div>
            </div>
            <div class="stat-block">
                <div class="stat-block-title">🗓️ <?= __('an_months') ?></div>
                <div id="anMonths" class="an-chart"></div>
            </div>
            <div class="stat-block">
                <div class="stat-block-title">🏆 <?= __('an_top') ?></div>
                <div id="anTop" class="an-top"></div>
            </div>
            <div class="stat-block an-wide">
                <div class="stat-block-title">🌍 <?= __('an_geo') ?></div>
                <div class="an-geo">
                    <div id="anMap" class="an-map"></div>
                    <div id="anCountries" class="an-countries"></div>
                </div>
            </div>
        </div>
    </div>

<style>
.an-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.an-periods { display: flex; gap: 6px; flex-wrap: wrap; }
.an-p { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 14px; padding: 0 14px; height: 26px; font-size: 12px; font-family: inherit; color: <?= $themeCSS['text'] ?>; cursor: pointer; opacity: .75; transition: all .2s; }
.an-p:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.an-p.active { opacity: 1; background: <?= $themeCSS['primary'] ?>; border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['onPrimary'] ?>; }
.an-export { display: flex; align-items: center; gap: 8px; }
.an-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.an-kpi { background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 10px 12px; text-align: center; min-width: 0; }
.an-kpi b { display: block; font-size: 24px; font-weight: 600; color: <?= $themeCSS['accentText'] ?>; line-height: 1.2; }
.an-kpi span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; color: <?= $themeCSS['muted'] ?>; margin-top: 3px; }
.an-kpi small { display: block; font-size: 10px; color: <?= $themeCSS['muted'] ?>; margin-top: 2px; }
.an-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.an-wide { grid-column: 1 / -1; }
.an-chart svg { width: 100%; height: auto; display: block; overflow: visible; }
.an-axis { stroke: <?= $themeCSS['border'] ?>; stroke-width: 1; }
.an-grid-l { stroke: <?= $themeCSS['border'] ?>; stroke-width: 1; stroke-dasharray: 2 3; opacity: .7; }
.an-lbl { fill: <?= $themeCSS['muted'] ?>; font-size: 10px; font-family: inherit; }
.an-bar-r { fill: <?= $themeCSS['primary'] ?>; opacity: .85; transition: opacity .15s; }
.an-bar-r.max { opacity: 1; }
.an-hit:hover + .an-bar-r, .an-bar-g:hover .an-bar-r { opacity: 1; filter: brightness(1.15); }
.an-line { fill: none; stroke: <?= $themeCSS['primary'] ?>; stroke-width: 2; stroke-linejoin: round; }
.an-area { fill: <?= $themeCSS['primary'] ?>; opacity: .16; }
.an-dot { fill: <?= $themeCSS['primary'] ?>; stroke: <?= $themeCSS['card'] ?>; stroke-width: 2; opacity: 0; }
.an-col:hover .an-dot { opacity: 1; }
.an-col:hover .an-colbg { fill: <?= $themeCSS['primary'] ?>; opacity: .1; }
.an-colbg { fill: transparent; }
.an-top { display: flex; flex-direction: column; gap: 7px; }
.an-top-row { display: grid; grid-template-columns: 150px minmax(0, 1fr) 52px; gap: 8px; align-items: center; font-size: 12px; }
.an-top-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.an-top-track { background: <?= $themeCSS['card'] ?>; border-radius: 4px; height: 12px; overflow: hidden; }
.an-top-fill { height: 100%; background: <?= $themeCSS['primary'] ?>; border-radius: 4px; }
.an-top-val { text-align: right; font-weight: 600; color: <?= $themeCSS['accentText'] ?>; font-variant-numeric: tabular-nums; }
.an-geo { display: grid; grid-template-columns: minmax(0, 2fr) minmax(220px, 1fr); gap: 16px; align-items: start; }
.an-map svg { width: 100%; height: auto; display: block; border-radius: 8px; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; }
.an-land { fill: <?= $themeCSS['border'] ?>; stroke: <?= $themeCSS['muted'] ?>; stroke-width: .5; opacity: .85; }
.an-mgrid { stroke: <?= $themeCSS['border'] ?>; stroke-width: .5; opacity: .6; }
.an-bub { fill: <?= $themeCSS['primary'] ?>; fill-opacity: .45; stroke: <?= $themeCSS['primary'] ?>; stroke-width: 1.5; transition: fill-opacity .15s; }
.an-bub:hover { fill-opacity: .8; }
.an-countries { display: flex; flex-direction: column; gap: 6px; font-size: 12px; max-height: 330px; overflow-y: auto; padding-right: 4px; }
.an-c-row { display: grid; grid-template-columns: minmax(0, 1fr) 70px; gap: 8px; align-items: center; }
.an-c-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.an-c-name small { color: <?= $themeCSS['muted'] ?>; margin-left: 6px; }
.an-c-val { text-align: right; font-weight: 600; color: <?= $themeCSS['accentText'] ?>; font-variant-numeric: tabular-nums; }
.an-c-track { grid-column: 1 / -1; background: <?= $themeCSS['card'] ?>; height: 4px; border-radius: 2px; overflow: hidden; margin-top: -3px; }
.an-c-track i { display: block; height: 100%; background: <?= $themeCSS['primary'] ?>; }
.an-empty { text-align: center; color: <?= $themeCSS['muted'] ?>; font-size: 12px; padding: 26px 0; }
.an-note { font-size: 11px; color: <?= $themeCSS['muted'] ?>; margin-top: 6px; }
@media (max-width: 1100px) { .an-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } .an-grid { grid-template-columns: 1fr; } .an-geo { grid-template-columns: 1fr; } }
</style>

<script>
(function () {
    const T = <?= json_encode($anT, JSON_UNESCAPED_UNICODE) ?>;
    const LOCALE = '<?= ($config['language'] ?? 'ru') === 'en' ? 'en-GB' : 'ru-RU' ?>';
    const NS = 'http://www.w3.org/2000/svg';
    const $ = id => document.getElementById(id);
    let period = 30, data = null, loaded = false, busy = false;

    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function nf(n) { return Number(n || 0).toLocaleString(LOCALE); }
    function plural(n) { return nf(n) + ' ' + T.an_requests; }
    function svgEl(tag, attrs, parent) {
        const e = document.createElementNS(NS, tag);
        for (const k in attrs) e.setAttribute(k, attrs[k]);
        if (parent) parent.appendChild(e);
        return e;
    }
    // «красивый» верх шкалы: 1, 2, 5 × 10^n
    function niceMax(v) {
        if (v <= 5) return 5;
        const p = Math.pow(10, Math.floor(Math.log10(v)));
        const m = v / p;
        return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 5 ? 5 : 10) * p;
    }
    function frame(box, W, H, pad, maxV) {
        box.textContent = '';
        const svg = svgEl('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'xMidYMid meet' }, box);
        const top = niceMax(maxV), ih = H - pad.t - pad.b;
        for (let i = 0; i <= 4; i++) {
            const y = pad.t + ih - ih * i / 4;
            svgEl('line', { x1: pad.l, x2: W - pad.r, y1: y, y2: y, class: i === 0 ? 'an-axis' : 'an-grid-l' }, svg);
            const t = svgEl('text', { x: pad.l - 6, y: y + 3, 'text-anchor': 'end', class: 'an-lbl' }, svg);
            t.textContent = nf(Math.round(top * i / 4));
        }
        return { svg: svg, top: top, ih: ih, iw: W - pad.l - pad.r };
    }
    function barChart(box, labels, values, tips, every) {
        const total = values.reduce((a, b) => a + b, 0);
        if (!total) { box.innerHTML = '<div class="an-empty">' + esc(T.an_no_data) + '</div>'; return; }
        const W = 560, H = 210, pad = { l: 44, r: 8, t: 8, b: 24 };
        const maxV = Math.max.apply(null, values);
        const f = frame(box, W, H, pad, maxV);
        const n = values.length, step = f.iw / n, bw = Math.max(2, step * 0.68);
        values.forEach((v, i) => {
            const h = f.top ? f.ih * v / f.top : 0, x = pad.l + step * i + (step - bw) / 2, y = pad.t + f.ih - h;
            const g = svgEl('g', { class: 'an-bar-g', 'data-tip': tips[i] }, f.svg);
            svgEl('rect', { x: pad.l + step * i, y: pad.t, width: step, height: f.ih, fill: 'transparent' }, g);
            svgEl('rect', { x: x, y: y, width: bw, height: Math.max(h, v > 0 ? 1 : 0), rx: 2, class: 'an-bar-r' + (v === maxV ? ' max' : '') }, g);
            if (i % every === 0) {
                const t = svgEl('text', { x: pad.l + step * i + step / 2, y: H - 7, 'text-anchor': 'middle', class: 'an-lbl' }, f.svg);
                t.textContent = labels[i];
            }
        });
    }
    function lineChart(box, series) {
        const total = series.reduce((a, s) => a + s[1], 0);
        if (!series.length || !total) { box.innerHTML = '<div class="an-empty">' + esc(T.an_no_data) + '</div>'; return; }
        const W = 1120, H = 230, pad = { l: 44, r: 12, t: 10, b: 24 };
        const maxV = Math.max.apply(null, series.map(s => s[1]));
        const f = frame(box, W, H, pad, maxV);
        const n = series.length, step = n > 1 ? f.iw / (n - 1) : f.iw;
        const X = i => pad.l + (n > 1 ? step * i : f.iw / 2), Y = v => pad.t + f.ih - (f.top ? f.ih * v / f.top : 0);
        let line = '', area = 'M' + X(0) + ',' + (pad.t + f.ih);
        series.forEach((s, i) => { line += (i ? 'L' : 'M') + X(i).toFixed(1) + ',' + Y(s[1]).toFixed(1); area += 'L' + X(i).toFixed(1) + ',' + Y(s[1]).toFixed(1); });
        area += 'L' + X(n - 1) + ',' + (pad.t + f.ih) + 'Z';
        svgEl('path', { d: area, class: 'an-area' }, f.svg);
        svgEl('path', { d: line, class: 'an-line' }, f.svg);
        const labelEvery = Math.max(1, Math.ceil(n / 9));
        series.forEach((s, i) => {
            const g = svgEl('g', { class: 'an-col', 'data-tip': fmtDate(s[0]) + ' — ' + plural(s[1]) }, f.svg);
            svgEl('rect', { x: X(i) - Math.max(step, 4) / 2, y: pad.t, width: Math.max(step, 4), height: f.ih, class: 'an-colbg' }, g);
            svgEl('circle', { cx: X(i), cy: Y(s[1]), r: 4, class: 'an-dot' }, g);
            if (i % labelEvery === 0) {
                const t = svgEl('text', { x: X(i), y: H - 7, 'text-anchor': 'middle', class: 'an-lbl' }, f.svg);
                t.textContent = shortDate(s[0]);
            }
        });
    }
    function fmtDate(iso) { const d = new Date(iso + 'T00:00:00'); return isNaN(d) ? iso : d.toLocaleDateString(LOCALE, { day: '2-digit', month: '2-digit', year: 'numeric' }); }
    function shortDate(iso) { const d = new Date(iso + 'T00:00:00'); return isNaN(d) ? iso : d.toLocaleDateString(LOCALE, { day: '2-digit', month: '2-digit' }); }
    function monthName(ym) { const d = new Date(ym + '-01T00:00:00'); return isNaN(d) ? ym : d.toLocaleDateString(LOCALE, { month: 'short', year: '2-digit' }); }

    // ---- карта мира (схематичная: упрощённые контуры материков, пузырьки по странам) ----
    const WORLD = [
        [[-168,65],[-162,70],[-141,70],[-125,70],[-110,68],[-95,68],[-90,64],[-94,58],[-85,55],[-80,52],[-79,57],[-77,62],[-70,60],[-64,60],[-60,55],[-56,52],[-60,47],[-66,44],[-70,42],[-74,40],[-76,35],[-81,31],[-80,26],[-82,26],[-84,30],[-89,30],[-94,29],[-97,26],[-97,22],[-95,19],[-91,19],[-90,21],[-87,21],[-88,16],[-84,15],[-83,10],[-80,9],[-77,8],[-80,7],[-86,12],[-92,14],[-97,16],[-105,20],[-110,24],[-115,30],[-117,33],[-121,35],[-124,40],[-124,47],[-128,51],[-134,57],[-140,60],[-150,60],[-155,57],[-163,55],[-158,58],[-165,61]],
        [[-80,63],[-62,66],[-70,72],[-85,70]],
        [[-55,60],[-45,60],[-20,70],[-20,80],[-40,83],[-60,82],[-72,78],[-55,70]],
        [[-77,8],[-72,12],[-62,10],[-52,5],[-50,0],[-35,-5],[-39,-14],[-41,-22],[-48,-26],[-53,-34],[-58,-38],[-65,-41],[-68,-50],[-70,-55],[-74,-50],[-73,-40],[-71,-30],[-70,-18],[-76,-14],[-81,-5],[-80,0]],
        [[-9,37],[-9,43],[-2,43.5],[-4,48],[2,51],[5,53],[8,54],[9,57],[11,56],[11,59],[8,58],[5,62],[14,67],[20,70],[30,71],[40,67],[44,68],[60,69],[70,73],[80,73],[100,77],[113,74],[130,71],[150,71],[170,70],[180,68],[180,65],[170,60],[163,58],[156,51],[143,59],[140,54],[135,48],[131,43],[128,39],[129,35],[126,35],[126,38],[122,40],[119,38],[122,31],[120,27],[114,22],[108,21],[106,18],[109,12],[105,9],[101,13],[100,8],[103,1.5],[98,8],[98,12],[94,17],[91,22],[87,21],[80,15],[77,8],[73,16],[72,21],[67,25],[62,25],[57,26],[56,27],[50,30],[48,29],[51,24],[56,25],[59,22],[52,16],[45,13],[43,15],[39,21],[35,28],[34,31],[36,36],[30,36],[27,37],[26,40],[23,37],[20,40],[19,42],[13,45],[18,40],[16,38],[15,38],[12,42],[10,44],[7,44],[3,43],[0,39],[-2,37],[-6,36]],
        [[-17,21],[-13,28],[-6,36],[10,37],[11,33],[20,31],[32,31],[34,27],[39,16],[43,12],[51,12],[46,5],[40,-3],[40,-11],[35,-20],[33,-26],[27,-34],[20,-35],[17,-30],[12,-17],[13,-9],[9,-1],[9,4],[4,6],[-8,4],[-14,10],[-17,14]],
        [[114,-22],[122,-18],[131,-12],[136,-12],[141,-11],[143,-14],[146,-19],[153,-26],[151,-34],[146,-39],[138,-35],[134,-33],[124,-34],[115,-34],[114,-26]],
        [[-5,50],[1,51],[1.5,53],[-2,56],[-3,58.5],[-6,58],[-5,55],[-3,54],[-5,52]],
        [[-10,52],[-6,52],[-6,55],[-10,54]],
        [[130,31],[135,34],[140,35],[142,39],[142,43],[140,42],[139,38],[133,35]],
        [[95,5],[98,3],[104,-2],[106,-6],[101,-3]],
        [[109,1],[117,7],[119,1],[116,-4],[110,-3]],
        [[131,-1],[141,-3],[150,-10],[142,-9],[135,-4]],
        [[44,-25],[47,-25],[50,-15],[49,-12],[44,-17]],
        [[172,-34],[178,-38],[175,-41],[173,-46],[167,-46],[172,-41]],
        [[-24,64],[-14,65],[-14,64],[-22,63]]
    ];
    const MW = 720, MH = 290, LAT_TOP = 85, LAT_BOT = -60;
    const mx = lon => (lon + 180) / 360 * MW, my = lat => (LAT_TOP - lat) / (LAT_TOP - LAT_BOT) * MH;
    function drawMap(countries) {
        const box = $('anMap');
        box.textContent = '';
        const svg = svgEl('svg', { viewBox: '0 0 ' + MW + ' ' + MH, preserveAspectRatio: 'xMidYMid meet' }, box);
        for (let lon = -150; lon <= 150; lon += 30) svgEl('line', { x1: mx(lon), x2: mx(lon), y1: 0, y2: MH, class: 'an-mgrid' }, svg);
        for (let lat = -30; lat <= 60; lat += 30) svgEl('line', { x1: 0, x2: MW, y1: my(lat), y2: my(lat), class: 'an-mgrid' }, svg);
        WORLD.forEach(poly => svgEl('polygon', { points: poly.map(p => mx(p[0]).toFixed(1) + ',' + my(p[1]).toFixed(1)).join(' '), class: 'an-land' }, svg));
        const pts = countries.filter(c => c.lat !== null && c.lon !== null);
        const maxC = pts.reduce((m, c) => Math.max(m, c.count), 1);
        // крупные пузырьки рисуем первыми, чтобы мелкие не прятались под ними
        pts.slice().sort((a, b) => b.count - a.count).forEach(c => {
            const r = 4 + 16 * Math.sqrt(c.count / maxC);
            const tip = c.country + ' — ' + plural(c.count) + ', ' + T.an_x_ips.replace('{n}', c.ips);
            svgEl('circle', { cx: mx(c.lon).toFixed(1), cy: my(c.lat).toFixed(1), r: r.toFixed(1), class: 'an-bub', 'data-tip': tip }, svg);
        });
    }
    function drawCountries(countries) {
        const box = $('anCountries');
        if (!countries.length) { box.innerHTML = '<div class="an-empty">' + esc(T.an_no_data) + '</div>'; return; }
        const maxC = countries.reduce((m, c) => Math.max(m, c.count), 1);
        box.innerHTML = countries.map(c => {
            const name = c.country === '' ? T.an_unknown : c.country;
            return '<div class="an-c-row"><div class="an-c-name">' + esc(name) + '<small>' + esc(T.an_x_ips.replace('{n}', c.ips)) + '</small></div>'
                + '<div class="an-c-val">' + nf(c.count) + '</div><div class="an-c-track"><i style="width:' + Math.max(2, Math.round(c.count / maxC * 100)) + '%"></i></div></div>';
        }).join('');
    }

    function render(d) {
        const hourLbl = h => String(h).padStart(2, '0') + ':00';
        const kp = [
            [nf(d.total), T.an_sum_total, T.an_period_name.replace('{p}', periodName(d.period))],
            [nf(d.devices), T.an_sum_devices, ''],
            [nf(d.ips), T.an_sum_ips, ''],
            [d.total ? nf(d.day_avg) : '—', T.an_sum_avg, ''],
            [d.peak_hour === null ? '—' : hourLbl(d.peak_hour), T.an_sum_peak, d.busiest_day ? T.an_sum_busiest.replace('{d}', fmtDate(d.busiest_day)).replace('{n}', nf(d.busiest_n)) : '']
        ];
        $('anKpis').innerHTML = kp.map(k => '<div class="an-kpi"><b>' + esc(k[0]) + '</b><span>' + esc(k[1]) + '</span>' + (k[2] ? '<small>' + esc(k[2]) + '</small>' : '') + '</div>').join('');

        lineChart($('anDays'), d.days || []);
        barChart($('anHours'), d.hours.map((_, h) => String(h).padStart(2, '0')), d.hours, d.hours.map((v, h) => hourLbl(h) + ' — ' + plural(v)), 2);
        const wd = T.an_wd.split(',');
        barChart($('anWeek'), wd, d.weekdays, d.weekdays.map((v, i) => wd[i] + ' — ' + plural(v)), 1);
        const mk = Object.keys(d.months || {});
        barChart($('anMonths'), mk.map(monthName), mk.map(k => d.months[k]), mk.map(k => monthName(k) + ' — ' + plural(d.months[k])), Math.max(1, Math.ceil(mk.length / 8)));

        const top = d.top_devices || [];
        if (!top.length) $('anTop').innerHTML = '<div class="an-empty">' + esc(T.an_no_data) + '</div>';
        else {
            const m = top[0][1] || 1;
            $('anTop').innerHTML = top.map((t, i) => '<div class="an-top-row" data-tip="' + esc(T.an_dev_last.replace('{d}', t[2] ? fmtDate(t[2].substr(0, 10)) : '—')) + '"><div class="an-top-name">' + (i + 1) + '. ' + esc(t[0]) + '</div>'
                + '<div class="an-top-track"><div class="an-top-fill" style="width:' + Math.max(2, Math.round(t[1] / m * 100)) + '%"></div></div><div class="an-top-val">' + nf(t[1]) + '</div></div>').join('');
        }
        drawMap(d.countries || []);
        drawCountries(d.countries || []);
    }
    function periodName(p) { return T['an_p' + p]; }

    function load() {
        if (busy) return;
        busy = true;
        const fd = new FormData();
        fd.append('ajax', 'get_analytics');
        fd.append('period', period);
        fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => { if (!d.success) throw new Error('fail'); data = d; loaded = true; render(d); })
            .catch(() => { $('anKpis').innerHTML = '<div class="an-empty" style="grid-column:1/-1">' + esc(T.an_error) + '</div>'; })
            .then(() => { busy = false; });
    }

    // ---- экспорт ----
    function buildExport() {
        const d = data, rows = [], P = T.an_period_label.replace('{p}', periodName(d.period));
        rows.push([T.an_sec_summary, T.an_sum_total, d.total, P]);
        rows.push([T.an_sec_summary, T.an_sum_devices, d.devices, '']);
        rows.push([T.an_sec_summary, T.an_sum_ips, d.ips, '']);
        rows.push([T.an_sec_summary, T.an_sum_avg, d.day_avg, '']);
        rows.push([T.an_sec_summary, T.an_sum_peak, d.peak_hour === null ? '' : String(d.peak_hour).padStart(2, '0') + ':00', '']);
        d.hours.forEach((v, h) => rows.push([T.an_sec_hours, String(h).padStart(2, '0') + ':00', v, '']));
        const wd = T.an_wd.split(',');
        d.weekdays.forEach((v, i) => rows.push([T.an_sec_weekdays, wd[i], v, '']));
        (d.days || []).forEach(s => rows.push([T.an_sec_days, s[0], s[1], '']));
        Object.keys(d.months || {}).forEach(k => rows.push([T.an_sec_months, k, d.months[k], '']));
        (d.top_devices || []).forEach(t => rows.push([T.an_sec_devices, t[0], t[1], t[2]]));
        (d.countries || []).forEach(c => rows.push([T.an_sec_countries, c.country === '' ? T.an_unknown : c.country, c.count, T.an_x_ips.replace('{n}', c.ips)]));
        return { title: T.an_export_title + ' — ' + P, name: 'analytics', headers: [T.an_h_section, T.an_h_item, T.an_h_requests, T.an_h_extra], types: ['text', 'text', 'num', 'text'], rows: rows };
    }
    function download(name, mime, b64) {
        const bin = atob(b64), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([bytes], { type: mime }));
        a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }
    function doExport(fmtName) {
        if (!data) return;
        const btns = document.querySelectorAll('.an-exp');
        btns.forEach(b => b.disabled = true);
        $('anExportMsg').className = 'sec-msg';
        const fd = new FormData();
        fd.append('ajax', 'export_report');
        fd.append('format', fmtName);
        fd.append('data', JSON.stringify(buildExport()));
        fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => { if (!d.success) throw new Error('fail'); download(d.filename, d.mime, d.data); })
            .catch(() => { const m = $('anExportMsg'); m.textContent = T.an_export_error; m.className = 'sec-msg err'; })
            .then(() => btns.forEach(b => b.disabled = false));
    }

    document.querySelectorAll('.an-p').forEach(b => b.addEventListener('click', function () {
        document.querySelectorAll('.an-p').forEach(x => x.classList.toggle('active', x === this));
        period = parseInt(this.dataset.p, 10);
        load();
    }));
    document.querySelectorAll('.an-exp').forEach(b => b.addEventListener('click', () => doExport(b.dataset.fmt)));
    window.statsAnalyticsOpen = function () { if (!loaded) load(); };
})();
</script>
