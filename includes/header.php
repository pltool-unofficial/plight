<?php
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}
startSession();
$currentUser = getCurrentUser();
$unreadCount = $currentUser ? getUnreadCount($currentUser['id']) : 0;
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escapeHtml($pageTitle) . ' - ' . SITE_NAME : SITE_NAME ?></title>
    <meta name="description" content="灯光 - 物理实验社区">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($useMarkdown) && $useMarkdown): ?>
    <link rel="stylesheet" href="/assets/css/markdown.css">
    <?php endif; ?>
    <?php if (isset($useAdminCss) && $useAdminCss): ?>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="/index.php" class="site-logo">灯<span>光</span></a>
        <nav class="main-nav">
            <a href="/index.php" class="<?= strpos($currentPath, '/index.php') !== false || $currentPath === '/' ? 'active' : '' ?>">首页</a>
            <a href="/lighthouse/index.php" class="<?= strpos($currentPath, '/lighthouse/') !== false ? 'active' : '' ?>">灯塔</a>
            <a href="/wenxuan/index.php" class="<?= strpos($currentPath, '/wenxuan/') !== false ? 'active' : '' ?>">文轩</a>
            <a href="/baoxia/index.php" class="<?= strpos($currentPath, '/baoxia/') !== false ? 'active' : '' ?>">宝匣</a>
            <a href="/shiji/index.php" class="<?= strpos($currentPath, '/shiji/') !== false ? 'active' : '' ?>">史记</a>
            <a href="/qiming/index.php" class="<?= strpos($currentPath, '/qiming/') !== false ? 'active' : '' ?>">齐鸣</a>
        </nav>
        <div class="header-user">
            <?php if ($currentUser): ?>
                <a href="/user/notifications.php" class="notif-link" title="通知">
                    通知
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="/user/profile.php?id=<?= $currentUser['id'] ?>" class="user-link">
                    <img src="<?= $currentUser['avatar'] ?? '/assets/images/default-avatar.svg' ?>" alt="头像" class="header-avatar">
                    <?= escapeHtml($currentUser['username']) ?>
                </a>
                <?php if (isAdmin($currentUser)): ?>
                    <a href="/admin/index.php" class="btn btn-sm btn-secondary">管理</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="/user/login.php" class="btn btn-sm btn-secondary">登录</a>
                <a href="/user/register.php" class="btn btn-sm btn-primary">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>
