<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

$page = max(1, (int)($_GET['page'] ?? 1));
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

$allowedCategories = ['思考', '闲聊', '水贴', '问答', '其他', '自定义'];
if ($category !== '' && !in_array($category, $allowedCategories, true)) {
    $category = '';
}

$perPage = (int)POSTS_PER_PAGE;

$where = "WHERE p.section = 'qiming'";
$params = [];
if ($category !== '') {
    $where .= " AND p.category = ?";
    $params[] = $category;
}

$stmt = $db->prepare("SELECT COUNT(*) FROM posts p " . $where);
$stmt->execute($params);
$totalPosts = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT p.*, u.username, u.avatar, u.vip_level
     FROM posts p JOIN users u ON p.user_id = u.id
     " . $where . "
     ORDER BY p.is_pinned DESC, p.created_at DESC
     LIMIT " . $perPage . " OFFSET " . $offset
);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$pageTitle = '齐鸣';
include __DIR__ . '/../includes/header.php';
?>
<main class="container section-qiming">
    <div class="section-head">
        <h1>齐鸣</h1>
        <p class="section-desc">灯光社区主板块，发帖与评论交流之地。</p>
    </div>

    <div class="section-toolbar">
        <div class="category-filter">
            <a href="/qiming/index.php" class="<?= $category === '' ? 'active' : '' ?>">全部</a>
            <?php foreach ($allowedCategories as $cat): ?>
                <a href="/qiming/index.php?category=<?= urlencode($cat) ?>" class="<?= $category === $cat ? 'active' : '' ?>"><?= escapeHtml($cat) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="section-actions">
            <?php if ($currentUser && canPostInQiming($currentUser)): ?>
                <a href="/qiming/create.php" class="btn btn-primary">发帖</a>
            <?php elseif (!$currentUser): ?>
                <a href="/user/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-secondary">登录后发帖</a>
            <?php else: ?>
                <span class="muted-tip">需身份认证后才能发帖</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($posts)): ?>
        <ul class="post-list">
            <?php foreach ($posts as $post): ?>
                <li class="post-list-item">
                    <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                    <?php if ($post['is_locked']): ?><span class="lock-tag">锁定</span><?php endif; ?>
                    <?php if (!empty($post['category'])): ?><span class="cat-tag"><?= escapeHtml($post['category']) ?></span><?php endif; ?>
                    <a href="<?= postUrl($post['id']) ?>" class="post-list-title"><?= escapeHtml($post['title']) ?></a>
                    <span class="post-list-meta">
                        <?= escapeHtml($post['username']) ?> · <?= timeAgo($post['created_at']) ?> · 浏览 <?= (int)$post['view_count'] ?> · 评论 <?= (int)$post['comment_count'] ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php
        $baseUrl = '/qiming/index.php';
        if ($category !== '') {
            $baseUrl .= '?category=' . urlencode($category);
        }
        echo buildPagination($page, $totalPages, $baseUrl);
        ?>
    <?php else: ?>
        <p class="empty-tip">暂无帖子，快来发表第一篇吧。</p>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
