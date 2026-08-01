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

$tab = isset($_GET['tab']) && $_GET['tab'] === 'sent' ? 'sent' : 'inbox';

if ($tab === 'inbox') {
    // 收件箱：收到的站内信
    $stmt = $db->prepare(
        'SELECT m.*, u.username AS sender_name, u.avatar AS sender_avatar, u.verify_label AS sender_label, u.is_admin AS sender_admin
         FROM messages m JOIN users u ON m.sender_id = u.id
         WHERE m.recipient_id = ?
         ORDER BY m.created_at DESC LIMIT 100'
    );
    $stmt->execute([$user['id']]);
    $messages = $stmt->fetchAll();

    // 进入页面后将未读标记为已读（仅标记当前显示的100条）
    $stmt = $db->prepare('UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND is_read = 0 LIMIT 100');
    $stmt->execute([$user['id']]);
} else {
    // 已发送：我发出的站内信
    $stmt = $db->prepare(
        'SELECT m.*, u.username AS recipient_name, u.verify_label AS recipient_label
         FROM messages m JOIN users u ON m.recipient_id = u.id
         WHERE m.sender_id = ?
         ORDER BY m.created_at DESC LIMIT 100'
    );
    $stmt->execute([$user['id']]);
    $messages = $stmt->fetchAll();
}

$pageTitle = '站内信';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container messages-page">
    <div class="messages-head">
        <h1>站内信</h1>
        <div class="messages-actions">
            <a href="message-send.php" class="btn btn-primary btn-sm">写信</a>
            <a href="/user/profile.php?id=<?= (int)$user['id'] ?>" class="btn btn-secondary btn-sm">返回主页</a>
        </div>
    </div>

    <div class="admin-tabs">
        <a href="?tab=inbox" class="<?= $tab === 'inbox' ? 'active' : '' ?>">收件箱</a>
        <a href="?tab=sent" class="<?= $tab === 'sent' ? 'active' : '' ?>">已发送</a>
    </div>

    <?php if (empty($messages)): ?>
        <p class="empty-tip"><?= $tab === 'inbox' ? '暂无站内信' : '暂无已发送的站内信' ?></p>
    <?php else: ?>
        <ul class="msg-list">
            <?php foreach ($messages as $m): ?>
                <li class="msg-item <?= ($tab === 'inbox' && !$m['is_read']) ? 'unread' : '' ?>">
                    <div class="msg-head">
                        <span class="msg-role">
                            <?php if ($tab === 'inbox'): ?>
                                <?php if ($m['sender_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                                <strong><?= escapeHtml($m['sender_name']) ?></strong>
                                <?= getVerifyBadge(['verify_label' => $m['sender_label']]) ?>
                            <?php else: ?>
                                发给 <strong><?= escapeHtml($m['recipient_name']) ?></strong>
                                <?= getVerifyBadge(['verify_label' => $m['recipient_label']]) ?>
                            <?php endif; ?>
                        </span>
                        <span class="msg-time"><?= timeAgo($m['created_at']) ?></span>
                    </div>
                    <div class="msg-subject">
                        <?= $m['is_read'] ? '' : '<span class="unread-dot"></span>' ?><?= escapeHtml($m['subject']) ?>
                    </div>
                    <div class="msg-content markdown-body"><?= $m['content_html'] ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
