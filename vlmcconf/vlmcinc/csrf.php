<?php
// ============================================
// ФАЙЛ: vlmcinc/csrf.php
// ВЕРСИЯ: 1.1.0
// ДАТА: 2026-11-01
// @description: Защита от CSRF (токен в сессии) и безопасные cookie сессии (HttpOnly, SameSite)
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'csrf.php') {
    http_response_code(403);
    exit('Access denied');
}

/**
 * Запуск сессии с безопасными параметрами cookie: HttpOnly, SameSite=Lax,
 * Secure (если соединение по HTTPS). Остальные параметры берутся из php.ini.
 * Требует PHP 7.3+ (SameSite в session_set_cookie_params).
 */
function vlmcSessionStart() {
    if (session_status() !== PHP_SESSION_NONE) {
        return true;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => (int)ini_get('session.cookie_lifetime'),
        'path'     => ini_get('session.cookie_path') ?: '/',
        'domain'   => (string)ini_get('session.cookie_domain'),
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    return session_start();
}

/** Вызывать при успешном входе: новый id сессии и новый CSRF-токен (защита от подмены сессии). */
function vlmcLoginSessionRefresh() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    unset($_SESSION['vlmc_csrf']);
}

/** Создать токен для вошедшего администратора, если его ещё нет (сессия должна быть открыта). */
function csrfEnsureToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (($_SESSION['vlmc_admin'] ?? false) === true && empty($_SESSION['vlmc_csrf'])) {
        $_SESSION['vlmc_csrf'] = bin2hex(random_bytes(32));
    }
}

function csrfToken() {
    return (string)($_SESSION['vlmc_csrf'] ?? '');
}

/**
 * Проверка токена для изменяющего запроса. Токен берётся из заголовка X-CSRF-Token
 * (fetch) или из поля csrf_token (формы). При несовпадении — 403 и завершение скрипта.
 */
function csrfVerifyOrDie() {
    $expected = csrfToken();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if ($expected !== '' && is_string($sent) && hash_equals($expected, $sent)) {
        return true;
    }

    $message = function_exists('__') ? __('csrf_error') : 'Security error: invalid request token. Reload the page and try again.';
    if ($message === 'csrf_error') {
        $message = 'Security error: invalid request token. Reload the page and try again.';
    }

    if (!headers_sent()) {
        http_response_code(403);
    }

    $wantsJson = isset($_POST['ajax'])
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

    if ($wantsJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'error' => $message, 'message' => $message]);
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>'
           . '<body style="font-family:sans-serif;padding:2em"><h3>403</h3><p>'
           . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
           . '</p><p><a href="' . htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES, 'UTF-8') . '">&#8635;</a></p></body></html>';
    }
    exit;
}

/**
 * <script>, который автоматически добавляет токен ко всем POST-формам
 * (в т.ч. созданным динамически и отправляемым через form.submit()) и ко всем
 * не-GET запросам fetch() на этот же сайт. Для гостей (токена нет) ничего не выводит.
 */
function csrfClientScript() {
    $token = csrfToken();
    if ($token === '') {
        return '';
    }
    $js = <<<'JS'
(function (T) {
    if (!T) return;
    function addToken(f) {
        if (!f || f.tagName !== 'FORM' || String(f.method || '').toLowerCase() !== 'post') return;
        if (f.querySelector('input[name="csrf_token"]')) return;
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'csrf_token'; i.value = T;
        f.appendChild(i);
    }
    document.addEventListener('submit', function (e) { addToken(e.target); }, true);
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { addToken(this); return nativeSubmit.call(this); };
    var nativeFetch = window.fetch;
    if (nativeFetch) {
        window.fetch = function (input, init) {
            try {
                init = init || {};
                var req = (typeof Request !== 'undefined' && input instanceof Request) ? input : null;
                var method = String(init.method || (req && req.method) || 'GET').toUpperCase();
                if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                    var a = document.createElement('a');
                    a.href = req ? req.url : String(input);
                    if (a.host === location.host) {
                        var h = new Headers(init.headers || (req && req.headers) || undefined);
                        h.set('X-CSRF-Token', T);
                        init.headers = h;
                    }
                }
            } catch (err) {}
            return nativeFetch.call(this, input, init);
        };
    }
})(__TOKEN__);
JS;
    return '<script>' . str_replace('__TOKEN__', json_encode($token), $js) . "</script>\n";
}
