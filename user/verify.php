<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$token = $_GET['token'] ?? '';
$email = trim($_GET['email'] ?? '');
$message = '';
$messageType = 'error';

if ($token === '' || $email === '') {
    $message = '验证链接无效，缺少必要参数';
} else {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND verify_token = ?');
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = '验证链接无效或已使用';
    } elseif ($user['email_verified'] == 1) {
        $message = '邮箱已验证，无需重复操作';
        $messageType = 'success';
    } else {
        // 检查是否过期
        $expiry = strtotime($user['token_expiry']);
        if ($expiry === false || $expiry < time()) {
            $message = '验证链接已过期，请重新注册或联系管理员';
        } else {
            $stmt = $db->prepare('UPDATE users SET email_verified = 1, verify_token = NULL, token_expiry = NULL WHERE id = ?');
            if ($stmt->execute([$user['id']])) {
                $message = '邮箱验证成功！您现在可以登录了';
                $messageType = 'success';
            } else {
                $message = '验证失败，请稍后重试';
            }
        }
    }
}

$pageTitle = '邮箱验证';
include __DIR__ . '/../includes/header.php';
?>
<main class="container auth-page">
    <div class="auth-box">
        <h1>邮箱验证</h1>
        <div class="alert <?= $messageType === 'success' ? 'success' : 'error' ?>"><?= escapeHtml($message) ?></div>
        <p class="auth-switch"><a href="login.php">前往登录</a> · <a href="/index.php">返回首页</a></p>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
