<?php
/**
 * 简易 Parsedown 兼容实现
 * 提供基础 Markdown 渲染，避免强依赖 Composer。
 * 如已通过 composer require erusev/parsedown 安装，本文件将被跳过（见 functions.php）。
 *
 * 安全说明：setSafeMode(true) 下会对 HTML 原文转义，并过滤链接/图片 URL scheme。
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
            // 先转义全部 HTML，后续 Markdown 语法基于已转义文本处理
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 代码块（```）— 内容已被整体转义，回调中不再二次转义
        $text = preg_replace_callback('/```[^\n]*\n(.*?)```/s', function ($m) {
            return '<pre><code>' . trim($m[1], "\n") . '</code></pre>';
        }, $text);

        // 行内代码 — 不二次转义
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code>' . $m[1] . '</code>';
        }, $text);

        // 按行处理块级元素
        $lines = explode("\n", $text);
        $html = '';
        $inUl = false;
        $inOl = false;
        $inQuote = false;
        $paragraph = [];

        $flushParagraph = function () use (&$paragraph, &$html) {
            if (!empty($paragraph)) {
                $html .= '<p>' . implode(' ', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        $closeLists = function () use (&$inUl, &$inOl, &$html) {
            if ($inUl) { $html .= '</ul>'; $inUl = false; }
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
        };
        $closeQuote = function () use (&$inQuote, &$html) {
            if ($inQuote) { $html .= '</blockquote>'; $inQuote = false; }
        };

        foreach ($lines as $line) {
            // 代码块占位行（被替换为 <pre><code>...</code></pre>）直接输出
            if (strpos($line, '<pre><code>') !== false) {
                $closeLists();
                $closeQuote();
                $flushParagraph();
                $html .= $line;
                continue;
            }
            // 标题
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $closeLists(); $closeQuote(); $flushParagraph();
                $level = strlen($m[1]);
                $html .= '<h' . $level . '>' . $this->inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            // 引用
            if (preg_match('/^&gt;\s?(.*)$/', $line, $m)) {
                $closeLists(); $flushParagraph();
                if (!$inQuote) { $html .= '<blockquote>'; $inQuote = true; }
                $html .= $this->inline($m[1]) . '<br>';
                continue;
            } else {
                $closeQuote();
            }
            // 无序列表
            if (preg_match('/^[-*+]\s+(.*)$/', $line, $m)) {
                $flushParagraph();
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                if (!$inUl) { $html .= '<ul>'; $inUl = true; }
                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            // 有序列表
            if (preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
                $flushParagraph();
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if (!$inOl) { $html .= '<ol>'; $inOl = true; }
                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            // 空行
            if (trim($line) === '') {
                $closeLists(); $closeQuote(); $flushParagraph();
                continue;
            }
            // 水平线
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
                $closeLists(); $closeQuote(); $flushParagraph();
                $html .= '<hr>';
                continue;
            }
            // 普通行 — 累积成段落（避免每行独立 <p>）
            $paragraph[] = $this->inline($line);
        }
        $closeLists(); $closeQuote(); $flushParagraph();

        return $html;
    }

    private function inline($text)
    {
        // 图片
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
            $url = $this->sanitizeUrl($m[2]);
            if ($url === null) return $m[1];
            return '<img src="' . $url . '" alt="' . $m[1] . '">';
        }, $text);
        // 链接
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = $this->sanitizeUrl($m[2]);
            if ($url === null) return $m[1];
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>';
        }, $text);
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

    /**
     * URL scheme 白名单过滤，防止 javascript:/vbscript:/data: 等 XSS 向量。
     * 返回安全 URL 或 null（不安全时仅返回链接文字）。
     */
    private function sanitizeUrl($url)
    {
        $url = trim($url);
        // 仅允许 http/https/mailto，相对路径（以 / 或 # 开头）也允许
        if (preg_match('#^(https?:|mailto:|/|#)#i', $url)) {
            return $url;
        }
        // 无 scheme 的裸链接视作相对路径
        if (strpos($url, ':') === false) {
            return $url;
        }
        return null;
    }
}
