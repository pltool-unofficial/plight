<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    http_response_code(403);
    die('权限不足');
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$tab = $_GET['tab'] ?? 'all';

// 处理认证审核
if ($action === 'verify' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $status = $_GET['status'] ?? 1; // 1=通过, 2=拒绝

    $stmt = $db->prepare('UPDATE users SET verified = ? WHERE id = ?');
    $stmt->execute([$status, $targetId]);
    logAdminAction($user['id'], 'verify_user', $targetId, "状态: $status");
    addNotification($targetId, 'verify', '您的身份认证已' . ($status == 1 ? '通过' : '被拒绝'));
    header('Location: users.php?tab=pending');
    exit;
}

// 处理禁言
if ($action === 'mute' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    $stmt = $db->prepare('UPDATE users SET is_muted = ? WHERE id = ?');
    $stmt->execute([$value, $targetId]);
    logAdminAction($user['id'], 'mute_user', $targetId, "禁言: $value");
    addNotification($targetId, 'system', $value == 1 ? '您已被管理员禁言' : '您的禁言已被解除');
    header('Location: users.php?tab=' . urlencode($tab));
    exit;
}

// 处理封号
if ($action === 'ban' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    $stmt = $db->prepare('UPDATE users SET is_banned = ? WHERE id = ?');
    $stmt->execute([$value, $targetId]);
    logAdminAction($user['id'], 'ban_user', $targetId, "封号: $value");
    addNotification($targetId, 'system', $value == 1 ? '您已被管理员封号' : '您的账号已被解封');
    header('Location: users.php?tab=' . urlencode($tab));
    exit;
}

// 处理V认证设置
if ($action === 'vip' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $level = $_GET['level'] ?? 'none';
    if (!in_array($level, ['none', 'blue', 'yellow', 'red'], true)) {
        $level = 'none';
    }
    $stmt = $db->prepare('UPDATE users SET vip_level = ? WHERE id = ?');
    $stmt->execute([$level, $targetId]);
    logAdminAction($user['id'], 'set_vip', $targetId, "等级: $level");
    $levelText = ['none' => '已取消', 'blue' => '蓝V', 'yellow' => '黄V', 'red' => '红V'];
    addNotification($targetId, 'system', '您的V认证已更新为' . ($levelText[$level] ?? '已取消'));
    header('Location: users.php?tab=' . urlencode($tab));
    exit;
}

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
                                <a href="?action=verify&id=<?= (int)$u['id'] ?>&status=1" class="btn-sm success">通过</a>
                                <a href="?action=verify&id=<?= (int)$u['id'] ?>&status=2" class="btn-sm danger">拒绝</a>
                            <?php endif; ?>
                            <?php if (!$u['is_muted']): ?>
                                <a href="?action=mute&id=<?= (int)$u['id'] ?>&value=1&tab=<?= urlencode($tab) ?>" class="btn-sm warning">禁言</a>
                            <?php else: ?>
                                <a href="?action=mute&id=<?= (int)$u['id'] ?>&value=0&tab=<?= urlencode($tab) ?>" class="btn-sm secondary">解除禁言</a>
                            <?php endif; ?>
                            <?php if (!$u['is_banned']): ?>
                                <a href="?action=ban&id=<?= (int)$u['id'] ?>&value=1&tab=<?= urlencode($tab) ?>" class="btn-sm danger">封号</a>
                            <?php else: ?>
                                <a href="?action=ban&id=<?= (int)$u['id'] ?>&value=0&tab=<?= urlencode($tab) ?>" class="btn-sm secondary">解封</a>
                            <?php endif; ?>
                            <a href="?action=vip&id=<?= (int)$u['id'] ?>&level=red&tab=<?= urlencode($tab) ?>" class="btn-sm danger">红V</a>
                            <a href="?action=vip&id=<?= (int)$u['id'] ?>&level=yellow&tab=<?= urlencode($tab) ?>" class="btn-sm warning">黄V</a>
                            <a href="?action=vip&id=<?= (int)$u['id'] ?>&level=blue&tab=<?= urlencode($tab) ?>" class="btn-sm" style="background:var(--color-primary);color:#fff">蓝V</a>
                            <a href="?action=vip&id=<?= (int)$u['id'] ?>&level=none&tab=<?= urlencode($tab) ?>" class="btn-sm secondary">取消V</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--color-text-secondary)">暂无用户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
