<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'PL在线工具 - 宝匣';
include __DIR__ . '/../includes/header.php';

$tools = [
    ['单位换算', '常用物理单位之间的换算工具'],
    ['公式查询', '物理实验常用公式速查'],
    ['常数速查', '基本物理常数快速检索'],
    ['实验数据处理', '实验数据的统计与处理辅助'],
    ['物理图谱', '物理知识图谱导航'],
    ['科学计算器', '在线科学计算器'],
];
?>
<main class="container">
    <div class="home-section-head">
        <div>
            <h1 class="page-title" style="margin:0 0 4px;">PL在线工具</h1>
            <p class="post-meta">物理实验在线工具集合,持续完善中。</p>
        </div>
        <a href="/baoxia/index.php" class="btn btn-sm btn-secondary">返回宝匣</a>
    </div>
    <div class="section-grid">
        <?php foreach ($tools as $tool): ?>
            <a href="#" class="card-link">
                <h3><?= escapeHtml($tool[0]) ?></h3>
                <p><?= escapeHtml($tool[1]) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';
