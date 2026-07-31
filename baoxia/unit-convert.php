<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = '单位换算 - 宝匣';
include __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <div class="portal-page-header">
        <h1>📏 单位换算</h1>
        <p>常用物理单位之间的快速换算。</p>
    </div>
    <div class="section-toolbar" style="justify-content:flex-end;margin-bottom:20px;">
        <a href="/baoxia/tools.php" class="btn btn-secondary">返回工具</a>
    </div>

    <div class="section-grid">
        <div class="card">
            <h3>长度</h3>
            <div class="form-group">
                <label>米 (m)</label>
                <input type="number" id="len-m" class="form-control" step="any" oninput="convertLen('m')">
            </div>
            <div class="form-group">
                <label>厘米 (cm)</label>
                <input type="number" id="len-cm" class="form-control" step="any" oninput="convertLen('cm')">
            </div>
            <div class="form-group">
                <label>毫米 (mm)</label>
                <input type="number" id="len-mm" class="form-control" step="any" oninput="convertLen('mm')">
            </div>
        </div>
        <div class="card">
            <h3>质量</h3>
            <div class="form-group">
                <label>千克 (kg)</label>
                <input type="number" id="mass-kg" class="form-control" step="any" oninput="convertMass('kg')">
            </div>
            <div class="form-group">
                <label>克 (g)</label>
                <input type="number" id="mass-g" class="form-control" step="any" oninput="convertMass('g')">
            </div>
            <div class="form-group">
                <label>毫克 (mg)</label>
                <input type="number" id="mass-mg" class="form-control" step="any" oninput="convertMass('mg')">
            </div>
        </div>
        <div class="card">
            <h3>温度</h3>
            <div class="form-group">
                <label>摄氏度 (°C)</label>
                <input type="number" id="temp-c" class="form-control" step="any" oninput="convertTemp('c')">
            </div>
            <div class="form-group">
                <label>华氏度 (°F)</label>
                <input type="number" id="temp-f" class="form-control" step="any" oninput="convertTemp('f')">
            </div>
            <div class="form-group">
                <label>开尔文 (K)</label>
                <input type="number" id="temp-k" class="form-control" step="any" oninput="convertTemp('k')">
            </div>
        </div>
    </div>
</main>
<script>
function convertLen(src) {
    const m = parseFloat(document.getElementById('len-m').value);
    const cm = parseFloat(document.getElementById('len-cm').value);
    const mm = parseFloat(document.getElementById('len-mm').value);
    let base = 0;
    if (src === 'm') base = m;
    else if (src === 'cm') base = cm / 100;
    else if (src === 'mm') base = mm / 1000;
    if (isNaN(base)) return;
    if (src !== 'm') document.getElementById('len-m').value = base;
    if (src !== 'cm') document.getElementById('len-cm').value = base * 100;
    if (src !== 'mm') document.getElementById('len-mm').value = base * 1000;
}
function convertMass(src) {
    const kg = parseFloat(document.getElementById('mass-kg').value);
    const g = parseFloat(document.getElementById('mass-g').value);
    const mg = parseFloat(document.getElementById('mass-mg').value);
    let base = 0;
    if (src === 'kg') base = kg;
    else if (src === 'g') base = g / 1000;
    else if (src === 'mg') base = mg / 1000000;
    if (isNaN(base)) return;
    if (src !== 'kg') document.getElementById('mass-kg').value = base;
    if (src !== 'g') document.getElementById('mass-g').value = base * 1000;
    if (src !== 'mg') document.getElementById('mass-mg').value = base * 1000000;
}
function convertTemp(src) {
    const c = parseFloat(document.getElementById('temp-c').value);
    const f = parseFloat(document.getElementById('temp-f').value);
    const k = parseFloat(document.getElementById('temp-k').value);
    let baseC = 0;
    if (src === 'c') baseC = c;
    else if (src === 'f') baseC = (f - 32) * 5 / 9;
    else if (src === 'k') baseC = k - 273.15;
    if (isNaN(baseC)) return;
    if (src !== 'c') document.getElementById('temp-c').value = baseC;
    if (src !== 'f') document.getElementById('temp-f').value = baseC * 9 / 5 + 32;
    if (src !== 'k') document.getElementById('temp-k').value = baseC + 273.15;
}
</script>
<?php include __DIR__ . '/../includes/footer.php';