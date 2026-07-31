<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// 仅接受 POST 请求 + CSRF 校验，防止 GET 强制登出（CSRF）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    renderErrorPage('请求无效', '请通过正常流程退出登录。', 405);
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    csrfFail();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: /index.php');
exit;
