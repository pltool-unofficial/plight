<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/database.php';

// 优先使用 Composer 的 Parsedown，否则使用内置降级实现
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (!class_exists('Parsedown')) {
    require_once __DIR__ . '/Parsedown.php';
}

// ========== 会话管理 ==========
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $params = [
            'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400,
            'path' => '/',
            'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        // 域名为空时不设置，让浏览器使用当前主机（本地/生产通用）
        if (defined('SESSION_DOMAIN') && SESSION_DOMAIN !== '') {
            $params['domain'] = SESSION_DOMAIN;
        }
        session_set_cookie_params($params);
        session_start();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// ========== 安全函数 ==========
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// CSRF 校验失败统一响应
function csrfFail() {
    http_response_code(403);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF验证失败']);
    } else {
        renderErrorPage('CSRF验证失败', '请通过正常流程提交表单。', 403);
    }
    exit;
}

/**
 * 渲染带完整 header/footer 的 Apple 风格错误页,替代裸 die()。
 */
function renderErrorPage($title, $message, $code = 404, $backLink = null) {
    http_response_code($code);
    if (!headers_sent() && (int)$code === 403) {
        // 保持 403
    }
    $pageTitle = $title;
    // 尝试引入 header(若 functions 上下文允许)
    if (!function_exists('escapeHtml')) {
        // 极端情况:functions.php 自身加载失败,输出最小化页面
        echo '<!DOCTYPE html><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
        echo '<style>body{font-family:-apple-system,sans-serif;background:#f5f5f7;color:#1d1d1f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}h1{font-size:48px;font-weight:600;margin-bottom:8px;color:#6e6e73}p{color:#6e6e73;font-size:17px}a{color:#0071e3;text-decoration:none}a:hover{text-decoration:underline}</style>';
        echo '<div><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
        echo '<p style="margin-top:24px"><a href="/">返回首页</a></p></div>';
        exit;
    }
    $safeTitle = escapeHtml($title);
    $safeMsg = escapeHtml($message);
    $back = $backLink !== null ? '<a href="' . escapeHtml($backLink) . '" class="btn btn-primary">返回</a>' : '<a href="/" class="btn btn-primary">返回首页</a>';
    include_once __DIR__ . '/header.php';
    echo '<main class="container"><div class="error-page">';
    echo '<div class="error-code">' . (int)$code . '</div>';
    echo '<h1 class="error-title">' . $safeTitle . '</h1>';
    echo '<p class="error-message">' . $safeMsg . '</p>';
    echo '<div class="error-actions">' . $back . '</div>';
    echo '</div></main>';
    include_once __DIR__ . '/footer.php';
    exit;
}

/**
 * 仅去除首尾空白，不再做 htmlspecialchars 转义。
 * 入库应存原始值，输出时用 escapeHtml 转义（避免双重转义）。
 */
function sanitizeInput($input) {
    return is_string($input) ? trim($input) : '';
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// ========== Markdown处理 ==========
function renderMarkdown($text) {
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    return $parsedown->text($text);
}

function previewMarkdown($text) {
    $preview = mb_substr($text, 0, 500);
    return renderMarkdown($preview);
}

// ========== 用户认证 ==========
function generateVerifyToken() {
    return bin2hex(random_bytes(32));
}

function sendVerificationEmail($email, $username, $token) {
    $subject = '【灯光】请验证您的邮箱';
    // 修正：verify.php 位于 /user/ 目录
    $link = SITE_URL . 'user/verify.php?token=' . $token . '&amp;email=' . urlencode($email);
    $safeUser = escapeHtml($username);
    $message = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body>
<h2>欢迎注册 灯光 ！</h2>
<p>您好，{$safeUser}：</p>
<p>请点击以下链接验证您的邮箱地址：</p>
<p><a href="{$link}">{$link}</a></p>
<p>该链接有效期为24小时。</p>
<p>如果这不是您本人操作，请忽略此邮件。</p>
<hr>
<p>灯光团队</p>
</body>
</html>
HTML;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_EMAIL . "\r\n";
    return mail($email, $subject, $message, $headers);
}

// ========== 权限检查 ==========
// 自由认证标记：管理员按人设置文字标记(参考媒体平台认证)，非空即视为有认证
function hasVerifyBadge($user) {
    return $user && !empty($user['verify_label']);
}

function canPostInLighthouse($user) {
    return $user && ($user['is_admin'] || hasVerifyBadge($user))
        && !$user['is_banned'] && !$user['is_muted'];
}

function canPostInWenxuan($user) {
    return $user && ($user['is_admin'] || hasVerifyBadge($user))
        && !$user['is_banned'] && !$user['is_muted'];
}

function canPostInQiming($user) {
    return $user && $user['verified'] == 1 && !$user['is_banned'] && !$user['is_muted'];
}

function isAdmin($user) {
    return $user && $user['is_admin'] == 1;
}

// 统一的板块发帖权限校验
function canPostInSection($user, $section) {
    if (!$user) return false;
    if ($section === 'qiming') return canPostInQiming($user);
    if ($section === 'lighthouse') return canPostInLighthouse($user);
    if ($section === 'wenxuan') return canPostInWenxuan($user);
    return false;
}

// ========== 通知系统 ==========
function addNotification($userId, $type, $content, $link = null) {
    $db = Database::getInstance();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, content, link) VALUES (?, ?, ?, ?)');
    return $stmt->execute([$userId, $type, $content, $link]);
}

function getUnreadCount($userId) {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

// ========== 站内信系统 ==========
function getUnreadMessageCount($userId) {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

// 发送一封站内信（单收件人），并同步抄送收件人邮箱
// @param array $sender 发件人用户数组
// @param int $recipientId 收件人ID
// @param string $subject 主题
// @param string $content 正文(原始Markdown)
// @return bool
function sendInternalMessage($sender, $recipientId, $subject, $content) {
    $db = Database::getInstance();

    // 校验收件人存在
    $stmt = $db->prepare('SELECT id, email, username FROM users WHERE id = ?');
    $stmt->execute([$recipientId]);
    $recipient = $stmt->fetch();
    if (!$recipient) {
        return false;
    }

    $contentHtml = renderMarkdown($content);
    $stmt = $db->prepare('INSERT INTO messages (sender_id, recipient_id, subject, content, content_html) VALUES (?, ?, ?, ?, ?)');
    $ok = $stmt->execute([$sender['id'], $recipient['id'], $subject, $content, $contentHtml]);

    // 站内信同时抄送收件人邮箱（尽力而为，失败不影响站内信）
    sendMessageEmail($recipient['email'], $recipient['username'], $sender['username'], $subject, $content);

    // 站内通知提醒
    addNotification($recipient['id'], 'message', $sender['username'] . ' 给您发送了一条站内信：' . $subject, '/user/messages.php');
    return $ok;
}

// 站内信抄送邮箱
function sendMessageEmail($toEmail, $toName, $fromName, $subject, $contentText) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $safeToName = escapeHtml($toName);
    $safeFromName = escapeHtml($fromName);
    $safeSubject = escapeHtml($subject);
    $safeContent = escapeHtml(mb_substr($contentText, 0, 3000, 'UTF-8'));
    $mailSubject = '【' . SITE_NAME . '】您收到一条新站内信：' . $subject;
    $siteLink = SITE_URL;
    $message = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body>
<h2>您收到一条来自 {$safeFromName} 的站内信</h2>
<p>主题：{$safeSubject}</p>
<hr>
<p>{$safeContent}</p>
<hr>
<p>请登录 {$safeToName} 的账号，前往站内信中心查看完整内容。</p>
<p><a href="{$siteLink}">前往站点查看</a></p>
</body>
</html>
HTML;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_EMAIL . "\r\n";
    return @mail($toEmail, $mailSubject, $message, $headers);
}

// ========== 日志记录 ==========
function logAdminAction($adminId, $action, $targetId = null, $details = null) {
    $db = Database::getInstance();
    // 优先取真实客户端 IP（仅在可信代理环境下信任 X-Forwarded-For）
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
            $ip = $forwarded;
        }
    }
    $stmt = $db->prepare('INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    return $stmt->execute([$adminId, $action, $targetId, $details, $ip]);
}

// ========== 分页辅助 ==========
function buildPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    // 解析已有 query 参数并保留
    $parsed = parse_url($baseUrl);
    $existingQuery = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $existingQuery);
    }
    $path = $parsed['path'] ?? $baseUrl;

    $html = '<nav class="pagination"><ul>';
    // 上一页
    if ($currentPage > 1) {
        $q = http_build_query(array_merge($existingQuery, ['page' => $currentPage - 1]));
        $html .= '<li><a href="' . escapeHtml($path . '?' . $q) . '">上一页</a></li>';
    }
    // 页码（超过 7 页时省略中间）
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    if ($start > 1) {
        $q = http_build_query(array_merge($existingQuery, ['page' => 1]));
        $html .= '<li><a href="' . escapeHtml($path . '?' . $q) . '">1</a></li>';
        if ($start > 2) $html .= '<li class="ellipsis">…</li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? ' class="active"' : '';
        $q = http_build_query(array_merge($existingQuery, ['page' => $i]));
        $html .= '<li' . $active . '><a href="' . escapeHtml($path . '?' . $q) . '">' . $i . '</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="ellipsis">…</li>';
        $q = http_build_query(array_merge($existingQuery, ['page' => $totalPages]));
        $html .= '<li><a href="' . escapeHtml($path . '?' . $q) . '">' . $totalPages . '</a></li>';
    }
    // 下一页
    if ($currentPage < $totalPages) {
        $q = http_build_query(array_merge($existingQuery, ['page' => $currentPage + 1]));
        $html .= '<li><a href="' . escapeHtml($path . '?' . $q) . '">下一页</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ========== 时间友好显示 ==========
function timeAgo($datetime) {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return '';
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . '秒前';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 604800) return floor($diff / 86400) . '天前';
    return date('Y-m-d H:i', $timestamp);
}

// ========== 自由认证标记徽章 ==========
// 参考媒体平台认证样式：显示管理员为该用户设置的认证文字，精确到每一个人。
// 兼容传入用户数组或纯标记字符串。
function getVerifyBadge($userOrLabel) {
    $label = is_array($userOrLabel) ? ($userOrLabel['verify_label'] ?? '') : $userOrLabel;
    if ($label === null || trim((string)$label) === '') {
        return '';
    }
    return '<span class="v-badge v-custom" title="认证标记">' . escapeHtml($label) . '</span>';
}

// ========== 帖子浏览数 ==========
function incrementViewCount($postId) {
    $db = Database::getInstance();
    $stmt = $db->prepare('UPDATE posts SET view_count = view_count + 1 WHERE id = ?');
    return $stmt->execute([$postId]);
}

// ========== 帖子详情页统一 URL ==========
// 所有板块帖子详情统一通过 /post.php 查看
function postUrl($postId) {
    return '/post.php?id=' . (int)$postId;
}

// ========== 头像 URL 校验（仅允许 http/https，防 data: 等方案） ==========
function isValidAvatarUrl($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower($scheme), ['http', 'https'], true);
}

// ============================================================
// ========== 金币(灯泡) + 经验值系统 ==========
// ============================================================

// 经验值等级阈值（系统自动控制，经验值由系统增减）
function expLevels() {
    return [
        0   => '初燃灯火',
        50  => '微光守望',
        120 => '烛火摇曳',
        220 => '灯芯渐明',
        350 => '灯火通明',
        520 => '执灯人',
        740 => '引路人',
        1000 => '燃灯使者',
        1320 => '灯塔守护者',
        1700 => '万千灯火',
    ];
}

// 根据经验值返回 [等级序号, 等级名称, 当前等级下限, 下一等级上限, 进度百分比]
function getExpLevelInfo($exp) {
    $exp = max(0, (int)$exp);
    $levels = expLevels();
    $keys = array_keys($levels);
    $levelIndex = 0;
    foreach ($keys as $i => $threshold) {
        if ($exp >= $threshold) {
            $levelIndex = $i;
        } else {
            break;
        }
    }
    $currentFloor = $keys[$levelIndex];
    $nextFloor = isset($keys[$levelIndex + 1]) ? $keys[$levelIndex + 1] : null;
    $progress = 100;
    if ($nextFloor !== null && $nextFloor > $currentFloor) {
        $progress = (int)floor(($exp - $currentFloor) / ($nextFloor - $currentFloor) * 100);
        $progress = max(0, min(100, $progress));
    }
    return [
        'level' => $levelIndex + 1,
        'name'  => $levels[$keys[$levelIndex]],
        'floor' => $currentFloor,
        'next'  => $nextFloor,
        'progress' => $progress,
    ];
}

// 给用户增加金币和经验（经验一般由系统调用，金币管理员也可调整）
function addCoinsExp($userId, $coins = 0, $exp = 0) {
    $db = Database::getInstance();
    $stmt = $db->prepare('UPDATE users SET coins = coins + ?, exp = exp + ? WHERE id = ?');
    return $stmt->execute([(int)$coins, (int)$exp, (int)$userId]);
}

// 管理员调整金币（可正可负，附原因；记入操作日志）
function adminAdjustCoins($adminId, $targetId, $amount, $reason = '') {
    $db = Database::getInstance();
    $amount = (int)$amount;
    if ($amount === 0) return false;
    $stmt = $db->prepare('UPDATE users SET coins = GREATEST(0, coins + ?) WHERE id = ?');
    $ok = $stmt->execute([$amount, (int)$targetId]);
    if ($ok) {
        logAdminAction($adminId, 'adjust_coins', $targetId, '金币' . ($amount > 0 ? '+' : '') . $amount . ($reason !== '' ? ' 原因: ' . $reason : ''));
        $stmt2 = $db->prepare('SELECT username FROM users WHERE id = ?');
        $stmt2->execute([$targetId]);
        $target = $stmt2->fetch();
        if ($target) {
            addNotification($targetId, 'system', '管理员调整了您的金币：' . ($amount > 0 ? '+' : '') . $amount . ($reason !== '' ? '（' . $reason . '）' : ''), '/user/profile.php?id=' . $targetId);
        }
    }
    return $ok;
}

// ========== 每日签到 ==========

// 今天是否已签到
function hasCheckedInToday($userId) {
    $db = Database::getInstance();
    try {
        $stmt = $db->prepare('SELECT id FROM checkins WHERE user_id = ? AND checkin_date = CURDATE()');
        $stmt->execute([(int)$userId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

// 当前连续签到天数（含今天；若今天未签但昨天签了，则从昨天起算，便于展示）
function getCheckinStreak($userId) {
    $db = Database::getInstance();
    try {
        $stmt = $db->prepare('SELECT checkin_date FROM checkins WHERE user_id = ? ORDER BY checkin_date DESC');
        $stmt->execute([(int)$userId]);
        $dates = $stmt->fetchAll();
    } catch (PDOException $e) {
        return 0;
    }
    if (empty($dates)) return 0;
    $streak = 0;
    $expected = new DateTime();
    $firstDate = date('Y-m-d', strtotime($dates[0]['checkin_date']));
    if ($firstDate === $expected->format('Y-m-d')) {
        // 今天已签，从今天开始数
    } elseif ($firstDate === $expected->modify('-1 day')->format('Y-m-d')) {
        // 昨天签过、今天未签：从昨天起算（便于展示当前连续天数）
    } else {
        return 0;
    }
    foreach ($dates as $d) {
        $dateStr = date('Y-m-d', strtotime($d['checkin_date']));
        if ($dateStr === $expected->format('Y-m-d')) {
            $streak++;
            $expected->modify('-1 day');
        } else {
            break;
        }
    }
    return $streak;
}

// 执行签到：返回 ['ok'=>bool, 'message'=>string, 'coins'=>int, 'exp'=>int, 'streak'=>int]
function doCheckin($userId) {
    $db = Database::getInstance();
    if (hasCheckedInToday($userId)) {
        return ['ok' => false, 'message' => '今天已经签到过了', 'coins' => 0, 'exp' => 0, 'streak' => getCheckinStreak($userId)];
    }
    $stmt = $db->prepare('INSERT INTO checkins (user_id, checkin_date) VALUES (?, CURDATE())');
    $stmt->execute([(int)$userId]);
    $streak = getCheckinStreak($userId);
    // 基础奖励
    $coins = 2;
    $exp = 10;
    // 连续签到奖励：第3天起每多1天 +1 金币，上限 +5；经验每多1天 +2，上限 +10
    if ($streak >= 3) {
        $coins += min(5, $streak - 2);
        $exp += min(10, ($streak - 2) * 2);
    }
    // 每 7 天额外奖励
    if ($streak % 7 === 0) {
        $coins += 5;
        $exp += 20;
    }
    addCoinsExp($userId, $coins, $exp);
    return ['ok' => true, 'message' => '签到成功', 'coins' => $coins, 'exp' => $exp, 'streak' => $streak];
}

// ============================================================
// ========== 荣誉勋章系统 ==========
// ============================================================

function medalLevelLabel($level) {
    $map = ['gold' => '金色', 'red' => '红色', 'purple' => '紫色', 'green' => '绿色', 'white' => '白色'];
    return $map[$level] ?? '白色';
}

// 获取用户拥有的勋章（含佩戴位置）
// 返回: [ ['id'=>, 'name'=>, 'level'=>, 'icon_url'=>, 'description'=>, 'slot'=>int|null], ... ]
function getUserMedals($userId) {
    static $cache = [];
    $userId = (int)$userId;
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    $db = Database::getInstance();
    try {
        $stmt = $db->prepare(
            'SELECT m.id, m.name, m.level, m.icon_url, m.description, um.slot
             FROM user_medals um JOIN medals m ON um.medal_id = m.id
             WHERE um.user_id = ?
             ORDER BY FIELD(m.level, \'gold\', \'red\', \'purple\', \'green\', \'white\'), um.created_at DESC'
        );
        $stmt->execute([$userId]);
        $cache[$userId] = $stmt->fetchAll();
    } catch (PDOException $e) {
        // 数据库尚未升级（缺 user_medals/medals 表）时安全降级为空
        $cache[$userId] = [];
    }
    return $cache[$userId];
}

// 获取用户按槽位佩戴的勋章: [1=>medal, 2=>medal, 3=>medal]
function getWornMedals($userId) {
    $worn = [];
    foreach (getUserMedals($userId) as $m) {
        if ($m['slot'] >= 1 && $m['slot'] <= 3) {
            $worn[(int)$m['slot']] = $m;
        }
    }
    ksort($worn);
    return $worn;
}

// 渲染单个勋章图标
// $context: 'all'=仅槽位1(全站认证处) / 'post'=槽位1+2(发帖处+个人信息) / 'profile'=槽位1+2+3(个人信息)
function renderMedalBadges($userId, $context = 'all') {
    if (!$userId) return '';
    $allowed = $context === 'post' ? [1, 2] : ($context === 'profile' ? [1, 2, 3] : [1]);
    $worn = getWornMedals($userId);
    $html = '';
    foreach ($allowed as $slot) {
        if (isset($worn[$slot])) {
            $m = $worn[$slot];
            $title = escapeHtml($m['name']) . (!empty($m['description']) ? '：' . escapeHtml($m['description']) : '');
            $html .= '<img src="' . escapeHtml($m['icon_url']) . '" alt="' . escapeHtml($m['name']) . '" class="medal-icon medal-' . escapeHtml($m['level']) . '" title="' . $title . '" loading="lazy" onerror="this.style.display=\'none\'">';
        }
    }
    return $html;
}

// 史记板块权限：管理员或授权编辑
function isEditor($user) {
    return $user && ($user['is_admin'] == 1 || $user['is_editor'] == 1);
}

// 人物名片：认证用户及以上（已认证/管理员/授权编辑）
function isHonorUser($user) {
    return $user && ($user['verified'] == 1 || $user['is_admin'] == 1 || $user['is_editor'] == 1 || !empty($user['verify_label']));
}

// 生成某月网站数据总结文本（大事记-系统自动总结）
function buildMonthlySummaryText($month) {
    $db = Database::getInstance();
    $start = $month . '-01 00:00:00';
    $end = date('Y-m-t 23:59:59', strtotime($start));

    $count = function ($table, $col = 'created_at') use ($db, $start, $end) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} >= ? AND {$col} <= ?");
        $stmt->execute([$start, $end]);
        return (int)$stmt->fetchColumn();
    };

    $newUsers = $count('users');
    $newPosts = $count('posts');
    $newComments = $count('comments');
    $newChats = $count('chat_messages');
    $newSuggestions = $count('suggestions');
    $newCheckins = $count('checkins', 'created_at');

    // 本月活跃用户（发帖/评论/聊天去重）
    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT uid) AS c FROM (
            SELECT user_id AS uid FROM posts WHERE created_at >= ? AND created_at <= ?
            UNION SELECT user_id FROM comments WHERE created_at >= ? AND created_at <= ?
            UNION SELECT user_id FROM chat_messages WHERE created_at >= ? AND created_at <= ?
        ) t'
    );
    $stmt->execute([$start, $end, $start, $end, $start, $end]);
    $activeUsers = (int)$stmt->fetchColumn();

    $lines = [];
    $lines[] = '### ' . $month . ' 月度数据总览';
    $lines[] = '';
    $lines[] = '- **新增用户**：' . $newUsers . ' 人';
    $lines[] = '- **新增帖子**：' . $newPosts . ' 篇';
    $lines[] = '- **新增评论**：' . $newComments . ' 条';
    $lines[] = '- **闲谈消息**：' . $newChats . ' 条';
    $lines[] = '- **反馈建议**：' . $newSuggestions . ' 条';
    $lines[] = '- **签到次数**：' . $newCheckins . ' 次';
    $lines[] = '- **活跃用户**：' . $activeUsers . ' 人';
    $lines[] = '';
    $lines[] = '由系统自动生成，供社区查阅。';
    return implode("\n", $lines);
}


