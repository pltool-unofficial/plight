<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '宝匣';
include __DIR__ . '/../includes/header.php';

$cards = [
    ['tools.php', 'PL在线工具', '物理实验常用在线工具集合'],
    ['resources.php', '资源下载', '实验资料、模板与参考文档下载'],
    ['manual.php', '实验手册', '实验操作指南与入门手册'],
];
?>
<main class="container">
    <div class="portal-page-header">
        <h1>🧰 宝匣</h1>
        <p>物理实验工具与资源宝匣，提供便捷在线工具与资料。</p>
    </div>
    <div class="section-grid">
        <?php foreach ($cards as $card): ?>
            <a href="<?= escapeHtml($card[0]) ?>" class="card-link">
                <h3><?= escapeHtml($card[1]) ?></h3>
                <p><?= escapeHtml($card[2]) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';
