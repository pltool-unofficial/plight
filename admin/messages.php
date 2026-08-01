<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    renderErrorPage('权限不足', '需要管理员权限才能访问此页面。', 403);
}

$db = Database::getInstance();
$error = '';

// 用户分组定义（站内信群发目标）
$groups = [
    'all' => '全部用户',
    'verified' => '已认证用户',
    'unverified' => '未认证用户',
    'admins' => '管理员',
    'banned' => '已封号用户',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    $action = $_POST['action'] ?? 'send';

    // ===== 发送站内信 =====
    if ($action === 'send') {
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $content = $_POST['content'] ?? '';
        $targetType = $_POST['target_type'] ?? 'group'; // group | one
        $groupId = $_POST['group'] ?? 'all';
        $singleUserId = (int)($_POST['user_id'] ?? 0);

        if ($subject === '' || mb_strlen($subject) < 2) {
            $error = '主题至少2个字符';
        } elseif (mb_strlen($subject) > 200) {
            $error = '主题不能超过200个字符';
        } elseif (empty($content) || mb_strlen($content) < 5) {
            $error = '内容至少5个字符';
        } elseif (mb_strlen($content) > 5000) {
            $error = '内容不能超过5000个字符';
        } else {
            // 解析收件人ID列表
            $recipientIds = [];
            if ($targetType === 'one') {
                if ($singleUserId <= 0) {
                    $error = '请选择要发送的用户';
                } else {
                    $recipientIds[] = $singleUserId;
                }
            } else {
                if (!isset($groups[$groupId])) {
                    $groupId = 'all';
                }
                switch ($groupId) {
                    case 'all':
                        $rows = $db->query('SELECT id FROM users WHERE is_banned = 0')->fetchAll();
                        break;
                    case 'verified':
                        $rows = $db->query('SELECT id FROM users WHERE verified = 1 AND is_banned = 0')->fetchAll();
                        break;
                    case 'unverified':
                        $rows = $db->query('SELECT id FROM users WHERE verified != 1 AND is_banned = 0')->fetchAll();
                        break;
                    case 'admins':
                        $rows = $db->query('SELECT id FROM users WHERE is_admin = 1')->fetchAll();
                        break;
                    case 'banned':
                        $rows = $db->query('SELECT id FROM users WHERE is_banned = 1')->fetchAll();
                        break;
                    default:
                        $rows = [];
                }
                foreach ($rows as $row) {
                    $recipientIds[] = (int)$row['id'];
                }
            }

            if ($error === '' && empty($recipientIds)) {
                $error = '该分组下没有可发送的用户';
            }

            if ($error === '') {
                $okCount = 0;
                foreach ($recipientIds as $recipientId) {
                    if (sendInternalMessage($user, $recipientId, $subject, $content)) {
                        $okCount++;
                    }
                }
                logAdminAction($user['id'], 'send_message', null, '收件人数量: ' . $okCount . '，主题: ' . $subject);
                $_SESSION['msg_flash'] = '站内信已发送给 ' . $okCount . ' 位用户（已同步抄送邮箱）';
                header('Location: messages.php');
                exit;
            }
        }
    }

    // ===== 删除站内信 =====
    if ($action === 'delete') {
        $messageId = (int)($_POST['id'] ?? 0);
        if ($messageId > 0) {
            $stmt = $db->prepare('DELETE FROM messages WHERE id = ?');
            $stmt->execute([$messageId]);
            logAdminAction($user['id'], 'delete_message', $messageId);
        }
        header('Location: messages.php');
        exit;
    }
}

$flash = $_SESSION['msg_flash'] ?? '';
unset($_SESSION['msg_flash']);

// 用户下拉列表（仅取前500，避免大表卡顿）
$userList = $db->query('SELECT id, username FROM users ORDER BY username ASC LIMIT 500')->fetchAll();

// 最近站内信列表
$stmt = $db->prepare(
    'SELECT m.*, su.username AS sender_name, ru.username AS recipient_name
     FROM messages m
     JOIN users su ON m.sender_id = su.id
     JOIN users ru ON m.recipient_id = ru.id
     ORDER BY m.created_at DESC LIMIT 100'
);
$stmt->execute();
$messages = $stmt->fetchAll();

$pageTitle = '站内信管理';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="messages.php" class="active">站内信</a></li>
                <li><a href="medals.php">勋章管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>站内信管理</h1>

            <?php if (!empty($flash)): ?>
                <div class="alert success"><?= escapeHtml($flash) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert error"><?= escapeHtml($error) ?></div>
            <?php endif; ?>

            <div class="admin-card">
                <h2>发送站内信</h2>
                <p class="admin-hint">可选择发送给单个用户或用户组；所有站内信会同步抄送收件人邮箱。</p>
                <form method="POST" action="messages.php" class="form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send">
                    <div class="form-group">
                        <label>发送对象</label>
                        <div class="radio-row">
                            <label class="radio-label"><input type="radio" name="target_type" value="group" checked> 用户组</label>
                            <label class="radio-label"><input type="radio" name="target_type" value="one"> 单个用户</label>
                        </div>
                    </div>
                    <div class="form-group" id="group-field">
                        <label for="group">选择用户组</label>
                        <select id="group" name="group">
                            <?php foreach ($groups as $key => $name): ?>
                                <option value="<?= escapeHtml($key) ?>"><?= escapeHtml($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="user-field" style="display:none">
                        <label for="user_id">选择用户</label>
                        <select id="user_id" name="user_id">
                            <option value="0">-- 请选择用户 --</option>
                            <?php foreach ($userList as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= escapeHtml($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject">主题</label>
                        <input type="text" id="subject" name="subject" required minlength="2" maxlength="200" value="<?= escapeHtml($_POST['subject'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="content">内容 (支持 Markdown)</label>
                        <textarea id="content" name="content" rows="6" required><?= escapeHtml($_POST['content'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">发送</button>
                </form>
            </div>

            <h2>最近站内信</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>发件人</th>
                        <th>收件人</th>
                        <th>主题</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?= (int)$m['id'] ?></td>
                        <td><?= escapeHtml($m['sender_name']) ?></td>
                        <td><?= escapeHtml($m['recipient_name']) ?></td>
                        <td>
                            <a href="#" onclick="alert(document.getElementById('msg-preview-<?= (int)$m['id'] ?>').textContent);return false;">
                                <?= escapeHtml($m['subject']) ?>
                            </a>
                            <div id="msg-preview-<?= (int)$m['id'] ?>" style="display:none"><?= strip_tags($m['content_html']) ?></div>
                        </td>
                        <td>
                            <?php if ($m['is_read']): ?>
                                <span class="badge success">已读</span>
                            <?php else: ?>
                                <span class="badge warning">未读</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escapeHtml(date('Y-m-d H:i', strtotime($m['created_at']))) ?></td>
                        <td>
                            <form method="POST" action="messages.php" style="display:inline-block;margin:2px" onsubmit="return confirm('确定删除这条站内信？')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="btn-sm danger">删除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($messages)): ?>
                    <tr><td colspan="7" class="empty-tip">暂无站内信</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var radios = document.querySelectorAll('input[name="target_type"]');
    var groupField = document.getElementById('group-field');
    var userField = document.getElementById('user-field');
    function toggle() {
        var isGroup = document.querySelector('input[name="target_type"]:checked').value === 'group';
        groupField.style.display = isGroup ? '' : 'none';
        userField.style.display = isGroup ? 'none' : '';
        document.getElementById('user_id').disabled = isGroup;
    }
    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    toggle();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
