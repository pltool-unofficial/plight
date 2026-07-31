<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '实验手册 - 宝匣';
$useMarkdown = true;
include __DIR__ . '/../includes/header.php';

$manual = <<<MD
# 实验手册

## 1. 实验前准备
- 认真阅读实验指导书，明确实验目的与原理。
- 检查实验器材是否完好，记录仪器型号与编号。
- 设计数据记录表格，提前规划测量次数。

## 2. 数据记录规范
- 数据应真实、完整，不得随意涂改。
- 注明各物理量的单位与有效数字。
- 多次测量取平均值，并估算不确定度。

## 3. 常用仪器使用要点
- **游标卡尺**：先读主尺，再加游标对齐线对应的读数。
- **螺旋测微器**：注意半毫米刻度是否露出。
- **电表**：选择合适量程，注意正负接线柱。

## 4. 实验报告结构
1. 实验目的
2. 实验原理
3. 实验仪器
4. 实验步骤
5. 数据处理
6. 结果分析与讨论
7. 思考题

## 5. 安全注意事项
- 用电实验必须先检查线路，确认无误后方可通电。
- 光学实验避免激光直射眼睛。
- 实验结束后整理仪器，关闭电源。
MD;
?>
<main class="container">
        <div class="portal-page-header">
        <h1>📖 实验手册</h1>
        <p>实验操作指南与入门手册。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/index.php" class="btn btn-secondary">返回宝匣</a>
    </div>

    <div class="home-section md-preview post-content">
        <?= renderMarkdown($manual) ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php';