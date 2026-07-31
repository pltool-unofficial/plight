<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '史记';
include __DIR__ . '/../includes/header.php';

$figures = [
    [
        'name' => 'admin',
        'avatar' => '/assets/images/default-avatar.svg',
        'role' => '创始人 / 管理员',
        'bio' => '灯光社区的发起人，负责站点运维与内容审核。',
        'joined' => '2026-07-29',
    ],
];

$db = Database::getInstance();
try {
    $stmt = $db->query('SELECT id, username, avatar, bio, temp_username, vip_level, created_at FROM users WHERE is_admin = 1 OR vip_level != "none" ORDER BY created_at ASC LIMIT 20');
    $dbFigures = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbFigures = [];
}
?>
<main class="container">
    <div class="portal-page-header">
        <h1>🏛️ 史记</h1>
        <p>记录社区人物与贡献者。</p>
    </div>

    <?php if (empty($dbFigures)): ?>
        <div class="home-section">
            <p class="empty-tip">暂无人物记录。</p>
        </div>
    <?php else: ?>
        <div class="section-grid">
            <?php foreach ($dbFigures as $f): ?>
                <div class="card">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <img src="<?= escapeHtml($f['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        <div>
                            <h3 style="margin:0;font-size:16px;">
                                <a href="/user/profile.php?id=<?= (int)$f['id'] ?>"><?= escapeHtml($f['username']) ?></a>
                                <?= getVBadge($f['vip_level']) ?>
                            </h3>
                            <p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">加入于 <?= escapeHtml(date('Y-m-d', strtotime($f['created_at']))) ?></p>
                        </div>
                    </div>
                    <p style="color:var(--color-text-secondary);font-size:14px;"><?= escapeHtml($f['bio'] ?: '这个人很懒，什么都没写') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php';