<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

// 查看单条公告
$singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($singleId > 0) {
    $ann = getAnnouncement($singleId);
    if (!$ann) {
        http_response_code(404);
        $pageTitle = '公告不存在';
        include __DIR__ . '/includes/header.php';
        echo '<main class="container"><div class="alert error">公告不存在或已被删除。</div><p><a href="/announcements.php" class="btn btn-secondary">返回公告列表</a></p></main>';
        include __DIR__ . '/includes/footer.php';
        exit;
    }
    // 如果是禁用或过期公告，仅管理员可见
    if ((!$ann['is_active'] || (!empty($ann['expires_at']) && strtotime($ann['expires_at']) < time())) && !isAdmin($currentUser)) {
        http_response_code(404);
        $pageTitle = '公告不存在';
        include __DIR__ . '/includes/header.php';
        echo '<main class="container"><div class="alert error">公告不存在或已被删除。</div><p><a href="/announcements.php" class="btn btn-secondary">返回公告列表</a></p></main>';
        include __DIR__ . '/includes/footer.php';
        exit;
    }

    $pageTitle = escapeHtml($ann['title']) . ' - 公告';
    $useMarkdown = true;
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="container">
        <div class="ann-detail">
            <div class="ann-detail-head">
                <a href="/announcements.php" class="btn btn-sm btn-secondary">← 返回公告列表</a>
                <div class="ann-detail-meta">
                    <span class="ann-type-badge <?= getAnnouncementTypeClass($ann['type']) ?>">
                        <?= getAnnouncementTypeIcon($ann['type']) ?> <?= getAnnouncementTypeName($ann['type']) ?>
                    </span>
                    <?php if ($ann['is_pinned']): ?><span class="badge success">置顶</span><?php endif; ?>
                </div>
            </div>

            <h1 class="ann-detail-title"><?= escapeHtml($ann['title']) ?></h1>

            <div class="ann-detail-info">
                <img src="<?= escapeHtml($ann['author_avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="ann-author-avatar">
                <span><?= escapeHtml($ann['author_name']) ?> <?= getVBadge($ann['vip_level']) ?></span>
                <span class="ann-sep">·</span>
                <span>发布于 <?= escapeHtml(date('Y-m-d H:i', strtotime($ann['created_at']))) ?></span>
                <?php if (!empty($ann['updater_name'])): ?>
                    <span class="ann-sep">·</span>
                    <span>最后由 <?= escapeHtml($ann['updater_name']) ?> 更新于 <?= escapeHtml(date('Y-m-d H:i', strtotime($ann['updated_at']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($ann['expires_at'])): ?>
                    <span class="ann-sep">·</span>
                    <span class="ann-expiry">过期时间: <?= escapeHtml(date('Y-m-d H:i', strtotime($ann['expires_at']))) ?></span>
                <?php endif; ?>
            </div>

            <div class="ann-detail-content md-preview post-content">
                <?= $ann['content_html'] ?>
            </div>
        </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// 公告列表页
$page = max(1, (int)($_GET['page'] ?? 1));
$typeFilter = $_GET['type'] ?? '';
$allowedTypes = ['info', 'success', 'warning', 'danger', 'maintenance'];
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

$perPage = 15;
$total = getAnnouncementsCount(['active_only' => true, 'type' => $typeFilter]);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$announcements = getAnnouncements([
    'active_only' => true,
    'type' => $typeFilter,
    'limit' => $perPage,
    'offset' => $offset,
]);

$pageTitle = '公告';
$useMarkdown = true;
include __DIR__ . '/includes/header.php';
?>
<main class="container">
    <div class="portal-page-header">
        <h1>📢 公告中心</h1>
        <p>站点官方通知、活动公告与重要提醒。</p>
    </div>

    <div class="section-toolbar">
        <div class="category-filter">
            <a href="/announcements.php" class="<?= $typeFilter === '' ? 'active' : '' ?>">全部</a>
            <?php foreach ($allowedTypes as $t): ?>
                <a href="/announcements.php?type=<?= $t ?>" class="<?= $typeFilter === $t ? 'active' : '' ?>">
                    <?= getAnnouncementTypeIcon($t) ?> <?= getAnnouncementTypeName($t) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="home-section">
            <p class="empty-tip">暂无公告。</p>
        </div>
    <?php else: ?>
        <div class="ann-list">
            <?php foreach ($announcements as $a): ?>
                <a href="/announcements.php?id=<?= (int)$a['id'] ?>" class="ann-card <?= getAnnouncementTypeClass($a['type']) ?>">
                    <div class="ann-card-head">
                        <span class="ann-type-badge <?= getAnnouncementTypeClass($a['type']) ?>">
                            <?= getAnnouncementTypeIcon($a['type']) ?> <?= getAnnouncementTypeName($a['type']) ?>
                        </span>
                        <?php if ($a['is_pinned']): ?><span class="badge success">置顶</span><?php endif; ?>
                    </div>
                    <h3 class="ann-card-title"><?= escapeHtml($a['title']) ?></h3>
                    <div class="ann-card-excerpt">
                        <?= escapeHtml(mb_substr(strip_tags($a['content_html']), 0, 120, 'UTF-8')) ?>
                        <?= mb_strlen(strip_tags($a['content_html']), 'UTF-8') > 120 ? '...' : '' ?>
                    </div>
                    <div class="ann-card-meta">
                        <img src="<?= escapeHtml($a['author_avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="ann-author-avatar-sm">
                        <span><?= escapeHtml($a['author_name']) ?></span>
                        <span class="ann-sep">·</span>
                        <span><?= timeAgo($a['created_at']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        $pagination = buildPagination($page, $totalPages, '/announcements.php');
        if ($typeFilter !== '') {
            $pagination = str_replace(
                '/announcements.php?page=',
                '/announcements.php?type=' . urlencode($typeFilter) . '&page=',
                $pagination
            );
        }
        echo $pagination;
        ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
