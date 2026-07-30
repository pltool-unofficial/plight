<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

if ($currentUser['is_banned'] || $currentUser['is_muted']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '您已被禁言，无法评论']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = $input['csrf_token'] ?? '';
if (!verifyCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF验证失败']);
    exit;
}

$postId = (int)($input['post_id'] ?? 0);
$parentId = $input['parent_id'] ?? null;
if ($parentId !== null && $parentId !== '') {
    $parentId = (int)$parentId;
    if ($parentId <= 0) {
        $parentId = null;
    }
} else {
    $parentId = null;
}
$content = trim((string)($input['content'] ?? ''));

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的帖子']);
    exit;
}
if ($content === '') {
    echo json_encode(['success' => false, 'message' => '评论内容不能为空']);
    exit;
}

$db = Database::getInstance();

$stmt = $db->prepare('SELECT id, is_locked, user_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();
if (!$post) {
    echo json_encode(['success' => false, 'message' => '帖子不存在']);
    exit;
}
if ($post['is_locked']) {
    echo json_encode(['success' => false, 'message' => '该帖子已锁定，无法评论']);
    exit;
}

$parentUserId = null;
if ($parentId !== null) {
    $stmt = $db->prepare('SELECT id, user_id FROM comments WHERE id = ? AND post_id = ?');
    $stmt->execute([$parentId, $postId]);
    $parent = $stmt->fetch();
    if (!$parent) {
        $parentId = null;
    } else {
        $parentUserId = (int)$parent['user_id'];
    }
}

$contentHtml = renderMarkdown($content);

$stmt = $db->prepare('INSERT INTO comments (post_id, user_id, parent_id, content, content_html) VALUES (?, ?, ?, ?, ?)');
if (!$stmt->execute([$postId, $currentUser['id'], $parentId, $content, $contentHtml])) {
    echo json_encode(['success' => false, 'message' => '评论失败，请重试']);
    exit;
}

$commentId = $db->lastInsertId();

$stmt = $db->prepare('UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?');
$stmt->execute([$postId]);

if ($parentId !== null && $parentUserId !== null && $parentUserId != $currentUser['id']) {
    $link = '/qiming/post.php?id=' . $postId . '#comment-' . $commentId;
    $notifContent = $currentUser['username'] . ' 回复了你的评论';
    addNotification($parentUserId, 'reply', $notifContent, $link);
}

echo json_encode(['success' => true]);
