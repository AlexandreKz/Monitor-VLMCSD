<?php
// ============================================
// ФАЙЛ: vlmcinc/version.php
// ВЕРСИЯ: 1.0.2
// ДАТА: 2026-11-05
// @description: Единый источник версии проекта и текста подвала (главная страница и панель настроек)
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'version.php') {
    http_response_code(403);
    exit('Access denied');
}

// Версия проекта и дата её выпуска. Меняется только здесь (в релизе: тег vX.Y.Z = VLMC_VERSION).
if (!defined('VLMC_VERSION')) define('VLMC_VERSION', '5.9.2');
if (!defined('VLMC_DATE'))    define('VLMC_DATE', '2026-11-05');

/** Текст подвала: одинаковый на главной странице и в панели настроек. */
function vlmcFooterText() {
    return 'KMS Log Monitor • ' . __('version') . ' ' . VLMC_VERSION . ' • ' . __('footer_copyright');
}
