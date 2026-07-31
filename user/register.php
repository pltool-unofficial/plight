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
    $physicsUsername = sanitizeInput($_POST['physics_username'] ?? '');
    $physicsId = sanitizeInput($_POST['physics_id'] ?? '');

    // 验证
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
        $error = '用户名长度需为3-30字符';
    } elseif (!$email) {
        $error = '邮箱格式无效';
    } elseif (strlen($password) < 8) {
        $error = '密码至少8位';
    } elseif ($physicsUsername === '' || strlen($physicsUsername) < 2 || strlen($physicsUsername) > 50) {
        $error = '物实用户名需为2-50字符';
    } elseif ($physicsId === '' || strlen($physicsId) < 1 || strlen($physicsId) > 50) {
        $error = '物实账户ID不能为空';
    } else {
        $db = Database::getInstance();

        // 检查用户名/邮箱/物实用户名/ID是否已存在
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? OR (physics_username = ? AND physics_id = ?)');
        $stmt->execute([$username, $email, $physicsUsername, $physicsId]);
        if ($stmt->fetch()) {
            $error = '用户名、邮箱或物实身份信息已被注册';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token = generateVerifyToken();
            $expiry = date('Y-m-d H:i:s', time() + 86400); // 24小时
            $tempUsername = '临时用户_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 6);

            $stmt = $db->prepare('INSERT INTO users (username, email, password, temp_username, physics_username, physics_id, verify_token, token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$username, $email, $hashed, $tempUsername, $physicsUsername, $physicsId, $token, $expiry])) {
                sendVerificationEmail($email, $username, $token);
                $_SESSION['register_success'] = '注册成功！请查收邮箱验证链接。管理员将在身份认证通过后为你开通完整权限。';
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
    <div class="auth-split">
        <div class="auth-welcome">
            <h1>加入 <?= SITE_NAME ?></h1>
            <p>注册账号，开启学习交流之旅。填写物实身份信息，通过管理员认证后即可获得完整权限。</p>
            <ul class="auth-features">
                <li>📬 邮箱验证注册</li>
                <li>📝 发帖参与讨论</li>
                <li>🧰 使用在线工具</li>
            </ul>
        </div>
        <div class="auth-box">
            <h2>用户注册</h2>
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
                    <label for="physics_username">物实用户名 *</label>
                    <input type="text" id="physics_username" name="physics_username" required minlength="2" maxlength="50" placeholder="请填写你的物实用户名" value="<?= escapeHtml($_POST['physics_username'] ?? '') ?>">
                    <small>用于管理员核对你的真实身份</small>
                </div>
                <div class="form-group">
                    <label for="physics_id">物实账户ID *</label>
                    <input type="text" id="physics_id" name="physics_id" required minlength="1" maxlength="50" placeholder="请填写你的物实账户ID" value="<?= escapeHtml($_POST['physics_id'] ?? '') ?>">
                    <small>注册后你将获得临时账户名，管理员通过物实用户名+ID认证后开通完整权限</small>
                </div>
                <button type="submit" class="btn btn-primary">注册</button>
            </form>
            <p class="auth-switch">已有账号？<a href="login.php">立即登录</a></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
