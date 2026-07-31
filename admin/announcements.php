<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    http_response_code(403);
    die('权限不足');
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$csrfOk = true;

// ============ 处理 POST 操作 ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF验证失败');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $expiresAt = trim($_POST['expires_at'] ?? '');
        if ($expiresAt === '') $expiresAt = null;

        $allowedTypes = ['info', 'success', 'warning', 'danger', 'maintenance'];
        if (!in_array($type, $allowedTypes, true)) $type = 'info';

        if ($title === '' || $content === '') {
            $_SESSION['admin_error'] = '标题和内容不能为空';
            header('Location: announcements.php?action=create');
            exit;
        }

        $contentHtml = renderMarkdown($content);
        $stmt = $db->prepare(
            'INSERT INTO announcements (title, content, content_html, type, is_pinned, is_active, created_by, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $content, $contentHtml, $type, $isPinned, $isActive, $user['id'], $expiresAt]);
        $newId = $db->lastInsertId();
        logAdminAction($user['id'], 'create_announcement', $newId, '标题: ' . $title);
        header('Location: announcements.php');
        exit;
    }

    if ($postAction === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $expiresAt = trim($_POST['expires_at'] ?? '');
        if ($expiresAt === '') $expiresAt = null;

        $allowedTypes = ['info', 'success', 'warning', 'danger', 'maintenance'];
        if (!in_array($type, $allowedTypes, true)) $type = 'info';

        if ($id <= 0 || $title === '' || $content === '') {
            $_SESSION['admin_error'] = '参数无效';
            header('Location: announcements.php');
            exit;
        }

        $contentHtml = renderMarkdown($content);
        $stmt = $db->prepare(
            'UPDATE announcements
             SET title = ?, content = ?, content_html = ?, type = ?, is_pinned = ?, is_active = ?, updated_by = ?, expires_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$title, $content, $contentHtml, $type, $isPinned, $isActive, $user['id'], $expiresAt, $id]);
        logAdminAction($user['id'], 'update_announcement', $id, '标题: ' . $title);
        header('Location: announcements.php');
        exit;
    }
}

// ============ 处理 GET 操作 ============
if ($action === 'toggle_pin' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    try {
        $stmt = $db->prepare('UPDATE announcements SET is_pinned = ? WHERE id = ?');
        $stmt->execute([$value, $id]);
        logAdminAction($user['id'], 'toggle_pin_announcement', $id, "置顶: $value");
    } catch (PDOException $e) {}
    header('Location: announcements.php');
    exit;
}

if ($action === 'toggle_active' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $value = $_GET['value'] ?? 1;
    try {
        $stmt = $db->prepare('UPDATE announcements SET is_active = ? WHERE id = ?');
        $stmt->execute([$value, $id]);
        logAdminAction($user['id'], 'toggle_active_announcement', $id, "启用: $value");
    } catch (PDOException $e) {}
    header('Location: announcements.php');
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $db->prepare('SELECT title FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        $ann = $stmt->fetch();
        if ($ann) {
            $stmt = $db->prepare('DELETE FROM announcements WHERE id = ?');
            $stmt->execute([$id]);
            logAdminAction($user['id'], 'delete_announcement', $id, '标题: ' . $ann['title']);
        }
    } catch (PDOException $e) {}
    header('Location: announcements.php');
    exit;
}

// ============ 渲染页面 ============
$pageTitle = '公告管理';
$useAdminCss = true;
$useMarkdown = true;
include __DIR__ . '/../includes/header.php';

$adminError = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_error']);
?>

<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="announcements.php" class="active">公告管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>

        <div class="admin-content">
            <?php if ($action === 'create' || $action === 'edit'):
                $editAnn = null;
                if ($action === 'edit') {
                    $editId = (int)($_GET['id'] ?? 0);
                    $editAnn = getAnnouncement($editId);
                    if (!$editAnn) {
                        echo '<div class="alert error">公告不存在</div>';
                        echo '<a href="announcements.php" class="btn btn-secondary">返回列表</a>';
                        include __DIR__ . '/../includes/footer.php';
                        exit;
                    }
                }
            ?>
                <div class="admin-page-head">
                    <h1><?= $action === 'create' ? '发布公告' : '编辑公告' ?></h1>
                    <a href="announcements.php" class="btn btn-sm btn-secondary">返回列表</a>
                </div>

                <?php if ($adminError): ?>
                    <div class="alert error"><?= escapeHtml($adminError) ?></div>
                <?php endif; ?>

                <form method="POST" class="admin-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= (int)$editAnn['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="title">公告标题 *</label>
                        <input type="text" id="title" name="title" required maxlength="200"
                               value="<?= $editAnn ? escapeHtml($editAnn['title']) : '' ?>"
                               placeholder="请输入公告标题">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="type">公告类型</label>
                            <select id="type" name="type">
                                <?php
                                $types = ['info' => '📢 通知', 'success' => '🎉 喜讯', 'warning' => '⚠️ 提醒', 'danger' => '🚨 紧急', 'maintenance' => '🔧 维护'];
                                $currentType = $editAnn['type'] ?? 'info';
                                foreach ($types as $k => $v):
                                ?>
                                    <option value="<?= $k ?>" <?= $currentType === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="expires_at">过期时间（可选）</label>
                            <input type="datetime-local" id="expires_at" name="expires_at"
                                   value="<?= $editAnn && $editAnn['expires_at'] ? date('Y-m-d\TH:i', strtotime($editAnn['expires_at'])) : '' ?>">
                            <small>留空表示永不过期</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="content">公告内容（支持 Markdown）*</label>
                        <textarea id="content" name="content" required class="md-textarea" rows="12"
                                  placeholder="请输入公告内容，支持 Markdown 语法"><?= $editAnn ? escapeHtml($editAnn['content']) : '' ?></textarea>
                        <small>
                            <button type="button" class="btn btn-sm btn-link" onclick="togglePreview(event)">预览</button>
                        </small>
                        <div id="previewBox" class="md-preview-box" style="display:none;"></div>
                    </div>

                    <div class="form-check-group">
                        <label class="form-check">
                            <input type="checkbox" name="is_pinned" value="1"
                                   <?= (!$editAnn || $editAnn['is_pinned']) ? 'checked' : '' ?>>
                            <span>置顶显示</span>
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1"
                                   <?= (!$editAnn || $editAnn['is_active']) ? 'checked' : '' ?>>
                            <span>启用（前台可见）</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $action === 'create' ? '发布公告' : '保存修改' ?></button>
                        <a href="announcements.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>

                <script>
                function togglePreview(e) {
                    var box = document.getElementById('previewBox');
                    var ta = document.getElementById('content');
                    var btn = e ? e.target : event.srcElement;
                    if (box.style.display === 'none') {
                        fetch('/api/markdown-preview.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({content: ta.value})
                        }).then(r => r.json()).then(data => {
                            box.innerHTML = data.html || '';
                            box.style.display = 'block';
                            btn.textContent = '编辑';
                            ta.style.display = 'none';
                        }).catch(err => {
                            box.innerHTML = '<p style="color:#ef4444;">预览加载失败</p>';
                            box.style.display = 'block';
                        });
                    } else {
                        box.style.display = 'none';
                        btn.textContent = '预览';
                        ta.style.display = 'block';
                    }
                }
                </script>

            <?php else: ?>
                <div class="admin-page-head">
                    <h1>公告管理</h1>
                    <a href="announcements.php?action=create" class="btn btn-primary">+ 发布公告</a>
                </div>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>类型</th>
                            <th>状态</th>
                            <th>发布者</th>
                            <th>发布时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $announcements = getAnnouncements(['limit' => 100]);
                        if (!empty($announcements)):
                            foreach ($announcements as $a):
                        ?>
                            <tr>
                                <td><?= (int)$a['id'] ?></td>
                                <td>
                                    <a href="/announcements.php?id=<?= (int)$a['id'] ?>" target="_blank">
                                        <?= escapeHtml($a['title']) ?>
                                    </a>
                                    <?php if ($a['is_pinned']): ?><span class="badge success">置顶</span><?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= getAnnouncementTypeClass($a['type']) ?>">
                                        <?= getAnnouncementTypeIcon($a['type']) ?> <?= getAnnouncementTypeName($a['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($a['is_active']): ?>
                                        <span class="status-badge active">启用</span>
                                    <?php else: ?>
                                        <span class="status-badge banned">禁用</span>
                                    <?php endif; ?>
                                    <?php if (!empty($a['expires_at']) && strtotime($a['expires_at']) < time()): ?>
                                        <span class="status-badge rejected">已过期</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= escapeHtml($a['author_name']) ?></td>
                                <td><?= escapeHtml(date('Y-m-d H:i', strtotime($a['created_at']))) ?></td>
                                <td>
                                    <a href="announcements.php?action=edit&id=<?= (int)$a['id'] ?>" class="btn-sm secondary">编辑</a>
                                    <?php if ($a['is_pinned']): ?>
                                        <a href="?action=toggle_pin&id=<?= (int)$a['id'] ?>&value=0" class="btn-sm secondary">取消置顶</a>
                                    <?php else: ?>
                                        <a href="?action=toggle_pin&id=<?= (int)$a['id'] ?>&value=1" class="btn-sm success">置顶</a>
                                    <?php endif; ?>
                                    <?php if ($a['is_active']): ?>
                                        <a href="?action=toggle_active&id=<?= (int)$a['id'] ?>&value=0" class="btn-sm warning">禁用</a>
                                    <?php else: ?>
                                        <a href="?action=toggle_active&id=<?= (int)$a['id'] ?>&value=1" class="btn-sm success">启用</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= (int)$a['id'] ?>" class="btn-sm danger"
                                       onclick="return confirm('确定删除该公告？此操作不可恢复。')">删除</a>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--color-text-secondary);padding:32px;">暂无公告，点击右上角"发布公告"创建第一条公告。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
