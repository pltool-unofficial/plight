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

// 获取管理员列表
$stmt = $db->prepare('SELECT id, username, avatar, verify_label FROM users WHERE is_admin = 1 ORDER BY id ASC');
$stmt->execute();
$admins = $stmt->fetchAll();

$error = '';
$success = '';
$subject = '';
$content = '';
$recipientType = 'all';
$specificAdminId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }

    $subject = sanitizeInput($_POST['subject'] ?? '');
    $content = $_POST['content'] ?? '';
    $recipientType = $_POST['recipient_type'] ?? 'all';
    $specificAdminId = (int)($_POST['admin_id'] ?? 0);

    if (empty($admins)) {
        $error = '暂无管理员可发送';
    } elseif ($subject === '' || mb_strlen($subject) < 2) {
        $error = '主题至少2个字符';
    } elseif (mb_strlen($subject) > 200) {
        $error = '主题不能超过200个字符';
    } elseif (empty($content) || mb_strlen($content) < 5) {
        $error = '内容至少5个字符';
    } elseif (mb_strlen($content) > 5000) {
        $error = '内容不能超过5000个字符';
    } elseif ($recipientType === 'one' && $specificAdminId <= 0) {
        $error = '请选择要发送的管理员';
    } else {
        $recipients = [];
        if ($recipientType === 'all') {
            foreach ($admins as $admin) {
                $recipients[] = (int)$admin['id'];
            }
        } else {
            // 校验所选管理员确实存在
            $found = false;
            foreach ($admins as $admin) {
                if ((int)$admin['id'] === $specificAdminId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $error = '所选管理员不存在';
            } else {
                $recipients[] = $specificAdminId;
            }
        }

        if ($error === '') {
            $okCount = 0;
            foreach ($recipients as $recipientId) {
                if (sendInternalMessage($user, $recipientId, $subject, $content)) {
                    $okCount++;
                }
            }
            if ($okCount > 0) {
                $success = '站内信已发送给 ' . $okCount . ' 位管理员（已同步抄送邮箱）';
                $subject = '';
                $content = '';
            } else {
                $error = '发送失败，请稍后重试';
            }
        }
    }
}

$pageTitle = '写信 - 站内信';
include __DIR__ . '/../includes/header.php';
?>
<main class="container messages-page">
    <div class="messages-head">
        <h1>写信给管理员</h1>
        <a href="messages.php" class="btn btn-secondary btn-sm">返回站内信</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert success"><?= escapeHtml($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="form">
        <?= csrfField() ?>
        <div class="form-group">
            <label>收件人</label>
            <div class="radio-row">
                <label class="radio-label"><input type="radio" name="recipient_type" value="all" <?= $recipientType === 'all' ? 'checked' : '' ?>> 全部管理员</label>
                <label class="radio-label"><input type="radio" name="recipient_type" value="one" <?= $recipientType === 'one' ? 'checked' : '' ?>> 指定管理员</label>
            </div>
            <select id="admin_id" name="admin_id" class="admin-select">
                <option value="0">-- 请选择管理员 --</option>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?= (int)$admin['id'] ?>" <?= (int)$admin['id'] === $specificAdminId ? 'selected' : '' ?>>
                        <?= escapeHtml($admin['username']) ?><?= !empty($admin['verify_label']) ? '（' . escapeHtml($admin['verify_label']) . '）' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="subject">主题</label>
            <input type="text" id="subject" name="subject" required minlength="2" maxlength="200" value="<?= escapeHtml($subject) ?>">
        </div>
        <div class="form-group">
            <label for="content">内容 (支持 Markdown)</label>
            <textarea id="content" name="content" rows="8" required><?= escapeHtml($content) ?></textarea>
            <small>发送后站内信将同步抄送收件人邮箱。</small>
        </div>
        <button type="submit" class="btn btn-primary">发送</button>
        <a href="messages.php" class="btn btn-secondary">取消</a>
    </form>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var oneRadio = document.querySelector('input[name="recipient_type"][value="one"]');
    var adminSelect = document.getElementById('admin_id');
    function toggle() {
        var isOne = oneRadio && oneRadio.checked;
        adminSelect.disabled = !isOne;
        if (!isOne) { adminSelect.value = '0'; }
    }
    document.querySelectorAll('input[name="recipient_type"]').forEach(function (r) {
        r.addEventListener('change', toggle);
    });
    toggle();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
