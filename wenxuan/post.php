<?php
/**
 * 文轩 - 帖子详情页
 * 与 qiming/lighthouse 同构，仅板块限制为 wenxuan
 */
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('无效的帖子ID');
}

$db = Database::getInstance();

// 获取帖子（仅限 wenxuan 板块）
$stmt = $db->prepare('
    SELECT p.*, u.username, u.avatar, u.vip_level, u.is_admin 
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = ? AND p.section = "wenxuan"
');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('帖子不存在或不在文轩板块');
}

// 更新浏览量
$stmt = $db->prepare('UPDATE posts SET view_count = view_count + 1 WHERE id = ?');
$stmt->execute([$id]);

// 获取评论
$stmt = $db->prepare('
    SELECT c.*, u.username, u.avatar, u.vip_level, u.is_admin 
    FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at ASC
');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$pageTitle = $post['title'] . ' - 文轩';
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/markdown.css">

<main class="container post-detail">
    <article class="post-article">
        <!-- 帖子头部 -->
        <header class="post-header">
            <h1 class="post-title"><?= escapeHtml($post['title']) ?></h1>
            <div class="post-meta">
                <div class="post-author">
                    <img src="<?= escapeHtml($post['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="" class="avatar-small">
                    <a href="/user/profile.php?id=<?= $post['user_id'] ?>">
                        <?= escapeHtml($post['username']) ?>
                    </a>
                    <?= getVBadge($post['vip_level']) ?>
                    <?php if ($post['is_admin']): ?>
                        <span class="admin-badge">管理员</span>
                    <?php endif; ?>
                </div>
                <div class="post-info">
                    <span class="post-category"><?= escapeHtml($post['category'] ?? '未分类') ?></span>
                    <span class="post-date">发布于 <?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                    <span class="post-stats">👁 <?= $post['view_count'] ?> · 💬 <?= $post['comment_count'] ?></span>
                </div>
            </div>
        </header>

        <!-- 帖子内容 -->
        <div class="post-content markdown-body">
            <?= $post['content_html'] ?>
        </div>

        <!-- 管理员操作 -->
        <?php if (isAdmin(getCurrentUser())): ?>
            <div class="admin-actions">
                <a href="/admin/edit-post.php?id=<?= $post['id'] ?>" class="btn btn-secondary">编辑</a>
                <a href="/admin/delete-post.php?id=<?= $post['id'] ?>" class="btn btn-danger" onclick="return confirm('确定要删除此帖吗？')">删除</a>
            </div>
        <?php endif; ?>
    </article>

    <!-- 评论区 -->
    <section class="post-comments">
        <h2>评论 (<?= count($comments) ?>)</h2>

        <?php if (isLoggedIn() && !$post['is_locked']): ?>
            <form method="POST" action="/api/comment.php" class="comment-form">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <?= csrfField() ?>
                <textarea name="content" rows="4" placeholder="写下你的评论..." required></textarea>
                <button type="submit" class="btn btn-primary">发表评论</button>
            </form>
        <?php elseif ($post['is_locked']): ?>
            <p class="hint">此帖已锁定，无法评论。</p>
        <?php else: ?>
            <p class="hint"><a href="/user/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">登录</a>后发表评论</p>
        <?php endif; ?>

        <?php if ($comments): ?>
            <div class="comments-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                        <div class="comment-meta">
                            <img src="<?= escapeHtml($comment['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="" class="avatar-small">
                            <strong><?= escapeHtml($comment['username']) ?></strong>
                            <?= getVBadge($comment['vip_level']) ?>
                            <span class="comment-date"><?= timeAgo($comment['created_at']) ?></span>
                        </div>
                        <div class="comment-content"><?= $comment['content_html'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="hint">还没有评论，快来抢沙发吧！</p>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/comment.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>