<?php
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}
startSession();
$currentUser = getCurrentUser();
$unreadCount = $currentUser ? getUnreadCount($currentUser['id']) : 0;
$unreadMsgCount = $currentUser ? getUnreadMessageCount($currentUser['id']) : 0;
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
            <a href="/chat/index.php" class="<?= navActive('chat') ?>">闲谈</a>
            <a href="/feedback/index.php" class="<?= navActive('feedback') ?>">反馈</a>
        </nav>
        <div class="header-user">
            <?php if ($currentUser): ?>
                <a href="/user/messages.php" class="icon-btn" title="站内信" aria-label="站内信">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?php if ($unreadMsgCount > 0): ?>
                        <span class="notif-badge"><?= $unreadMsgCount > 99 ? '99+' : $unreadMsgCount ?></span>
                    <?php endif; ?>
                </a>
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
                <a href="/user/checkin.php" class="coin-chip" title="金币（灯泡）· 点击签到"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg> <span class="coin-num"><?= (int)($currentUser['coins'] ?? 0) ?></span></a>
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
