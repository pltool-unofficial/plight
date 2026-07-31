<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    http_response_code(403);
    die('权限不足');
}

$db = Database::getInstance();

// 统计数据
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPosts = $db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
try {
    $totalAnnouncements = $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    $activeAnnouncements = $db->query('SELECT COUNT(*) FROM announcements WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())')->fetchColumn();
} catch (PDOException $e) {
    $totalAnnouncements = 0;
    $activeAnnouncements = 0;
}
$pendingUsers = $db->query("SELECT COUNT(*) FROM users WHERE verified = 0 AND physics_username IS NOT NULL")->fetchColumn();
$bannedUsers = $db->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn();

// 最近 10 条操作日志（JOIN users 取管理员用户名）
$stmt = $db->prepare(
    'SELECT l.*, u.username AS admin_name
     FROM admin_logs l
     JOIN users u ON l.admin_id = u.id
     ORDER BY l.created_at DESC
     LIMIT 10'
);
$stmt->execute();
$recentLogs = $stmt->fetchAll();

$pageTitle = '管理首页';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php" class="active">首页</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="announcements.php">公告管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>管理首页</h1>
            <div class="admin-stat-grid">
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$totalUsers ?></div>
                    <div class="stat-label">总用户数</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$totalPosts ?></div>
                    <div class="stat-label">总帖子数</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$activeAnnouncements ?></div>
                    <div class="stat-label">生效公告</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$pendingUsers ?></div>
                    <div class="stat-label">待审核用户数</div>
                </div>
            </div>

            <div class="admin-quick-actions">
                <h2>快捷操作</h2>
                <div class="admin-action-grid">
                    <a href="announcements.php?action=create" class="admin-action-card">
                        <span class="admin-action-icon">📢</span>
                        <span class="admin-action-text">发布公告</span>
                    </a>
                    <a href="announcements.php" class="admin-action-card">
                        <span class="admin-action-icon">📋</span>
                        <span class="admin-action-text">管理公告</span>
                    </a>
                    <a href="users.php" class="admin-action-card">
                        <span class="admin-action-icon">👥</span>
                        <span class="admin-action-text">用户管理</span>
                    </a>
                    <a href="posts.php" class="admin-action-card">
                        <span class="admin-action-icon">📝</span>
                        <span class="admin-action-text">帖子管理</span>
                    </a>
                </div>
            </div>

            <h2 id="logs">最近操作日志</h2>
            <div class="admin-log">
                <?php if (!empty($recentLogs)): ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="admin-log-line">
                            <span class="admin-log-time">[<?= escapeHtml($log['created_at']) ?>]</span>
                            <strong><?= escapeHtml($log['admin_name']) ?></strong>
                            执行了 <strong><?= escapeHtml($log['action']) ?></strong>
                            <?php if (!empty($log['target_id'])): ?>（目标ID: <?= (int)$log['target_id'] ?>）<?php endif; ?>
                            <?php if (!empty($log['details'])): ?> — <?= escapeHtml($log['details']) ?><?php endif; ?>
                            <span class="admin-log-time">IP: <?= escapeHtml($log['ip_address'] ?? '-') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="admin-log-line">暂无操作日志</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
