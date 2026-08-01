<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

// 获取最近100条聊天记录
$stmt = $db->prepare(
    'SELECT c.*, u.username, u.avatar, u.verify_label, u.is_admin
     FROM chat_messages c JOIN users u ON c.user_id = u.id
     ORDER BY c.id DESC LIMIT 100'
);
$stmt->execute();
$rows = array_reverse($stmt->fetchAll());

$pageTitle = '闲谈';
include __DIR__ . '/../includes/header.php';
?>
<main class="container chat-page">
    <div class="chat-head">
        <div>
            <h1>闲谈</h1>
            <p class="chat-desc">板块之外的聊天室，随意聊聊物理与生活。</p>
        </div>
        <a href="/index.php" class="btn btn-sm btn-secondary">返回首页</a>
    </div>

    <div class="chat-box">
        <div class="chat-list" id="chat-list">
            <?php if (empty($rows)): ?>
                <p class="empty-tip">还没有消息，来说第一句吧。</p>
            <?php else: ?>
                <?php foreach ($rows as $msg): ?>
                    <div class="chat-message" data-mid="<?= (int)$msg['id'] ?>">
                        <img src="<?= escapeHtml($msg['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="chat-avatar" onerror="this.src='/assets/images/default-avatar.svg'">
                        <div class="chat-body">
                            <div class="chat-meta">
                                <a href="/user/profile.php?id=<?= (int)$msg['user_id'] ?>" class="chat-name"><?= escapeHtml($msg['username']) ?></a>
                                <?= getVerifyBadge(['verify_label' => $msg['verify_label']]) ?>
                                <?= renderMedalBadges((int)$msg['user_id'], 'all') ?>
                                <?php if ($msg['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                                <span class="chat-time"><?= timeAgo($msg['created_at']) ?></span>
                                <?php if ($currentUser && isAdmin($currentUser)): ?>
                                    <form method="POST" action="/api/chat.php" class="chat-del-form" data-del-form>
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                                        <button type="submit" class="chat-del-btn" title="删除该消息">删除</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="chat-content markdown-body"><?= $msg['content_html'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input-area">
            <?php if ($currentUser): ?>
                <?php if ($currentUser['is_banned'] || $currentUser['is_muted']): ?>
                    <p class="muted-tip">您已被禁言，无法发言。</p>
                <?php else: ?>
                    <form id="chat-form" class="chat-form">
                        <?= csrfField() ?>
                        <textarea id="chat-input" name="content" rows="2" maxlength="2000" placeholder="说点什么…（支持 Markdown，回车发送）" required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">发送</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="login-tip">请 <a href="/user/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">登录</a> 后参与聊天。</p>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>window.CHAT_IS_ADMIN = <?= ($currentUser && isAdmin($currentUser)) ? 'true' : 'false' ?>;</script>
<script src="/assets/js/chat.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
