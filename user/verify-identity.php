<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// 需登录
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = Database::getInstance();
$user = getCurrentUser();

if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    $physicsUsername = sanitizeInput($_POST['physics_username'] ?? '');
    $physicsId = sanitizeInput($_POST['physics_id'] ?? '');

    if ($physicsUsername === '' || strlen($physicsUsername) > 50) {
        $error = '请填写有效的物实用户名（1-50字符）';
    } elseif ($physicsId === '' || strlen($physicsId) > 50) {
        $error = '请填写有效的物实账户ID（1-50字符）';
    } else {
        // 提交后设置为待审核(verified=0)，并保存物实信息
        $stmt = $db->prepare('UPDATE users SET physics_username = ?, physics_id = ?, verified = 0 WHERE id = ?');
        if ($stmt->execute([$physicsUsername, $physicsId, $user['id']])) {
            $success = '身份认证申请已提交，等待管理员审核';
            $user = getCurrentUser();
        } else {
            $error = '提交失败，请稍后重试';
        }
    }
}

$pageTitle = '身份认证';
include __DIR__ . '/../includes/header.php';
?>
<main class="container verify-page">
    <div class="settings-box">
        <h1>身份认证申请</h1>
        <p class="verify-tip">请填写您的物理实验（物实）账户信息，提交后将进入人工审核。审核通过后可在齐鸣板块发帖。</p>

        <?php if ($error !== ''): ?>
            <div class="alert error"><?= escapeHtml($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert success"><?= escapeHtml($success) ?></div>
        <?php endif; ?>

        <div class="verify-status">
            <p>当前状态：
                <?php if ($user['verified'] == 1): ?>
                    <span class="verified-badge">已认证</span>
                <?php elseif ($user['verified'] == 0 && !empty($user['physics_username'])): ?>
                    <span class="pending-badge">待审核</span>
                <?php elseif ($user['verified'] == 2): ?>
                    <span class="banned-badge">已拒绝</span>
                <?php else: ?>
                    <span class="unverified-badge">未提交</span>
                <?php endif; ?>
            </p>
        </div>

        <form method="POST" class="form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="physics_username">物实用户名</label>
                <input type="text" id="physics_username" name="physics_username" required maxlength="50" value="<?= escapeHtml($user['physics_username'] ?? '') ?>" placeholder="请填写您的物实用户名">
            </div>
            <div class="form-group">
                <label for="physics_id">物实账户ID</label>
                <input type="text" id="physics_id" name="physics_id" required maxlength="50" value="<?= escapeHtml($user['physics_id'] ?? '') ?>" placeholder="请填写您的物实账户ID">
            </div>
            <button type="submit" class="btn btn-primary">提交认证申请</button>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
