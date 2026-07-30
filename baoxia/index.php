<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '宝匣';
include __DIR__ . '/../includes/header.php';

$cards = [
    ['tools.php', 'PL在线工具', '物理实验常用在线工具集合'],
    ['#', '资源下载', '建设中,即将开放'],
    ['#', '实验手册', '建设中,即将开放'],
];
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">宝匣</h1>
            <p class="post-meta">物理实验工具与资源宝匣,提供便捷在线工具与资料。</p>
        </div>
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
