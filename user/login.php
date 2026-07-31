<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// 已登录则跳转首页
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_success']);

// 解析并校验 redirect 参数（仅允许站内相对路径，防止开放重定向）
$redirect = $_GET['redirect'] ?? '';
if ($redirect !== '' && (strpos($redirect, '/') !== 0 || strpos($redirect, '//') === 0)) {
    $redirect = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $postRedirect = $_POST['redirect'] ?? '';
    // 再次校验 POST 来的 redirect
    if ($postRedirect !== '' && (strpos($postRedirect, '/') !== 0 || strpos($postRedirect, '//') === 0)) {
        $postRedirect = '';
    }

    if (!$email) {
        $error = '邮箱格式无效';
    } elseif ($password === '') {
        $error = '请输入密码';
    } else {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = '邮箱或密码错误';
        } elseif ($user['is_banned'] == 1) {
            $error = '该账号已被封号，无法登录';
        } elseif ($user['email_verified'] != 1) {
            $error = '邮箱尚未验证，请查收验证邮件后点击链接激活账号。';
        } else {
            $_SESSION['user_id'] = $user['id'];
            // 登录成功跳转
            $target = $postRedirect !== '' ? $postRedirect : '/index.php';
            header('Location: ' . $target);
            exit;
        }
    }
}

$pageTitle = '登录';
include __DIR__ . '/../includes/header.php';
?>
<main class="container auth-page">
    <div class="auth-split">
        <div class="auth-welcome">
            <h1>欢迎回到 <?= SITE_NAME ?></h1>
            <p>登录后继续探索社区内容，与志同道合的学习者交流。</p>
            <ul class="auth-features">
                <li>📬 邮箱验证，安全可靠</li>
                <li>✅ 加 V 认证，身份可信</li>
                <li>🛠️ 在线工具，即开即用</li>
            </ul>
        </div>
        <div class="auth-box">
            <h2>用户登录</h2>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?= escapeHtml($error) ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert success"><?= escapeHtml($success) ?></div>
            <?php endif; ?>
            <form method="POST" class="form">
                <?= csrfField() ?>
                <?php if ($redirect !== ''): ?>
                    <input type="hidden" name="redirect" value="<?= escapeHtml($redirect) ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" name="email" required value="<?= escapeHtml($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">登录</button>
            </form>
            <p class="auth-switch">还没有账号？<a href="register.php">立即注册</a></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
