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
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '.plight.chenyinweb.cn',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
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

// ========== 设备检测 ==========
function isMobile() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileAgents = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'Windows Phone',
        'BlackBerry', 'Opera Mini', 'IEMobile', 'webOS', 'Kindle',
        'Silk', 'SamsungBrowser', 'UCBrowser', 'MIUIBrowser'
    ];
    foreach ($mobileAgents as $agent) {
        if (stripos($ua, $agent) !== false) {
            return true;
        }
    }
    return false;
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

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ========== Markdown处理 ==========
function renderMarkdown($text) {
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    return $parsedown->text($text);
}

function previewMarkdown($text) {
    // 用于前端预览，仅截取前500字符
    $preview = mb_substr($text, 0, 500);
    return renderMarkdown($preview);
}

// ========== 用户认证 ==========
function generateVerifyToken() {
    return bin2hex(random_bytes(32));
}

function sendVerificationEmail($email, $username, $token) {
    $subject = '【灯光】请验证您的邮箱';
    $link = SITE_URL . 'verify.php?token=' . $token . '&email=' . urlencode($email);
    $message = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body>
<h2>欢迎注册 灯光 ！</h2>
<p>您好，{$username}：</p>
<p>请点击以下链接验证您的邮箱地址：</p>
<p><a href="{$link}">{$link}</a></p>
<p>该链接有效期为24小时。</p>
<p>如果这不是您本人操作，请忽略此邮件。</p>
<hr>
<p>灯光团队</p>
</body>
</html>
HTML;
    // 使用mail()或SMTP发送
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
    return $user && ($user['is_admin'] || hasVBadge($user));
}

function canPostInWenxuan($user) {
    return $user && ($user['is_admin'] || hasVBadge($user));
}

function canPostInQiming($user) {
    return $user && $user['verified'] == 1 && !$user['is_banned'] && !$user['is_muted'];
}

function isAdmin($user) {
    return $user && $user['is_admin'] == 1;
}

// ========== 通知系统 ==========
function addNotification($userId, $type, $content, $link = null) {
    $db = Database::getInstance();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, content, link) VALUES (?, ?, ?, ?)');
    return $stmt->execute([$userId, $type, $content, $link]);
}

function getUnreadCount($userId) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ========== 日志记录 ==========
function logAdminAction($adminId, $action, $targetId = null, $details = null) {
    $db = Database::getInstance();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare('INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    return $stmt->execute([$adminId, $action, $targetId, $details, $ip]);
}

// ========== 分页辅助 ==========
function buildPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination"><ul>';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ========== 时间友好显示 ==========
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
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

// ========== 公告系统 ==========
/**
 * 获取公告列表（支持分页、过滤）
 * @param array $options 查询选项
 *   - active_only: bool 只获取启用的公告
 *   - limit: int 限制数量
 *   - offset: int 偏移量
 *   - type: string 公告类型
 * @return array
 */
function getAnnouncements(array $options = []) {
    try {
        $db = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($options['active_only'])) {
            $where[] = 'a.is_active = 1';
            $where[] = '(a.expires_at IS NULL OR a.expires_at > NOW())';
        }
        if (!empty($options['type'])) {
            $where[] = 'a.type = ?';
            $params[] = $options['type'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($options['limit']) ? (int)$options['limit'] : 20;
        $offset = isset($options['offset']) ? (int)$options['offset'] : 0;

        $sql = "SELECT a.*, u.username AS author_name, u.avatar AS author_avatar, u.vip_level,
                       uu.username AS updater_name
                FROM announcements a
                JOIN users u ON a.created_by = u.id
                LEFT JOIN users uu ON a.updated_by = uu.id
                {$whereClause}
                ORDER BY a.is_pinned DESC, a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * 获取单个公告
 */
function getAnnouncement($id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT a.*, u.username AS author_name, u.avatar AS author_avatar, u.vip_level,
                    uu.username AS updater_name
             FROM announcements a
             JOIN users u ON a.created_by = u.id
             LEFT JOIN users uu ON a.updated_by = uu.id
             WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 获取公告总数
 */
function getAnnouncementsCount(array $options = []) {
    try {
        $db = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($options['active_only'])) {
            $where[] = 'is_active = 1';
            $where[] = '(expires_at IS NULL OR expires_at > NOW())';
        }
        if (!empty($options['type'])) {
            $where[] = 'type = ?';
            $params[] = $options['type'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM announcements {$whereClause}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * 获取公告类型的中文名称
 */
function getAnnouncementTypeName($type) {
    $names = [
        'info' => '通知',
        'success' => '喜讯',
        'warning' => '提醒',
        'danger' => '紧急',
        'maintenance' => '维护',
    ];
    return $names[$type] ?? '通知';
}

/**
 * 获取公告类型的CSS类名
 */
function getAnnouncementTypeClass($type) {
    $classes = [
        'info' => 'ann-info',
        'success' => 'ann-success',
        'warning' => 'ann-warning',
        'danger' => 'ann-danger',
        'maintenance' => 'ann-maintenance',
    ];
    return $classes[$type] ?? 'ann-info';
}

/**
 * 获取公告类型的图标emoji
 */
function getAnnouncementTypeIcon($type) {
    $icons = [
        'info' => '📢',
        'success' => '🎉',
        'warning' => '⚠️',
        'danger' => '🚨',
        'maintenance' => '🔧',
    ];
    return $icons[$type] ?? '📢';
}
