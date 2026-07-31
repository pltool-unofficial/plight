<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$db = Database::getInstance();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /index.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    renderErrorPage('用户不存在', '该用户不存在或已注销。', 404);
}

// 获取用户帖子总数
$stmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
$stmt->execute([$userId]);
$postCount = (int)$stmt->fetchColumn();

// 获取用户帖子列表
$stmt = $db->prepare('SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$stmt->execute([$userId]);
$posts = $stmt->fetchAll();

$isOwner = isLoggedIn() && $_SESSION['user_id'] == $userId;

$pageTitle = escapeHtml($user['username']) . ' 的主页';
include __DIR__ . '/../includes/header.php';
?>
<main class="container profile-page">
    <div class="profile-header">
        <div class="profile-avatar">
            <img src="<?= escapeHtml($user['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" onerror="this.src='/assets/images/default-avatar.svg'">
        </div>
        <div class="profile-info">
            <h1>
                <?= escapeHtml($user['username']) ?>
                <?= getVBadge($user['vip_level']) ?>
                <?php if ($user['is_admin'] == 1): ?>
                    <span class="admin-badge">管理员</span>
                <?php endif; ?>
                <?php if ($user['verified'] == 1): ?>
                    <span class="verified-badge">已认证</span>
                <?php elseif ($user['verified'] == 0 && !empty($user['physics_username'])): ?>
                    <span class="pending-badge">待审核</span>
                <?php endif; ?>
                <?php if ($user['is_banned'] == 1): ?>
                    <span class="banned-badge">已封号</span>
                <?php endif; ?>
            </h1>
            <p class="profile-bio"><?= escapeHtml($user['bio'] ?? '这个人很懒，什么都没写') ?></p>
            <div class="profile-meta">
                <span>加入于 <?= date('Y-m-d', strtotime($user['created_at'])) ?></span>
                <span>帖子 <?= $postCount ?></span>
                <?php if (!empty($user['physics_username'])): ?>
                    <span>物实：<?= escapeHtml($user['physics_username']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isOwner): ?>
                <div class="profile-actions">
                    <a href="settings.php" class="btn btn-secondary">编辑资料</a>
                    <a href="verify-identity.php" class="btn btn-primary">申请身份认证</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-posts">
        <h2>发布的帖子</h2>
        <?php if (empty($posts)): ?>
            <p class="empty-tip">暂无帖子</p>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-card">
                        <h3>
                            <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                            <a href="<?= postUrl($post['id']) ?>"><?= escapeHtml($post['title']) ?></a>
                        </h3>
                        <p class="post-excerpt"><?= escapeHtml(mb_substr(strip_tags($post['content_html']), 0, 150, 'UTF-8')) ?><?= mb_strlen(strip_tags($post['content_html']), 'UTF-8') > 150 ? '...' : '' ?></p>
                        <span class="post-meta">
                            <?= escapeHtml($post['section']) ?> · <?= timeAgo($post['created_at']) ?> · <?= (int)$post['comment_count'] ?>评 · <?= (int)$post['view_count'] ?>阅
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
