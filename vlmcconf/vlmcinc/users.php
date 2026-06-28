<?php
// ============================================
// ФАЙЛ: vlmcinc/users.php
// ВЕРСИЯ: 4.0.0
// ДАТА: 2026-06-02
// @description: Управление пользователями + автоматическая миграция (один раз)
// ============================================

// Права доступа (битовые маски)
define('PERM_GROUPS_NONE', 0);
define('PERM_GROUPS_VIEW', 1);
define('PERM_GROUPS_EDIT', 2);

define('PERM_DEVICES_NONE', 0);
define('PERM_DEVICES_VIEW', 4);
define('PERM_DEVICES_EDIT', 8);

define('PERM_LOGS_NONE', 0);
define('PERM_LOGS_VIEW', 16);
define('PERM_LOGS_EDIT', 32);

define('PERM_USERS_NONE', 0);
define('PERM_USERS_VIEW', 64);
define('PERM_USERS_EDIT', 128);

define('PERM_INFO_NONE', 0);
define('PERM_INFO_VIEW', 256);

// Права для раздела ИНСТРУМЕНТЫ
define('PERM_TOOLS_NONE', 0);
define('PERM_TOOLS_VIEW', 512);
define('PERM_TOOLS_EDIT', 1024);

// Право для управления белыми IP (исключения из подозрительных)
define('PERM_IP_WHITELIST', 2048);

// Права для интеграций
define('PERM_INTEGRATIONS_VIEW', 4096);
define('PERM_INTEGRATIONS_EDIT', 8192);

// Права для API
define('PERM_API_VIEW', 16384);
define('PERM_API_EDIT', 32768);

// Полные права администратора
define('PERM_ADMIN_FULL', 
    PERM_GROUPS_VIEW | PERM_GROUPS_EDIT |
    PERM_DEVICES_VIEW | PERM_DEVICES_EDIT |
    PERM_LOGS_VIEW | PERM_LOGS_EDIT |
    PERM_USERS_VIEW | PERM_USERS_EDIT |
    PERM_INFO_VIEW |
    PERM_TOOLS_EDIT |
    PERM_IP_WHITELIST |
    PERM_INTEGRATIONS_VIEW |
    PERM_INTEGRATIONS_EDIT |
    PERM_API_VIEW |
    PERM_API_EDIT
);

// ============================================
// АВТОМАТИЧЕСКАЯ МИГРАЦИЯ USERS.JSON (ОДИН РАЗ)
// ============================================

/**
 * Автоматическая миграция users.json из старого формата {"users": [...]} в новый [...]
 * Выполняется только один раз — при обнаружении старого формата
 */
function migrate_users_if_needed() {
    $users_file = __DIR__ . '/../users.json';
    if (!file_exists($users_file)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($users_file), true);
    if (!is_array($data)) {
        return false;
    }
    
    // Старый формат: есть ключ "users"
    if (isset($data['users']) && is_array($data['users'])) {
        $users = $data['users'];
        $needSave = false;
        
        foreach ($users as &$user) {
            // Обновляем права 4095 -> PERM_ADMIN_FULL
            if (isset($user['permissions']) && $user['permissions'] === 4095) {
                $user['permissions'] = PERM_ADMIN_FULL;
                $needSave = true;
            }
            // Добавляем source если нет
            if (!isset($user['source'])) {
                $user['source'] = 'local';
                $needSave = true;
            }
        }
        
        // Сохраняем в новом формате (прямой массив)
        file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    // Новый формат — ничего не делаем
    return false;
}

// Выполняем миграцию (проверка — лёгкая, после первой миграции условие не сработает)
migrate_users_if_needed();

// ============================================
// ОСНОВНЫЕ ФУНКЦИИ
// ============================================

/**
 * Получить путь к файлу с пользователями
 */
function getUsersFile() {
    return dirname(__DIR__) . '/users.json';
}

/**
 * Проверка логина
 */
function validateUsername($username) {
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = __('username_min_length');
    }
    if (!preg_match('/^[a-zA-Zа-яА-Я0-9_-]+$/u', $username)) {
        $errors[] = __('username_invalid_chars');
    }
    
    return $errors;
}

/**
 * Проверка сложности пароля с детальными сообщениями
 */
function checkPasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = __('password_min_length');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = __('password_need_uppercase');
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = __('password_need_lowercase');
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = __('password_need_digit');
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|]/', $password)) {
        $errors[] = __('password_need_special');
    }
    
    return $errors;
}

function isStrongPassword($password) {
    return empty(checkPasswordStrength($password));
}

/**
 * Инициализация файла с пользователями
 */
function initUsers() {
    $file = getUsersFile();
    if (file_exists($file)) return;
    
    $hash = password_hash('root', PASSWORD_DEFAULT);
    $data = [
        [
            'id' => 1,
            'username' => 'root',
            'password_hash' => $hash,
            'permissions' => PERM_ADMIN_FULL,
            'must_change_password' => true,
            'source' => 'local',
            'created' => date('Y-m-d H:i:s'),
            'last_login' => null
        ]
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Получить всех пользователей
 */
function getAllUsers() {
    initUsers();
    $file = getUsersFile();
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function getUserById($id) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['id'] == $id) return $user;
    }
    return null;
}

function getUserByUsername($username) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['username'] === $username) return $user;
    }
    return null;
}

function verifyUser($username, $password) {
    $user = getUserByUsername($username);
    if (!$user) return false;
    return password_verify($password, $user['password_hash']);
}

function authenticate_user($username, $password) {
    return verifyUser($username, $password);
}

function updateUser($id, $data) {
    $users = getAllUsers();
    $found = false;
    foreach ($users as &$user) {
        if ($user['id'] == $id) {
            $found = true;
            if (isset($data['password'])) {
                $user['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $user['must_change_password'] = false;
            }
            if (isset($data['permissions'])) $user['permissions'] = (int)$data['permissions'];
            if (isset($data['must_change_password'])) $user['must_change_password'] = $data['must_change_password'];
            if (isset($data['last_login'])) $user['last_login'] = $data['last_login'];
            if (isset($data['username'])) $user['username'] = $data['username'];
            if (isset($data['fullname'])) $user['fullname'] = $data['fullname'];
            if (isset($data['email'])) $user['email'] = $data['email'];
            if (isset($data['source'])) $user['source'] = $data['source'];
            break;
        }
    }
    if (!$found) return false;
    $file = getUsersFile();
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

function updateLastLogin($username) {
    $user = getUserByUsername($username);
    if ($user) updateUser($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
}

/**
 * Добавить пользователя с детальной валидацией
 */
function addUser($username, $password, $permissions = PERM_DEVICES_VIEW) {
    $users = getAllUsers();
    
    // Проверка существования пользователя
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return ['success' => false, 'message' => 'Пользователь уже существует'];
        }
    }
    
    // Валидация логина
    $usernameErrors = validateUsername($username);
    if (!empty($usernameErrors)) {
        return ['success' => false, 'message' => implode('<br>', $usernameErrors)];
    }
    
    // Валидация пароля
    $passwordErrors = checkPasswordStrength($password);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode('<br>', $passwordErrors)];
    }
    
    $newId = 1;
    foreach ($users as $user) {
        if ($user['id'] >= $newId) $newId = $user['id'] + 1;
    }
    
    $users[] = [
        'id' => $newId,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'permissions' => (int)$permissions,
        'must_change_password' => false,
        'source' => 'local',
        'created' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
    
    $file = getUsersFile();
    $result = file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result !== false) {
        return ['success' => true, 'message' => 'Пользователь добавлен'];
    }
    return ['success' => false, 'message' => 'Ошибка сохранения'];
}

function deleteUser($id) {
    if ($id == 1) {
        return ['success' => false, 'message' => 'Нельзя удалить главного администратора'];
    }
    $users = getAllUsers();
    $newUsers = [];
    $deleted = false;
    foreach ($users as $user) {
        if ($user['id'] != $id) {
            $newUsers[] = $user;
        } else {
            $deleted = true;
        }
    }
    if (!$deleted) {
        return ['success' => false, 'message' => 'Пользователь не найден'];
    }
    $file = getUsersFile();
    $result = file_put_contents($file, json_encode($newUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result !== false) {
        return ['success' => true, 'message' => 'Пользователь удален'];
    }
    return ['success' => false, 'message' => 'Ошибка сохранения'];
}

function changeUserPasswordAsAdmin($userId, $newPassword) {
    $user = getUserById($userId);
    if (!$user) return ['success' => false, 'message' => 'Пользователь не найден'];
    
    $errors = checkPasswordStrength($newPassword);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }
    
    if (updateUser($userId, ['password' => $newPassword])) {
        return ['success' => true, 'message' => 'Пароль пользователя изменен'];
    }
    return ['success' => false, 'message' => 'Ошибка при смене пароля'];
}
// ============================================
// ФУНКЦИЯ ПРОВЕРКИ ПРАВ ДОСТУПА
// ============================================

/**
 * Проверка наличия права у текущего пользователя
 * @param int $permission Константа права (например, PERM_INTEGRATIONS_VIEW)
 * @return bool
 */
function check_permission($permission) {
    if (!isset($_SESSION['vlmc_admin']) || $_SESSION['vlmc_admin'] !== true) {
        return false;
    }
    $user_permissions = $_SESSION['vlmc_permissions'] ?? 0;
    return ($user_permissions & $permission) === $permission;
}
?>