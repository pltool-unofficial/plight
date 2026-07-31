<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '公式查询 - 宝匣';
include __DIR__ . '/../includes/header.php';

$formulas = [
    ['力学', [
        ['匀速直线运动', 'v = s / t'],
        ['匀变速直线运动', 'v = v₀ + a t'],
        ['牛顿第二定律', 'F = m a'],
        ['动能', 'Eₖ = ½ m v²'],
        ['势能', 'Eₚ = m g h'],
    ]],
    ['热学', [
        ['热量', 'Q = c m Δt'],
        ['理想气体状态方程', 'p V = n R T'],
    ]],
    ['电磁学', [
        ['欧姆定律', 'I = U / R'],
        ['电功率', 'P = U I = I² R'],
        ['电阻定律', 'R = ρ L / S'],
    ]],
    ['光学', [
        ['光速', 'c = λ f'],
        ['折射定律', 'n₁ sin θ₁ = n₂ sin θ₂'],
    ]],
];
?>
<main class="container">
    <div class="portal-page-header">
        <h1>🧮 公式查询</h1>
        <p>物理实验常用公式速查手册。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="section-grid">
        <?php foreach ($formulas as $cat): ?>
            <div class="card">
                <h3><?= escapeHtml($cat[0]) ?></h3>
                <ul class="post-list" style="margin-top:12px;">
                    <?php foreach ($cat[1] as $f): ?>
                        <li class="post-list-item" style="flex-direction:column;align-items:flex-start;">
                            <strong><?= escapeHtml($f[0]) ?></strong>
                            <code style="font-size:14px;color:var(--color-primary);"><?= escapeHtml($f[1]) ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';