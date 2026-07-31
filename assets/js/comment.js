// 评论交互：提交评论、展开回复框
(function () {
    'use strict';

    // 展开回复表单
    document.querySelectorAll('.comment-reply-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('.comment').querySelector('.reply-form');
            if (form) {
                form.classList.toggle('active');
            }
        });
    });

    // AJAX 提交评论
    document.querySelectorAll('[data-comment-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(form);
            var payload = {
                post_id: formData.get('post_id'),
                parent_id: formData.get('parent_id') || null,
                content: formData.get('content'),
                csrf_token: formData.get('csrf_token')
            };

            if (!payload.content || !payload.content.trim()) {
                alert('评论内容不能为空');
                return;
            }

            fetch('/api/comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || '评论失败');
                }
            })
            .catch(function () {
                alert('网络错误，请重试');
            });
        });
    });
})();
