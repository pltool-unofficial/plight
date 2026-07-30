<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    $username = sanitizeInput($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $tempUsername = sanitizeInput($_POST['temp_username'] ?? '');

    // 验证
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
        $error = '用户名长度需为3-30字符';
    } elseif (!$email) {
        $error = '邮箱格式无效';
    } elseif (strlen($password) < 8) {
        $error = '密码至少8位';
    } else {
        $db = Database::getInstance();

        // 检查用户名/邮箱是否存在
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = '用户名或邮箱已被注册';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token = generateVerifyToken();
            $expiry = date('Y-m-d H:i:s', time() + 86400); // 24小时

            $stmt = $db->prepare('INSERT INTO users (username, email, password, temp_username, verify_token, token_expiry) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$username, $email, $hashed, $tempUsername, $token, $expiry])) {
                sendVerificationEmail($email, $username, $token);
                $_SESSION['register_success'] = '注册成功！请查收邮箱验证链接。';
                header('Location: login.php');
                exit;
            } else {
                $error = '注册失败，请稍后重试';
            }
        }
    }
}

$pageTitle = '注册';
include __DIR__ . '/../includes/header.php';
?>
<main class="container auth-page">
    <div class="auth-box">
        <h1>用户注册</h1>
        <?php if ($error !== ''): ?>
            <div class="alert error"><?= escapeHtml($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="30" value="<?= escapeHtml($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required value="<?= escapeHtml($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">密码 (至少8位)</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="temp_username">临时账户名</label>
                <input type="text" id="temp_username" name="temp_username" placeholder="请填写您的物实临时账户名" value="<?= escapeHtml($_POST['temp_username'] ?? '') ?>">
                <small>注册后将获得临时账户名，请在身份认证中填写物实用户名和ID</small>
            </div>
            <button type="submit" class="btn btn-primary">注册</button>
        </form>
        <p class="auth-switch">已有账号？<a href="login.php">立即登录</a></p>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
