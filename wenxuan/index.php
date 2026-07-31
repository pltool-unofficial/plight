<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '文轩';
include __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$stmt = $db->prepare('SELECT p.*, u.username, u.vip_level FROM posts p JOIN users u ON p.user_id = u.id WHERE p.section = ? ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 10');
$stmt->execute(['wenxuan']);
$posts = $stmt->fetchAll();

$cards = [
    ['frontier.php', '前沿新闻', '物理学前沿动态与新闻'],
    ['papers.php', '论文共赏', '经典与前沿论文共赏'],
    ['wenxin.php', '文心雕龙', '科普通识与随笔'],
    ['haina.php', '海纳百川', '跨学科与综合话题'],
];
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">文轩</h1>
            <p class="post-meta">学术与人文交汇之所,汇聚前沿、论文与随笔。</p>
        </div>
        <?php if (canPostInWenxuan($currentUser)): ?>
            <a href="/qiming/create.php?section=wenxuan" class="btn btn-sm btn-primary">发布文轩帖</a>
        <?php endif; ?>
    </div>

    <div class="section-grid">
        <?php foreach ($cards as $card): ?>
            <a href="<?= escapeHtml($card[0]) ?>" class="card-link">
                <h3><?= escapeHtml($card[1]) ?></h3>
                <p><?= escapeHtml($card[2]) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="home-section">
        <h2>最新帖子</h2>
        <?php if (empty($posts)): ?>
            <p class="empty-tip">文轩暂无帖子。</p>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-list-item">
                        <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                        <a href="<?= postUrl($post['id']) ?>" class="post-list-title"><?= escapeHtml($post['title']) ?></a>
                        <div class="post-list-meta">作者: <?= escapeHtml($post['username']) ?> <?= getVBadge($post['vip_level']) ?> · <?= timeAgo($post['created_at']) ?> · 评论 <?= (int)$post['comment_count'] ?> · 浏览 <?= (int)$post['view_count'] ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';
