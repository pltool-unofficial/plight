<?php
// 站点配置
define('SITE_NAME', '灯光');
define('SITE_URL', 'https://plight.chenyinweb.cn/');
define('SITE_EMAIL', 'noreply@plight.chenyinweb.cn');

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'plight');
define('DB_USER', 'plight');  // 请根据实际修改
define('DB_PASS', 'Fuckezezsb250wjsm');

// 安全配置
define('SALT', 'plight_salt_2026_secure');
define('SESSION_LIFETIME', 86400); // 24小时

// 上传配置
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 分页配置
define('POSTS_PER_PAGE', 20);
define('COMMENTS_PER_PAGE', 50);

// 邮件配置 (使用PHPMailer或SMTP)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@example.com');
define('SMTP_PASS', 'your-password');
define('SMTP_SECURE', 'tls');

// 错误报告 (生产环境关闭)
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
