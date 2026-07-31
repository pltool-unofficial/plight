<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '常数速查 - 宝匣';
include __DIR__ . '/../includes/header.php';

$constants = [
    ['真空光速 c', '299 792 458 m/s'],
    ['引力常量 G', '6.674 × 10⁻¹¹ N·m²/kg²'],
    ['普朗克常量 h', '6.626 × 10⁻³⁴ J·s'],
    ['元电荷 e', '1.602 × 10⁻¹⁹ C'],
    ['电子质量 mₑ', '9.109 × 10⁻³¹ kg'],
    ['质子质量 mₚ', '1.673 × 10⁻²⁷ kg'],
    ['中子质量 mₙ', '1.675 × 10⁻²⁷ kg'],
    ['阿伏伽德罗常数 Nₐ', '6.022 × 10²³ mol⁻¹'],
    ['玻尔兹曼常量 k', '1.381 × 10⁻²³ J/K'],
    ['标准大气压', '101 325 Pa'],
    ['绝对零度', '0 K = -273.15 °C'],
];
?>
<main class="container">
        <div class="portal-page-header">
        <h1>⚛️ 常数速查</h1>
        <p>基本物理常数快速检索。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="home-section">
        <table class="data-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;padding:12px;">常数</th>
                    <th style="text-align:left;padding:12px;">数值</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($constants as $c): ?>
                    <tr style="border-bottom:1px solid var(--color-border-light);">
                        <td style="padding:12px;font-weight:500;"><?= escapeHtml($c[0]) ?></td>
                        <td style="padding:12px;font-family:var(--font-mono);"><?= escapeHtml($c[1]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';