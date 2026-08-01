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

    $formType = $_POST['form_type'] ?? 'profile';

    // ===== 个人资料（用户名/头像/简介/签名/介绍页；不含物实账户） =====
    if ($formType === 'profile') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $avatar = sanitizeInput($_POST['avatar'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $signature = sanitizeInput($_POST['signature'] ?? '');
        $profilePage = sanitizeInput($_POST['profile_page'] ?? '');

        if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
            $error = '用户名长度需为3-30字符';
        } else {
            // 检查用户名是否被他人占用
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $stmt->execute([$username, $user['id']]);
            if ($stmt->fetch()) {
                $error = '该用户名已被占用';
            } elseif ($avatar !== '' && !isValidAvatarUrl($avatar)) {
                $error = '头像URL格式无效（仅支持 http/https 链接）';
            } elseif (mb_strlen($bio, 'UTF-8') > 500) {
                $error = '个人简介不能超过500字符';
            } elseif (mb_strlen($signature, 'UTF-8') > 200) {
                $error = '个人签名不能超过200字符';
            } elseif ($profilePage !== '' && !isValidAvatarUrl($profilePage)) {
                $error = '个人介绍页面链接格式无效（仅支持 http/https 链接）';
            } else {
                $avatarValue = $avatar === '' ? null : $avatar;
                $bioValue = $bio === '' ? null : $bio;
                $signatureValue = $signature === '' ? null : $signature;
                $profilePageValue = $profilePage === '' ? null : $profilePage;

                $stmt = $db->prepare('UPDATE users SET username = ?, avatar = ?, bio = ?, signature = ?, profile_page = ? WHERE id = ?');
                if ($stmt->execute([$username, $avatarValue, $bioValue, $signatureValue, $profilePageValue, $user['id']])) {
                    $success = '资料更新成功';
                    $user = getCurrentUser();
                } else {
                    $error = '更新失败，请稍后重试';
                }
            }
        }
    }

    // ===== 修改密码 =====
    if ($formType === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $error = '当前密码错误';
        } elseif (strlen($newPassword) < 8) {
            $error = '新密码至少8位';
        } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = '新密码需至少包含字母和数字';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次输入的新密码不一致';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($stmt->execute([$hashed, $user['id']])) {
                $success = '密码修改成功，下次登录请使用新密码';
            } else {
                $error = '密码修改失败，请稍后重试';
            }
        }
    }

    // ===== 修改邮箱（需重新验证） =====
    if ($formType === 'email') {
        $newEmail = filter_var(trim($_POST['new_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$newEmail) {
            $error = '新邮箱格式无效';
        } elseif ($newEmail === $user['email']) {
            $error = '新邮箱与当前邮箱相同';
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$newEmail]);
            if ($stmt->fetch()) {
                $error = '该邮箱已被其他账号使用';
            } else {
                $token = generateVerifyToken();
                $expiry = date('Y-m-d H:i:s', time() + 86400); // 24小时
                $stmt = $db->prepare('UPDATE users SET email = ?, email_verified = 0, verify_token = ?, token_expiry = ? WHERE id = ?');
                if ($stmt->execute([$newEmail, $token, $expiry, $user['id']])) {
                    sendVerificationEmail($newEmail, $user['username'], $token);
                    $success = '邮箱修改成功，请前往新邮箱点击验证链接完成验证（验证前无法登录）';
                    $user = getCurrentUser();
                } else {
                    $error = '邮箱修改失败，请稍后重试';
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
        <h1>用户中心</h1>
        <?php if ($error !== ''): ?>
            <div class="alert error"><?= escapeHtml($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert success"><?= escapeHtml($success) ?></div>
        <?php endif; ?>

        <h2 class="settings-sub">基本资料</h2>
        <form method="POST" class="form">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="profile">
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
            <div class="form-group">
                <label for="signature">个人签名 (最多200字符)</label>
                <input type="text" id="signature" name="signature" maxlength="200" placeholder="展示在个人主页的签名" value="<?= escapeHtml($user['signature'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="profile_page">个人介绍页面链接 (可选)</label>
                <input type="url" id="profile_page" name="profile_page" placeholder="https://... 配置后史记·人物名片会单独展示跳转入口" value="<?= escapeHtml($user['profile_page'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">保存资料</button>
        </form>

        <h2 class="settings-sub">修改密码</h2>
        <form method="POST" class="form">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="password">
            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">新密码 (至少8位，需含字母和数字)</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">修改密码</button>
        </form>

        <h2 class="settings-sub">修改邮箱</h2>
        <form method="POST" class="form">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="email">
            <div class="form-group">
                <label for="new_email">新邮箱</label>
                <input type="email" id="new_email" name="new_email" required value="">
                <small>修改后需前往新邮箱验证，验证前无法登录；当前邮箱：<?= escapeHtml($user['email']) ?></small>
            </div>
            <button type="submit" class="btn btn-primary">修改邮箱</button>
        </form>

        <h2 class="settings-sub">物实账户</h2>
        <div class="physics-lock-box">
            <p><strong>物实用户名：</strong><?= escapeHtml($user['physics_username'] ?? '未绑定') ?></p>
            <p><strong>物实账户ID：</strong><?= escapeHtml($user['physics_id'] ?? '未绑定') ?></p>
            <p class="form-hint">物实账户一经绑定不可自行修改；如需变更请联系管理员。</p>
        </div>

        <div class="settings-extras">
            <h2>其他操作</h2>
            <p><a href="verify-identity.php" class="btn btn-secondary">申请身份认证</a></p>
            <p><a href="messages.php" class="btn btn-secondary">站内信</a></p>
            <p><a href="profile.php?id=<?= (int)$user['id'] ?>" class="btn btn-secondary">查看我的主页</a></p>
            <form method="POST" action="logout.php" style="display:inline" onsubmit="return confirm('确定要退出登录吗？')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger">退出登录</button>
            </form>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
