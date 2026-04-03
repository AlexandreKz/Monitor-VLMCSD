<?php
// ============================================
// ФАЙЛ: vlmcinc/auth.php
// ВЕРСИЯ: 1.0.0
// @description: Функции авторизации
// ============================================

/**
 * Проверка авторизации
 */
function checkAuth() {
    if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
        header('Location: login.php');
        exit;
    }
    
    if (isset($_SESSION['vlmc_login_time']) && (time() - $_SESSION['vlmc_login_time'] > 1800)) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    
    $_SESSION['vlmc_login_time'] = time();
}
?>