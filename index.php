<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

// 获取最新公告（置顶优先，最多3条）
$pinnedAnnouncements = getAnnouncements(['active_only' => true, 'limit' => 3]);

// 获取各板块最新帖子/内容
$postSections = [
    'qiming' => '齐鸣',
    'lighthouse' => '灯塔',
    'wenxuan' => '文轩'
];
$latestPosts = [];
foreach ($postSections as $key => $name) {
    $stmt = $db->prepare(
        'SELECT p.*, u.username, u.avatar, u.vip_level
         FROM posts p JOIN users u ON p.user_id = u.id
         WHERE p.section = ?
         ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 5'
    );
    $stmt->execute([$key]);
    $latestPosts[$key] = $stmt->fetchAll();
}

// 首页展示的板块（含非帖子类板块）
$sections = [
    ['key' => 'qiming', 'name' => '齐鸣', 'type' => 'posts'],
    ['key' => 'lighthouse', 'name' => '灯塔', 'type' => 'posts'],
    ['key' => 'wenxuan', 'name' => '文轩', 'type' => 'posts'],
    ['key' => 'baoxia', 'name' => '宝匣', 'type' => 'custom', 'link' => '/baoxia/index.php', 'items' => [
        ['title' => 'PL在线工具', 'url' => '/baoxia/tools.php', 'meta' => '常用物理单位换算、公式速查等'],
        ['title' => '资源下载', 'url' => '/baoxia/index.php', 'meta' => '工具与资料收录'],
    ]],
    ['key' => 'shiji', 'name' => '史记', 'type' => 'custom', 'link' => '/shiji/index.php', 'items' => [
        ['title' => '人物记录', 'url' => '/shiji/index.php', 'meta' => '社区人物与贡献者档案'],
    ]],
];

// 统计数据
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPosts = $db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$pageTitle = '首页';

include __DIR__ . '/includes/header.php';

// 根据设备类型加载对应模板
if (isMobile()) {
    include __DIR__ . '/includes/home-mobile.php';
} else {
    include __DIR__ . '/includes/home-desktop.php';
}

include __DIR__ . '/includes/footer.php';