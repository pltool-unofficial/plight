<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$currentUser = getCurrentUser();
$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    http_response_code(404);
    die('帖子不存在');
}

$db = Database::getInstance();
$stmt = $db->prepare('SELECT * FROM posts WHERE id = ? AND section = "qiming"');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('帖子不存在');
}

if (!$currentUser || ($currentUser['id'] != $post['user_id'] && !isAdmin($currentUser))) {
    http_response_code(403);
    die('无权编辑此帖子');
}

$categories = ['思考', '闲聊', '水贴', '问答', '其他', '自定义'];
$error = '';
$title = $post['title'];
$category = $post['category'] ?? '其他';
$content = $post['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    $title = sanitizeInput($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category = sanitizeInput($_POST['category'] ?? '其他');
    if (!in_array($category, $categories, true)) {
        $category = '其他';
    }

    if (empty($title) || mb_strlen($title) < 3) {
        $error = '标题至少3个字符';
    } elseif (empty($content) || mb_strlen($content) < 10) {
        $error = '内容至少10个字符';
    } else {
        $html = renderMarkdown($content);
        $stmt = $db->prepare('UPDATE posts SET title = ?, category = ?, content = ?, content_html = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt->execute([$title, $category, $content, $html, $postId])) {
            header('Location: post.php?id=' . $postId);
            exit;
        }
        $error = '更新失败，请重试';
    }
}

$pageTitle = '编辑帖子';
$useMarkdown = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <h1>编辑帖子</h1>
    <?php if ($error): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="form">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="title">标题</label>
            <input type="text" id="title" name="title" required minlength="3" maxlength="200" value="<?= escapeHtml($title) ?>">
        </div>
        <div class="form-group">
            <label for="category">分类</label>
            <select id="category" name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= escapeHtml($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= escapeHtml($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>内容 (支持 Markdown)</label>
            <div class="md-editor">
                <textarea id="markdown-editor" name="content" rows="15" required><?= escapeHtml($content) ?></textarea>
                <div id="markdown-preview" class="md-preview"></div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">保存修改</button>
        <a href="/qiming/post.php?id=<?= (int)$postId ?>" class="btn btn-secondary">取消</a>
    </form>
</main>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="/assets/js/markdown-editor.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
