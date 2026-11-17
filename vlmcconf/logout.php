<?php
// ============================================
// ФАЙЛ: vlmcconf/logout.php
// ВЕРСИЯ: 1.1.0
// ДАТА: 2026-11-17
// @description: Выход из панели управления
// ============================================

require_once __DIR__ . '/vlmcinc/csrf.php';
vlmcSessionStart();

// Выход фиксируется в журнале действий
if (!empty($_SESSION['vlmc_username'])) {
    require_once __DIR__ . '/vlmcinc/audit.php';
    auditLog('logout');
}

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем сессию
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Перенаправляем на главную страницу монитора
header('Location: ../vlmc.php');
exit;
?>