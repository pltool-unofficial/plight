<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '前沿新闻 - 文轩';
include __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = POSTS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$total = $db->prepare('SELECT COUNT(*) FROM posts WHERE section = ? AND category = ?');
$total->execute(['wenxuan', '前沿']);
$totalPosts = $total->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$stmt = $db->prepare('SELECT p.*, u.username, u.verify_label FROM posts p JOIN users u ON p.user_id = u.id WHERE p.section = ? AND p.category = ? ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, 'wenxuan');
$stmt->bindValue(2, '前沿');
$stmt->bindValue(3, $perPage, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">前沿新闻</h1>
            <p class="post-meta">物理学前沿动态与新闻。</p>
        </div>
        <a href="/wenxuan/index.php" class="btn btn-sm btn-secondary">返回文轩</a>
    </div>
    <div class="home-section">
        <?php if (empty($posts)): ?>
            <p class="empty-tip">暂无内容,敬请期待。</p>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-list-item">
                        <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                        <a href="<?= postUrl($post['id']) ?>" class="post-list-title"><?= escapeHtml($post['title']) ?></a>
                        <div class="post-list-meta">作者: <?= escapeHtml($post['username']) ?> <?= getVerifyBadge($post['verify_label']) ?> · <?= timeAgo($post['created_at']) ?> · 评论 <?= (int)$post['comment_count'] ?> · 浏览 <?= (int)$post['view_count'] ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= buildPagination($page, $totalPages, '/wenxuan/frontier.php') ?>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';
