<?php
/**
 * 手机端首页模板
 * 垂直流式布局：Hero → 公告横向滚动 → 板块卡片
 */
?>
<main class="container home home-mobile">
    <section class="hero hero-mobile">
        <h1>欢迎来到 <?= SITE_NAME ?></h1>
        <p class="hero-sub">灯光 - 学习交流社区，汇聚知识，分享成长</p>
        <div class="hero-stats">
            <span class="stat"><strong><?= $totalUsers ?></strong> <span>用户</span></span>
            <span class="stat"><strong><?= $totalPosts ?></strong> <span>帖子</span></span>
        </div>
        <?php if (!$currentUser): ?>
            <a href="/user/register.php" class="btn btn-primary">立即加入</a>
        <?php elseif (canPostInQiming($currentUser)): ?>
            <a href="/qiming/create.php" class="btn btn-primary">发布帖子</a>
        <?php endif; ?>
    </section>

    <?php if (!empty($pinnedAnnouncements)): ?>
    <section class="home-announcements-mobile">
        <div class="home-section-head">
            <h2>📢 最新公告</h2>
            <a href="/announcements.php" class="more-link">全部 →</a>
        </div>
        <div class="ann-scroll">
            <?php foreach ($pinnedAnnouncements as $a): ?>
                <a href="/announcements.php?id=<?= (int)$a['id'] ?>" class="ann-card <?= getAnnouncementTypeClass($a['type']) ?>">
                    <span class="ann-type-badge <?= getAnnouncementTypeClass($a['type']) ?>">
                        <?= getAnnouncementTypeIcon($a['type']) ?> <?= getAnnouncementTypeName($a['type']) ?>
                    </span>
                    <h3 class="ann-card-title"><?= escapeHtml($a['title']) ?></h3>
                    <span class="ann-card-meta"><?= timeAgo($a['created_at']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="home-sections-list-mobile">
        <?php foreach ($sections as $sec): ?>
            <section class="home-section">
                <div class="home-section-head">
                    <h2><?= escapeHtml($sec['name']) ?></h2>
                    <a href="<?= escapeHtml($sec['link'] ?? '/' . $sec['key'] . '/index.php') ?>" class="more-link">更多 →</a>
                </div>
                <?php if ($sec['type'] === 'posts'): ?>
                    <?php if (!empty($latestPosts[$sec['key']])): ?>
                        <ul class="post-list">
                            <?php foreach ($latestPosts[$sec['key']] as $post): ?>
                                <li class="post-list-item">
                                    <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                                    <a href="/<?= $sec['key'] ?>/post.php?id=<?= $post['id'] ?>" class="post-list-title">
                                        <?= escapeHtml($post['title']) ?>
                                    </a>
                                    <span class="post-list-meta">
                                        <?= escapeHtml($post['username']) ?> · <?= timeAgo($post['created_at']) ?> · <?= (int)$post['comment_count'] ?>评
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-tip">暂无内容</p>
                    <?php endif; ?>
                <?php else: ?>
                    <ul class="post-list">
                        <?php foreach ($sec['items'] as $item): ?>
                            <li class="post-list-item">
                                <a href="<?= escapeHtml($item['url']) ?>" class="post-list-title">
                                    <?= escapeHtml($item['title']) ?>
                                </a>
                                <span class="post-list-meta"><?= escapeHtml($item['meta']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>