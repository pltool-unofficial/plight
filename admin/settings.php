<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    http_response_code(403);
    die('权限不足');
}

$db = Database::getInstance();
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPosts = $db->query('SELECT COUNT(*) FROM posts')->fetchColumn();

$pageTitle = '系统设置';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="settings.php" class="active">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>系统设置</h1>

            <div class="admin-stat-grid">
                <div class="admin-stat-card">
                    <div class="stat-num"><?= escapeHtml(SITE_NAME) ?></div>
                    <div class="stat-label">站点名称</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$totalUsers ?></div>
                    <div class="stat-label">注册用户</div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-num"><?= (int)$totalPosts ?></div>
                    <div class="stat-label">帖子总数</div>
                </div>
            </div>

            <h2>站点信息</h2>
            <table class="admin-table">
                <tbody>
                    <tr><th style="width:220px">站点名称</th><td><?= escapeHtml(SITE_NAME) ?></td></tr>
                    <tr><th>站点地址</th><td><?= escapeHtml(SITE_URL) ?></td></tr>
                    <tr><th>联系邮箱</th><td><?= escapeHtml(SITE_EMAIL) ?></td></tr>
                    <tr><th>PHP 版本</th><td><?= escapeHtml(PHP_VERSION) ?></td></tr>
                    <tr><th>服务器时间</th><td><?= escapeHtml(date('Y-m-d H:i:s')) ?></td></tr>
                </tbody>
            </table>

            <h2>参数配置（预留）</h2>
            <form method="post" action="settings.php" onsubmit="return false;">
                <?= csrfField() ?>
                <table class="admin-table">
                    <tbody>
                        <tr>
                            <th style="width:220px">每页帖子数</th>
                            <td><input type="number" name="posts_per_page" value="<?= (int)POSTS_PER_PAGE ?>" disabled></td>
                        </tr>
                        <tr>
                            <th>每页评论数</th>
                            <td><input type="number" name="comments_per_page" value="<?= (int)COMMENTS_PER_PAGE ?>" disabled></td>
                        </tr>
                        <tr>
                            <th>最大上传大小（字节）</th>
                            <td><input type="number" name="max_upload_size" value="<?= (int)MAX_UPLOAD_SIZE ?>" disabled></td>
                        </tr>
                        <tr>
                            <th>允许的图片扩展名</th>
                            <td><input type="text" name="allowed_ext" value="<?= escapeHtml(implode(', ', ALLOWED_EXTENSIONS)) ?>" disabled style="width:300px"></td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top:12px;color:var(--color-text-secondary);font-size:13px">以上参数当前为只读展示，修改请编辑 <code>config/config.php</code>。</p>
            </form>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
