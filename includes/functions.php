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
function hasVBadge($user) {
    return $user && in_array($user['vip_level'], ['red', 'yellow', 'blue']);
}

function canPostInLighthouse($user) {
    return $user && ($user['is_admin'] || hasVBadge($user))
        && !$user['is_banned'] && !$user['is_muted'];
}

function canPostInWenxuan($user) {
    return $user && ($user['is_admin'] || hasVBadge($user))
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

// ========== V认证徽章 ==========
function getVBadge($level) {
    $badges = [
        'red' => '<span class="v-badge v-red" title="红V认证">红V</span>',
        'yellow' => '<span class="v-badge v-yellow" title="黄V认证">黄V</span>',
        'blue' => '<span class="v-badge v-blue" title="蓝V认证">蓝V</span>',
        'none' => ''
    ];
    return $badges[$level] ?? '';
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
