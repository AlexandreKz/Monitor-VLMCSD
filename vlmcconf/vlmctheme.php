<?php
// ============================================
// ФАЙЛ: vlmcconf/vlmctheme.php
// ВЕРСИЯ: 1.5.2
// ДАТА: 2026-04-03
// @description: Библиотека тем для KMS Monitor (добавлены Summer и Autumn)
// ============================================

function getThemeCSS($theme) {
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
			return [
				'name' => 'theme_corporate_blue',
				'bg' => '#1b2434',
				'card' => '#1e2a3a',
				'text' => '#0097dc',
				'border' => '#2d4055',
				'header' => 'linear-gradient(145deg, #1b2434 0%, #16202e 100%)',
				'input' => '#1e2a3a',
				'inputBorder' => '#2d4055',
				'success' => '#2ecc71',
				'warning' => '#f39c12',
				'danger' => '#e74c3c',
				'shadow' => '0 4px 15px rgba(0,151,220,0.2)',
				'hover' => '#243449',
				'menuBg' => '#16202e',
				'menuHover' => '#1e2e42',
				'menuText' => '#0097dc',
				'accent' => '#0097dc',
				'primary' => '#0097dc',
				'primary_hover' => '#0077b3',
				'logBg' => '#1b2434',
				'logText' => '#0097dc'
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