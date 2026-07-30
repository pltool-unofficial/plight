<?php
/**
 * 简易 Parsedown 兼容实现
 * 提供基础 Markdown 渲染，避免强依赖 Composer。
 * 如已通过 composer require erusev/parsedown 安装，本文件将被跳过（见 functions.php）。
 */
class Parsedown
{
    private $safeMode = false;

    public function setSafeMode($safe)
    {
        $this->safeMode = (bool)$safe;
        return $this;
    }

    public function text($text)
    {
        $text = trim($text);
        if ($this->safeMode) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // 标准化换行
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 代码块（```）
        $text = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $lang = !empty($m[1]) ? ' class="language-' . htmlspecialchars($m[1]) . '"' : '';
            return '<pre><code' . $lang . '>' . htmlspecialchars($m[2]) . '</code></pre>';
        }, $text);

        // 行内代码
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code>' . htmlspecialchars($m[1]) . '</code>';
        }, $text);

        // 按行处理块级元素
        $lines = explode("\n", $text);
        $html = '';
        $inList = false;
        $inQuote = false;

        foreach ($lines as $line) {
            // 标题
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inQuote) { $html .= '</blockquote>'; $inQuote = false; }
                $level = strlen($m[1]);
                $html .= '<h' . $level . '>' . $this->inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            // 引用
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if (!$inQuote) { $html .= '<blockquote>'; $inQuote = true; }
                $html .= $this->inline($m[1]) . '<br>';
                continue;
            } else {
                if ($inQuote) { $html .= '</blockquote>'; $inQuote = false; }
            }
            // 无序列表
            if (preg_match('/^[-*+]\s+(.*)$/', $line, $m)) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            // 有序列表
            if (preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            // 空行
            if (trim($line) === '') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inQuote) { $html .= '</blockquote>'; $inQuote = false; }
                continue;
            }
            // 水平线
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<hr>';
                continue;
            }
            // 普通段落
            $html .= '<p>' . $this->inline($line) . '</p>';
        }
        if ($inList) $html .= '</ul>';
        if ($inQuote) $html .= '</blockquote>';

        return $html;
    }

    private function inline($text)
    {
        if ($this->safeMode) {
            // safeMode 下整段已转义，这里仅处理 Markdown 语法
        }
        // 图片
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $text);
        // 链接
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        // 粗体
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
        // 删除线
        $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
        return $text;
    }
}
