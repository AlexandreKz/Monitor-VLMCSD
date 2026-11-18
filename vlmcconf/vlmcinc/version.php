<?php
// ============================================
// ФАЙЛ: vlmcinc/version.php
// ВЕРСИЯ: 1.1.3
// ДАТА: 2026-11-18
// @description: Единый источник версии проекта и текста подвала (главная страница и панель настроек)
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'version.php') {
    http_response_code(403);
    exit('Access denied');
}

// Версия проекта и дата её выпуска. Меняется только здесь (в релизе: тег vX.Y.Z = VLMC_VERSION).
if (!defined('VLMC_VERSION')) define('VLMC_VERSION', '6.1.0');
if (!defined('VLMC_DATE'))    define('VLMC_DATE', '2026-11-18');

// Первый год проекта. Конечный год вычисляется (не меньше года релиза).
if (!defined('VLMC_FIRST_YEAR')) define('VLMC_FIRST_YEAR', 2025);

/** Автор: в шаблонах и локалях не хранится, собирается здесь. */
function vlmcAuthor() {
    return base64_decode('QWxleGFuZHJlS3o=');
}

/** Копирайт: «© 2025–<текущий год> <автор>». Год не нужно править вручную. */
function vlmcCopyright() {
    $to = max((int)date('Y'), (int)substr(VLMC_DATE, 0, 4), VLMC_FIRST_YEAR);
    $years = ($to > VLMC_FIRST_YEAR) ? VLMC_FIRST_YEAR . '–' . $to : (string)$to;
    return '© ' . $years . ' ' . vlmcAuthor();
}

/** Текст подвала: одинаковый на главной странице и в панели настроек. */
function vlmcFooterText() {
    return 'KMS Log Monitor • ' . __('version') . ' ' . VLMC_VERSION . ' • ' . vlmcCopyright();
}
