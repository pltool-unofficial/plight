<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$currentUser = getCurrentUser();
$db = Database::getInstance();

$tab = $_GET['tab'] ?? 'people';
if (!in_array($tab, ['people', 'chronicle', 'museum', 'library'], true)) {
    $tab = 'people';
}

$flash = $_SESSION['shiji_flash'] ?? '';
unset($_SESSION['shiji_flash']);
$error = '';

// ===== 写操作（管理员/授权编辑） =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    if (!isEditor($currentUser)) {
        renderErrorPage('权限不足', '仅管理员或授权编辑可操作史记内容。', 403);
    }
    $action = $_POST['action'] ?? '';

    // 人工添加大事记
    if ($action === 'add_chronicle') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        if (mb_strlen($title) < 2 || mb_strlen($title) > 200) {
            $error = '标题需 2-200 字符';
        } elseif (empty($content) || mb_strlen($content) < 5) {
            $error = '内容至少 5 个字符';
        } else {
            $html = renderMarkdown($content);
            $stmt = $db->prepare('INSERT INTO chronicles (type, title, content, content_html, author_id) VALUES (\'manual\', ?, ?, ?, ?)');
            if ($stmt->execute([$title, $content, $html, $currentUser['id']])) {
                $_SESSION['shiji_flash'] = '大事记已添加';
            } else {
                $error = '添加失败，请重试';
            }
        }
    }

    // 删除大事记
    if ($action === 'del_chronicle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM chronicles WHERE id = ?');
            $stmt->execute([$id]);
            logAdminAction($currentUser['id'], 'del_chronicle', $id);
            $_SESSION['shiji_flash'] = '大事记已删除';
        }
    }

    // 生成上月网站数据总结（系统自动总结，可手动触发）
    if ($action === 'gen_monthly') {
        $month = date('Y-m', strtotime('first day of last month'));
        // 若已存在同月总结则跳过
        $stmt = $db->prepare("SELECT id FROM chronicles WHERE type = 'auto' AND month = ?");
        $stmt->execute([$month]);
        if ($stmt->fetch()) {
            $error = $month . ' 的月总结已存在';
        } else {
            $title = $month . ' 网站月度数据总结';
            $content = buildMonthlySummaryText($month);
            $html = renderMarkdown($content);
            $stmt = $db->prepare("INSERT INTO chronicles (type, month, title, content, content_html, author_id) VALUES ('auto', ?, ?, ?, ?, ?)");
            if ($stmt->execute([$month, $title, $content, $html, $currentUser['id']])) {
                $_SESSION['shiji_flash'] = $month . ' 月度总结已生成';
            } else {
                $error = '生成失败，请重试';
            }
        }
    }

    // 藏书阁：添加帖子
    if ($action === 'add_library') {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId <= 0) {
            $error = '帖子ID无效';
        } else {
            $stmt = $db->prepare('SELECT id, title FROM posts WHERE id = ?');
            $stmt->execute([$postId]);
            if (!$stmt->fetch()) {
                $error = '帖子不存在';
            } else {
                $stmt = $db->prepare('INSERT INTO library_posts (post_id, added_by) VALUES (?, ?)');
                try {
                    $stmt->execute([$postId, $currentUser['id']]);
                    $_SESSION['shiji_flash'] = '已收入藏书阁（帖子ID: ' . $postId . '）';
                } catch (PDOException $e) {
                    $error = '该帖子已在藏书阁中';
                }
            }
        }
    }

    // 藏书阁：移除帖子
    if ($action === 'del_library') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM library_posts WHERE id = ?');
            $stmt->execute([$id]);
            logAdminAction($currentUser['id'], 'del_library', $id);
            $_SESSION['shiji_flash'] = '已从藏书阁移除';
        }
    }

    // 有错误时留在本页展示，否则重定向（防重复提交）
    if (empty($error)) {
        header('Location: index.php?tab=' . urlencode($tab));
        exit;
    }
}

// ===== 数据 =====
$people = [];
if ($tab === 'people') {
    // 人物名片：认证用户及以上（已认证/管理员/授权编辑/有认证标记）
    $stmt = $db->query(
        "SELECT id, username, avatar, bio, signature, verify_label, is_admin, is_editor, profile_page, verified, created_at,
                (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS post_count
         FROM users u
         WHERE verified = 1 OR is_admin = 1 OR is_editor = 1 OR (verify_label IS NOT NULL AND verify_label <> '')
         ORDER BY is_admin DESC, verified DESC, created_at ASC"
    );
    $people = $stmt->fetchAll();
}

$chronicles = [];
if ($tab === 'chronicle') {
    $stmt = $db->query(
        'SELECT c.*, u.username AS author_name
         FROM chronicles c LEFT JOIN users u ON c.author_id = u.id
         ORDER BY c.created_at DESC LIMIT 100'
    );
    $chronicles = $stmt->fetchAll();
}

$medals = [];
$holders = [];
if ($tab === 'museum') {
    $stmt = $db->query('SELECT * FROM medals ORDER BY FIELD(level, \'gold\', \'red\', \'purple\', \'green\', \'white\'), created_at ASC');
    $medals = $stmt->fetchAll();
    // 每枚勋章持有者数量
    $stmt = $db->query('SELECT medal_id, COUNT(*) AS cnt FROM user_medals GROUP BY medal_id');
    foreach ($stmt->fetchAll() as $row) {
        $holders[(int)$row['medal_id']] = (int)$row['cnt'];
    }
}

$library = [];
if ($tab === 'library') {
    $stmt = $db->query(
        'SELECT lp.id AS lib_id, lp.post_id, lp.created_at, p.title, p.section, p.created_at AS post_created,
                u.username
         FROM library_posts lp
         JOIN posts p ON lp.post_id = p.id
         JOIN users u ON p.user_id = u.id
         ORDER BY lp.created_at DESC LIMIT 200'
    );
    $library = $stmt->fetchAll();
}

$pageTitle = '史记';
$useMarkdown = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container shiji-page">
    <div class="shiji-head">
        <h1>史记</h1>
        <p class="shiji-desc">灯光社区的人物、大事、荣誉与典藏。</p>
    </div>

    <nav class="shiji-tabs">
        <a href="index.php?tab=people" class="<?= $tab === 'people' ? 'active' : '' ?>">人物名片</a>
        <a href="index.php?tab=chronicle" class="<?= $tab === 'chronicle' ? 'active' : '' ?>">大事记</a>
        <a href="index.php?tab=museum" class="<?= $tab === 'museum' ? 'active' : '' ?>">博物馆</a>
        <a href="index.php?tab=library" class="<?= $tab === 'library' ? 'active' : '' ?>">藏书阁</a>
    </nav>

    <?php if ($flash !== ''): ?>
        <div class="alert success"><?= escapeHtml($flash) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert error"><?= escapeHtml($error) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'people'): ?>
        <div class="people-grid">
            <?php if (empty($people)): ?>
                <p class="empty-tip">暂无人物名片，认证用户将自动上架。</p>
            <?php else: ?>
                <?php foreach ($people as $p): ?>
                    <div class="person-card">
                        <a href="/user/profile.php?id=<?= (int)$p['id'] ?>" class="person-card-main">
                            <img src="<?= escapeHtml($p['avatar'] ?? '/assets/images/default-avatar.svg') ?>" alt="头像" class="person-avatar" onerror="this.src='/assets/images/default-avatar.svg'">
                            <div class="person-info">
                                <div class="person-name">
                                    <?= escapeHtml($p['username']) ?>
                                    <?= getVerifyBadge(['verify_label' => $p['verify_label']]) ?>
                                    <?= renderMedalBadges((int)$p['id'], 'all') ?>
                                    <?php if ($p['is_admin']): ?><span class="admin-badge">管理员</span><?php endif; ?>
                                    <?php if ($p['is_editor'] && !$p['is_admin']): ?><span class="editor-badge">编辑</span><?php endif; ?>
                                </div>
                                <p class="person-bio"><?= escapeHtml(mb_substr($p['bio'] ?? '这个人很懒，什么都没写', 0, 60, 'UTF-8')) ?></p>
                                <div class="person-meta">
                                    <span>加入于 <?= date('Y-m-d', strtotime($p['created_at'])) ?></span>
                                    <span>帖子 <?= (int)$p['post_count'] ?></span>
                                </div>
                            </div>
                        </a>
                        <?php if (!empty($p['profile_page'])): ?>
                            <a href="<?= escapeHtml($p['profile_page']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-hero-ghost person-page-link">个人介绍页面 →</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'chronicle'): ?>
        <?php if (isEditor($currentUser)): ?>
            <div class="shiji-editor-box">
                <h2>添加大事记</h2>
                <form method="POST" action="index.php?tab=chronicle" class="form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_chronicle">
                    <div class="form-group">
                        <label for="c_title">标题</label>
                        <input type="text" id="c_title" name="title" required minlength="2" maxlength="200" placeholder="事件标题">
                    </div>
                    <div class="form-group">
                        <label for="c_content">内容 (支持 Markdown)</label>
                        <textarea id="c_content" name="content" rows="5" required placeholder="详细记录这一事件…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">发布大事记</button>
                </form>
                <form method="POST" action="index.php?tab=chronicle" style="margin-top:12px">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="gen_monthly">
                    <button type="submit" class="btn btn-secondary">生成上月网站数据总结</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="chronicle-list">
            <?php if (empty($chronicles)): ?>
                <p class="empty-tip">暂无大事记。</p>
            <?php else: ?>
                <?php foreach ($chronicles as $c): ?>
                    <div class="chronicle-item <?= $c['type'] === 'auto' ? 'auto' : '' ?>">
                        <div class="chronicle-head">
                            <h3><?= $c['type'] === 'auto' ? '[系统月总结]' : '[大事记]' ?> <?= escapeHtml($c['title']) ?></h3>
                            <span class="chronicle-time"><?= timeAgo($c['created_at']) ?></span>
                        </div>
                        <div class="chronicle-content markdown-body"><?= $c['content_html'] ?></div>
                        <div class="chronicle-meta">
                            <?php if ($c['type'] === 'auto'): ?><span class="badge success">系统月总结</span><?php else: ?><span class="badge muted">人工记录</span><?php endif; ?>
                            <?php if (!empty($c['author_name'])): ?>记录人：<?= escapeHtml($c['author_name']) ?><?php endif; ?>
                            <?php if (isEditor($currentUser)): ?>
                                <form method="POST" action="index.php?tab=chronicle" style="display:inline-block;margin-left:8px" onsubmit="return confirm('确定删除这条大事记？')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="del_chronicle">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'museum'): ?>
        <div class="museum-intro">
            <p>博物馆收录社区所有配置的荣誉勋章与成就，永久陈列。</p>
        </div>
        <div class="museum-grid">
            <?php if (empty($medals)): ?>
                <p class="empty-tip">博物馆暂时空置，管理员可在后台配置勋章。</p>
            <?php else: ?>
                <?php foreach ($medals as $m): ?>
                    <div class="museum-item">
                        <img src="<?= escapeHtml($m['icon_url']) ?>" alt="<?= escapeHtml($m['name']) ?>" class="museum-icon medal-<?= escapeHtml($m['level']) ?>" loading="lazy" onerror="this.style.display='none'">
                        <div class="museum-name"><?= escapeHtml($m['name']) ?></div>
                        <div class="museum-level"><?= medalLevelLabel($m['level']) ?>勋章</div>
                        <p class="museum-desc"><?= escapeHtml($m['description'] ?? '暂无简介') ?></p>
                        <div class="museum-holders"><?= (int)($holders[$m['id']] ?? 0) ?> 人持有</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'library'): ?>
        <?php if (isEditor($currentUser)): ?>
            <div class="shiji-editor-box">
                <h2>收入藏书阁</h2>
                <form method="POST" action="index.php?tab=library" class="form lib-add-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_library">
                    <div class="form-group">
                        <label for="lib_post_id">帖子 ID</label>
                        <input type="number" id="lib_post_id" name="post_id" required min="1" placeholder="输入帖子ID（在帖子详情页地址栏可查看）">
                    </div>
                    <button type="submit" class="btn btn-primary">收入藏书阁</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="library-list">
            <?php if (empty($library)): ?>
                <p class="empty-tip">藏书阁暂无典藏。</p>
            <?php else: ?>
                <?php foreach ($library as $item): ?>
                    <div class="library-item">
                        <a href="<?= postUrl($item['post_id']) ?>" class="library-title"><?= escapeHtml($item['title']) ?></a>
                        <div class="library-meta">
                            <span>作者：<?= escapeHtml($item['username']) ?></span>
                            <span>板块：<?= escapeHtml($item['section']) ?></span>
                            <span>收录于 <?= timeAgo($item['created_at']) ?></span>
                            <?php if (isEditor($currentUser)): ?>
                                <form method="POST" action="index.php?tab=library" style="display:inline-block;margin-left:8px" onsubmit="return confirm('确定移出藏书阁？')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="del_library">
                                    <input type="hidden" name="id" value="<?= (int)$item['lib_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">移除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
