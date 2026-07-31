<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '物理图谱 - 宝匣';
include __DIR__ . '/../includes/header.php';

$map = [
    '力学' => ['运动学', '牛顿定律', '动量守恒', '机械能', '振动与波'],
    '热学' => ['温度与热量', '气体定律', '热力学第一定律', '物态变化'],
    '电磁学' => ['静电场', '恒定电流', '磁场', '电磁感应', '交变电流'],
    '光学' => ['几何光学', '光的干涉', '光的衍射', '光的偏振'],
    '近代物理' => ['原子结构', '原子核', '量子基础', '相对论简介'],
];
?>
<main class="container">
        <div class="portal-page-header">
        <h1>🗺️ 物理图谱</h1>
        <p>物理知识图谱导航，按主题梳理核心概念。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="section-grid">
        <?php foreach ($map as $subject => $topics): ?>
            <div class="card">
                <h3><?= escapeHtml($subject) ?></h3>
                <ul class="post-list" style="margin-top:12px;">
                    <?php foreach ($topics as $topic): ?>
                        <li class="post-list-item"><?= escapeHtml($topic) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';