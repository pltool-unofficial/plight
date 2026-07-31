<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '实验数据处理 - 宝匣';
include __DIR__ . '/../includes/header.php';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['data'] ?? '';
    $numbers = array_filter(array_map('floatval', preg_split('/[\s,，]+/', $raw)), function($n) { return !is_nan($n); });
    if (count($numbers) > 0) {
        $count = count($numbers);
        $sum = array_sum($numbers);
        $mean = $sum / $count;
        $variance = 0;
        foreach ($numbers as $n) {
            $variance += pow($n - $mean, 2);
        }
        $std = sqrt($variance / $count);
        $result = [
            'count' => $count,
            'sum' => round($sum, 6),
            'mean' => round($mean, 6),
            'std' => round($std, 6),
            'min' => min($numbers),
            'max' => max($numbers),
        ];
    }
}
?>
<main class="container">
        <div class="portal-page-header">
        <h1>📊 实验数据处理</h1>
        <p>输入一组实验数据，快速计算平均值、标准差等统计量。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="section-grid">
        <div class="card">
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="data">实验数据</label>
                    <textarea id="data" name="data" rows="8" class="form-control" placeholder="用空格、逗号或换行分隔，例如：1.2, 1.3, 1.25, 1.28"><?= escapeHtml($_POST['data'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">计算</button>
            </form>
        </div>
        <?php if ($result): ?>
            <div class="card">
                <h3>统计结果</h3>
                <ul class="post-list" style="margin-top:12px;">
                    <li class="post-list-item"><span>数据个数</span><strong><?= $result['count'] ?></strong></li>
                    <li class="post-list-item"><span>总和</span><strong><?= $result['sum'] ?></strong></li>
                    <li class="post-list-item"><span>平均值</span><strong><?= $result['mean'] ?></strong></li>
                    <li class="post-list-item"><span>标准差</span><strong><?= $result['std'] ?></strong></li>
                    <li class="post-list-item"><span>最小值</span><strong><?= $result['min'] ?></strong></li>
                    <li class="post-list-item"><span>最大值</span><strong><?= $result['max'] ?></strong></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';