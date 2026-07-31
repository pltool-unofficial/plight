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
        csrfFail();
    }

    $username = sanitizeInput($_POST['username'] ?? '');
    $avatar = sanitizeInput($_POST['avatar'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');

    // 验证用户名
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
        $error = '用户名长度需为3-30字符';
    } else {
        // 检查用户名是否被他人占用
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $user['id']]);
        if ($stmt->fetch()) {
            $error = '该用户名已被占用';
        } else {
            // 头像URL校验（允许为空，仅允许 http/https）
            if ($avatar !== '' && !isValidAvatarUrl($avatar)) {
                $error = '头像URL格式无效（仅支持 http/https 链接）';
            } else {
                // 简介长度限制
                if (mb_strlen($bio, 'UTF-8') > 500) {
                    $error = '个人简介不能超过500字符';
                } else {
                    $avatarValue = $avatar === '' ? null : $avatar;
                    $bioValue = $bio === '' ? null : $bio;

                    $stmt = $db->prepare('UPDATE users SET username = ?, avatar = ?, bio = ? WHERE id = ?');
                    if ($stmt->execute([$username, $avatarValue, $bioValue, $user['id']])) {
                        $success = '资料更新成功';
                        // 刷新用户数据
                        $user = getCurrentUser();
                    } else {
                        $error = '更新失败，请稍后重试';
                    }
                }
            }
        }
    }
}

$pageTitle = '设置';
include __DIR__ . '/../includes/header.php';
?>
<main class="container settings-page">
    <div class="settings-box">
        <h1>账号设置</h1>
        <?php if ($error !== ''): ?>
            <div class="alert error"><?= escapeHtml($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert success"><?= escapeHtml($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="30" value="<?= escapeHtml($user['username']) ?>">
            </div>
            <div class="form-group">
                <label for="avatar">头像 URL</label>
                <input type="url" id="avatar" name="avatar" placeholder="留空使用默认头像" value="<?= escapeHtml($user['avatar'] ?? '') ?>">
                <div class="avatar-preview">
                    <img src="<?= escapeHtml($user['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="当前头像" class="avatar-thumb" onerror="this.src='/assets/images/default-avatar.svg'">
                </div>
            </div>
            <div class="form-group">
                <label for="bio">个人简介 (最多500字符)</label>
                <textarea id="bio" name="bio" rows="4" maxlength="500" placeholder="介绍一下自己吧"><?= escapeHtml($user['bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">保存修改</button>
        </form>

        <div class="settings-extras">
            <h2>其他操作</h2>
            <p><a href="verify-identity.php" class="btn btn-secondary">申请身份认证</a></p>
            <p><a href="profile.php?id=<?= (int)$user['id'] ?>" class="btn btn-secondary">查看我的主页</a></p>
            <form method="POST" action="logout.php" style="display:inline" onsubmit="return confirm('确定要退出登录吗？')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger">退出登录</button>
            </form>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
