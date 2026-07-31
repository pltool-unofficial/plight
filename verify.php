<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

$pageTitle = '邮箱验证';
$error = '';
$success = '';

if ($token !== '' && $email !== '') {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND verify_token = ? AND token_expiry > NOW() AND email_verified = 0');
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $db->prepare('UPDATE users SET email_verified = 1, verify_token = NULL, token_expiry = NULL WHERE id = ?');
        $stmt->execute([$user['id']]);
        $success = '邮箱验证成功，现在可以登录了。';
    } else {
        $error = '验证链接无效或已过期。';
    }
} else {
    $error = '缺少验证参数。';
}

include __DIR__ . '/includes/header.php';
?>
<main class="container auth-page">
    <div class="auth-box">
        <h1>邮箱验证</h1>
        <?php if ($error !== ''): ?>
            <div class="alert error"><?= escapeHtml($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert success"><?= escapeHtml($success) ?></div>
            <p style="text-align:center;margin-top:16px;"><a href="/user/login.php" class="btn btn-primary">前往登录</a></p>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php';