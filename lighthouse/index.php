<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '灯塔';
include __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$stmt = $db->prepare('SELECT p.*, u.username, u.vip_level FROM posts p JOIN users u ON p.user_id = u.id WHERE p.section = ? ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 10');
$stmt->execute(['lighthouse']);
$posts = $stmt->fetchAll();

$cards = [
    ['tutorial-basic.php', '基础教程', '物实基础教程与入门指引'],
    ['tutorial-advanced.php', '进阶教程', '深入物实进阶技巧与方法'],
    ['announcements.php', '公告总结', '灯塔板块公告与重要通知'],
    ['news.php', '最近新闻', '近期动态与资讯汇总'],
    ['other.php', '其他', '教程之外的补充内容'],
];
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">灯塔</h1>
            <p class="post-meta">物理实验学习与教程中心,汇聚基础与进阶指引。</p>
        </div>
        <?php if (canPostInLighthouse($currentUser)): ?>
            <a href="/qiming/create.php?section=lighthouse" class="btn btn-sm btn-primary">发布灯塔帖</a>
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
            <p class="empty-tip">灯塔暂无帖子。</p>
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
