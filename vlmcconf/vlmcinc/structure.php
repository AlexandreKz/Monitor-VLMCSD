<?php
// ============================================
// ФАЙЛ: vlmcinc/structure.php
// ВЕРСИЯ: 2.5.0
// ДАТА: 2026-03-26
// @description: Динамическая структура проекта (папки свёрнуты по умолчанию)
// ============================================

/**
 * Рекурсивное получение списка файлов в директории
 */
function getDirectoryStructure($dir, $baseDir = null, $ignore = ['.', '..', '.git', '.DS_Store', 'backups', 'cache', 'tmp', 'pic', 'scr', 'style', '.htaccess', 'index.php']) {
    if ($baseDir === null) {
        $baseDir = $dir;
    }
    
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }
    
    $result = [];
    $files = scandir($dir);
    
    if ($files === false) {
        return [];
    }
    
    foreach ($files as $file) {
        if (in_array($file, $ignore)) {
            continue;
        }
        
        $fullPath = $dir . '/' . $file;
        $relativePath = str_replace($baseDir, '', $fullPath);
        $relativePath = ltrim($relativePath, '/');
        
        if ($relativePath === '' && $dir === $baseDir) {
            $relativePath = $file;
        }
        
        if (is_dir($fullPath)) {
            $children = getDirectoryStructure($fullPath, $baseDir, $ignore);
            $result[] = [
                'type' => 'dir',
                'name' => $file,
                'path' => $relativePath,
                'fullPath' => $fullPath,
                'children' => $children
            ];
        } else {
            $result[] = [
                'type' => 'file',
                'name' => $file,
                'path' => $relativePath,
                'fullPath' => $fullPath,
                'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                'mtime' => file_exists($fullPath) ? filemtime($fullPath) : 0
            ];
        }
    }
    
    usort($result, function($a, $b) {
        if ($a['type'] === $b['type']) {
            return strcasecmp($a['name'], $b['name']);
        }
        return ($a['type'] === 'dir') ? -1 : 1;
    });
    
    return $result;
}

function getFileVersionFromContent($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) return '-';
    $content = @file_get_contents($filePath);
    if ($content === false) return '-';
    if (preg_match('~//\s*ВЕРСИЯ:\s*([0-9.]+)~', $content, $matches)) return $matches[1];
    if (preg_match('~/\*\s*@version\s+([0-9.]+)~', $content, $matches)) return $matches[1];
    return '-';
}

function getFileDescriptionFromContent($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) return null;
    $content = @file_get_contents($filePath);
    if ($content === false) return null;
    if (preg_match('~//\s*@description:\s*(.+)$~m', $content, $matches)) return trim($matches[1]);
    if (preg_match('~//\s*НАЗНАЧЕНИЕ:\s*(.+)$~m', $content, $matches)) return trim($matches[1]);
    return null;
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function getFileStability($filename) {
    $stableFiles = [
        // Основные файлы
        'vlmc.php' => 95,
        'vlmcconf.php' => 95,
        'login.php' => 100,
        'logout.php' => 100,
        
        // Библиотеки
        'vlmctheme.php' => 99,
        'vlmcgeoip.php' => 99,
        'vlmcloghandler.php' => 98,
        'flags.php' => 100,
        
        // Подключаемые модули
        'config.php' => 100,
        'structure.php' => 95,
        'ajax.php' => 95,
        'auth.php' => 100,
        'users.php' => 98,
        'analytics.php' => 95,
        
        // Секции панели управления
        'general.php' => 100,
        'groups.php' => 100,
        'devices.php' => 98,
        'security.php' => 95,
        'stats.php' => 95,
        'info.php' => 95,
        'documentation.php' => 95,
        
        // Языковые файлы
        'ru.php' => 98,
        'en.php' => 98,
        
        // Изображения и скрипты
        'timer.js' => 100,
        'favicon.png' => 100,
        
        // Документация (отдельный файл, не секция)
        'KMS Migration v2.9.html' => 100,
    ];
    return $stableFiles[$filename] ?? 80;
}

function getFileIcon($filename, $type) {
    if ($type === 'dir') return '📁';
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $icons = ['php' => '🐘', 'js' => '⚡', 'json' => '📋', 'log' => '📝', 'png' => '🖼️', 'jpg' => '🖼️', 'svg' => '🖼️', 'css' => '🎨', 'html' => '🌐', 'md' => '📄', 'txt' => '📄', 'sh' => '💻', 'py' => '🐍', 'htaccess' => '🔧'];
    if ($filename === '.htaccess') return '🔧';
    return $icons[$ext] ?? '📄';
}

/**
 * Рендер структуры проекта с возможностью сворачивания папок
 * Папки по умолчанию свёрнуты
 */
function renderFileStructure($themeCSS, $fileVersions, $configFile) {
    $baseDir = dirname(__DIR__, 2);
    $structure = getDirectoryStructure($baseDir, $baseDir);
    
    $renderItem = function($item, $level = 0, $parentId = '') use (&$renderItem, $themeCSS, $fileVersions) {
        $indent = $level * 20;
        $icon = getFileIcon($item['name'], $item['type']);
        $color = $item['type'] === 'dir' ? '#3b82f6' : '#8aa0bb';
        $itemId = 'folder_' . md5($item['fullPath']);
        
        // Получаем описание из файла или определяем по типу
        $description = getFileDescriptionFromContent($item['fullPath']);
        if (!$description && $item['type'] === 'dir') {
            $description = 'Папка';
        }
        if (!$description && $item['type'] === 'file') {
            $ext = pathinfo($item['name'], PATHINFO_EXTENSION);
            $typeNames = [
                'php' => 'PHP скрипт',
                'js' => 'JavaScript файл',
                'json' => 'JSON конфигурация',
                'log' => 'Лог-файл',
                'png' => 'Изображение',
                'jpg' => 'Изображение',
                'svg' => 'Векторное изображение',
                'css' => 'Таблица стилей',
                'html' => 'HTML документ',
                'md' => 'Документация',
                'txt' => 'Текстовый файл',
                'sh' => 'Shell скрипт',
                'py' => 'Python скрипт',
                'htaccess' => 'Конфигурация Apache'
            ];
            $description = $typeNames[$ext] ?? 'Файл';
        }
        
        if ($item['type'] === 'dir') {
            $hasChildren = !empty($item['children']);
            // По умолчанию иконка "▶" (свёрнуто)
            $toggleIcon = $hasChildren ? '▶' : '•';
            $toggleStyle = $hasChildren ? 'cursor: pointer;' : 'opacity: 0.5; cursor: default;';
            $childrenContainerId = $itemId . '_children';
            
            $html = '<div style="margin-top: 2px;">';
            $html .= '<div style="display: flex; align-items: center; gap: 10px; padding: 2px 0; color: ' . $color . '; margin-left: ' . $indent . 'px;">';
            $html .= '<span class="folder-toggle" data-folder="' . $childrenContainerId . '" style="width: 20px; text-align: center; ' . $toggleStyle . '" onclick="toggleFolder(this)">' . $toggleIcon . '</span>';
            $html .= '<span style="width: 20px;">' . $icon . '</span>';
            $html .= '<span style="font-weight: 600;">' . htmlspecialchars($item['name']) . '/</span>';
            $html .= '<span style="color: #8aa0bb; flex: 3; font-size: 11px;">' . htmlspecialchars($description) . '</span>';
            $html .= '<span style="flex: 1;"></span><span style="width: 120px;"></span><span style="width: 70px;"></span><span style="width: 60px;"></span>';
            $html .= '</div>';
            
            if ($hasChildren) {
                // Контейнер с дочерними элементами по умолчанию скрыт (collapsed)
                $html .= '<div id="' . $childrenContainerId . '" class="folder-children collapsed" style="margin-left: ' . ($indent + 30) . 'px; border-left: 2px solid ' . $themeCSS['border'] . '; padding-left: 12px;">';
                foreach ($item['children'] as $child) {
                    $html .= $renderItem($child, $level + 1, $itemId);
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
        } else {
            $sizeFormatted = formatFileSize($item['size']);
            $dateFormatted = $item['mtime'] ? date('d.m.Y H:i', $item['mtime']) : '—';
            $version = getFileVersionFromContent($item['fullPath']);
            $stability = getFileStability($item['name']);
            $stableColor = $stability >= 95 ? '#2ecc71' : ($stability >= 85 ? '#3b82f6' : '#f39c12');
            
            $html = '<div style="display: flex; align-items: center; gap: 10px; padding: 2px 0; border-bottom: 1px dashed ' . $themeCSS['border'] . ';" class="file-row" data-path="' . htmlspecialchars($item['fullPath']) . '" style="margin-left: ' . $indent . 'px;">';
            $html .= '<span style="width: 20px;"></span>';
            $html .= '<span style="width: 20px;">' . $icon . '</span>';
            $html .= '<span style="flex: 2; font-size: 12px;">' . htmlspecialchars($item['name']) . '</span>';
            $html .= '<span style="color: #8aa0bb; flex: 3; font-size: 11px;">' . htmlspecialchars($description) . '</span>';
            $html .= '<span style="font-family: monospace; text-align: right; flex: 1; font-size: 11px;" class="file-size">' . $sizeFormatted . '</span>';
            $html .= '<span style="font-family: monospace; text-align: center; width: 120px; font-size: 11px;" class="file-mtime">' . $dateFormatted . '</span>';
            $html .= '<span style="text-align: center; width: 70px; font-size: 11px;" class="file-version">' . $version . '</span>';
            $html .= '<span style="text-align: center; width: 60px;"><span style="background: ' . $stableColor . '20; color: ' . $stableColor . '; padding: 2px 8px; border-radius: 12px; font-size: 10px;">' . $stability . '%</span></span>';
            $html .= '</div>';
            return $html;
        }
    };
    
    ?>
    <div style="background: <?= $themeCSS['card'] ?>; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
        <style>
            .folder-toggle {
                display: inline-block;
                cursor: pointer;
                user-select: none;
                font-size: 12px;
                transition: none;
            }
            .folder-children {
                transition: all 0.2s;
            }
            .folder-children.collapsed {
                display: none;
            }
            .file-row {
                padding: 2px 0 !important;
                margin: 0 !important;
                line-height: 1.3 !important;
            }
            .folder-children {
                margin-left: 25px !important;
                padding-left: 8px !important;
            }
        </style>
        
        <div style="font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.4;">
            <?php if (empty($structure)): ?>
                <div style="color: <?= $themeCSS['danger'] ?>; text-align: center; padding: 20px;">
                    ⚠️ Структура не найдена. Проверьте права доступа к файлам.
                </div>
            <?php else: ?>
                <?php foreach ($structure as $item): ?>
                    <?= $renderItem($item, 0) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; gap: 20px; margin-top: 12px; padding: 6px 12px; background: <?= $themeCSS['menuBg'] ?>; border-radius: 6px; font-size: 11px;">
            <div><span style="background: #2ecc7120; color: #2ecc71; padding: 2px 8px; border-radius: 12px;">95-100%</span> — <?= __('structure_stable') ?></div>
            <div><span style="background: #3b82f620; color: #3b82f6; padding: 2px 8px; border-radius: 12px;">85-94%</span> — <?= __('structure_working') ?></div>
            <div><span style="background: #f39c1220; color: #f39c12; padding: 2px 8px; border-radius: 12px;">&lt;85%</span> — <?= __('structure_development') ?></div>
        </div>
    </div>
    
    <script>
    function toggleFolder(element) {
        const folderId = element.getAttribute('data-folder');
        const childrenContainer = document.getElementById(folderId);
        
        if (childrenContainer) {
            if (childrenContainer.classList.contains('collapsed')) {
                // Разворачиваем
                childrenContainer.classList.remove('collapsed');
                element.textContent = '▼';
            } else {
                // Сворачиваем
                childrenContainer.classList.add('collapsed');
                element.textContent = '▶';
            }
        }
    }
    </script>
    <?php
}