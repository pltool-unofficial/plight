// 闲谈聊天室：轮询新消息 + 发送消息
(function () {
    'use strict';

    var chatList = document.getElementById('chat-list');
    if (!chatList) { return; }

    // 当前最大消息ID
    var lastId = 0;
    var items = chatList.querySelectorAll('.chat-message[data-mid]');
    if (items.length) {
        lastId = parseInt(items[items.length - 1].getAttribute('data-mid'), 10) || 0;
    }

    var emptyTip = chatList.querySelector('.empty-tip');
    var isAdmin = window.CHAT_IS_ADMIN === true;

    // 转义HTML，防XSS
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function timeAgo(str) {
        var t = new Date(str.replace(/-/g, '/').replace('T', ' '));
        if (isNaN(t.getTime())) { return ''; }
        var diff = Math.floor((Date.now() - t.getTime()) / 1000);
        if (diff < 60) { return diff + '秒前'; }
        if (diff < 3600) { return Math.floor(diff / 60) + '分钟前'; }
        if (diff < 86400) { return Math.floor(diff / 3600) + '小时前'; }
        return (str || '').slice(0, 16);
    }

    function verifyBadge(label) {
        if (!label) { return ''; }
        return '<span class="v-badge v-custom" title="认证标记">' + esc(label) + '</span>';
    }

    function messageHtml(m) {
        var avatar = m.avatar || '/assets/images/default-avatar.svg';
        var adminBadge = m.is_admin ? '<span class="admin-badge">管理员</span>' : '';
        var delForm = '';
        if (isAdmin) {
            var csrf = document.querySelector('#chat-form [name="csrf_token"]');
            var token = csrf ? csrf.value : '';
            delForm = '<form method="POST" action="/api/chat.php" class="chat-del-form">' +
                '<input type="hidden" name="csrf_token" value="' + esc(token) + '">' +
                '<input type="hidden" name="action" value="delete">' +
                '<input type="hidden" name="id" value="' + parseInt(m.id, 10) + '">' +
                '<button type="submit" class="chat-del-btn" title="删除该消息">删除</button></form>';
        }
        return '<div class="chat-message" data-mid="' + parseInt(m.id, 10) + '">' +
            '<img src="' + esc(avatar) + '" alt="头像" class="chat-avatar" onerror="this.src=\'/assets/images/default-avatar.svg\'">' +
            '<div class="chat-body">' +
            '<div class="chat-meta">' +
            '<a href="/user/profile.php?id=' + parseInt(m.user_id, 10) + '" class="chat-name">' + esc(m.username) + '</a>' +
            verifyBadge(m.verify_label) + (m.medals_html || '') + adminBadge +
            '<span class="chat-time">' + timeAgo(m.created_at) + '</span>' + delForm +
            '</div>' +
            '<div class="chat-content markdown-body">' + m.content_html + '</div>' +
            '</div></div>';
    }

    function appendMessages(messages) {
        if (!messages || !messages.length) { return; }
        if (emptyTip) { emptyTip.remove(); emptyTip = null; }
        messages.forEach(function (m) {
            var div = document.createElement('div');
            div.innerHTML = messageHtml(m);
            var node = div.firstChild;
            chatList.appendChild(node);
            lastId = Math.max(lastId, parseInt(m.id, 10));
        });
        chatList.scrollTop = chatList.scrollHeight;
        // 限制列表长度，避免无限增长
        while (chatList.children.length > 300) {
            chatList.removeChild(chatList.firstChild);
        }
    }

    // 轮询新消息
    function poll() {
        fetch('/api/chat.php?after=' + lastId, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) { appendMessages(data.messages); }
            })
            .catch(function () { /* 网络错误静默重试 */ });
    }
    setInterval(poll, 5000);

    // 发送消息
    var form = document.getElementById('chat-form');
    if (form) {
        var input = document.getElementById('chat-input');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var content = input.value.trim();
            if (!content) { return; }
            var csrf = form.querySelector('[name="csrf_token"]');
            fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send',
                    content: content,
                    csrf_token: csrf ? csrf.value : ''
                })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    input.value = '';
                    appendMessages([data.message]);
                } else {
                    alert(data.message || '发送失败');
                }
            })
            .catch(function () { alert('网络错误，请重试'); });
        });

        // Ctrl/Cmd + Enter 发送
        input.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                form.dispatchEvent(new Event('submit'));
            }
        });
    }

    // 删除消息（事件委托，兼容轮询新增的消息）
    chatList.addEventListener('submit', function (e) {
        var form = e.target.closest('.chat-del-form');
        if (!form) { return; }
        e.preventDefault();
        var formData = new FormData(form);
        if (!confirm('确定删除这条聊天消息？')) { return; }
        fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: formData.get('id'),
                csrf_token: formData.get('csrf_token')
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                form.closest('.chat-message').remove();
            } else {
                alert(data.message || '删除失败');
            }
        })
        .catch(function () { alert('网络错误，请重试'); });
    });
})();
