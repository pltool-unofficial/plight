<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$user = getCurrentUser();
if (!$user || !isAdmin($user)) {
    renderErrorPage('权限不足', '需要管理员权限才能访问此页面。', 403);
}

$db = Database::getInstance();

// 处理 POST 写操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        csrfFail();
    }
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['id'] ?? 0);
    $tab = $_POST['tab'] ?? 'all';
    $redirectTab = in_array($tab, ['all', 'pending', 'verified', 'banned'], true) ? $tab : 'all';

    if ($targetId > 0) {
        // 认证审核
        if ($action === 'verify') {
            $status = (int)($_POST['status'] ?? 0);
            if (!in_array($status, [1, 2], true)) {
                $status = 1;
            }
            // 检查目标用户是否提交过 physics_username
            $check = $db->prepare('SELECT physics_username FROM users WHERE id = ?');
            $check->execute([$targetId]);
            $target = $check->fetch();
            if ($target && !empty($target['physics_username'])) {
                $stmt = $db->prepare('UPDATE users SET verified = ? WHERE id = ?');
                $stmt->execute([$status, $targetId]);
                logAdminAction($user['id'], 'verify_user', $targetId, "状态: $status");
                addNotification($targetId, 'verify', '您的身份认证已' . ($status == 1 ? '通过' : '被拒绝'));
            }
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 禁言
        if ($action === 'mute') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE users SET is_muted = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'mute_user', $targetId, "禁言: $value");
            addNotification($targetId, 'system', $value == 1 ? '您已被管理员禁言' : '您的禁言已被解除');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 封号
        if ($action === 'ban') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE users SET is_banned = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'ban_user', $targetId, "封号: $value");
            addNotification($targetId, 'system', $value == 1 ? '您已被管理员封号' : '您的账号已被解封');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 自由认证标记设置（媒体平台式文字认证，精确到每个人）
        if ($action === 'setlabel') {
            $label = trim($_POST['label'] ?? '');
            if (mb_strlen($label) > 50) {
                $label = mb_substr($label, 0, 50);
            }
            $labelValue = $label === '' ? null : $label;
            $stmt = $db->prepare('UPDATE users SET verify_label = ? WHERE id = ?');
            $stmt->execute([$labelValue, $targetId]);
            logAdminAction($user['id'], 'set_verify_label', $targetId, '标记: ' . ($labelValue === null ? '(清空)' : $label));
            addNotification($targetId, 'verify', $labelValue === null ? '您的认证标记已被取消' : '您的认证标记已更新为：' . $label);
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 取消自由认证标记
        if ($action === 'clearlabel') {
            $stmt = $db->prepare('UPDATE users SET verify_label = NULL WHERE id = ?');
            $stmt->execute([$targetId]);
            logAdminAction($user['id'], 'clear_verify_label', $targetId);
            addNotification($targetId, 'verify', '您的认证标记已被管理员取消');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 调整金币（灯泡）
        if ($action === 'coins') {
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = sanitizeInput($_POST['reason'] ?? '');
            if ($amount !== 0) {
                adminAdjustCoins($user['id'], $targetId, $amount, $reason);
            }
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }

        // 设置史记授权编辑
        if ($action === 'editor') {
            $value = (int)($_POST['value'] ?? 1);
            if (!in_array($value, [0, 1], true)) {
                $value = 1;
            }
            $stmt = $db->prepare('UPDATE users SET is_editor = ? WHERE id = ?');
            $stmt->execute([$value, $targetId]);
            logAdminAction($user['id'], 'set_editor', $targetId, '史记编辑: ' . $value);
            addNotification($targetId, 'system', $value == 1 ? '您已被授权为史记板块编辑' : '您的史记编辑权限已被取消');
            header('Location: users.php?tab=' . urlencode($redirectTab));
            exit;
        }
    }

    header('Location: users.php?tab=' . urlencode($redirectTab));
    exit;
}

$tab = $_GET['tab'] ?? 'all';

// 获取用户列表
$sql = 'SELECT * FROM users';
$params = [];
if ($tab === 'pending') {
    $sql .= ' WHERE verified = 0 AND physics_username IS NOT NULL';
} elseif ($tab === 'verified') {
    $sql .= ' WHERE verified = 1';
} elseif ($tab === 'banned') {
    $sql .= ' WHERE is_banned = 1';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = '用户管理';
$useAdminCss = true;
include __DIR__ . '/../includes/header.php';

// 渲染单个 POST 操作按钮的辅助函数
function adminUserBtn($u, $tab, $action, $label, $class, $extraHidden = []) {
    $html = '<form method="POST" action="users.php" style="display:inline-block;margin:2px">';
    $html .= csrfField();
    $html .= '<input type="hidden" name="id" value="' . (int)$u['id'] . '">';
    $html .= '<input type="hidden" name="tab" value="' . escapeHtml($tab) . '">';
    foreach ($extraHidden as $name => $val) {
        $html .= '<input type="hidden" name="' . escapeHtml($name) . '" value="' . escapeHtml($val) . '">';
    }
    $html .= '<button type="submit" name="action" value="' . escapeHtml($action) . '" class="btn-sm ' . escapeHtml($class) . '">' . escapeHtml($label) . '</button>';
    $html .= '</form>';
    return $html;
}
?>
<main class="container">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>管理后台</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="users.php" class="active">用户管理</a></li>
                <li><a href="posts.php">帖子管理</a></li>
                <li><a href="messages.php">站内信</a></li>
                <li><a href="medals.php">勋章管理</a></li>
                <li><a href="settings.php">系统设置</a></li>
                <li><a href="index.php#logs">操作日志</a></li>
            </ul>
        </aside>
        <div class="admin-content">
            <h1>用户管理</h1>
            <div class="admin-tabs">
                <a href="?tab=all" class="<?= $tab === 'all' ? 'active' : '' ?>">全部</a>
                <a href="?tab=pending" class="<?= $tab === 'pending' ? 'active' : '' ?>">待审核</a>
                <a href="?tab=verified" class="<?= $tab === 'verified' ? 'active' : '' ?>">已认证</a>
                <a href="?tab=banned" class="<?= $tab === 'banned' ? 'active' : '' ?>">已封号</a>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>物实ID</th>
                        <th>认证标记</th>
                        <th>金币</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td>
                            <a href="/user/profile.php?id=<?= (int)$u['id'] ?>">
                                <?= escapeHtml($u['username']) ?>
                            </a>
                            <?php if ($u['is_editor']): ?><span class="badge muted">史记编辑</span><?php endif; ?>
                        </td>
                        <td><?= escapeHtml($u['physics_id'] ?? '-') ?></td>
                        <td>
                            <?php $verifyLabel = $u['verify_label'] ?? ''; ?>
                            <?php if ($verifyLabel !== '' && $verifyLabel !== null): ?>
                                <?= getVerifyBadge($u) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="users.php" style="display:inline-flex;align-items:center;gap:4px">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
                                <span class="coin-cell"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg> <?= (int)$u['coins'] ?></span>
                                <input type="number" name="amount" value="0" step="1" class="admin-coin-input" title="正数增加，负数扣减">
                                <button type="submit" name="action" value="coins" class="btn-sm primary">调</button>
                            </form>
                        </td>
                        <td>
                            <?php if ($u['is_banned']): ?>
                                <span class="badge danger">已封号</span>
                            <?php elseif ($u['is_muted']): ?>
                                <span class="badge warning">已禁言</span>
                            <?php elseif ($u['verified'] == 1): ?>
                                <span class="badge success">已认证</span>
                            <?php elseif ($u['verified'] == 2): ?>
                                <span class="badge danger">已拒绝</span>
                            <?php else: ?>
                                <span class="badge muted">待审核</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['verified'] == 0 && !empty($u['physics_username'])): ?>
                                <?= adminUserBtn($u, $tab, 'verify', '通过', 'success', ['status' => 1]) ?>
                                <?= adminUserBtn($u, $tab, 'verify', '拒绝', 'danger', ['status' => 2]) ?>
                            <?php endif; ?>
                            <?php if (!$u['is_muted']): ?>
                                <?= adminUserBtn($u, $tab, 'mute', '禁言', 'warning', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminUserBtn($u, $tab, 'mute', '解除禁言', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <?php if (!$u['is_banned']): ?>
                                <?= adminUserBtn($u, $tab, 'ban', '封号', 'danger', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminUserBtn($u, $tab, 'ban', '解封', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                            <form method="POST" action="users.php" style="display:inline-flex;align-items:center;gap:4px;margin:2px">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
                                <input type="text" name="label" maxlength="50" placeholder="认证标记文字" value="<?= escapeHtml($u['verify_label'] ?? '') ?>" class="admin-label-input" title="输入后点设置认证，作为该用户独有的认证标记">
                                <button type="submit" name="action" value="setlabel" class="btn-sm primary">设置认证</button>
                            </form>
                            <?= adminUserBtn($u, $tab, 'clearlabel', '取消认证', 'secondary') ?>
                            <?php if (!$u['is_editor']): ?>
                                <?= adminUserBtn($u, $tab, 'editor', '授权史记编辑', 'secondary', ['value' => 1]) ?>
                            <?php else: ?>
                                <?= adminUserBtn($u, $tab, 'editor', '取消编辑授权', 'secondary', ['value' => 0]) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="empty-tip">暂无用户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
