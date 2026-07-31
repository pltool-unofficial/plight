<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$content = (string)($input['content'] ?? '');
// L11: 内容长度限制，超长截断
if (mb_strlen($content) > 10000) {
    $content = mb_substr($content, 0, 10000);
}

echo json_encode([
    'html' => renderMarkdown($content)
]);
