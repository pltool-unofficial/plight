/**
 * Markdown 编辑器组件
 * 支持实时预览、全屏模式、工具栏
 */
class MarkdownEditor {
    constructor(textareaId, previewId, options = {}) {
        this.textarea = document.getElementById(textareaId);
        this.preview = document.getElementById(previewId);
        this.toolbar = options.toolbar !== false;
        this.autoPreview = options.autoPreview !== false;

        this.initToolbar();
        this.initEvents();
        this.updatePreview();
    }

    initToolbar() {
        if (!this.toolbar) return;
        const toolbar = document.createElement('div');
        toolbar.className = 'md-toolbar';
        toolbar.innerHTML = `
            <button type="button" data-action="bold" title="粗体"><b>B</b></button>
            <button type="button" data-action="italic" title="斜体"><i>I</i></button>
            <button type="button" data-action="heading" title="标题">H</button>
            <button type="button" data-action="link" title="链接">链接</button>
            <button type="button" data-action="image" title="图片">图片</button>
            <button type="button" data-action="code" title="代码">&lt;/&gt;</button>
            <button type="button" data-action="quote" title="引用">引用</button>
            <button type="button" data-action="list" title="列表">列表</button>
            <button type="button" data-action="preview-toggle" title="切换预览">预览</button>
            <button type="button" data-action="fullscreen" title="全屏">全屏</button>
        `;
        this.textarea.parentNode.insertBefore(toolbar, this.textarea);

        toolbar.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            this.handleAction(btn.dataset.action);
        });
    }

    handleAction(action) {
        const start = this.textarea.selectionStart;
        const end = this.textarea.selectionEnd;
        const text = this.textarea.value;
        let newText = text;
        let cursorOffset = 0;

        const wrappers = {
            bold: ['**', '**'],
            italic: ['*', '*'],
            heading: ['# ', ''],
            quote: ['> ', ''],
            code: ['```\n', '\n```'],
            list: ['- ', '']
        };

        if (action === 'link') {
            const selected = text.substring(start, end) || '链接文字';
            const insert = `[${selected}](url)`;
            newText = text.substring(0, start) + insert + text.substring(end);
            cursorOffset = insert.length - 4;
        } else if (action === 'image') {
            const insert = `![图片描述](url)`;
            newText = text.substring(0, start) + insert + text.substring(end);
            cursorOffset = insert.length - 5;
        } else if (action === 'preview-toggle') {
            this.togglePreview();
            return;
        } else if (action === 'fullscreen') {
            this.toggleFullscreen();
            return;
        } else if (wrappers[action]) {
            const [open, close] = wrappers[action];
            const selected = text.substring(start, end);
            const insert = open + selected + close;
            newText = text.substring(0, start) + insert + text.substring(end);
            cursorOffset = open.length;
        }

        this.textarea.value = newText;
        this.textarea.focus();
        this.textarea.setSelectionRange(start + cursorOffset, start + cursorOffset);
        this.updatePreview();
    }

    initEvents() {
        // L15: input 事件加 debounce(300ms)
        let debounceTimer = null;
        this.textarea.addEventListener('input', () => {
            if (this.autoPreview) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => this.updatePreview(), 300);
            }
        });

        // Tab键支持 + Escape退出全屏
        this.textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.textarea.selectionStart;
                this.textarea.value =
                    this.textarea.value.substring(0, start) + '  ' +
                    this.textarea.value.substring(start);
                this.textarea.setSelectionRange(start + 2, start + 2);
            } else if (e.key === 'Escape') {
                const container = this.textarea.closest('.md-editor');
                if (container && container.classList.contains('fullscreen')) {
                    container.classList.remove('fullscreen');
                    e.preventDefault();
                }
            }
        });
    }

    updatePreview() {
        const content = this.textarea.value;
        // L17: 仅使用后端 API 渲染，失败时显示原始文本
        fetch('/api/markdown-preview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content })
        })
        .then(res => res.json())
        .then(data => {
            if (this.preview) {
                this.preview.innerHTML = data.html;
            }
        })
        .catch(() => {
            if (this.preview) {
                this.preview.textContent = content;
            }
        });
    }

    togglePreview() {
        if (this.preview) {
            // L16: 初始 display 为 ''，用 !== 'block' 判断确保首次点击生效
            this.preview.style.display =
                this.preview.style.display !== 'block' ? 'block' : 'none';
        }
    }

    toggleFullscreen() {
        const container = this.textarea.closest('.md-editor');
        if (container) {
            container.classList.toggle('fullscreen');
        }
    }
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('markdown-editor');
    const preview = document.getElementById('markdown-preview');
    if (editor) {
        new MarkdownEditor('markdown-editor', 'markdown-preview');
    }
});
