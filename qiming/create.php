<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user) {
    header('Location: /user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 支持多板块发帖：qiming / lighthouse / wenxuan
$allowedSections = ['qiming', 'lighthouse', 'wenxuan'];
$section = $_GET['section'] ?? ($_POST['section'] ?? 'qiming');
if (!in_array($section, $allowedSections, true)) {
    $section = 'qiming';
}

// 按板块校验权限
$canPost = ($section === 'qiming') ? canPostInQiming($user)
         : (($section === 'lighthouse') ? canPostInLighthouse($user)
         : canPostInWenxuan($user));
if (!$canPost) {
    renderErrorPage('无权发帖', '当前账号无权在此板块发帖。', 403);
}

// 各板块可用分类
$sectionCategories = [
    'qiming' => ['思考', '闲聊', '水贴', '问答', '其他', '自定义'],
    'lighthouse' => ['基础教程', '进阶教程', '公告', '新闻', '其他'],
    'wenxuan' => ['前沿', '论文', '文心', '海纳', '其他'],
];
$categories = $sectionCategories[$section];

$error = '';
$title = '';
$category = $categories[0];
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }

    // 表单提交的 section 必须与进入时一致
    $postSection = $_POST['section'] ?? 'qiming';
    if (!in_array($postSection, $allowedSections, true)) {
        $postSection = 'qiming';
    }
    $section = $postSection;

    // C3: 重新校验发帖权限，防止绕过
    if (!canPostInSection($user, $section)) {
        http_response_code(403);
        die('当前账号无权在此板块发帖');
    }

    $title = sanitizeInput($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category = sanitizeInput($_POST['category'] ?? $categories[0]);
    if (!in_array($category, $sectionCategories[$section], true)) {
        $category = $sectionCategories[$section][0];
    }

    if (empty($title) || mb_strlen($title) < 3) {
        $error = '标题至少3个字符';
    } elseif (mb_strlen($title) > 200) {
        $error = '标题不能超过200个字符';
    } elseif (empty($content) || mb_strlen($content) < 10) {
        $error = '内容至少10个字符';
    } else {
        $html = renderMarkdown($content);
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO posts (user_id, section, category, title, content, content_html) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$user['id'], $section, $category, $title, $content, $html])) {
            $postId = $db->lastInsertId();
            header('Location: ' . postUrl($postId));
            exit;
        }
        $error = '发布失败，请重试';
    }
}

$pageTitle = '发布帖子';
$useMarkdown = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <h1>发布新帖子</h1>
    <?php if ($error): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="form">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="<?= escapeHtml($section) ?>">
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
        <button type="submit" class="btn btn-primary">发布</button>
        <a href="/qiming/index.php" class="btn btn-secondary">取消</a>
    </form>
</main>
<script src="/assets/js/markdown-editor.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
