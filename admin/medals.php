<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    renderErrorPage('权限不足', '需要管理员权限才能访问此页面。', 403);
}

$db = Database::getInstance();
$flash = $_SESSION['medal_flash'] ?? '';
unset($_SESSION['medal_flash']);
$error = '';

// ===== 写操作 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    $action = $_POST['action'] ?? '';

    // 新建/编辑勋章
    if ($action === 'save') {
        $medalId = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $level = $_POST['level'] ?? 'white';
        if (!in_array($level, ['gold', 'red', 'purple', 'green', 'white'], true)) {
            $level = 'white';
        }
        $iconUrl = sanitizeInput($_POST['icon_url'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        if ($name === '' || mb_strlen($name) > 50) {
            $error = '勋章名称需 1-50 字符';
        } elseif (!isValidAvatarUrl($iconUrl)) {
            $error = '图标链接需为有效的 http/https 链接';
        } elseif (mb_strlen($description) > 255) {
            $error = '简介不能超过 255 字符';
        } else {
            $descValue = $description === '' ? null : $description;
            if ($medalId > 0) {
                $stmt = $db->prepare('UPDATE medals SET name = ?, level = ?, icon_url = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $level, $iconUrl, $descValue, $medalId]);
                logAdminAction($user['id'], 'edit_medal', $medalId, $name);
                $_SESSION['medal_flash'] = '勋章已更新';
            } else {
                $stmt = $db->prepare('INSERT INTO medals (name, level, icon_url, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $level, $iconUrl, $descValue]);
                $newId = (int)$db->lastInsertId();
                logAdminAction($user['id'], 'create_medal', $newId, $name);
                $_SESSION['medal_flash'] = '勋章已创建';
            }
        }
    }

    // 删除勋章
    if ($action === 'delete') {
        $medalId = (int)($_POST['id'] ?? 0);
        if ($medalId > 0) {
            $stmt = $db->prepare('DELETE FROM medals WHERE id = ?');
            $stmt->execute([$medalId]);
            logAdminAction($user['id'], 'delete_medal', $medalId);
            $_SESSION['medal_flash'] = '勋章已删除（持有者记录同步移除）';
        }
    }

    // 颁发勋章给用户
    if ($action === 'award') {
        $medalId = (int)($_POST['medal_id'] ?? 0);
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($medalId > 0 && $targetId > 0) {
            $stmt = $db->prepare('INSERT INTO user_medals (user_id, medal_id) VALUES (?, ?)');
            try {
                $stmt->execute([$targetId, $medalId]);
                $stmt2 = $db->prepare('SELECT name FROM medals WHERE id = ?');
                $stmt2->execute([$medalId]);
                $medal = $stmt2->fetch();
                $stmt3 = $db->prepare('SELECT username FROM users WHERE id = ?');
                $stmt3->execute([$targetId]);
                $target = $stmt3->fetch();
                logAdminAction($user['id'], 'award_medal', $targetId, '勋章: ' . ($medal['name'] ?? $medalId));
                addNotification($targetId, 'system', '恭喜！您获得荣誉勋章「' . ($medal['name'] ?? '') . '」，可在个人主页荣誉陈列中佩戴', '/user/profile.php?id=' . $targetId);
                $_SESSION['medal_flash'] = '已向 ' . ($target['username'] ?? $targetId) . ' 颁发勋章';
            } catch (PDOException $e) {
                $error = '该用户已拥有此勋章，或勋章/用户不存在';
            }
        }
    }

    // 收回勋章
    if ($action === 'revoke') {
        $userMedalId = (int)($_POST['um_id'] ?? 0);
        if ($userMedalId > 0) {
            $stmt = $db->prepare('SELECT user_id, medal_id FROM user_medals WHERE id = ?');
            $stmt->execute([$userMedalId]);
            $um = $stmt->fetch();
            if ($um) {
                $stmt = $db->prepare('DELETE FROM user_medals WHERE id = ?');
                $stmt->execute([$userMedalId]);
                logAdminAction($user['id'], 'revoke_medal', (int)$um['user_id'], '勋章ID: ' . (int)$um['medal_id']);
                $_SESSION['medal_flash'] = '勋章已收回';
            }
        }
    }

    header('Location: medals.php');
    exit;
}

// ===== 数据 =====
$medals = $db->query('SELECT * FROM medals ORDER BY FIELD(level, \'gold\', \'red\', \'purple\', \'green\', \'white\'), created_at ASC')->fetchAll();
$users = $db->query('SELECT id, username FROM users ORDER BY id ASC LIMIT 500')->fetchAll();

// 每枚勋章持有者
$holderMap = [];
$stmt = $db->query(
    'SELECT um.medal_id, um.id AS um_id, u.id AS user_id, u.username
     FROM user_medals um JOIN users u ON um.user_id = u.id
     ORDER BY u.id ASC'
);
foreach ($stmt->fetchAll() as $row) {
    $holderMap[(int)$row['medal_id']][] = $row;
}

$levels = ['gold' => '金色', 'red' => '红色', 'purple' => '紫色', 'green' => '绿色', 'white' => '白色'];

$pageTitle = '勋章管理';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="messages.php">站内信</a></li>
                <li><a href="medals.php" class="active">勋章管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>勋章管理</h1>
            <?php if ($flash !== ''): ?>
                <div class="alert success"><?= escapeHtml($flash) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?= escapeHtml($error) ?></div>
            <?php endif; ?>

            <div class="admin-card">
                <h2>配置勋章</h2>
                <form method="POST" action="medals.php" class="form" style="max-width:560px">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <div class="form-group">
                        <label>勋章名称</label>
                        <input type="text" name="name" required maxlength="50" placeholder="如：社区元老">
                    </div>
                    <div class="form-group">
                        <label>等级</label>
                        <select name="level">
                            <?php foreach ($levels as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>勋章图标链接</label>
                        <input type="url" name="icon_url" required placeholder="https://...（png/svg 等图片链接）">
                    </div>
                    <div class="form-group">
                        <label>简介 (最多255字符)</label>
                        <input type="text" name="description" maxlength="255" placeholder="勋章说明文字">
                    </div>
                    <button type="submit" class="btn btn-primary">创建勋章</button>
                </form>
            </div>

            <div class="admin-card">
                <h2>勋章列表</h2>
                <?php if (empty($medals)): ?>
                    <p class="empty-tip">尚未配置任何勋章</p>
                <?php else: ?>
                    <?php foreach ($medals as $m): ?>
                        <div class="medal-admin-row">
                            <img src="<?= escapeHtml($m['icon_url']) ?>" alt="" class="medal-icon medal-<?= escapeHtml($m['level']) ?>" loading="lazy" onerror="this.style.display='none'">
                            <div class="medal-admin-info">
                                <strong><?= escapeHtml($m['name']) ?></strong>
                                <span class="badge <?= $m['level'] === 'gold' ? 'warning' : 'muted' ?>"><?= $levels[$m['level']] ?? '白色' ?></span>
                                <p><?= escapeHtml($m['description'] ?? '暂无简介') ?></p>
                            </div>
                            <div class="medal-admin-actions">
                                <form method="POST" action="medals.php" style="display:inline-block" onsubmit="return confirm('确定删除该勋章？持有者记录将一并移除')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                </form>
                            </div>
                        </div>
                        <?php if (!empty($holderMap[$m['id']])): ?>
                            <div class="medal-holders">
                                <strong>持有者：</strong>
                                <?php foreach ($holderMap[$m['id']] as $h): ?>
                                    <span class="medal-holder">
                                        <a href="/user/profile.php?id=<?= (int)$h['user_id'] ?>"><?= escapeHtml($h['username']) ?></a>
                                        <form method="POST" action="medals.php" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="um_id" value="<?= (int)$h['um_id'] ?>">
                                            <button type="submit" class="medal-revoke" title="收回勋章">收回</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <h2>颁发勋章</h2>
                <form method="POST" action="medals.php" class="form" style="max-width:560px">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="award">
                    <div class="form-group">
                        <label>勋章</label>
                        <select name="medal_id" required>
                            <option value="">选择勋章…</option>
                            <?php foreach ($medals as $m): ?>
                                <option value="<?= (int)$m['id'] ?>"><?= escapeHtml($m['name']) ?>（<?= $levels[$m['level']] ?? '白色' ?>）</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>颁发给用户</label>
                        <select name="target_id" required>
                            <option value="">选择用户…</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= escapeHtml($u['username']) ?> (ID <?= (int)$u['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">颁发勋章</button>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
