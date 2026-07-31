<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '科学计算器 - 宝匣';
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
        <div class="portal-page-header">
        <h1>🧮 科学计算器</h1>
        <p>在线科学计算器，支持常用函数与表达式。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="home-section" style="max-width:600px;margin:0 auto;">
        <input type="text" id="calc-display" class="form-control" style="font-size:20px;font-family:var(--font-mono);text-align:right;margin-bottom:16px;" readonly>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
            <button class="btn btn-secondary" onclick="calcClear()">C</button>
            <button class="btn btn-secondary" onclick="calcBack()">←</button>
            <button class="btn btn-secondary" onclick="calcAppend('(')">(</button>
            <button class="btn btn-secondary" onclick="calcAppend(')')">)</button>
            <button class="btn btn-secondary" onclick="calcAppend('7')">7</button>
            <button class="btn btn-secondary" onclick="calcAppend('8')">8</button>
            <button class="btn btn-secondary" onclick="calcAppend('9')">9</button>
            <button class="btn btn-secondary" onclick="calcAppend('/')">/</button>
            <button class="btn btn-secondary" onclick="calcAppend('4')">4</button>
            <button class="btn btn-secondary" onclick="calcAppend('5')">5</button>
            <button class="btn btn-secondary" onclick="calcAppend('6')">6</button>
            <button class="btn btn-secondary" onclick="calcAppend('*')">×</button>
            <button class="btn btn-secondary" onclick="calcAppend('1')">1</button>
            <button class="btn btn-secondary" onclick="calcAppend('2')">2</button>
            <button class="btn btn-secondary" onclick="calcAppend('3')">3</button>
            <button class="btn btn-secondary" onclick="calcAppend('-')">-</button>
            <button class="btn btn-secondary" onclick="calcAppend('0')">0</button>
            <button class="btn btn-secondary" onclick="calcAppend('.')">.</button>
            <button class="btn btn-primary" onclick="calcEval()">=</button>
            <button class="btn btn-secondary" onclick="calcAppend('+')">+</button>
        </div>
        <p style="margin-top:16px;color:var(--color-text-muted);font-size:13px;">提示：支持 + - * / 和括号，暂不支持函数。</p>
    </div>
</main>
<script>
let expr = '';
function calcUpdate() {
    document.getElementById('calc-display').value = expr;
}
function calcAppend(ch) {
    expr += ch;
    calcUpdate();
}
function calcClear() {
    expr = '';
    calcUpdate();
}
function calcBack() {
    expr = expr.slice(0, -1);
    calcUpdate();
}
function calcEval() {
    try {
        expr = String(Function('"use strict"; return (' + expr + ')')());
    } catch (e) {
        expr = 'Error';
    }
    calcUpdate();
}
</script>
<?php include __DIR__ . '/../includes/footer.php';