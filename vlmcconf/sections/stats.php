<?php
// ============================================
// ФАЙЛ: sections/stats.php
// ВЕРСИЯ: 3.4.3
// ДАТА: 2026-11-17
// @description: Секция "Статистика и отчёты": вкладки «Статистика» и «Отчёты»
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'stats.php') {
    http_response_code(403);
    exit('Access denied');
}

// Один потоковый проход по логу: ip => число подключений
$ipCounts = getLogIpCounts($fullLogPath);
$totalRequests = array_sum($ipCounts);

// ТОП IP
$topIps = $ipCounts;
arsort($topIps);
$topIps = array_slice($topIps, 0, 4, true);

// ТОП стран: данные ТОЛЬКО из кэша, сетевых запросов при отрисовке страницы нет.
// IP без кэша определяются в фоне через AJAX (см. скрипт ниже).
$countryCounts = [];
$geoPending = 0;
foreach ($ipCounts as $ip => $cnt) {
    $country = getGeoCachedCountry($ip);
    if ($country === null) {
        if (geoLookupFailedRecently($ip)) {
            $country = __('geo_unknown');
        } else {
            $geoPending++;
            continue;
        }
    }
    $countryCounts[$country] = ($countryCounts[$country] ?? 0) + $cnt;
}
arsort($countryCounts);
$topCountries = array_slice($countryCounts, 0, 4, true);

// Статистика по группам (устройства из конфига)
$groupStats = [];
foreach ($config['groupColors'] as $group => $color) {
    $groupStats[$group] = isset($config['devices'][$group]) ? count($config['devices'][$group]) : 0;
}
arsort($groupStats);
$totalDevices = array_sum($groupStats);
$totalGroups = count($groupStats);
?>

<div id="section-stats" class="settings-section <?= $activeSection === 'stats' ? 'active' : '' ?>">
    <div class="section-title">
        <span>📊 <?= __('stats_title') ?></span>
        <?php if ($activeSection === 'stats' && $message): ?>
        <div class="section-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="stats-pages">
            <button type="button" class="stats-page-btn active" data-page="overview" onclick="switchStatsPage('overview')">📊 <?= __('stats_tab_overview') ?></button>
            <button type="button" class="stats-page-btn" data-page="reports" onclick="switchStatsPage('reports')">📑 <?= __('stats_tab_reports') ?></button>
        </div>
    </div>

    <div id="statsPageOverview" class="stats-page active">
    <!-- Общая статистика (3 карточки) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-value"><?= $totalDevices ?></div>
            <div class="stat-card-label"><?= __('stats_total_devices') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?= $totalGroups ?></div>
            <div class="stat-card-label"><?= __('stats_total_groups') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value"><?= number_format($totalRequests, 0, ',', ' ') ?></div>
            <div class="stat-card-label"><?= __('stats_total_requests') ?></div>
        </div>
    </div>
    
    <!-- Три блока в одну строку: Группы | Топ IP | Топ стран -->
    <div class="stats-detailed">
        
        <!-- Блок: По группам -->
		<div class="stat-block">
			<div class="stat-block-title">👥 <?= __('stats_by_groups') ?></div>
			<div class="stat-list">
				<?php if (empty($groupStats)): ?>
					<div class="stat-list-empty"><?= __('stats_no_groups') ?></div>
				<?php else: ?>
					<?php foreach ($groupStats as $group => $count): 
						$groupColor = is_array($config['groupColors'][$group]) 
							? $config['groupColors'][$group]['color'] 
							: $config['groupColors'][$group];
					?>
					<div class="stat-list-item">
						<span class="stat-list-label" style="color: <?= htmlspecialchars($groupColor) ?>;">
							<?= __($group) ?>
						</span>
						<span class="stat-list-value"><?= $count ?></span>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
        
        <!-- Блок: Топ IP адресов -->
        <div class="stat-block">
            <div class="stat-block-title">🌐 <?= __('stats_top_ips') ?></div>
            <div class="stat-list">
                <?php if (empty($topIps)): ?>
                    <div class="stat-list-empty"><?= __('stats_no_data_ips') ?></div>
                <?php else: ?>
                    <?php foreach ($topIps as $ip => $count): ?>
                    <div class="stat-list-item">
                        <span class="stat-list-label stat-copyable" data-copy="<?= htmlspecialchars($ip) ?>" title="<?= htmlspecialchars(__('stats_ip_copy_hint')) ?>" role="button" tabindex="0"><?= htmlspecialchars($ip) ?></span>
                        <span class="stat-list-value"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Блок: Топ стран -->
        <div class="stat-block">
            <div class="stat-block-title">🌍 <?= __('stats_top_countries') ?></div>
            <?php if ($geoPending > 0): ?>
            <div class="stat-list-empty" id="geo-pending-hint">🌐 <?= sprintf(__('stats_geo_pending'), $geoPending) ?></div>
            <script>
            (function () {
                var rounds = 0, resolvedTotal = 0, lastError = '';
                function finish() {
                    var el = document.getElementById('geo-pending-hint');
                    if (!el) return;
                    if (resolvedTotal > 0) {
                        el.textContent = <?= json_encode('🌐 ' . __('stats_geo_done')) ?>;
                    } else if (lastError === 'ssl_ca' || lastError === 'ssl') {
                        el.textContent = <?= json_encode('🔒 ' . __('stats_geo_ssl_error')) ?>;
                    } else {
                        el.remove();
                    }
                }
                function step() {
                    if (rounds++ >= 100) { finish(); return; }
                    var fd = new FormData();
                    fd.append('ajax', 'geo_resolve_portion');
                    fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d || !d.success) { finish(); return; }
                            resolvedTotal += d.resolved;
                            if (d.error) lastError = d.error;
                            if (d.remaining > 0 && (d.resolved + d.failed) > 0) { step(); } else { finish(); }
                        })
                        .catch(finish);
                }
                window.addEventListener('load', function () { setTimeout(step, 500); });
            })();
            </script>
            <?php endif; ?>
            <div class="stat-list">
                <?php if (empty($topCountries)): ?>
                    <div class="stat-list-empty"><?= __('stats_no_data_countries') ?></div>
                <?php else: ?>
                    <?php foreach ($topCountries as $country => $count): ?>
                    <div class="stat-list-item">
                        <span class="stat-list-label">
                            <?= getCountryFlag($country) ?> <?= htmlspecialchars($country) ?>
                        </span>
                        <span class="stat-list-value"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Активность (на всю ширину) -->
    <div class="stat-block" style="margin-top: 0;">
        <div class="stat-block-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="stat-tab-btn active" data-tab="timeline" onclick="switchStatTab('timeline')">📅 <?= __('stats_overall') ?></button>
                <button class="stat-tab-btn" data-tab="device" onclick="switchStatTab('device')">📱 <?= __('stats_by_device') ?></button>
                
                <select id="deviceSelect" class="form-control" style="width: 220px; display: none;" onchange="loadDeviceActivity()">
                    <option value="">-- <?= __('stats_select_device') ?> --</option>
                    <?php foreach ($deviceList as $device): ?>
                    <option value="<?= htmlspecialchars((string)$device) ?>"><?= htmlspecialchars((string)$device) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="period-selector" id="periodSelector">
                <button class="period-btn active" data-period="day" onclick="changePeriod('day')"><?= __('stats_days') ?></button>
                <button class="period-btn" data-period="week" onclick="changePeriod('week')"><?= __('stats_weeks') ?></button>
                <button class="period-btn" data-period="month" onclick="changePeriod('month')"><?= __('stats_months') ?></button>
            </div>
        </div>
        
        <div class="chart-container">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
    </div>

    <!-- Вкладка «Отчёты» -->
    <div id="statsPageReports" class="stats-page">
        <div class="stat-block rep-block" id="repBlock">
            <div class="stat-block-title">📑 <?= __('stats_tab_reports') ?></div>
            <div class="rep-controls">
                <div class="rep-field">
                    <label><?= __('report_label_type') ?></label>
                    <div class="rep-dd" id="repType"></div>
                </div>
                <div class="rep-field rep-field-query" id="repQueryWrap">
                    <label id="repQueryLabel"></label>
                    <div class="rep-combo">
                        <input type="text" id="reportInput" class="form-control" autocomplete="off" spellcheck="false">
                        <span class="arrow rep-combo-arrow" id="repComboArrow">▼</span>
                    </div>
                </div>
                <div class="rep-field" id="repDaysWrap" style="display: none;">
                    <label id="repDaysLabel"></label>
                    <div class="rep-dd" id="repDays"></div>
                </div>
                <div class="rep-field" id="repCountryWrap" style="display: none;">
                    <label><?= __('report_label_country') ?></label>
                    <div class="rep-dd" id="repCountry"></div>
                </div>
                <div class="rep-field rep-field-btn">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-primary" id="reportRun">🔍 <?= __('report_show') ?></button>
                </div>
            </div>
            <div class="report-hint" id="repDesc"></div>
            <div id="reportHead" class="report-head" style="display: none;">
                <div id="reportSummary" class="report-summary"></div>
                <div class="report-actions">
                    <span id="reportExportMsg" class="report-export-msg"></span>
                    <button type="button" class="rep-export" data-fmt="xlsx" title="<?= htmlspecialchars(__('report_export_xlsx_title')) ?>">📗 XLSX</button>
                    <button type="button" class="rep-export" data-fmt="pdf" title="<?= htmlspecialchars(__('report_export_pdf_title')) ?>">📕 PDF</button>
                </div>
            </div>
            <div id="reportResult" class="report-result"></div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: <?= $themeCSS['card'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}

.stat-card-value {
    font-size: 28px;
    font-weight: 600;
    color: <?= $themeCSS['accentText'] ?>;
    margin-bottom: 4px;
}

.stat-card-label {
    font-size: 10px;
    color: <?= $themeCSS['muted'] ?>;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.stats-detailed {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-block {
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 12px;
}

.stat-block-title {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    color: <?= $themeCSS['muted'] ?>;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
    padding-bottom: 6px;
}

.stat-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.stat-list-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    padding: 4px 0;
    border-bottom: 1px dashed <?= $themeCSS['border'] ?>;
}

.stat-copyable {
    cursor: pointer;
    border-radius: 3px;
    transition: background 0.15s;
}
.stat-copyable:hover,
.stat-copyable:focus {
    background: rgba(61, 139, 253, 0.18);
    outline: none;
}
.stat-copy-badge {
    margin-left: 8px;
    font-size: 10px;
    white-space: nowrap;
}
.stat-copy-badge.ok { color: #2ea043; }
.stat-copy-badge.err { color: #f85149; }

.stat-list-label {
    color: <?= $themeCSS['muted'] ?>;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-list-value {
    font-weight: 600;
    color: <?= $themeCSS['accentText'] ?>;
}

.stat-list-empty {
    text-align: center;
    color: <?= $themeCSS['muted'] ?>;
    font-size: 11px;
    padding: 15px 0;
}

.stat-tab-btn {
    padding: 4px 12px;
    background: <?= $themeCSS['input'] ?>;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.stat-tab-btn.active {
    background: <?= $themeCSS['primary'] ?>;
    color: <?= $themeCSS['onPrimary'] ?>;
    border-color: <?= $themeCSS['primary'] ?>;
}

.chart-container {
    position: relative;
    height: 274px;
    width: 100%;
    margin-top: 8px;
}

.period-selector {
    display: flex;
    gap: 5px;
}

.period-btn {
    padding: 3px 8px;
    border: 1px solid <?= $themeCSS['border'] ?>;
    background: <?= $themeCSS['input'] ?>;
    color: <?= $themeCSS['text'] ?>;
    border-radius: 4px;
    cursor: pointer;
    font-size: 10px;
}

.period-btn.active {
    background: <?= $themeCSS['primary'] ?>;
    color: <?= $themeCSS['onPrimary'] ?>;
    border-color: <?= $themeCSS['primary'] ?>;
}

.form-control {
    background: <?= $themeCSS['field'] ?>;
    border: 1px solid <?= $themeCSS['inputBorder'] ?>;
    border-radius: 4px;
    color: <?= $themeCSS['text'] ?>;
    padding: 4px 8px;
    font-size: 12px;
}
/* Вкладки раздела */
.stats-pages { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.stats-page-btn { background: <?= $themeCSS['input'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 20px; padding: 6px 20px; font-size: 13px; font-weight: 500; cursor: pointer; color: <?= $themeCSS['text'] ?>; opacity: 0.7; transition: all 0.2s; font-family: inherit; }
.stats-page-btn:hover { opacity: 1; background: <?= $themeCSS['hover'] ?>; }
.stats-page-btn.active { opacity: 1; color: <?= $themeCSS['accentText'] ?>; background: <?= $themeCSS['card'] ?>; border-color: <?= $themeCSS['primary'] ?>; }
.stats-page { display: none; }
.stats-page.active { display: block; }

/* Отчёты */
.rep-controls { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-top: 4px; }
.rep-field label { display: block; font-size: 11px; color: <?= $themeCSS['muted'] ?>; margin-bottom: 4px; }
.rep-field-query { flex: 1; min-width: 220px; max-width: 340px; }
.rep-field .form-control { height: 34px; box-sizing: border-box; }
.rep-combo .form-control { padding-right: 32px; }
.rep-combo .form-control:hover { border-color: <?= $themeCSS['primary'] ?>; }
.rep-combo-arrow { position: absolute; right: 0; top: 0; width: 32px; height: 34px; display: flex; align-items: center; justify-content: center; font-size: 10px; opacity: 0.7; cursor: pointer; color: <?= $themeCSS['text'] ?>; transition: transform 0.15s; }
.rep-combo.open .rep-combo-arrow { transform: rotate(180deg); }
.rep-combo.open .form-control { border-color: <?= $themeCSS['primary'] ?>; }
.rep-field-btn .btn { padding: 0 18px; height: 34px; font-size: 13px; }
.rep-dd, .rep-combo { position: relative; }
.rep-block { display: flex; flex-direction: column; box-sizing: border-box; }
.rep-dd-btn { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-width: 260px; width: 100%; box-sizing: border-box; height: 34px; padding: 0 12px; background: <?= $themeCSS['field'] ?>; border: 1px solid <?= $themeCSS['inputBorder'] ?>; border-radius: 6px; color: <?= $themeCSS['text'] ?>; font-size: 13px; font-family: inherit; cursor: pointer; text-align: left; }
.rep-dd.sm .rep-dd-btn { min-width: 110px; }
.rep-dd-btn:hover, .rep-dd.open .rep-dd-btn { border-color: <?= $themeCSS['primary'] ?>; }
.rep-dd-btn .arrow { font-size: 10px; opacity: 0.7; transition: transform 0.15s; }
.rep-dd.open .arrow { transform: rotate(180deg); }
.rep-pop { position: fixed; z-index: 3000; overflow-y: auto; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; padding: 4px; box-shadow: 0 8px 24px rgba(0,0,0,0.35); box-sizing: border-box; }
.rep-opt { padding: 7px 10px; font-size: 13px; color: <?= $themeCSS['text'] ?>; border-radius: 5px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rep-opt:hover, .rep-opt.hl { background: <?= $themeCSS['hover'] ?>; }
.rep-opt.sel { color: <?= $themeCSS['accentText'] ?>; font-weight: 600; }
.rep-opt.empty { color: <?= $themeCSS['muted'] ?>; cursor: default; }
.rep-opt.empty:hover { background: transparent; }
.report-hint { font-size: 11px; color: <?= $themeCSS['muted'] ?>; margin-top: 10px; }
.report-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin: 12px 0 8px; flex-wrap: wrap; }
.report-summary { font-size: 12px; color: <?= $themeCSS['text'] ?>; font-weight: 600; }
.report-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.report-export-msg { font-size: 11px; color: <?= $themeCSS['danger'] ?>; }
.rep-export { height: 28px; padding: 0 12px; background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['inputBorder'] ?>; border-radius: 6px; color: <?= $themeCSS['text'] ?>; font-size: 12px; font-family: inherit; cursor: pointer; white-space: nowrap; transition: border-color .15s, color .15s; }
.rep-export:hover:not(:disabled) { border-color: <?= $themeCSS['primary'] ?>; color: <?= $themeCSS['accentText'] ?>; }
.rep-export:disabled { opacity: 0.5; cursor: wait; }
.report-result { margin-top: 10px; flex: 1; min-height: 0; display: flex; flex-direction: column; }
.report-empty { font-size: 12px; color: <?= $themeCSS['muted'] ?>; padding: 12px 0; }
.report-table-wrap { overflow: auto; flex: 1; min-height: 0; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 8px; background: <?= $themeCSS['input'] ?>; }
.report-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.report-table th { position: sticky; top: 0; text-align: left; padding: 8px 10px; background: <?= $themeCSS['card'] ?>; color: <?= $themeCSS['muted'] ?>; font-weight: 600; white-space: nowrap; }
.report-table td { padding: 7px 10px; border-top: 1px solid <?= $themeCSS['border'] ?>; }
.report-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.report-table th.num { text-align: right; }
.report-table td.mono { font-family: monospace; white-space: nowrap; }
.report-table td.muted { color: <?= $themeCSS['muted'] ?>; }
.report-table tr:hover td { background: <?= $themeCSS['hover'] ?>; }
.report-link { color: <?= $themeCSS['accentText'] ?>; cursor: pointer; }
.report-link:hover { text-decoration: underline; }
.stats-pages { margin: 0 0 0 auto; margin-bottom: 0; }
.stats-page-btn { padding: 4px 16px; font-size: 12px; }
</style>

<script>
// ============================================
// ГРАФИК АКТИВНОСТИ
// ============================================

let statActivityChart = null;
let currentStatTab = 'timeline';
let currentStatPeriod = 'day';
let currentStatDevice = '';

function switchStatTab(tab) {
    currentStatTab = tab;
    
    document.querySelectorAll('.stat-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.stat-tab-btn[data-tab="${tab}"]`).classList.add('active');
    
    const deviceSelect = document.getElementById('deviceSelect');
    if (tab === 'device') {
        deviceSelect.style.display = 'inline-block';
        if (currentStatDevice) {
            loadDeviceActivity();
        } else {
            updateStatChart({});
        }
    } else {
        deviceSelect.style.display = 'none';
        loadTimelineActivity();
    }
}

function changePeriod(period) {
    currentStatPeriod = period;
    
    document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.period-btn[data-period="${period}"]`).classList.add('active');
    
    if (currentStatTab === 'device' && currentStatDevice) {
        loadDeviceActivity();
    } else {
        loadTimelineActivity();
    }
}

function loadTimelineActivity() {
    const fd = new FormData();
    fd.append('ajax', 'get_activity');
    fd.append('period', currentStatPeriod);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if (d.success) {
                updateStatChart(d.data);
            }
        })
        .catch(e => console.error('Timeline error:', e));
}

function loadDeviceActivity() {
    const deviceSelect = document.getElementById('deviceSelect');
    currentStatDevice = deviceSelect.value;
    if (!currentStatDevice) return;
    
    const fd = new FormData();
    fd.append('ajax', 'get_device_activity');
    fd.append('device', currentStatDevice);
    fd.append('period', currentStatPeriod);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if (d.success) {
                updateStatChart(d.data);
            }
        })
        .catch(e => console.error('Device activity error:', e));
}

function updateStatChart(data) {
    const labels = Object.keys(data);
    const values = Object.values(data);
    
    if (statActivityChart) {
        statActivityChart.destroy();
    }
    
    const ctx = document.getElementById('activityChart').getContext('2d');
    if (ctx) {
        let maxValue = Math.max(...values, 10);
        if (maxValue > 100) maxValue = 100;
        
        let stepSize = 1;
        if (maxValue > 50) stepSize = 10;
        else if (maxValue > 20) stepSize = 5;
        else if (maxValue > 10) stepSize = 2;
        
        statActivityChart = new Chart(ctx, {
            type: 'line',
            data: { 
                labels: labels, 
                datasets: [{ 
                    data: values, 
                    borderColor: '<?= $themeCSS['primary'] ?>', 
                    backgroundColor: '<?= $themeCSS['primary'] ?>20', 
                    borderWidth: 2, 
                    tension: 0.1, 
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.raw;
                                let word = '<?= __('graph_requests') ?>';
                                if (label === 1) word = '<?= __('graph_request') ?>';
                                else if (label >= 2 && label <= 4) word = '<?= __('graph_requests_few') ?>';
                                return `📊 ${label} ${word}`;
                            }
                        }
                    } 
                }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '<?= $themeCSS['border'] ?>' }, 
                        ticks: { 
                            color: '<?= $themeCSS['text'] ?>', 
                            stepSize: stepSize,
                            max: maxValue,
                            callback: function(value) {
                                if (Number.isInteger(value)) return value;
                                return null;
                            }
                        } 
                    }, 
                    x: { 
                        grid: { display: false }, 
                        ticks: { 
                            color: '<?= $themeCSS['text'] ?>', 
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 15
                        } 
                    } 
                } 
            }
        });
    }
}

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================

(function() {
    function checkAndLoad() {
        const statsSection = document.getElementById('section-stats');
        if (statsSection && statsSection.classList.contains('active')) {
            if (typeof loadTimelineActivity === 'function') {
                loadTimelineActivity();
                return true;
            }
        }
        return false;
    }
    
    if (!checkAndLoad()) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.id === 'section-stats' && target.classList.contains('active')) {
                        if (typeof loadTimelineActivity === 'function') {
                            loadTimelineActivity();
                            observer.disconnect();
                        }
                    }
                }
            });
        });
        
        const statsSection = document.getElementById('section-stats');
        if (statsSection) {
            observer.observe(statsSection, { attributes: true });
        }
        
        setTimeout(function() {
            observer.disconnect();
            if (typeof loadTimelineActivity === 'function') {
                const section = document.getElementById('section-stats');
                if (section && section.classList.contains('active')) {
                    loadTimelineActivity();
                }
            }
        }, 500);
    }
})();
</script>
<script>
// Копирование IP из блока «Топ IP адресов» по клику (работает и без HTTPS: запасной вариант через execCommand)
(function () {
    if (window.__statCopyInit) return;
    window.__statCopyInit = true;

    const I18N = <?= json_encode(['copied' => __('stats_ip_copied'), 'failed' => __('stats_ip_copy_failed')], JSON_UNESCAPED_UNICODE) ?>;

    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function showBadge(el, ok) {
        const old = el.parentNode.querySelector('.stat-copy-badge');
        if (old) old.remove();
        const badge = document.createElement('span');
        badge.className = 'stat-copy-badge ' + (ok ? 'ok' : 'err');
        badge.textContent = ok ? '✅ ' + I18N.copied : '❌ ' + I18N.failed;
        el.insertAdjacentElement('afterend', badge);
        setTimeout(function () { badge.remove(); }, 1600);
    }

    function handle(el) {
        copyText(el.getAttribute('data-copy') || '').then(function (ok) { showBadge(el, ok); });
    }

    document.addEventListener('click', function (e) {
        const el = e.target.closest ? e.target.closest('.stat-copyable') : null;
        if (el) handle(el);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const el = e.target.closest ? e.target.closest('.stat-copyable') : null;
        if (el) { e.preventDefault(); handle(el); }
    });
})();
</script>
<?php
?>

<script>
(function () {
    const T = {
<?php foreach (['report_label_type', 'report_label_days_inactive', 'report_label_days_new', 'report_days_n', 'report_input_device', 'report_input_ip', 'report_empty_query', 'report_bad_ip', 'report_not_found', 'report_error', 'report_loading', 'report_col_device', 'report_col_ip', 'report_col_country', 'report_col_count', 'report_col_first', 'report_col_last', 'report_col_ips', 'report_col_devices', 'report_col_days_ago', 'report_col_last_ip', 'report_col_ip_list', 'report_col_product', 'report_sum_device', 'report_sum_devices', 'report_sum_ip', 'report_sum_rows', 'report_sum_rows_limit', 'report_mode_device', 'report_mode_ip', 'report_type_multi_ip', 'report_type_shared_ip', 'report_type_top_devices', 'report_type_inactive', 'report_type_new', 'report_type_products', 'report_desc_device_ips', 'report_desc_ip_devices', 'report_desc_multi_ip', 'report_desc_shared_ip', 'report_desc_top_devices', 'report_desc_inactive', 'report_desc_new', 'report_desc_country', 'report_label_country', 'report_country_all', 'report_country_unknown', 'report_col_device_list', 'report_sum_country', 'report_geo_hint', 'report_export_error', 'report_type_country', 'report_desc_products'] as $k): ?>
        <?= $k ?>: <?= json_encode(__($k), JSON_UNESCAPED_UNICODE) ?>,
<?php endforeach; ?>
    };

    const $ = function (id) { return document.getElementById(id); };
    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }
    function fmt(str, args) {
        let i = 0;
        return str.replace(/%[sd]/g, function () { const v = args[i++]; return v === undefined ? '' : String(v); });
    }
    function post(params) {
        const fd = new FormData();
        fd.append('ajax', 'get_report');
        Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
        return fetch('', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    // ---- Описание отчётов ----
    // param: 'device' | 'ip' (текстовый запрос с подсказками), 'days' (срок), '' (без параметров)
    // cols: k — поле строки, t — тип (device, ip, country, num, time, text, mono)
    const C = {
        device:   { k: 'device',   l: T.report_col_device,   t: 'device' },
        ip:       { k: 'ip',       l: T.report_col_ip,       t: 'ip' },
        country:  { k: 'country',  l: T.report_col_country,  t: 'country' },
        count:    { k: 'count',    l: T.report_col_count,    t: 'num' },
        first:    { k: 'first',    l: T.report_col_first,    t: 'time' },
        last:     { k: 'last',     l: T.report_col_last,     t: 'time' },
        ips:      { k: 'ips',      l: T.report_col_ips,      t: 'num' },
        devices:  { k: 'devices',  l: T.report_col_devices,  t: 'num' },
        daysAgo:  { k: 'days_ago', l: T.report_col_days_ago, t: 'num' },
        lastIp:   { k: 'last_ip',  l: T.report_col_last_ip,  t: 'ip' },
        ipList:   { k: 'ip_list',  l: T.report_col_ip_list,  t: 'muted' },
        devList:  { k: 'devices',  l: T.report_col_device_list, t: 'devlist' },
        product:  { k: 'product',  l: T.report_col_product,  t: 'text' }
    };
    const REPORTS = [
        { id: 'device_ips',  name: T.report_mode_device,       desc: T.report_desc_device_ips,  param: 'device' },
        { id: 'ip_devices',  name: T.report_mode_ip,           desc: T.report_desc_ip_devices,  param: 'ip' },
        { id: 'multi_ip',    name: T.report_type_multi_ip,     desc: T.report_desc_multi_ip,    param: '',
          cols: [C.device, C.ips, C.count, C.last, C.ipList] },
        { id: 'shared_ip',   name: T.report_type_shared_ip,    desc: T.report_desc_shared_ip,   param: '',
          cols: [C.ip, C.country, C.devices, C.count, C.last] },
        { id: 'top_devices', name: T.report_type_top_devices,  desc: T.report_desc_top_devices, param: '',
          cols: [C.device, C.count, C.ips, C.first, C.last] },
        { id: 'inactive',    name: T.report_type_inactive,     desc: T.report_desc_inactive,    param: 'days', daysLabel: T.report_label_days_inactive, days: [30, 60, 90, 180, 365], defDays: 90,
          cols: [C.device, C.last, C.daysAgo, C.count, C.lastIp] },
        { id: 'new_devices', name: T.report_type_new,          desc: T.report_desc_new,         param: 'days', daysLabel: T.report_label_days_new, days: [7, 14, 30, 90], defDays: 30,
          cols: [C.device, C.first, C.last, C.count, C.ips] },
        { id: 'products',    name: T.report_type_products,     desc: T.report_desc_products,    param: '',
          cols: [C.product, C.devices, C.count] },
        { id: 'by_country',  name: T.report_type_country,      desc: T.report_desc_country,     param: 'country',
          cols: [C.country, C.ip, C.devList, C.count, C.last] }
    ];
    const byId = {};
    REPORTS.forEach(function (r) { byId[r.id] = r; });
    let cur = REPORTS[0];
    let days = 30;
    let country = '';
    let countryDd = null;
    const suggest = { device: [], ip: [] };
    let listsLoaded = false;

    // ---- Выпадающие списки в стиле панели ----
    // Список выносится в <body> и позиционируется по кнопке (position: fixed): его не обрезает прокрутка окна
    // и не перекрывают соседние блоки.
    const closers = [];
    function closeAll(except) { closers.forEach(function (c) { if (c !== except) c(); }); }
    document.addEventListener('click', function () { closeAll(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
    window.addEventListener('resize', function () { closeAll(); });
    window.addEventListener('scroll', function (e) {
        if (e.target && e.target.closest && e.target.closest('.rep-pop')) return;   // прокрутка самого списка
        closeAll();
    }, true);

    function placePop(anchor, pop) {
        const r = anchor.getBoundingClientRect();
        pop.style.minWidth = r.width + 'px';
        pop.style.left = r.left + 'px';
        pop.style.display = 'block';
        const below = window.innerHeight - r.bottom - 12;
        const above = r.top - 12;
        const h = Math.min(pop.scrollHeight + 10, 340);
        if (h > below && above > below) {            // снизу тесно — открываем вверх
            pop.style.maxHeight = Math.min(340, above) + 'px';
            pop.style.top = 'auto';
            pop.style.bottom = (window.innerHeight - r.top + 4) + 'px';
        } else {
            pop.style.maxHeight = Math.min(340, Math.max(120, below)) + 'px';
            pop.style.bottom = 'auto';
            pop.style.top = (r.bottom + 4) + 'px';
        }
    }

    function makeDropdown(box, options, value, onChange) {
        let val = value;
        box.textContent = '';
        const btn = el('button', 'rep-dd-btn');
        btn.type = 'button';
        const label = el('span');
        btn.appendChild(label);
        btn.appendChild(el('span', 'arrow', '▼'));
        box.appendChild(btn);
        const pop = el('div', 'rep-pop');
        pop.style.display = 'none';
        document.body.appendChild(pop);

        function close() { pop.style.display = 'none'; box.classList.remove('open'); }
        function paint() {
            pop.textContent = '';
            options.forEach(function (o) {
                const d = el('div', 'rep-opt' + (o.value === val ? ' sel' : ''), o.label);
                d.onclick = function (e) { e.stopPropagation(); val = o.value; set(val); close(); onChange(val); };
                pop.appendChild(d);
            });
        }
        function set(v) {
            val = v;
            const o = options.filter(function (x) { return x.value === v; })[0];
            label.textContent = o ? o.label : '';
        }
        btn.onclick = function (e) {
            e.stopPropagation();
            const wasOpen = pop.style.display !== 'none';
            closeAll();
            if (!wasOpen) { paint(); placePop(btn, pop); box.classList.add('open'); }
        };
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
        const closer = close;
        closers.push(closer);
        set(value);
        return { set: set, destroy: function () { close(); pop.remove(); const i = closers.indexOf(closer); if (i >= 0) closers.splice(i, 1); } };
    }

    // ---- Поле с подсказками (устройства / IP) ----
    const input = $('reportInput');
    const comboList = el('div', 'rep-pop');
    comboList.style.display = 'none';
    document.body.appendChild(comboList);
    let hl = -1;
    function comboClose() { comboList.style.display = 'none'; hl = -1; input.parentNode.classList.remove('open'); }
    closers.push(comboClose);
    // filtered = true — пользователь печатает: показываем совпадения; иначе (клик, фокус, стрелка) — весь список,
    // а не только ранее выбранное значение, которое осталось в поле
    function comboOpen(filtered) {
        const src = suggest[cur.param] || [];
        const q = filtered === true ? input.value.trim().toLowerCase() : '';
        const items = src.filter(function (v) { return !q || v.toLowerCase().indexOf(q) !== -1; }).slice(0, 200);
        comboList.textContent = '';
        hl = -1;
        if (!items.length) { comboList.style.display = 'none'; return; }
        items.forEach(function (v) {
            const d = el('div', 'rep-opt' + (v === input.value.trim() ? ' sel' : ''), v);
            d.onclick = function (e) { e.stopPropagation(); input.value = v; comboClose(); run(); };
            comboList.appendChild(d);
        });
        placePop(input, comboList);
        input.parentNode.classList.add('open');
    }
    input.addEventListener('focus', function () { closeAll(comboClose); input.select(); comboOpen(false); });
    input.addEventListener('click', function (e) { e.stopPropagation(); closeAll(comboClose); if (comboList.style.display === 'none') { input.select(); comboOpen(false); } });
    input.addEventListener('input', function () { comboOpen(true); });
    $('repComboArrow').addEventListener('click', function (e) {
        e.stopPropagation();
        if (comboList.style.display !== 'none') { comboClose(); return; }
        closeAll(comboClose);
        input.focus();          // фокус сам откроет полный список
        if (comboList.style.display === 'none') comboOpen(false);
    });
    comboList.addEventListener('click', function (e) { e.stopPropagation(); });
    input.addEventListener('keydown', function (e) {
        const opts = comboList.querySelectorAll('.rep-opt');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (comboList.style.display === 'none') { comboOpen(false); return; }
            if (!opts.length) return;
            hl = (hl + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
            opts.forEach(function (o, i) { o.classList.toggle('hl', i === hl); });
            opts[hl].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (hl >= 0 && opts[hl]) input.value = opts[hl].textContent;
            comboClose();
            run();
        }
    });

    // ---- Выбор отчёта ----
    const typeDd = makeDropdown($('repType'), REPORTS.map(function (r) { return { value: r.id, label: r.name }; }), cur.id, function (id) { selectReport(id); });
    let daysDd = null;

    function selectReport(id, keepInput) {
        cur = byId[id];
        typeDd.set(id);
        $('repDesc').textContent = cur.desc;
        const textual = cur.param === 'device' || cur.param === 'ip';
        $('repQueryWrap').style.display = textual ? '' : 'none';
        $('repDaysWrap').style.display = cur.param === 'days' ? '' : 'none';
        $('repCountryWrap').style.display = cur.param === 'country' ? '' : 'none';
        if (textual) {
            $('repQueryLabel').textContent = cur.param === 'device' ? T.report_input_device : T.report_input_ip;
            input.placeholder = cur.param === 'device' ? T.report_input_device : T.report_input_ip;
            if (!keepInput) input.value = '';
        }
        if (cur.param === 'days') {
            days = cur.defDays;
            $('repDaysLabel').textContent = cur.daysLabel;
            if (daysDd) daysDd.destroy();
            daysDd = makeDropdown($('repDays'), cur.days.map(function (d) { return { value: d, label: fmt(T.report_days_n, [d]) }; }), days, function (v) { days = v; run(); });
            $('repDays').classList.add('sm');
        }
        comboClose();
        clearResult();
        if (cur.param === 'country') { loadCountries(); return; }
        if (!textual) run();
    }

    function loadCountries() {
        const rep = cur;
        post({ report: 'countries' }).then(function (d) {
            if (rep !== cur || !d.success) return;
            const opts = [{ value: '', label: T.report_country_all }];
            (d.countries || []).forEach(function (c) {
                const name = c.country === '' ? T.report_country_unknown : ((c.flag ? c.flag + ' ' : '') + c.country);
                opts.push({ value: c.country === '' ? '__unknown__' : c.country, label: name + ' · ' + c.ips + ' IP · ' + c.count });
            });
            country = '';
            if (countryDd) countryDd.destroy();
            countryDd = makeDropdown($('repCountry'), opts, country, function (v) { country = v; run(); });
            run();
        }).catch(function () { showMessage(T.report_error); });
    }

    function clearResult() {
        $('reportHead').style.display = 'none';
        $('reportResult').textContent = '';
    }
    function showMessage(text) {
        $('reportHead').style.display = 'none';
        const r = $('reportResult');
        r.textContent = '';
        r.appendChild(el('div', 'report-empty', text));
    }

    // ---- Отрисовка ----
    function cell(col, row) {
        const v = row[col.k];
        let td;
        if (col.t === 'device') {
            td = el('td');
            const a = el('span', 'report-link', v);
            a.onclick = function () { openDrill('device_ips', v); };
            td.appendChild(a);
        } else if (col.t === 'ip') {
            td = el('td', 'mono');
            if (v) {
                const a = el('span', 'report-link', v);
                a.onclick = function () { openDrill('ip_devices', v); };
                td.appendChild(a);
            } else td.textContent = '—';
        } else if (col.t === 'country') {
            td = el('td', '', v ? ((row.flag ? row.flag + ' ' : '') + v) : '—');
        } else if (col.t === 'devlist') {
            td = el('td');
            (v || []).forEach(function (name, i) {
                if (i) td.appendChild(document.createTextNode(', '));
                const a = el('span', 'report-link', name);
                a.onclick = function () { openDrill('device_ips', name); };
                td.appendChild(a);
            });
            if (row.devices_more) td.appendChild(el('span', 'muted', ' +' + row.devices_more));
        } else if (col.t === 'num') {
            td = el('td', 'num', v === null || v === undefined ? '—' : String(v));
        } else if (col.t === 'time') {
            td = el('td', 'mono', v || '—');
        } else if (col.t === 'muted') {
            td = el('td', 'muted', v || '—');
        } else {
            td = el('td', '', v === undefined || v === '' ? '—' : String(v));
        }
        return td;
    }

    function table(cols, rows) {
        const wrap = el('div', 'report-table-wrap');
        const tb = el('table', 'report-table');
        const trh = el('tr');
        cols.forEach(function (c) { trh.appendChild(el('th', c.t === 'num' ? 'num' : '', c.l)); });
        const thead = el('thead'); thead.appendChild(trh); tb.appendChild(thead);
        const tbody = el('tbody');
        rows.forEach(function (row) {
            const tr = el('tr');
            cols.forEach(function (c) { tr.appendChild(cell(c, row)); });
            tbody.appendChild(tr);
        });
        tb.appendChild(tbody);
        wrap.appendChild(tb);
        return wrap;
    }

    // ---- Экспорт: выгружается ровно то, что показано в таблице ----
    let lastExport = null;
    function cellText(col, row) {
        const v = row[col.k];
        if (col.t === 'country') return v || '';                       // без флага-эмодзи
        if (col.t === 'devlist') return (v || []).join(', ') + (row.devices_more ? ' +' + row.devices_more : '');
        return v === undefined || v === null ? '' : String(v);
    }
    function exportTitle(d, q) {
        let t = cur.name;
        if (cur.id === 'device_ips') t += ' — ' + ((d.matched && d.matched.length === 1) ? d.matched[0] : q);
        else if (cur.id === 'ip_devices') t += ' — ' + q;
        else if (cur.param === 'days') t += ' — ' + fmt(T.report_days_n, [days]);
        else if (cur.id === 'by_country') t += ' — ' + (country === '' ? T.report_country_all : (country === '__unknown__' ? T.report_country_unknown : country));
        return t;
    }
    function buildExport(cols, rows, d, q) {
        return {
            name: cur.id,
            title: exportTitle(d, q),
            headers: cols.map(function (c) { return c.l; }),
            types: cols.map(function (c) { return c.t === 'num' ? 'num' : 'text'; }),
            rows: rows.map(function (row) { return cols.map(function (c) { return cellText(c, row); }); })
        };
    }
    function download(name, mime, b64) {
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([bytes], { type: mime }));
        a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    }
    function exportReport(fmtName) {
        if (!lastExport) return;
        const btns = document.querySelectorAll('.rep-export');
        btns.forEach(function (b) { b.disabled = true; });
        $('reportExportMsg').textContent = '';
        const fd = new FormData();
        fd.append('ajax', 'export_report');
        fd.append('format', fmtName);
        fd.append('data', JSON.stringify(lastExport));
        fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error('fail');
                download(d.filename, d.mime, d.data);
            })
            .catch(function () { $('reportExportMsg').textContent = T.report_export_error; })
            .then(function () { btns.forEach(function (b) { b.disabled = false; }); });
    }
    document.querySelectorAll('.rep-export').forEach(function (b) { b.onclick = function () { exportReport(b.dataset.fmt); }; });

    function render(d, q) {
        const rows = d.rows || [];
        if (!rows.length) { showMessage(T.report_not_found); return; }
        const sum = $('reportSummary');
        let cols = cur.cols;
        let requests = 0;
        rows.forEach(function (x) { requests += x.count || 0; });
        if (cur.id === 'device_ips') {
            const multi = d.matched && d.matched.length > 1;
            const ips = {};
            rows.forEach(function (x) { ips[x.ip] = 1; });
            sum.textContent = multi
                ? fmt(T.report_sum_devices, [d.matched.length, Object.keys(ips).length, requests])
                : fmt(T.report_sum_device, [rows[0].device, Object.keys(ips).length, requests]);
            cols = (multi ? [C.device] : []).concat([C.ip, C.country, C.count, C.first, C.last]);
        } else if (cur.id === 'ip_devices') {
            sum.textContent = fmt(T.report_sum_ip, [(d.flag ? d.flag + ' ' : '') + q + (d.country ? ' (' + d.country + ')' : ''), rows.length, requests]);
            cols = [C.device, C.count, C.first, C.last];
        } else if (cur.id === 'by_country') {
            const sm = d.summary || {};
            const label = country === '' ? T.report_country_all : (country === '__unknown__' ? T.report_country_unknown : country);
            sum.textContent = fmt(T.report_sum_country, [label, sm.ips || 0, sm.devices || 0, sm.requests || 0]);
        } else {
            sum.textContent = d.total ? fmt(T.report_sum_rows_limit, [d.total, rows.length]) : fmt(T.report_sum_rows, [rows.length]);
        }
        $('reportHead').style.display = 'flex';
        $('reportExportMsg').textContent = '';
        lastExport = buildExport(cols, rows, d, q);
        const r = $('reportResult');
        r.textContent = '';
        r.appendChild(table(cols, rows));
    }

    function run() {
        const rep = cur;
        const params = { report: rep.id };
        if (rep.param === 'device' || rep.param === 'ip') {
            const q = input.value.trim();
            if (!q) { showMessage(T.report_empty_query); return; }
            params.q = q;
        }
        if (rep.param === 'days') params.days = days;
        if (rep.param === 'country') params.q = country;
        showMessage(T.report_loading);
        post(params).then(function (d) {
            if (rep !== cur) return;   // отчёт уже переключили
            if (!d.success) { showMessage(rep.param === 'ip' ? T.report_bad_ip : T.report_error); return; }
            render(d, params.q);
            if (rep.id === 'by_country' && d.rows && d.rows.some(function (x) { return !x.country; }) && country === '') {
                const h = el('div', 'report-hint', T.report_geo_hint);
                $('reportResult').appendChild(h);
            }
        }).catch(function () { showMessage(T.report_error); });
    }

    function openDrill(id, value) {
        input.value = value;
        selectReport(id, true);
        input.value = value;
        run();
    }

    function loadLists() {
        if (listsLoaded) return;
        listsLoaded = true;
        post({ report: 'lists' }).then(function (d) {
            if (!d.success) { listsLoaded = false; return; }
            suggest.device = d.devices || [];
            suggest.ip = d.ips || [];
        }).catch(function () { listsLoaded = false; });
    }

    // Блок отчётов занимает всё оставшееся место окна; таблица прокручивается внутри
    function fitReports() {
        const block = $('repBlock');
        if (!block || block.offsetParent === null) return;
        const cont = block.closest('.settings-content');
        const top = block.getBoundingClientRect().top;
        let bottom = window.innerHeight;
        let pad = 0;
        if (cont) {
            bottom = cont.getBoundingClientRect().bottom;
            pad = parseFloat(getComputedStyle(cont).paddingBottom) || 0;
        }
        block.style.height = Math.max(260, Math.floor(bottom - top - pad - 2)) + 'px';
    }
    window.addEventListener('resize', fitReports);
    window.fitStatsReports = fitReports;

    window.switchStatsPage = function (page) {
        document.querySelectorAll('.stats-page-btn').forEach(function (b) { b.classList.toggle('active', b.dataset.page === page); });
        $('statsPageOverview').classList.toggle('active', page === 'overview');
        $('statsPageReports').classList.toggle('active', page === 'reports');
        closeAll();
        if (page === 'reports') { loadLists(); setTimeout(fitReports, 0); }
    };

    $('reportRun').onclick = run;
    selectReport(cur.id);
})();
</script>
