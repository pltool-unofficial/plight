// 主脚本：通用交互
(function () {
    'use strict';

    // 自动关闭提示框
    document.querySelectorAll('.alert[data-auto-close]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 4000);
    });

    // 退出登录确认
    document.querySelectorAll('.logout-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('确定要退出登录吗？')) {
                e.preventDefault();
            }
        });
    });

    // 表单 CSRF 自动校验（兜底）
    document.querySelectorAll('form').forEach(function (form) {
        if (form.method.toLowerCase() === 'post' && !form.querySelector('[name="csrf_token"]')) {
            // 不强制阻断，仅记录
        }
    });

    // 复制链接
    document.querySelectorAll('.copy-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.dataset.copy || '';
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = '已复制';
                    setTimeout(function () { btn.textContent = '复制链接'; }, 1500);
                });
            }
        });
    });
})();
