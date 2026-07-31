<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '资源下载 - 宝匣';
include __DIR__ . '/../includes/header.php';

$resources = [
    ['实验报告模板', 'docx', '通用实验报告 Word 模板，可直接填写使用。'],
    ['误差分析指南', 'pdf', '系统误差与随机误差分析入门指南。'],
    ['常用单位换算表', 'pdf', '力学、热学、电磁学常用单位换算速查表。'],
    ['实验安全手册', 'pdf', '实验室基础安全规范与注意事项。'],
];
?>
<main class="container">
        <div class="portal-page-header">
        <h1>📥 资源下载</h1>
        <p>实验资料、模板与参考文档。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/index.php" class="btn btn-secondary">返回宝匣</a>
    </div>

    <div class="home-section">
        <p class="empty-tip">资源文件正在整理中，敬请期待。</p>
        <ul class="post-list" style="margin-top:16px;">
            <?php foreach ($resources as $r): ?>
                <li class="post-list-item" style="justify-content:space-between;">
                    <div>
                        <strong><?= escapeHtml($r[0]) ?></strong>
                        <span class="cat-tag"><?= escapeHtml($r[1]) ?></span>
                        <p style="margin:4px 0 0;color:var(--color-text-muted);font-size:13px;"><?= escapeHtml($r[2]) ?></p>
                    </div>
                    <button class="btn btn-sm btn-secondary" disabled>待上传</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';