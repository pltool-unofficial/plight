<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$db = Database::getInstance();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /index.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    renderErrorPage('用户不存在', '该用户不存在或已注销。', 404);
}

// 勋章
$ownedMedals = getUserMedals($userId);
$wornMedals = getWornMedals($userId);

// 佩戴勋章表单处理
$flash = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    if (!isLoggedIn() || (int)$_SESSION['user_id'] !== $userId) {
        $error = '只能佩戴自己的勋章';
    } else {
        $slot1 = (int)($_POST['slot1'] ?? 0);
        $slot2 = (int)($_POST['slot2'] ?? 0);
        $slot3 = (int)($_POST['slot3'] ?? 0);
        // 清空当前佩戴
        $stmt = $db->prepare('UPDATE user_medals SET slot = NULL WHERE user_id = ?');
        $stmt->execute([$userId]);
        $setSlot = function ($slot, $medalId) use ($db, $userId) {
            if ($medalId <= 0) return;
            $stmt = $db->prepare('UPDATE user_medals SET slot = ? WHERE user_id = ? AND medal_id = ?');
            $stmt->execute([$slot, $userId, $medalId]);
        };
        $setSlot(1, $slot1);
        $setSlot(2, $slot2);
        $setSlot(3, $slot3);
        $flash = '勋章佩戴已更新';
        $ownedMedals = getUserMedals($userId);
        $wornMedals = getWornMedals($userId);
    }
}

// 获取用户帖子总数
$stmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
$stmt->execute([$userId]);
$postCount = (int)$stmt->fetchColumn();

// 获取用户帖子列表
$stmt = $db->prepare('SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$stmt->execute([$userId]);
$posts = $stmt->fetchAll();

$isOwner = isLoggedIn() && $_SESSION['user_id'] == $userId;
$levelInfo = getExpLevelInfo($user['exp']);

$pageTitle = escapeHtml($user['username']) . ' 的主页';
include __DIR__ . '/../includes/header.php';
?>
<main class="container profile-page">
    <?php if ($flash !== ''): ?>
        <div class="alert success"><?= escapeHtml($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>

    <div class="profile-header">
        <div class="profile-avatar">
            <img src="<?= escapeHtml($user['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" onerror="this.src='/assets/images/default-avatar.svg'">
        </div>
        <div class="profile-info">
            <h1>
                <?= escapeHtml($user['username']) ?>
                <?= getVerifyBadge($user['verify_label']) ?>
                <?= renderMedalBadges($userId, 'profile') ?>
                <?php if ($user['is_admin'] == 1): ?>
                    <span class="admin-badge">管理员</span>
                <?php endif; ?>
                <?php if ($user['verified'] == 1): ?>
                    <span class="verified-badge">已认证</span>
                <?php elseif ($user['verified'] == 0 && !empty($user['physics_username'])): ?>
                    <span class="pending-badge">待审核</span>
                <?php endif; ?>
                <?php if ($user['is_banned'] == 1): ?>
                    <span class="banned-badge">已封号</span>
                <?php endif; ?>
            </h1>
            <p class="profile-bio"><?= escapeHtml($user['bio'] ?? '这个人很懒，什么都没写') ?></p>
            <?php if (!empty($user['signature'])): ?>
                <p class="profile-signature">签名：<?= escapeHtml($user['signature']) ?></p>
            <?php endif; ?>
            <div class="profile-meta">
                <span>加入于 <?= date('Y-m-d', strtotime($user['created_at'])) ?></span>
                <span>帖子 <?= $postCount ?></span>
                <?php if (!empty($user['physics_username'])): ?>
                    <span>物实：<?= escapeHtml($user['physics_username']) ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-economy">
                <span class="eco-item bulb" title="金币（灯泡）"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg> <?= (int)($user['coins'] ?? 0) ?></span>
                <span class="eco-item" title="经验值"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <?= (int)($user['exp'] ?? 0) ?></span>
                <span class="eco-item" title="等级">Lv.<?= $levelInfo['level'] ?> <?= escapeHtml($levelInfo['name']) ?></span>
                <div class="exp-bar"><div class="exp-bar-fill" style="width:<?= (int)$levelInfo['progress'] ?>%"></div></div>
            </div>
            <?php if (!empty($user['profile_page'])): ?>
                <p class="profile-page-link">个人介绍页面：<a href="<?= escapeHtml($user['profile_page']) ?>" target="_blank" rel="noopener"><?= escapeHtml($user['profile_page']) ?></a></p>
            <?php endif; ?>
            <?php if ($isOwner): ?>
                <div class="profile-actions">
                    <a href="settings.php" class="btn btn-secondary">编辑资料</a>
                    <a href="verify-identity.php" class="btn btn-primary">申请身份认证</a>
                    <a href="checkin.php" class="btn btn-secondary">每日签到</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isOwner && !empty($ownedMedals)): ?>
        <div class="profile-section medal-wear-box">
            <h2>佩戴勋章</h2>
            <p class="profile-section-desc">选择 3 枚勋章佩戴：①全站认证处显示 ②发帖与主页并列 ③仅主页显示。</p>
            <form method="POST" action="profile.php?id=<?= (int)$userId ?>" class="medal-wear-form">
                <?= csrfField() ?>
                <?php foreach ([1 => '① 全站佩戴', 2 => '② 发帖佩戴', 3 => '③ 主页佩戴'] as $slot => $label): ?>
                    <div class="form-group">
                        <label><?= $label ?></label>
                        <select name="slot<?= $slot ?>">
                            <option value="0">不佩戴</option>
                            <?php foreach ($ownedMedals as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= isset($wornMedals[$slot]) && (int)$wornMedals[$slot]['id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= escapeHtml($m['name']) ?>（<?= medalLevelLabel($m['level']) ?>）
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary">保存佩戴</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="profile-section honor-display">
        <h2>荣誉陈列</h2>
        <?php if (empty($ownedMedals)): ?>
            <p class="empty-tip">暂无荣誉勋章，努力为社区做贡献吧！</p>
        <?php else: ?>
            <div class="honor-grid">
                <?php foreach ($ownedMedals as $m): ?>
                    <div class="honor-item">
                        <img src="<?= escapeHtml($m['icon_url']) ?>" alt="<?= escapeHtml($m['name']) ?>" class="honor-icon medal-<?= escapeHtml($m['level']) ?>" loading="lazy" onerror="this.style.display='none'">
                        <div class="honor-name"><?= escapeHtml($m['name']) ?></div>
                        <div class="honor-level"><?= medalLevelLabel($m['level']) ?>勋章<?= $m['slot'] >= 1 && $m['slot'] <= 3 ? ' · 已佩戴' : '' ?></div>
                        <p class="honor-desc"><?= escapeHtml($m['description'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="profile-posts">
        <h2>发布的帖子</h2>
        <?php if (empty($posts)): ?>
            <p class="empty-tip">暂无帖子</p>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-card">
                        <h3>
                            <?php if ($post['is_pinned']): ?><span class="pin-tag">置顶</span><?php endif; ?>
                            <a href="<?= postUrl($post['id']) ?>"><?= escapeHtml($post['title']) ?></a>
                        </h3>
                        <p class="post-excerpt"><?= escapeHtml(mb_substr(strip_tags($post['content_html']), 0, 150, 'UTF-8')) ?><?= mb_strlen(strip_tags($post['content_html']), 'UTF-8') > 150 ? '...' : '' ?></p>
                        <span class="post-meta">
                            <?= escapeHtml($post['section']) ?> · <?= timeAgo($post['created_at']) ?> · <?= (int)$post['comment_count'] ?>评 · <?= (int)$post['view_count'] ?>阅
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
