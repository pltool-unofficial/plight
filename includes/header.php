<?php
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}
startSession();
$currentUser = getCurrentUser();
$unreadCount = $currentUser ? getUnreadCount($currentUser['id']) : 0;
// 规范化路径用于导航高亮判断
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
// 判断是否为首页（仅根目录 index.php）
$isHome = ($currentScript === '/index.php' || $currentScript === '/' || $currentScript === '');

function navActive($dir) {
    global $currentScript;
    if ($dir === '') {
        return ($currentScript === '/index.php') ? 'active' : '';
    }
    return (strpos($currentScript, '/' . $dir . '/') === 0) ? 'active' : '';
}
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
            <a href="/index.php" class="<?= navActive('') ?>">首页</a>
            <a href="/lighthouse/index.php" class="<?= navActive('lighthouse') ?>">灯塔</a>
            <a href="/wenxuan/index.php" class="<?= navActive('wenxuan') ?>">文轩</a>
            <a href="/baoxia/index.php" class="<?= navActive('baoxia') ?>">宝匣</a>
            <a href="/shiji/index.php" class="<?= navActive('shiji') ?>">史记</a>
            <a href="/qiming/index.php" class="<?= navActive('qiming') ?>">齐鸣</a>
        </nav>
        <div class="header-user">
            <?php if ($currentUser): ?>
                <a href="/user/notifications.php" class="icon-btn" title="通知" aria-label="通知">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="/user/profile.php?id=<?= $currentUser['id'] ?>" class="user-link" title="<?= escapeHtml($currentUser['username']) ?>">
                    <img src="<?= escapeHtml($currentUser['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="header-avatar" onerror="this.src='/assets/images/default-avatar.svg'">
                    <span><?= escapeHtml($currentUser['username']) ?></span>
                </a>
                <?php if (isAdmin($currentUser)): ?>
                    <a href="/admin/index.php" class="icon-btn" title="管理后台" aria-label="管理后台">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </a>
                <?php endif; ?>
                <form method="POST" action="/user/logout.php" style="display:inline">
                    <?= csrfField() ?>
                    <button type="submit" class="icon-btn" title="退出登录" aria-label="退出登录">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </form>
            <?php else: ?>
                <a href="/user/login.php" class="btn btn-sm btn-secondary">登录</a>
                <a href="/user/register.php" class="btn btn-sm btn-primary">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>
