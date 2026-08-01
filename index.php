<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
$currentUser = getCurrentUser();

$pageTitle = '首页';
include __DIR__ . '/includes/header.php';
?>
<main class="home-hero-wrap">
    <section class="home-hero" style="background-image:url('https://bowmo.xyz/p/11.jpg')">
        <div class="home-hero-overlay"></div>
        <div class="home-hero-content">
            <p class="home-hero-eyebrow">欢迎来到</p>
            <h1 class="home-hero-title">灯<span>光</span></h1>
            <div class="home-clock" id="home-clock">--</div>
            <p class="home-hero-tagline">万千灯火，汇成璀璨星河。</p>
            <div class="home-hero-actions">
                <?php if ($currentUser): ?>
                    <a href="/qiming/index.php" class="btn btn-hero">进入社区</a>
                    <a href="/user/checkin.php" class="btn btn-hero-ghost">每日签到</a>
                <?php else: ?>
                    <a href="/user/register.php" class="btn btn-hero">立即加入</a>
                    <a href="/user/login.php" class="btn btn-hero-ghost">登录</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
        var el = document.getElementById('home-clock');
        if (!el) return;
        var now = new Date();
        var week = ['日', '一', '二', '三', '四', '五', '六'][now.getDay()];
        el.textContent = now.getFullYear() + '年' + (now.getMonth() + 1) + '月' + now.getDate() + '日 星期' + week + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
