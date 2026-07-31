<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    renderErrorPage('权限不足', '需要管理员权限才能访问此页面。', 403);
}

$db = Database::getInstance();

// 处理 POST 写操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['id'] ?? 0);
    $tab = $_POST['tab'] ?? 'all';
    $redirectTab = in_array($tab, ['all', 'pending', 'verified', 'banned'], true) ? $tab : 'all';

    if ($targetId > 0) {
        // 认证审核
        if ($action === 'verify') {
            $status = (int)($_POST['status'] ?? 0);
            if (!in_array($status, [1, 2], true)) {
                $status = 1;
            }
            // 检查目标用户是否提交过 physics_username
            $check = $db->prepare('SELECT physics_username FROM users WHERE id = ?');
            $check->execute([$targetId]);
            $target = $check->fetch();
            if ($target && !empty($target['physics_username'])) {
                $stmt = $db->prepare('UPDATE users SET verified = ? WHERE id = ?');
                $stmt->execute([$status, $targetId]);
                logAdminAction($user['id'], 'verify_user', $targetId, "状态: $status");
                addNotification($targetId, 'verify', '您的身份认证已' . ($status == 1 ? '通过' : '被拒绝'));
            }
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 禁言
        if ($action === 'mute') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE users SET is_muted = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'mute_user', $targetId, "禁言: $value");
            addNotification($targetId, 'system', $value == 1 ? '您已被管理员禁言' : '您的禁言已被解除');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 封号
        if ($action === 'ban') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE users SET is_banned = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'ban_user', $targetId, "封号: $value");
            addNotification($targetId, 'system', $value == 1 ? '您已被管理员封号' : '您的账号已被解封');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // V认证设置
        if ($action === 'vip') {
            $level = $_POST['level'] ?? 'none';
            if (!in_array($level, ['none', 'blue', 'yellow', 'red'], true)) {
                $level = 'none';
            }
            $stmt = $db->prepare('UPDATE users SET vip_level = ? WHERE id = ?');
            $stmt->execute([$level, $targetId]);
            logAdminAction($user['id'], 'set_vip', $targetId, "等级: $level");
            $levelText = ['none' => '已取消', 'blue' => '蓝V', 'yellow' => '黄V', 'red' => '红V'];
            addNotification($targetId, 'system', '您的V认证已更新为' . ($levelText[$level] ?? '已取消'));
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }
    }

    header('Location: users.php?tab=' . urlencode($redirectTab));
    exit;
}

$tab = $_GET['tab'] ?? 'all';

// 获取用户列表
$sql = 'SELECT * FROM users';
$params = [];
if ($tab === 'pending') {
    $sql .= ' WHERE verified = 0 AND physics_username IS NOT NULL';
} elseif ($tab === 'verified') {
    $sql .= ' WHERE verified = 1';
} elseif ($tab === 'banned') {
    $sql .= ' WHERE is_banned = 1';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = '用户管理';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';

// 渲染单个 POST 操作按钮的辅助函数
function adminUserBtn($u, $tab, $action, $label, $class, $extraHidden = []) {
    $html = '<form method="POST" action="users.php" style="display:inline-block;margin:2px">';
    $html .= csrfField();
    $html .= '<input type="hidden" name="id" value="' . (int)$u['id'] . '">';
    $html .= '<input type="hidden" name="tab" value="' . escapeHtml($tab) . '">';
    foreach ($extraHidden as $name => $val) {
        $html .= '<input type="hidden" name="' . escapeHtml($name) . '" value="' . escapeHtml($val) . '">';
    }
    $html .= '<button type="submit" name="action" value="' . escapeHtml($action) . '" class="btn-sm ' . escapeHtml($class) . '">' . escapeHtml($label) . '</button>';
    $html .= '</form>';
    return $html;
}
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php" class="active">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>用户管理</h1>
            <div class="admin-tabs">
                <a href="?tab=all" class="<?= $tab === 'all' ? 'active' : '' ?>">全部</a>
                <a href="?tab=pending" class="<?= $tab === 'pending' ? 'active' : '' ?>">待审核</a>
                <a href="?tab=verified" class="<?= $tab === 'verified' ? 'active' : '' ?>">已认证</a>
                <a href="?tab=banned" class="<?= $tab === 'banned' ? 'active' : '' ?>">已封号</a>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>物实ID</th>
                        <th>V认证</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td>
                            <a href="/user/profile.php?id=<?= (int)$u['id'] ?>">
                                <?= escapeHtml($u['username']) ?>
                            </a>
                        </td>
                        <td><?= escapeHtml($u['physics_id'] ?? '-') ?></td>
                        <td>
                            <?php if ($u['vip_level'] === 'red'): ?>
                                <span class="v-badge v-red">红V</span>
                            <?php elseif ($u['vip_level'] === 'yellow'): ?>
                                <span class="v-badge v-yellow">黄V</span>
                            <?php elseif ($u['vip_level'] === 'blue'): ?>
                                <span class="v-badge v-blue">蓝V</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_banned']): ?>
                                <span class="badge danger">已封号</span>
                            <?php elseif ($u['is_muted']): ?>
                                <span class="badge warning">已禁言</span>
                            <?php elseif ($u['verified'] == 1): ?>
                                <span class="badge success">已认证</span>
                            <?php elseif ($u['verified'] == 2): ?>
                                <span class="badge danger">已拒绝</span>
                            <?php else: ?>
                                <span class="badge muted">待审核</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['verified'] == 0 && !empty($u['physics_username'])): ?>
                                <?= adminUserBtn($u, $tab, 'verify', '通过', 'success', ['status' => 1]) ?>
                                <?= adminUserBtn($u, $tab, 'verify', '拒绝', 'danger', ['status' => 2]) ?>
                            <?php endif; ?>
                            <?php if (!$u['is_muted']): ?>
                                <?= adminUserBtn($u, $tab, 'mute', '禁言', 'warning', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminUserBtn($u, $tab, 'mute', '解除禁言', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <?php if (!$u['is_banned']): ?>
                                <?= adminUserBtn($u, $tab, 'ban', '封号', 'danger', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminUserBtn($u, $tab, 'ban', '解封', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <?= adminUserBtn($u, $tab, 'vip', '红V', 'danger', ['level' => 'red']) ?>
                            <?= adminUserBtn($u, $tab, 'vip', '黄V', 'warning', ['level' => 'yellow']) ?>
                            <?= adminUserBtn($u, $tab, 'vip', '蓝V', 'primary', ['level' => 'blue']) ?>
                            <?= adminUserBtn($u, $tab, 'vip', '取消V', 'secondary', ['level' => 'none']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="empty-tip">暂无用户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
