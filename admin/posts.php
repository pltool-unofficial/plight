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

// 处理置顶
if ($action === 'pin' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    $stmt = $db->prepare('UPDATE posts SET is_pinned = ? WHERE id = ?');
    $stmt->execute([$value, $targetId]);
    logAdminAction($user['id'], 'pin_post', $targetId, "置顶: $value");
    header('Location: posts.php');
    exit;
}

// 处理锁定
if ($action === 'lock' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    $stmt = $db->prepare('UPDATE posts SET is_locked = ? WHERE id = ?');
    $stmt->execute([$value, $targetId]);
    logAdminAction($user['id'], 'lock_post', $targetId, "锁定: $value");
    header('Location: posts.php');
    exit;
}

// 处理删除
if ($action === 'delete' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];
    $stmt = $db->prepare('SELECT title, user_id FROM posts WHERE id = ?');
    $stmt->execute([$targetId]);
    $post = $stmt->fetch();
    if ($post) {
        $stmt = $db->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$targetId]);
        logAdminAction($user['id'], 'delete_post', $targetId, '标题: ' . $post['title']);
        addNotification($post['user_id'], 'system', '您的帖子已被管理员删除：' . $post['title']);
    }
    header('Location: posts.php');
    exit;
}

// 获取帖子列表（JOIN users 取作者）
$stmt = $db->prepare(
    'SELECT p.*, u.username AS author_name, u.vip_level
     FROM posts p JOIN users u ON p.user_id = u.id
     ORDER BY p.created_at DESC'
);
$stmt->execute();
$posts = $stmt->fetchAll();

$sectionNames = [
    'qiming' => '齐鸣',
    'lighthouse' => '灯塔',
    'wenxuan' => '文轩'
];

$pageTitle = '帖子管理';
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
                <li><a href="posts.php" class="active">帖子管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>帖子管理</h1>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>作者</th>
                        <th>板块</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td>
                            <a href="/<?= escapeHtml($p['section']) ?>/post.php?id=<?= (int)$p['id'] ?>">
                                <?= escapeHtml($p['title']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="/user/profile.php?id=<?= (int)$p['user_id'] ?>">
                                <?= escapeHtml($p['author_name']) ?>
                            </a>
                            <?= getVBadge($p['vip_level']) ?>
                        </td>
                        <td><?= escapeHtml($sectionNames[$p['section']] ?? $p['section']) ?></td>
                        <td>
                            <?php if ($p['is_pinned']): ?><span class="badge success">置顶</span><?php endif; ?>
                            <?php if ($p['is_locked']): ?><span class="badge warning">锁定</span><?php endif; ?>
                            <?php if (!$p['is_pinned'] && !$p['is_locked']): ?><span class="badge muted">正常</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$p['is_pinned']): ?>
                                <a href="?action=pin&id=<?= (int)$p['id'] ?>&value=1" class="btn-sm success">置顶</a>
                            <?php else: ?>
                                <a href="?action=pin&id=<?= (int)$p['id'] ?>&value=0" class="btn-sm secondary">取消置顶</a>
                            <?php endif; ?>
                            <?php if (!$p['is_locked']): ?>
                                <a href="?action=lock&id=<?= (int)$p['id'] ?>&value=1" class="btn-sm warning">锁定</a>
                            <?php else: ?>
                                <a href="?action=lock&id=<?= (int)$p['id'] ?>&value=0" class="btn-sm secondary">解锁</a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?= (int)$p['id'] ?>" class="btn-sm danger" onclick="return confirm('确定删除该帖子？此操作不可恢复。')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--color-text-secondary)">暂无帖子</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
