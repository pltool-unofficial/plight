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

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    // ----- 通用资料更新 -----
    $username = sanitizeInput($_POST['username'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $updateError = false;

    // 验证用户名
    if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
        $error = '用户名长度需为3-30字符';
        $updateError = true;
    } else {
        // 检查用户名是否被他人占用
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $user['id']]);
        if ($stmt->fetch()) {
            $error = '该用户名已被占用';
            $updateError = true;
        }
    }

    if (!$updateError) {
        // 头像URL校验（允许为空）
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
            $error = '头像URL格式无效';
            $updateError = true;
        } else {
            // 简介长度限制
            if (mb_strlen($bio, 'UTF-8') > 500) {
                $error = '个人简介不能超过500字符';
                $updateError = true;
            }
        }
    }

    if (!$updateError) {
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

    // ----- 修改密码（如果填写了密码字段）-----
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 只有填写了当前密码才尝试修改密码
    if ($currentPassword !== '') {
        // 验证当前密码是否正确
        if (!password_verify($currentPassword, $user['password'])) {
            $error = '当前密码错误';
        } elseif ($newPassword === '') {
            $error = '请填写新密码';
        } elseif (strlen($newPassword) < 8) {
            $error = '新密码至少8位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次输入的密码不一致';
        } else {
            // 更新密码
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt->execute([$hashed, $user['id']])) {
                $success = ($success ? $success . ' 且密码已更新' : '密码更新成功');
                // 刷新用户数据（可选）
                $user = getCurrentUser();
            } else {
                $error = '密码更新失败，请稍后重试';
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
            <!-- 基本资料 -->
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

            <hr class="settings-divider">

            <!-- 修改密码区域 -->
            <h2>修改密码</h2>
            <p class="hint">如不修改密码，请留空以下所有密码框。</p>
            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password" placeholder="填写当前密码以启用密码修改">
            </div>
            <div class="form-group">
                <label for="new_password">新密码 (至少8位)</label>
                <input type="password" id="new_password" name="new_password" placeholder="新密码">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="再次输入新密码">
            </div>

            <button type="submit" class="btn btn-primary">保存所有修改</button>
        </form>

        <div class="settings-extras">
            <h2>其他操作</h2>
            <p><a href="verify-identity.php" class="btn btn-secondary">申请身份认证</a></p>
            <p><a href="profile.php?id=<?= (int)$user['id'] ?>" class="btn btn-secondary">查看我的主页</a></p>
            <p><a href="logout.php" class="btn btn-danger" onclick="return confirm('确定要退出登录吗？')">退出登录</a></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>