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
    $page = max(1, (int)($_POST['page'] ?? 1));
    $pageParam = $page > 1 ? '?page=' . $page : '';

    if ($targetId > 0) {
        // 置顶
        if ($action === 'pin') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE posts SET is_pinned = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'pin_post', $targetId, "置顶: $value");
            header('Location: posts.php' . $pageParam);
            exit;
        }

        // 锁定
        if ($action === 'lock') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE posts SET is_locked = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'lock_post', $targetId, "锁定: $value");
            header('Location: posts.php' . $pageParam);
            exit;
        }

        // 删除
        if ($action === 'delete') {
            $stmt = $db->prepare('SELECT title, user_id FROM posts WHERE id = ?');
            $stmt->execute([$targetId]);
            $post = $stmt->fetch();
            if ($post) {
                $stmt = $db->prepare('DELETE FROM posts WHERE id = ?');
                $stmt->execute([$targetId]);
                logAdminAction($user['id'], 'delete_post', $targetId, '标题: ' . $post['title']);
                addNotification($post['user_id'], 'system', '您的帖子已被管理员删除：' . $post['title']);
            }
            header('Location: posts.php' . $pageParam);
            exit;
        }
    }

    header('Location: posts.php' . $pageParam);
    exit;
}

// 分页
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = POSTS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalPosts = $db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$stmt = $db->prepare(
    'SELECT p.*, u.username AS author_name, u.vip_level
     FROM posts p JOIN users u ON p.user_id = u.id
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
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

// 渲染单个 POST 操作按钮的辅助函数
function adminPostBtn($postId, $page, $action, $label, $class, $extraHidden = [], $confirm = false) {
    $html = '<form method="POST" action="posts.php" style="display:inline-block;margin:2px"';
    if ($confirm) {
        $html .= " onsubmit=\"return confirm('确定执行此操作？此操作可能不可恢复。')\"";
    }
    $html .= '>';
    $html .= csrfField();
    $html .= '<input type="hidden" name="id" value="' . (int)$postId . '">';
    $html .= '<input type="hidden" name="page" value="' . (int)$page . '">';
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
                            <a href="<?= postUrl($p['id']) ?>">
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
                                <?= adminPostBtn($p['id'], $page, 'pin', '置顶', 'success', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminPostBtn($p['id'], $page, 'pin', '取消置顶', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <?php if (!$p['is_locked']): ?>
                                <?= adminPostBtn($p['id'], $page, 'lock', '锁定', 'warning', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminPostBtn($p['id'], $page, 'lock', '解锁', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <?= adminPostBtn($p['id'], $page, 'delete', '删除', 'danger', [], true) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <tr><td colspan="6" class="empty-tip">暂无帖子</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?= buildPagination($page, $totalPages, '/admin/posts.php') ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
