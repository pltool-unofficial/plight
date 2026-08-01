<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();

// ========== GET：拉取指定ID之后的新消息 ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $after = max(0, (int)($_GET['after'] ?? 0));
    $stmt = $db->prepare(
        'SELECT c.id, c.user_id, c.content_html, c.created_at,
                u.username, u.avatar, u.verify_label, u.is_admin
         FROM chat_messages c JOIN users u ON c.user_id = u.id
         WHERE c.id > ?
         ORDER BY c.id ASC LIMIT 100'
    );
    $stmt->execute([$after]);
    $messages = $stmt->fetchAll();
    foreach ($messages as &$m) {
        $m['medals_html'] = renderMedalBadges((int)$m['user_id'], 'all');
    }
    unset($m);
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

// ========== POST：发送消息 / 删除消息 ==========
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF验证失败']);
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$action = $input['action'] ?? 'send';

// 删除消息（仅管理员）
if ($action === 'delete') {
    if (!isAdmin($currentUser)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '仅管理员可删除聊天消息']);
        exit;
    }
    $messageId = (int)($input['id'] ?? 0);
    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的消息']);
        exit;
    }
    $stmt = $db->prepare('DELETE FROM chat_messages WHERE id = ?');
    $stmt->execute([$messageId]);
    logAdminAction($currentUser['id'], 'delete_chat_message', $messageId);
    echo json_encode(['success' => true]);
    exit;
}

// 发送消息
if ($currentUser['is_banned'] || $currentUser['is_muted']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '您已被禁言，无法发言']);
    exit;
}

$content = trim((string)($input['content'] ?? ''));
if ($content === '') {
    echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
    exit;
}
if (mb_strlen($content) > 2000) {
    echo json_encode(['success' => false, 'message' => '消息不能超过2000个字符']);
    exit;
}

$contentHtml = renderMarkdown($content);

$stmt = $db->prepare('INSERT INTO chat_messages (user_id, content, content_html) VALUES (?, ?, ?)');
$stmt->execute([$currentUser['id'], $content, $contentHtml]);
$messageId = (int)$db->lastInsertId();

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $messageId,
        'user_id' => (int)$currentUser['id'],
        'username' => $currentUser['username'],
        'avatar' => $currentUser['avatar'],
        'verify_label' => $currentUser['verify_label'],
        'is_admin' => (int)$currentUser['is_admin'],
        'content_html' => $contentHtml,
        'created_at' => date('Y-m-d H:i:s'),
        'medals_html' => renderMedalBadges((int)$currentUser['id'], 'all'),
    ],
]);
