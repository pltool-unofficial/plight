<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    renderErrorPage('帖子不存在', '您访问的帖子可能已被删除或链接错误。', 404);
}

$db = Database::getInstance();

$stmt = $db->prepare(
    'SELECT p.*, u.username, u.avatar, u.verify_label, u.is_admin
     FROM posts p JOIN users u ON p.user_id = u.id
     WHERE p.id = ?'
);
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    renderErrorPage('帖子不存在', '您访问的帖子可能已被删除或链接错误。', 404);
}

incrementViewCount($postId);

$stmt = $db->prepare(
    'SELECT c.*, u.username, u.avatar, u.verify_label, u.is_admin
     FROM comments c JOIN users u ON c.user_id = u.id
     WHERE c.post_id = ?
     ORDER BY c.created_at ASC'
);
$stmt->execute([$postId]);
$allComments = $stmt->fetchAll();

$topComments = [];
$replies = [];
$commentMap = [];
foreach ($allComments as $c) {
    $commentMap[(int)$c['id']] = $c;
}
foreach ($allComments as $c) {
    if ($c['parent_id'] === null) {
        $topComments[] = $c;
    } else {
        $ancestor = (int)$c['parent_id'];
        while (isset($commentMap[$ancestor]) && $commentMap[$ancestor]['parent_id'] !== null) {
            $ancestor = (int)$commentMap[$ancestor]['parent_id'];
        }
        $replies[$ancestor][] = $c;
    }
}

$canEdit = $currentUser && ($currentUser['id'] == $post['user_id'] || isAdmin($currentUser));
$canComment = $currentUser && !$post['is_locked']
    && !$currentUser['is_muted'] && !$currentUser['is_banned'];

$pageTitle = $post['title'];
$useMarkdown = true;
include __DIR__ . '/includes/header.php';
?>
<main class="container post-detail">
    <article class="post-article">
        <header class="post-header">
            <h1 class="post-title">
                <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                <?php if ($post['is_locked']): ?><span class="lock-tag">锁定</span><?php endif; ?>
                <?= escapeHtml($post['title']) ?>
            </h1>
            <div class="post-meta">
                <span class="post-author">
                    <img src="<?= escapeHtml($post['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="avatar-sm">
                    <a href="/user/profile.php?id=<?= (int)$post['user_id'] ?>"><?= escapeHtml($post['username']) ?></a>
                    <?= getVerifyBadge($post['verify_label']) ?>
                    <?= renderMedalBadges((int)$post['user_id'], 'post') ?>
                    <?php if ($post['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                </span>
                <span class="post-info">
                    <?php if (!empty($post['category'])): ?><span class="cat-tag"><?= escapeHtml($post['category']) ?></span><?php endif; ?>
                    <span><?= timeAgo($post['created_at']) ?></span>
                    <span>浏览 <?= (int)$post['view_count'] ?></span>
                    <span>评论 <?= (int)$post['comment_count'] ?></span>
                </span>
                <?php if ($canEdit): ?>
                    <a href="/qiming/edit.php?id=<?= (int)$post['id'] ?>" class="btn btn-sm btn-secondary">编辑</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="post-content markdown-body">
            <?= $post['content_html'] ?>
        </div>
    </article>

    <section class="comments-section">
        <h2 class="comments-title">评论 (<?= (int)$post['comment_count'] ?>)</h2>

        <?php if ($post['is_locked']): ?>
            <p class="locked-tip">该帖子已锁定，无法评论。</p>
        <?php elseif (!$currentUser): ?>
            <p class="login-tip">请 <a href="/user/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">登录</a> 后参与评论。</p>
        <?php else: ?>
            <form class="comment-form" data-comment-form method="POST" action="/api/comment.php">
                <?= csrfField() ?>
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <input type="hidden" name="parent_id" value="">
                <div class="form-group">
                    <textarea name="content" rows="4" placeholder="发表你的评论..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">发表评论</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($topComments)): ?>
            <ul class="comment-list">
                <?php foreach ($topComments as $comment): ?>
                    <li class="comment" id="comment-<?= (int)$comment['id'] ?>">
                        <div class="comment-avatar">
                            <img src="<?= escapeHtml($comment['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="avatar-sm">
                        </div>
                        <div class="comment-body">
                            <div class="comment-head">
                                <a href="/user/profile.php?id=<?= (int)$comment['user_id'] ?>"><?= escapeHtml($comment['username']) ?></a>
                                <?= getVerifyBadge($comment['verify_label']) ?>
                                <?= renderMedalBadges((int)$comment['user_id'], 'all') ?>
                                <?php if ($comment['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                                <span class="comment-time"><?= timeAgo($comment['created_at']) ?></span>
                            </div>
                            <div class="comment-content"><?= $comment['content_html'] ?></div>
                            <?php if ($canComment): ?>
                                <button type="button" class="comment-reply-btn btn btn-sm btn-link">回复</button>
                                <form class="reply-form" data-comment-form method="POST" action="/api/comment.php">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                    <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
                                    <textarea name="content" rows="3" placeholder="回复 <?= escapeHtml($comment['username']) ?>..." required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">回复</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($replies[(int)$comment['id']])): ?>
                                <ul class="comment-children">
                                    <?php foreach ($replies[(int)$comment['id']] as $reply): ?>
                                        <li class="comment comment-child" id="comment-<?= (int)$reply['id'] ?>">
                                            <div class="comment-avatar">
                                                <img src="<?= escapeHtml($reply['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="avatar-sm">
                                            </div>
                                            <div class="comment-body">
                                                <div class="comment-head">
                                                    <a href="/user/profile.php?id=<?= (int)$reply['user_id'] ?>"><?= escapeHtml($reply['username']) ?></a>
                                                    <?= getVerifyBadge($reply['verify_label']) ?>
                                                    <?= renderMedalBadges((int)$reply['user_id'], 'all') ?>
                                                    <?php if ($reply['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                                                    <span class="comment-time"><?= timeAgo($reply['created_at']) ?></span>
                                                </div>
                                                <div class="comment-content"><?= $reply['content_html'] ?></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-tip">暂无评论，快来抢沙发。</p>
        <?php endif; ?>
    </section>
</main>
<script src="/assets/js/comment.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
