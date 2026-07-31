<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

// 获取各板块最新帖子
$sections = [
    'qiming' => '齐鸣',
    'lighthouse' => '灯塔',
    'wenxuan' => '文轩'
];
$latestPosts = [];
foreach ($sections as $key => $name) {
    $stmt = $db->prepare(
        'SELECT p.*, u.username, u.avatar, u.vip_level
         FROM posts p JOIN users u ON p.user_id = u.id
         WHERE p.section = ?
         ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 5'
    );
    $stmt->execute([$key]);
    $latestPosts[$key] = $stmt->fetchAll();
}

// 统计数据
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPosts = $db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$pageTitle = '首页';
include __DIR__ . '/includes/header.php';
?>
<main class="container home">
    <section class="hero">
        <h1>欢迎来到 <?= SITE_NAME ?></h1>
        <p class="hero-sub">物理实验爱好者的交流社区</p>
        <div class="hero-stats">
            <span class="stat"><strong><?= $totalUsers ?></strong> 用户</span>
            <span class="stat"><strong><?= $totalPosts ?></strong> 帖子</span>
        </div>
        <?php if (!$currentUser): ?>
            <a href="/user/register.php" class="btn btn-primary">立即加入</a>
        <?php elseif (canPostInQiming($currentUser)): ?>
            <a href="/qiming/create.php" class="btn btn-primary">发布帖子</a>
        <?php endif; ?>
    </section>

    <div class="home-grid">
        <?php foreach ($sections as $key => $name): ?>
            <section class="home-section">
                <div class="home-section-head">
                    <h2><?= $name ?></h2>
                    <a href="/<?= $key ?>/index.php" class="more-link">更多 →</a>
                </div>
                <?php if (!empty($latestPosts[$key])): ?>
                    <ul class="post-list">
                        <?php foreach ($latestPosts[$key] as $post): ?>
                            <li class="post-list-item">
                                <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                                <a href="<?= postUrl($post['id']) ?>" class="post-list-title">
                                    <?= escapeHtml($post['title']) ?>
                                </a>
                                <span class="post-list-meta">
                                    <?= escapeHtml($post['username']) ?> · <?= timeAgo($post['created_at']) ?> · <?= (int)$post['comment_count'] ?>评
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="empty-tip">暂无内容</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
