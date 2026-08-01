<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// 需登录
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = Database::getInstance();
$user = getCurrentUser();

if (!$user) {
    header('Location: login.php');
    exit;
}

// 获取当前用户通知列表
$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// 进入页面后将未读标记为已读（仅标记当前显示的100条，避免大批量更新）
$stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 LIMIT 100');
$stmt->execute([$user['id']]);

// 通知类型文案映射
$typeLabels = [
    'reply' => '回复',
    'like' => '点赞',
    'system' => '系统',
    'verify' => '认证',
    'message' => '站内信',
];

$pageTitle = '通知';
include __DIR__ . '/../includes/header.php';
?>
<main class="container notifications-page">
    <div class="notifications-head">
        <h1>我的通知</h1>
        <a href="/user/profile.php?id=<?= (int)$user['id'] ?>" class="btn btn-secondary btn-sm">返回主页</a>
    </div>

    <?php if (empty($notifications)): ?>
        <p class="empty-tip">暂无通知</p>
    <?php else: ?>
        <ul class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <li class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                    <div class="notification-main">
                        <span class="notification-type"><?= escapeHtml($typeLabels[$n['type']] ?? $n['type']) ?></span>
                        <span class="notification-content"><?= escapeHtml($n['content']) ?></span>
                    </div>
                    <div class="notification-meta">
                        <span class="notification-time"><?= timeAgo($n['created_at']) ?></span>
                        <?php if (!empty($n['link'])): ?>
                            <a href="<?= escapeHtml($n['link']) ?>" class="notification-link">查看</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
