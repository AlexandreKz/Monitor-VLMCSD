<?php
// ============================================
// ФАЙЛ: vlmcconf/login.php
// ВЕРСИЯ: 3.1.0
// ДАТА: 2026-03-27
// @description: Страница авторизации с полной локализацией
// ============================================
session_start();
require_once __DIR__ . '/vlmcinc/users.php';

// ============================================
// ЗАЩИТА ОТ ПРЯМОГО ДОСТУПА К КОНФИГУРАЦИОННЫМ ФАЙЛАМ
// ============================================

$requestUri = $_SERVER['REQUEST_URI'];
$protectedExtensions = ['json', 'log', 'ini', 'conf', 'txt'];

foreach ($protectedExtensions as $ext) {
    if (preg_match('/\.' . $ext . '$/', $requestUri)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// ============================================
// ЗАГРУЗКА КОНФИГУРАЦИИ ДЛЯ ЯЗЫКА
// ============================================
$configFile = __DIR__ . '/vlmcconf_config.json';
$config = [
    'language' => 'ru'
];

if (file_exists($configFile)) {
    $loaded = json_decode(file_get_contents($configFile), true);
    if ($loaded && isset($loaded['language'])) {
        $config['language'] = $loaded['language'];
    }
}

// Загрузка переводов
$language = $config['language'];
$localeFile = __DIR__ . '/locale/' . $language . '.php';
if (file_exists($localeFile)) {
    $translations = include $localeFile;
} else {
    $translations = include __DIR__ . '/locale/ru.php';
}

// Функция перевода
function __($key) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : $key;
}

// Если уже авторизован
if (isset($_SESSION['vlmc_admin']) && $_SESSION['vlmc_admin'] === true) {
    if (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true) {
        header('Location: vlmcconf.php');
        exit;
    }
}

$error = '';
$changePasswordMode = false;
$username = '';

// Обработка смены пароля
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $username = $_POST['username'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $user = getUserByUsername($username);
    
    if (!$user) {
        $error = __('user_not_found');
    } elseif ($newPassword !== $confirmPassword) {
        $error = __('passwords_do_not_match');
    } else {
        $errors = checkPasswordStrength($newPassword);
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            if (updateUser($user['id'], ['password' => $newPassword])) {
                $user = getUserById($user['id']);
                
                $_SESSION['vlmc_admin'] = true;
                $_SESSION['vlmc_user_id'] = $user['id'];
                $_SESSION['vlmc_username'] = $user['username'];
                $_SESSION['vlmc_permissions'] = $user['permissions'];
                $_SESSION['vlmc_login_time'] = time();
                
                updateLastLogin($username);
                
                header('Location: vlmcconf.php');
                exit;
            } else {
                $error = __('password_change_error');
            }
        }
    }
    $changePasswordMode = true;
}

// Обычная авторизация
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (verifyUser($username, $password)) {
        $user = getUserByUsername($username);
        
        if (isset($user['must_change_password']) && $user['must_change_password'] === true) {
            $_SESSION['pending_user'] = $user['id'];
            $changePasswordMode = true;
            $error = '';
        } else {
            $_SESSION['vlmc_admin'] = true;
            $_SESSION['vlmc_user_id'] = $user['id'];
            $_SESSION['vlmc_username'] = $user['username'];
            $_SESSION['vlmc_permissions'] = $user['permissions'];
            $_SESSION['vlmc_login_time'] = time();
            
            updateLastLogin($username);
            
            header('Location: vlmcconf.php');
            exit;
        }
    } else {
        $error = __('invalid_login');
    }
}

// Если режим смены пароля, получаем имя пользователя
if ($changePasswordMode && empty($username)) {
    $pendingId = $_SESSION['pending_user'] ?? null;
    if ($pendingId) {
        $pendingUser = getUserById($pendingId);
        if ($pendingUser) {
            $username = $pendingUser['username'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>KMS Monitor • <?= __('login_title') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔒</text></svg>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a2634 0%, #0f1a2f 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(31, 41, 55, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 16px;
            width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: white;
            font-size: 28px;
            font-weight: 600;
        }
        
        .logo span {
            color: #8aa0bb;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #e1e9f0;
            font-size: 13px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid #33485d;
            border-radius: 8px;
            font-size: 14px;
            color: #e1e9f0;
            outline: none;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #3b82f6;
            outline: none;
        }
        
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        
        .password-wrapper input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #8aa0bb;
            padding: 0;
            margin: 0;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #3b82f6;
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 5px;
            transition: transform 0.2s;
        }
        
        button[type="submit"]:hover {
            transform: translateY(-2px);
        }
        
        .error {
            color: #fca5a5;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background: rgba(220, 38, 38, 0.1);
            border-radius: 6px;
        }
        
        .password-hint {
            font-size: 11px;
            color: #8aa0bb;
            margin-top: 5px;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            color: #6b8ba4;
            font-size: 12px;
        }
        
        .footer a {
            color: #8aa0bb;
            text-decoration: none;
        }
        
        .footer a:hover {
            color: #3b82f6;
        }
        
        hr {
            margin: 20px 0;
            border-color: #33485d;
        }
        
        .disabled-input {
            opacity: 0.7;
            cursor: not-allowed;
            background: rgba(17, 24, 39, 0.5) !important;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>⚙️ KMS MONITOR</h1>
            <span><?= $changePasswordMode ? __('change_password_title') : __('control_panel') ?></span>
        </div>
        
        <?php if ($error): ?>
            <div class="error">⚠️ <?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($changePasswordMode): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                
                <div class="form-group">
                    <label><?= __('user_label') ?></label>
                    <input type="text" value="<?= htmlspecialchars($username) ?>" disabled class="disabled-input">
                </div>
                
                <div class="form-group">
                    <label><?= __('new_password_label') ?></label>
                    <div class="password-wrapper">
                        <input type="password" id="newPassword" name="new_password" required autofocus autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('newPassword')">👁️</button>
                    </div>
                    <div class="password-hint">
                        ⚠️ <?= __('password_requirements') ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><?= __('confirm_password_label') ?></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmPassword" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
                    </div>
                </div>
                
                <button type="submit">🔓 <?= __('set_password_and_login') ?></button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label><?= __('username_label') ?></label>
                    <input type="text" name="username" placeholder="<?= __('username_placeholder') ?>" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label><?= __('password_label') ?></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="<?= __('password_placeholder') ?>" required autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                    </div>
                </div>
                
                <button type="submit">🔓 <?= __('login_button') ?></button>
            </form>
            
            <hr>
            
            <div class="footer">
                💡 <?= __('first_login_hint') ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <a href="../vlmc.php">← <?= __('back_to_monitor') ?></a>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                if (field.type === 'password') {
                    field.type = 'text';
                } else {
                    field.type = 'password';
                }
            }
        }
    </script>
</body>
</html>