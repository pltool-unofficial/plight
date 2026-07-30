<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '文心雕龙 - 文轩';
include __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$stmt = $db->prepare('SELECT p.*, u.username, u.vip_level FROM posts p JOIN users u ON p.user_id = u.id WHERE p.section = ? AND p.category = ? ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 20');
$stmt->execute(['wenxuan', '文心']);
$posts = $stmt->fetchAll();
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">文心雕龙</h1>
            <p class="post-meta">科普通识与随笔。</p>
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
                        <a href="/qiming/post.php?id=<?= (int)$post['id'] ?>" class="post-list-title"><?= escapeHtml($post['title']) ?></a>
                        <div class="post-list-meta">作者: <?= escapeHtml($post['username']) ?> <?= getVBadge($post['vip_level']) ?> · <?= timeAgo($post['created_at']) ?> · 评论 <?= (int)$post['comment_count'] ?> · 浏览 <?= (int)$post['view_count'] ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';
