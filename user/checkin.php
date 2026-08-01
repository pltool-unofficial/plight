<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$db = Database::getInstance();
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    if ($user['is_banned']) {
        $error = '账号已被封禁，无法签到';
    } else {
        $result = doCheckin($user['id']);
        if ($result['ok']) {
            $flash = '签到成功！获得 ' . $result['coins'] . ' 金币（灯泡）、' . $result['exp'] . ' 经验，连续签到 ' . $result['streak'] . ' 天';
            $user = getCurrentUser();
        } else {
            $error = $result['message'];
        }
    }
}

$checkedToday = hasCheckedInToday($user['id']);
$streak = getCheckinStreak($user['id']);
$levelInfo = getExpLevelInfo($user['exp'] ?? 0);

// 近 7 天签到情况
$week = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $stmt = $db->prepare('SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?');
    $stmt->execute([$user['id'], $d]);
    $week[] = ['date' => $d, 'done' => (bool)$stmt->fetch()];
}

$pageTitle = '每日签到';
include __DIR__ . '/../includes/header.php';
?>
<main class="container checkin-page">
    <div class="checkin-head">
        <h1>每日签到</h1>
        <p class="checkin-desc">每天签到点亮一盏灯火，连续签到有额外奖励。</p>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert success"><?= escapeHtml($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>

    <div class="checkin-card">
        <div class="checkin-status">
            <div class="checkin-streak-num"><?= (int)$streak ?></div>
            <div class="checkin-streak-label">连续签到天数</div>
            <?php if ($checkedToday): ?>
                <span class="checkin-done-badge">今日已签到</span>
            <?php else: ?>
                <form method="POST" action="checkin.php">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-hero">立即签到</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="checkin-week">
            <?php foreach ($week as $w): ?>
                <div class="checkin-day <?= $w['done'] ? 'done' : '' ?>">
                    <div class="checkin-day-dot"><?= $w['done'] ? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' : '·' ?></div>
                    <div class="checkin-day-date"><?= date('m/d', strtotime($w['date'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="checkin-rules">
        <h2>签到奖励规则</h2>
        <ul>
            <li>每日签到：<strong>+2 金币（灯泡）</strong>、<strong>+10 经验</strong></li>
            <li>连续签到第 3 天起，每多一天额外 <strong>+1 金币</strong>（上限 +5）、<strong>+2 经验</strong>（上限 +10）</li>
            <li>每连续签到 7 天，额外 <strong>+5 金币</strong>、<strong>+20 经验</strong></li>
            <li>经验值由系统自动累计，用于提升等级称号</li>
        </ul>
    </div>

    <div class="checkin-wallet">
        <div class="wallet-item">
            <span class="wallet-label">金币（灯泡）</span>
            <span class="wallet-value bulb"><?= (int)($user['coins'] ?? 0) ?></span>
        </div>
        <div class="wallet-item">
            <span class="wallet-label">经验值</span>
            <span class="wallet-value"><?= (int)($user['exp'] ?? 0) ?></span>
        </div>
        <div class="wallet-item">
            <span class="wallet-label">等级称号</span>
            <span class="wallet-value">Lv.<?= $levelInfo['level'] ?> <?= escapeHtml($levelInfo['name']) ?></span>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
