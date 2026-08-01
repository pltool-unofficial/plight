<?php
// 站点配置
define('SITE_NAME', '灯光');
define('SITE_URL', 'https://plight.chenyinweb.cn/');
define('SITE_EMAIL', 'noreply@plight.chenyinweb.cn');

// 数据库配置 — 优先从环境变量读取，避免凭据写入源码
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'plight');
define('DB_USER', getenv('DB_USER') ?: 'plight');
define('DB_PASS', getenv('DB_PASS') ?: 'Fuckezezsb250wjsm');

// 会话配置
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 86400)); // 24小时
// 会话域：留空表示使用当前域名（本地/生产通用）；生产可设 .plight.chenyinweb.cn
define('SESSION_DOMAIN', getenv('SESSION_DOMAIN') ?: '');
// 是否强制 HTTPS Cookie；本地 HTTP 开发请设 0
define('SESSION_SECURE', getenv('SESSION_SECURE') !== false ? (getenv('SESSION_SECURE') === '1') : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));

// 上传配置
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 分页配置
define('POSTS_PER_PAGE', 20);
define('COMMENTS_PER_PAGE', 50);

// 邮件配置
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.example.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');

// 是否为开发环境（显示错误）
define('IS_DEV', getenv('APP_ENV') === 'dev');

// 错误报告
if (IS_DEV) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
