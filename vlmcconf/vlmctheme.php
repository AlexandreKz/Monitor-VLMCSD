<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmctheme.php
// ВЕРСИЯ: 1.6.2
// ДАТА: 2026-11-17
// @description: Библиотека тем для KMS Monitor (добавлены Summer и Autumn)
// ============================================

function getThemeCSSBase($theme) {
    switch ($theme) {
        case 'light':
            return [
                'name' => 'theme_light',
                'bg' => '#f0f4f8',
                'card' => '#ffffff',
                'text' => '#1e293b',
                'border' => '#cbd5e1',
                'header' => 'linear-gradient(145deg, #e2e8f0 0%, #d1d9e6 100%)',
                'input' => '#ffffff',
                'inputBorder' => '#94a3b8',
                'success' => '#059669',
                'warning' => '#d97706',
                'danger' => '#dc2626',
                'shadow' => '0 4px 6px rgba(0,0,0,0.05)',
                'hover' => '#f1f5f9',
                'menuBg' => '#e2e8f0',
                'menuHover' => '#d1d9e6',
                'menuText' => '#1e293b',
                'primary' => '#3b82f6',
                'logBg' => '#f8fafc',
                'logText' => '#1e293b'
            ];
            
        case 'gray':
            return [
                'name' => 'theme_gray',
                'bg' => '#1e1e1e',
                'card' => '#2a2a2a',
                'text' => '#d4d4d4',
                'border' => '#3a3a3a',
                'header' => 'linear-gradient(145deg, #2a2a2a 0%, #1f1f1f 100%)',
                'input' => '#2a2a2a',
                'inputBorder' => '#3a3a3a',
                'success' => '#6b7280',
                'warning' => '#9ca3af',
                'danger' => '#4b5563',
                'shadow' => '0 4px 15px rgba(0,0,0,0.5)',
                'hover' => '#3a3a3a',
                'menuBg' => '#252525',
                'menuHover' => '#3a3a3a',
                'menuText' => '#d4d4d4',
                'accent' => '#6b7280',
                'primary' => '#3b82f6',
                'logBg' => '#1e1e1e',
                'logText' => '#d4d4d4'
            ];
            
        case 'ash_and_flame':
            return [
                'name' => 'theme_ash_flame',
                'bg' => '#1a1a1a',
                'card' => '#262626',
                'text' => '#f0f0f0',
                'border' => '#f97316',
                'header' => 'linear-gradient(145deg, #d95f0e 0%, #b45309 100%)',
                'input' => '#1f1f1f',
                'inputBorder' => '#f97316',
                'success' => '#f97316',
                'warning' => '#f97316',
                'danger' => '#ef4444',
                'shadow' => '0 4px 15px rgba(217, 95, 14, 0.3)',
                'hover' => '#2d2d2d',
                'menuBg' => '#1f1f1f',
                'menuHover' => '#f97316',
                'menuText' => '#f0f0f0',
                'accent' => '#f97316',
                'primary' => '#9a3412',
                'primary_hover' => '#b45309',
                'logBg' => '#1a1a1a',
                'logText' => '#f0f0f0'
            ];
            
        case 'night_elf':
            return [
                'name' => 'theme_night_elf',
                'bg' => '#1a1a2e',
                'card' => '#16213e',
                'text' => '#e0e0ff',
                'border' => '#4a4a8a',
                'header' => 'linear-gradient(145deg, #0f3460 0%, #16213e 100%)',
                'input' => '#16213e',
                'inputBorder' => '#4a4a8a',
                'success' => '#a855f7',
                'warning' => '#8b5cf6',
                'danger' => '#ec4899',
                'shadow' => '0 4px 15px rgba(106, 90, 205, 0.3)',
                'hover' => '#1e2a5a',
                'menuBg' => '#0f1a2f',
                'menuHover' => '#1a2a4a',
                'menuText' => '#e0e0ff',
                'accent' => '#a855f7',
                'primary' => '#3b82f6',
                'logBg' => '#1a1a2e',
                'logText' => '#e0e0ff'
            ];
            
        case 'corporate':
            return [
                'name' => 'theme_corporate',
                'bg' => '#0b1a2f',
                'card' => '#132433',
                'text' => '#e2e8f0',
                'border' => '#2d4b6e',
                'header' => 'linear-gradient(145deg, #1e3a5f 0%, #0f2b44 100%)',
                'input' => '#1e2e42',
                'inputBorder' => '#3a5670',
                'success' => '#0891b2',
                'warning' => '#d97706',
                'danger' => '#dc2626',
                'shadow' => '0 4px 20px rgba(0,20,40,0.4)',
                'hover' => '#1e3a5f',
                'menuBg' => '#0f1a28',
                'menuHover' => '#1a2b3c',
                'menuText' => '#e2e8f0',
                'primary' => '#3b82f6',
                'logBg' => '#0b1a2f',
                'logText' => '#e2e8f0'
            ];
            
        case 'business_classic':
            return [
                'name' => 'theme_business_classic',
                'bg' => '#0f172a',
                'card' => '#1e293b',
                'text' => '#f1f5f9',
                'border' => '#334155',
                'header' => 'linear-gradient(145deg, #1e293b 0%, #0f172a 100%)',
                'input' => '#0f172a',
                'inputBorder' => '#334155',
                'success' => '#3b82f6',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
                'shadow' => '0 4px 6px rgba(0,0,0,0.3)',
                'hover' => '#334155',
                'menuBg' => '#0f172a',
                'menuHover' => '#1e293b',
                'menuText' => '#f1f5f9',
                'accent' => '#3b82f6',
                'primary' => '#3b82f6',
                'logBg' => '#0f172a',
                'logText' => '#f1f5f9'
            ];
            
        case 'silver_standard':
            return [
                'name' => 'theme_silver_standard',
                'bg' => '#2c2c2c',
                'card' => '#3a3a3a',
                'text' => '#e8e8e8',
                'border' => '#5a5a5a',
                'header' => 'linear-gradient(145deg, #4a4a4a 0%, #3a3a3a 100%)',
                'input' => '#3a3a3a',
                'inputBorder' => '#5a5a5a',
                'success' => '#2ecc71',
                'warning' => '#f39c12',
                'danger' => '#e74c3c',
                'shadow' => '0 4px 6px rgba(0,0,0,0.5)',
                'hover' => '#4a4a4a',
                'menuBg' => '#323232',
                'menuHover' => '#424242',
                'menuText' => '#e8e8e8',
                'accent' => '#808080',
                'primary' => '#3b82f6',
                'logBg' => '#2c2c2c',
                'logText' => '#e8e8e8'
            ];
            
        case 'dark_quartz':
            return [
                'name' => 'theme_dark_quartz',
                'bg' => '#0a0a0a',
                'card' => '#141414',
                'text' => '#e0e0e0',
                'border' => '#2a2a2a',
                'header' => 'linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%)',
                'input' => '#141414',
                'inputBorder' => '#2a2a2a',
                'success' => '#2ecc71',
                'warning' => '#f39c12',
                'danger' => '#e74c3c',
                'shadow' => '0 4px 6px rgba(0,0,0,0.6)',
                'hover' => '#1a1a1a',
                'menuBg' => '#0f0f0f',
                'menuHover' => '#1a1a1a',
                'menuText' => '#e0e0e0',
                'accent' => '#5a5a5a',
                'primary' => '#3b82f6',
                'logBg' => '#0a0a0a',
                'logText' => '#e0e0e0'
            ];
            
        case 'burgundy_gray':
            return [
                'name' => 'theme_cabernet_sauvignon',
                'bg' => '#2a2a2a',
                'card' => '#3a3a3a',
                'text' => '#e8e8e8',
                'border' => '#A0000F',
                'header' => 'linear-gradient(145deg, #4a2a2a 0%, #3a2a2a 100%)',
                'input' => '#2a2a2a',
                'inputBorder' => '#A0000F',
                'success' => '#A0000F',
                'warning' => '#A0000F',
                'danger' => '#A0000F',
                'shadow' => '0 4px 15px rgba(160, 0, 15, 0.3)',
                'hover' => '#4a2a2a',
                'menuBg' => '#1f1f1f',
                'menuHover' => '#A0000F',
                'menuText' => '#e8e8e8',
                'accent' => '#A0000F',
                'primary' => '#A0000F',
                'primary_hover' => '#c0001f',
                'logBg' => '#1f1f1f',
                'logText' => '#e8e8e8'
            ];
            
        case 'spring':
            return [
                'name' => 'theme_spring',
                'bg' => '#e8f5e9',
                'card' => '#ffffff',
                'text' => '#2e7d32',
                'border' => '#c8e6c9',
                'header' => 'linear-gradient(145deg, #a5d6a7 0%, #81c784 100%)',
                'input' => '#ffffff',
                'inputBorder' => '#81c784',
                'success' => '#4caf50',
                'warning' => '#ff9800',
                'danger' => '#f44336',
                'shadow' => '0 4px 15px rgba(76, 175, 80, 0.2)',
                'hover' => '#f1f8e9',
                'menuBg' => '#c8e6c9',
                'menuHover' => '#a5d6a7',
                'menuText' => '#2e7d32',
                'accent' => '#4caf50',
                'primary' => '#4caf50',
                'primary_hover' => '#388e3c',
                'logBg' => '#f1f8e9',
                'logText' => '#1b5e20'
            ];
            
        case 'winter':
            return [
                'name' => 'theme_winter',
                'bg' => '#e3f2fd',
                'card' => '#ffffff',
                'text' => '#01579b',
                'border' => '#b3e5fc',
                'header' => 'linear-gradient(145deg, #81d4fa 0%, #4fc3f7 100%)',
                'input' => '#ffffff',
                'inputBorder' => '#81d4fa',
                'success' => '#4caf50',
                'warning' => '#ff9800',
                'danger' => '#f44336',
                'shadow' => '0 4px 15px rgba(3, 169, 244, 0.2)',
                'hover' => '#e1f5fe',
                'menuBg' => '#b3e5fc',
                'menuHover' => '#81d4fa',
                'menuText' => '#01579b',
                'accent' => '#03a9f4',
                'primary' => '#03a9f4',
                'primary_hover' => '#0288d1',
                'logBg' => '#e1f5fe',
                'logText' => '#0277bd'
            ];

		case 'summer':
			return [
				'name' => 'theme_summer',
				'bg' => '#e6f7f0',
				'card' => '#ffffff',
				'text' => '#2d6a4f',
				'border' => '#95d5b2',
				'header' => 'linear-gradient(145deg, #d8f3dc 0%, #b7e4c7 100%)',
				'input' => '#ffffff',
				'inputBorder' => '#95d5b2',
				'success' => '#2ecc71',
				'warning' => '#f4a261',
				'danger' => '#e76f51',
				'shadow' => '0 4px 15px rgba(46, 204, 113, 0.15)',
				'hover' => '#d8f3dc',
				'menuBg' => '#cfe8dc',
				'menuHover' => '#b7e4c7',
				'menuText' => '#2d6a4f',
				'accent' => '#2ecc71',
				'primary' => '#2ecc71',
				'primary_hover' => '#27ae60',
				'logBg' => '#d8f3dc',
				'logText' => '#1b4332'
			];
	
        case 'autumn':
            return [
                'name' => 'theme_autumn',
                'bg' => '#fef5e7',
                'card' => '#ffffff',
                'text' => '#a0522d',
                'border' => '#e6b17e',
                'header' => 'linear-gradient(145deg, #e6b17e 0%, #c97e5a 100%)',
                'input' => '#ffffff',
                'inputBorder' => '#c97e5a',
                'success' => '#6b8e23',
                'warning' => '#ff8c00',
                'danger' => '#cd5c5c',
                'shadow' => '0 4px 15px rgba(201, 126, 90, 0.3)',
                'hover' => '#fae6d3',
                'menuBg' => '#e6b17e',
                'menuHover' => '#c97e5a',
                'menuText' => '#8b4513',
                'accent' => '#cd7a3a',
                'primary' => '#cd7a3a',
                'primary_hover' => '#b85c1a',
                'logBg' => '#fae6d3',
                'logText' => '#8b4513'
            ];
            
        case 'dark':
        default:
            return [
                'name' => 'theme_dark',
                'bg' => '#1a2634',
                'card' => '#1f2e3c',
                'text' => '#e1e9f0',
                'border' => '#33485d',
                'header' => 'linear-gradient(145deg, #253240 0%, #1e2a36 100%)',
                'input' => '#1e2a36',
                'inputBorder' => '#2f4052',
                'success' => '#2ecc71',
                'warning' => '#f39c12',
                'danger' => '#e74c3c',
                'shadow' => '0 4px 6px rgba(0,0,0,0.3)',
                'hover' => '#2d3f52',
                'menuBg' => '#151f2b',
                'menuHover' => '#1f2e3c',
                'menuText' => '#e1e9f0',
                'primary' => '#3b82f6',
                'logBg' => '#1b2632',
                'logText' => '#d6e2f0'
            ];
			
		case 'poker':
			return [
				'name' => 'theme_poker',
				'bg' => '#f5f5f5',
				'card' => '#ffffff',
				'text' => '#1a1a1a',
				'border' => '#c0c0c0',
				'header' => 'linear-gradient(145deg, #e8e8e8 0%, #d4d4d4 100%)',
				'input' => '#ffffff',
				'inputBorder' => '#b0b0b0',
				'success' => '#2ecc71',
				'warning' => '#f39c12',
				'danger' => '#e74c3c',
				'shadow' => '0 4px 15px rgba(0,0,0,0.1)',
				'hover' => '#f0f0f0',
				'menuBg' => '#e0e0e0',
				'menuHover' => '#d0d0d0',
				'menuText' => '#1a1a1a',
				'accent' => '#c0392b',
				'primary' => '#c0392b',
				'primary_hover' => '#a93226',
				'logBg' => '#fafafa',
				'logText' => '#1a1a1a'
			];
			
		case 'corporate_blue':
			// В духе тёмной темы веб-интерфейса Keenetic: графитово-синий фон, светлый текст, яркий синий акцент
			return [
				'name' => 'theme_corporate_blue',
				'bg' => '#181c24',
				'card' => '#222733',
				'text' => '#e4e8ef',
				'border' => '#323a4a',
				'header' => 'linear-gradient(145deg, #222733 0%, #1c212b 100%)',
				'input' => '#1a1f29',
				'inputBorder' => '#363f51',
				'success' => '#2fbf71',
				'warning' => '#f0a020',
				'danger' => '#e5484d',
				'shadow' => '0 4px 15px rgba(0,0,0,0.35)',
				'hover' => '#2a3040',
				'menuBg' => '#1c212b',
				'menuHover' => '#262d3b',
				'menuText' => '#c3cad6',
				'accent' => '#2d8cf0',
				'primary' => '#2d8cf0',
				'primary_hover' => '#1f74d0',
				'logBg' => '#14181f',
				'logText' => '#c9d1dc'
			];	
	}
}

// Функция для получения списка всех доступных тем
function getAvailableThemes() {
    return [
        'dark' => 'theme_dark',
        'light' => 'theme_light',
        'gray' => 'theme_gray',
        'ash_and_flame' => 'theme_ash_flame',
        'night_elf' => 'theme_night_elf',
        'corporate' => 'theme_corporate',
        'business_classic' => 'theme_business_classic',
        'silver_standard' => 'theme_silver_standard',
        'dark_quartz' => 'theme_dark_quartz',
        'burgundy_gray' => 'theme_cabernet_sauvignon',
        'spring' => 'theme_spring',
        'winter' => 'theme_winter',
        'summer' => 'theme_summer',
        'autumn' => 'theme_autumn',
		'poker' => 'theme_poker',
		'corporate_blue' => 'theme_corporate_blue',
    ];
}
// ---------- производные цвета: читаемость в любой теме (палитры не меняются, добавляются только запасные цвета) ----------

function themeLum($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
    if (strlen($hex) !== 6) { return 0.5; }
    $l = 0;
    foreach ([0.2126, 0.7152, 0.0722] as $i => $k) {
        $v = hexdec(substr($hex, $i * 2, 2)) / 255;
        $l += $k * ($v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4));
    }
    return $l;
}

function themeContrast($a, $b) {
    $x = themeLum($a); $y = themeLum($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

/** Смесь двух цветов: $t = 0 — $a, 1 — $b. */
function themeMix($a, $b, $t) {
    $a = ltrim($a, '#'); $b = ltrim($b, '#');
    $o = '#';
    for ($i = 0; $i < 3; $i++) {
        $x = hexdec(substr($a, $i * 2, 2)); $y = hexdec(substr($b, $i * 2, 2));
        $o .= str_pad(dechex((int)round($x + ($y - $x) * $t)), 2, '0', STR_PAD_LEFT);
    }
    return $o;
}

function getThemeCSS($theme) {
    $c = getThemeCSSBase($theme);
    $bgs = [$c['card'], $c['bg'], $c['input'], $c['hover']];
    $min = function ($fg) use ($bgs) { $m = 99; foreach ($bgs as $b) { $m = min($m, themeContrast($fg, $b)); } return $m; };
    $dark = themeLum($c['card']) < 0.3;
    // второстепенный текст (подписи): прежний серо-голубой, если читается, иначе светлее/темнее по теме
    $muted = '#8aa0bb';
    if ($min($muted) < 3.5) {
        foreach ($dark ? ['#a3b4c8', '#b8c5d6', '#cfd8e3'] : ['#6b7f96', '#5b6f86', '#4a5d73'] as $cand) {
            $muted = $cand;
            if ($min($cand) >= 3.5) { break; }
        }
    }
    // текст акцентного цвета на фоне карточек: сам акцент, если читается, иначе смесь с цветом текста
    $accent = $c['primary'];
    if (themeContrast($accent, $c['card']) < 3.0) {
        for ($t = 0.15; $t <= 1.0; $t += 0.15) {
            $accent = themeMix($c['primary'], $c['text'], $t);
            if (themeContrast($accent, $c['card']) >= 3.0) { break; }
        }
    }
    $on = function ($bg, $min = 2.0) { return themeContrast('#ffffff', $bg) >= $min ? '#ffffff' : '#111111'; };   // белый текст на цветной кнопке, пока он читается (иначе тёмный)
    // цвета статусов как текст на фоне карточек: свой цвет, если читается, иначе смесь с цветом текста
    $txt = function ($col) use ($c) {
        if (themeContrast($col, $c['card']) >= 3.0) { return $col; }
        for ($t = 0.15; $t <= 1.0; $t += 0.15) {
            $m = themeMix($col, $c['text'], $t);
            if (themeContrast($m, $c['card']) >= 3.0) { return $m; }
        }
        return $c['text'];
    };
    $c['okText'] = $txt($c['success']);
    $c['warnText'] = $txt($c['warning']);
    $c['dangerText'] = $txt($c['danger']);
    $c['muted'] = $muted;
    $c['accentText'] = $accent;
    $c['onPrimary'] = $on($c['primary'], 3.0);
    $c['onSuccess'] = $on($c['success']);
    $c['onWarning'] = $on($c['warning']);
    $c['onDanger'] = $on($c['danger']);
    return $c;
}
