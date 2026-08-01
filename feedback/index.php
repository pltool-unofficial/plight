<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$db = Database::getInstance();

$error = '';
$success = '';
$flash = $_SESSION['feedback_flash'] ?? '';
unset($_SESSION['feedback_flash']);

// ===== 处理写操作 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    $action = $_POST['action'] ?? 'create';

    // 发布建议
    if ($action === 'create') {
        if (!$currentUser) {
            renderErrorPage('请先登录', '登录后才能发布建议。', 403);
        }
        if ($currentUser['is_banned'] || $currentUser['is_muted']) {
            $error = '您已被禁言，无法发布建议';
        } else {
            $title = sanitizeInput($_POST['title'] ?? '');
            $content = $_POST['content'] ?? '';

            if (empty($title) || mb_strlen($title) < 3) {
                $error = '标题至少3个字符';
            } elseif (mb_strlen($title) > 200) {
                $error = '标题不能超过200个字符';
            } elseif (empty($content) || mb_strlen($content) < 5) {
                $error = '内容至少5个字符';
            } elseif (mb_strlen($content) > 5000) {
                $error = '内容不能超过5000个字符';
            } else {
                $html = renderMarkdown($content);
                $stmt = $db->prepare('INSERT INTO suggestions (user_id, title, content, content_html) VALUES (?, ?, ?, ?)');
                if ($stmt->execute([$currentUser['id'], $title, $content, $html])) {
                    // 通知管理员有新的建议
                    $adminIds = $db->query('SELECT id FROM users WHERE is_admin = 1')->fetchAll();
                    foreach ($adminIds as $admin) {
                        addNotification((int)$admin['id'], 'system', '收到新的建议：' . $title, '/feedback/index.php');
                    }
                    $_SESSION['feedback_flash'] = '建议已发布，感谢您的反馈！';
                    header('Location: index.php');
                    exit;
                }
                $error = '发布失败，请稍后重试';
            }
        }
    }

    // 完结 / 重新开启（仅管理员）
    if ($action === 'done' || $action === 'reopen') {
        if (!$currentUser || !isAdmin($currentUser)) {
            renderErrorPage('权限不足', '需要管理员权限才能执行此操作。', 403);
        }
        $suggestionId = (int)($_POST['id'] ?? 0);
        if ($suggestionId > 0) {
            if ($action === 'done') {
                $stmt = $db->prepare("UPDATE suggestions SET status = 'done', done_at = NOW() WHERE id = ?");
                $stmt->execute([$suggestionId]);
                logAdminAction($currentUser['id'], 'done_suggestion', $suggestionId);
                // 通知发布者
                $stmt2 = $db->prepare('SELECT user_id, title FROM suggestions WHERE id = ?');
                $stmt2->execute([$suggestionId]);
                $sug = $stmt2->fetch();
                if ($sug) {
                    addNotification((int)$sug['user_id'], 'system', '您的建议已完结：' . $sug['title'], '/feedback/index.php');
                }
            } else {
                $stmt = $db->prepare("UPDATE suggestions SET status = 'open', done_at = NULL WHERE id = ?");
                $stmt->execute([$suggestionId]);
                logAdminAction($currentUser['id'], 'reopen_suggestion', $suggestionId);
            }
        }
        header('Location: index.php');
        exit;
    }

    // 删除（管理员或发布者本人）
    if ($action === 'delete') {
        $suggestionId = (int)($_POST['id'] ?? 0);
        if ($suggestionId > 0) {
            $stmt = $db->prepare('SELECT user_id, title FROM suggestions WHERE id = ?');
            $stmt->execute([$suggestionId]);
            $sug = $stmt->fetch();
            if ($sug) {
                $canDelete = $currentUser && (isAdmin($currentUser) || (int)$sug['user_id'] === (int)$currentUser['id']);
                if (!$canDelete) {
                    renderErrorPage('权限不足', '仅管理员或建议发布者可以删除。', 403);
                }
                $stmt = $db->prepare('DELETE FROM suggestions WHERE id = ?');
                $stmt->execute([$suggestionId]);
                if (isAdmin($currentUser) && (int)$sug['user_id'] !== (int)$currentUser['id']) {
                    logAdminAction($currentUser['id'], 'delete_suggestion', $suggestionId, '标题: ' . $sug['title']);
                }
            }
        }
        header('Location: index.php');
        exit;
    }

    header('Location: index.php');
    exit;
}

// ===== 列表 =====
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalStmt = $db->query('SELECT COUNT(*) FROM suggestions');
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $db->prepare(
    'SELECT s.*, u.username, u.avatar, u.verify_label, u.is_admin
     FROM suggestions s JOIN users u ON s.user_id = u.id
     ORDER BY (s.status = \'open\') DESC, s.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$suggestions = $stmt->fetchAll();

$pageTitle = '反馈建议';
include __DIR__ . '/../includes/header.php';
?>
<main class="container feedback-page">
    <div class="feedback-head">
        <div>
            <h1>反馈建议</h1>
            <p class="feedback-desc">你的每一条建议都会被认真对待，列表公开，管理员完成后将标记为「已完结」。</p>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert success"><?= escapeHtml($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>

    <?php if ($currentUser): ?>
        <?php if ($currentUser['is_banned'] || $currentUser['is_muted']): ?>
            <p class="muted-tip">您已被禁言，无法发布建议。</p>
        <?php else: ?>
            <form method="POST" action="index.php" class="feedback-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="title">建议标题</label>
                    <input type="text" id="title" name="title" required minlength="3" maxlength="200" placeholder="用一句话概括你的建议">
                </div>
                <div class="form-group">
                    <label for="content">建议内容</label>
                    <textarea id="content" name="content" rows="4" required placeholder="详细描述你的建议…"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">提交建议</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="login-tip">请 <a href="/user/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">登录</a> 后提交建议。</p>
    <?php endif; ?>

    <div class="feedback-list">
        <?php if (empty($suggestions)): ?>
            <p class="empty-tip">暂无建议，来提第一条吧。</p>
        <?php else: ?>
            <?php foreach ($suggestions as $s): ?>
                <div class="suggestion-card <?= $s['status'] === 'done' ? 'done' : '' ?>">
                    <div class="suggestion-head">
                        <span class="suggestion-status <?= $s['status'] === 'done' ? 'done' : 'open' ?>">
                            <?= $s['status'] === 'done' ? '已完结' : '进行中' ?>
                        </span>
                        <h3 class="suggestion-title"><?= escapeHtml($s['title']) ?></h3>
                    </div>
                    <div class="suggestion-content markdown-body"><?= $s['content_html'] ?></div>
                    <div class="suggestion-meta">
                        <span>
                            <img src="<?= escapeHtml($s['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="avatar-xs" onerror="this.src='/assets/images/default-avatar.svg'">
                            <a href="/user/profile.php?id=<?= (int)$s['user_id'] ?>"><?= escapeHtml($s['username']) ?></a>
                            <?= getVerifyBadge(['verify_label' => $s['verify_label']]) ?>
                            <?= renderMedalBadges((int)$s['user_id'], 'all') ?>
                            <?php if ($s['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                        </span>
                        <span class="suggestion-time">
                            <?= timeAgo($s['created_at']) ?>
                            <?php if ($s['status'] === 'done' && !empty($s['done_at'])): ?> · 完结于 <?= timeAgo($s['done_at']) ?><?php endif; ?>
                        </span>
                    </div>
                    <?php
                    $canDeleteSug = $currentUser && (isAdmin($currentUser) || (int)$s['user_id'] === (int)$currentUser['id']);
                    if ($canDeleteSug):
                    ?>
                    <div class="suggestion-actions">
                        <?php if (isAdmin($currentUser)): ?>
                            <?php if ($s['status'] === 'open'): ?>
                                <form method="POST" action="index.php" style="display:inline-block">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="done">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">完结</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="index.php" style="display:inline-block">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reopen">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">重新开启</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canDeleteSug): ?>
                            <form method="POST" action="index.php" style="display:inline-block" onsubmit="return confirm('确定删除这条建议？')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">删除</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?= buildPagination($page, $totalPages, '/feedback/index.php') ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
