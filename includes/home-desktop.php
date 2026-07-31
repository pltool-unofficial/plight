<?php
/**
 * 电脑端首页模板（参考腾讯/网易等大厂门户风格）
 * 特点：全宽 Banner、板块卡片网格、左右分栏内容区、信息密度高
 */

// 板块图标与配色
$sectionMeta = [
    'qiming' => ['icon' => '💬', 'color' => '#4f46e5', 'desc' => '发帖交流、观点碰撞'],
    'lighthouse' => ['icon' => '💡', 'color' => '#f59e0b', 'desc' => '物理实验教程与指引'],
    'wenxuan' => ['icon' => '📚', 'color' => '#10b981', 'desc' => '论文品鉴与学术分享'],
    'baoxia' => ['icon' => '🧰', 'color' => '#8b5cf6', 'desc' => '在线工具与资料下载'],
    'shiji' => ['icon' => '🏛️', 'color' => '#ec4899', 'desc' => '社区人物与贡献者'],
];

// 取最新 6 条帖子作为“热门动态”
$db = Database::getInstance();
$hotPosts = [];
try {
    $stmt = $db->query(
        'SELECT p.*, u.username, u.vip_level
         FROM posts p JOIN users u ON p.user_id = u.id
         ORDER BY p.created_at DESC LIMIT 6'
    );
    $hotPosts = $stmt->fetchAll();
} catch (PDOException $e) {
    $hotPosts = [];
}
?>
<main class="home-desktop-v2">
    <!-- 全宽 Hero Banner -->
    <section class="portal-banner">
        <div class="portal-banner-bg"></div>
        <div class="container portal-banner-inner">
            <div class="portal-banner-content">
                <h1>欢迎来到 <?= SITE_NAME ?></h1>
                <p>灯光 - 学习交流社区，汇聚知识，分享成长</p>
                <div class="portal-stats">
                    <div class="portal-stat">
                        <strong><?= $totalUsers ?></strong>
                        <span>用户</span>
                    </div>
                    <div class="portal-stat">
                        <strong><?= $totalPosts ?></strong>
                        <span>帖子</span>
                    </div>
                    <div class="portal-stat">
                        <strong><?= count($pinnedAnnouncements) ?></strong>
                        <span>公告</span>
                    </div>
                </div>
                <div class="portal-actions">
                    <?php if (!$currentUser): ?>
                        <a href="/user/register.php" class="btn btn-primary btn-lg">立即加入</a>
                        <a href="/user/login.php" class="btn btn-secondary btn-lg">登录</a>
                    <?php elseif (canPostInQiming($currentUser)): ?>
                        <a href="/qiming/create.php" class="btn btn-primary btn-lg">发布帖子</a>
                        <a href="/user/profile.php?id=<?= (int)$currentUser['id'] ?>" class="btn btn-secondary btn-lg">我的主页</a>
                    <?php else: ?>
                        <a href="/user/verify.php" class="btn btn-primary btn-lg">申请认证</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($pinnedAnnouncements)): ?>
            <div class="portal-banner-notice">
                <div class="notice-head">
                    <span>📢 最新公告</span>
                    <a href="/announcements.php">全部 →</a>
                </div>
                <ul class="notice-list">
                    <?php foreach (array_slice($pinnedAnnouncements, 0, 3) as $a): ?>
                        <li>
                            <a href="/announcements.php?id=<?= (int)$a['id'] ?>">
                                <span class="notice-badge <?= getAnnouncementTypeClass($a['type']) ?>"><?= getAnnouncementTypeName($a['type']) ?></span>
                                <span class="notice-title"><?= escapeHtml($a['title']) ?></span>
                                <span class="notice-time"><?= timeAgo($a['created_at']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 板块入口网格 -->
    <section class="portal-sections">
        <div class="container">
            <div class="portal-section-head">
                <h2>探索板块</h2>
                <p>五大主题，覆盖学习、工具与社区</p>
            </div>
            <div class="portal-section-grid">
                <?php foreach ($sections as $sec):
                    $meta = $sectionMeta[$sec['key']] ?? ['icon' => '📁', 'color' => '#64748b', 'desc' => ''];
                    $link = $sec['link'] ?? '/' . $sec['key'] . '/index.php';
                ?>
                    <a href="<?= escapeHtml($link) ?>" class="portal-section-card" style="--section-color:<?= escapeHtml($meta['color']) ?>">
                        <div class="portal-section-icon" style="background:<?= escapeHtml($meta['color']) ?>20;color:<?= escapeHtml($meta['color']) ?>">
                            <?= $meta['icon'] ?>
                        </div>
                        <div class="portal-section-info">
                            <h3><?= escapeHtml($sec['name']) ?></h3>
                            <p><?= escapeHtml($meta['desc']) ?></p>
                        </div>
                        <span class="portal-section-arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 主体内容：左侧最新动态 + 右侧侧边栏 -->
    <section class="portal-content">
        <div class="container portal-content-inner">
            <div class="portal-main">
                <div class="portal-panel">
                    <div class="portal-panel-head">
                        <h2>🔥 热门动态</h2>
                        <a href="/qiming/index.php" class="more-link">进入齐鸣 →</a>
                    </div>
                    <?php if (!empty($hotPosts)): ?>
                        <div class="portal-post-grid">
                            <?php foreach ($hotPosts as $post):
                                $secName = $postSections[$post['section']] ?? '其他';
                                $meta = $sectionMeta[$post['section']] ?? ['color' => '#64748b'];
                            ?>
                                <a href="/<?= escapeHtml($post['section']) ?>/post.php?id=<?= (int)$post['id'] ?>" class="portal-post-card">
                                    <div class="portal-post-tag" style="color:<?= escapeHtml($meta['color']) ?>"><?= escapeHtml($secName) ?></div>
                                    <h3><?= escapeHtml($post['title']) ?></h3>
                                    <p><?= escapeHtml(mb_substr(strip_tags($post['content_html']), 0, 60, 'UTF-8')) ?>…</p>
                                    <div class="portal-post-meta">
                                        <span><?= escapeHtml($post['username']) ?> <?= getVBadge($post['vip_level']) ?></span>
                                        <span>· <?= timeAgo($post['created_at']) ?></span>
                                        <span>· <?= (int)$post['comment_count'] ?>评 · <?= (int)$post['view_count'] ?>阅</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-tip">暂无动态</p>
                    <?php endif; ?>
                </div>

                <?php foreach ($sections as $sec): if ($sec['type'] !== 'posts') continue; ?>
                    <div class="portal-panel">
                        <div class="portal-panel-head">
                            <h2><?= $sectionMeta[$sec['key']]['icon'] ?? '' ?> <?= escapeHtml($sec['name']) ?></h2>
                            <a href="<?= escapeHtml($sec['link'] ?? '/' . $sec['key'] . '/index.php') ?>" class="more-link">更多 →</a>
                        </div>
                        <?php if (!empty($latestPosts[$sec['key']])): ?>
                            <ul class="portal-list">
                                <?php foreach (array_slice($latestPosts[$sec['key']], 0, 4) as $post): ?>
                                    <li>
                                        <a href="/<?= $sec['key'] ?>/post.php?id=<?= $post['id'] ?>">
                                            <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                                            <span class="portal-list-title"><?= escapeHtml($post['title']) ?></span>
                                            <span class="portal-list-meta"><?= escapeHtml($post['username']) ?> · <?= timeAgo($post['created_at']) ?> · <?= (int)$post['comment_count'] ?>评</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="empty-tip">暂无内容</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <aside class="portal-sidebar">
                <div class="portal-panel">
                    <div class="portal-panel-head">
                        <h2>📌 公告</h2>
                    </div>
                    <?php if (!empty($pinnedAnnouncements)): ?>
                        <ul class="portal-list portal-list-compact">
                            <?php foreach ($pinnedAnnouncements as $a): ?>
                                <li>
                                    <a href="/announcements.php?id=<?= (int)$a['id'] ?>">
                                        <span class="portal-list-title"><?= escapeHtml($a['title']) ?></span>
                                        <span class="portal-list-meta"><?= timeAgo($a['created_at']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-tip">暂无公告</p>
                    <?php endif; ?>
                </div>

                <div class="portal-panel">
                    <div class="portal-panel-head">
                        <h2>⚡ 快捷入口</h2>
                    </div>
                    <div class="portal-quick-links">
                        <a href="/qiming/create.php">发布帖子</a>
                        <a href="/baoxia/tools.php">在线工具</a>
                        <a href="/shiji/index.php">人物记录</a>
                        <a href="/announcements.php">公告中心</a>
                    </div>
                </div>

                <div class="portal-panel portal-cta">
                    <h3>加入灯光社区</h3>
                    <p>与志同道合的学习者一起交流、成长。</p>
                    <a href="/user/register.php" class="btn btn-primary">立即注册</a>
                </div>
            </aside>
        </div>
    </section>
</main>