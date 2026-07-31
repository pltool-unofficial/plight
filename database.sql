-- 灯光 (plight) 数据库结构
CREATE DATABASE IF NOT EXISTS plight
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plight;

-- 用户表
CREATE TABLE `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL COMMENT '用户名',
  `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
  `password` VARCHAR(255) NOT NULL COMMENT '密码哈希',
  `temp_username` VARCHAR(50) DEFAULT NULL COMMENT '临时账户名',
  `physics_username` VARCHAR(50) DEFAULT NULL COMMENT '物实用户名',
  `physics_id` VARCHAR(50) DEFAULT NULL COMMENT '物实账户ID',
  `verified` TINYINT(1) DEFAULT 0 COMMENT '是否已认证(0待审核/1已认证/2拒绝)',
  `vip_level` ENUM('none','blue','yellow','red') DEFAULT 'none' COMMENT 'V认证等级',
  `is_admin` TINYINT(1) DEFAULT 0 COMMENT '是否管理员',
  `is_banned` TINYINT(1) DEFAULT 0 COMMENT '是否封号',
  `is_muted` TINYINT(1) DEFAULT 0 COMMENT '是否禁言',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
  `bio` TEXT DEFAULT NULL COMMENT '个人简介',
  `email_verified` TINYINT(1) DEFAULT 0 COMMENT '邮箱是否已验证',
  `verify_token` VARCHAR(64) DEFAULT NULL COMMENT '邮箱验证令牌',
  `token_expiry` DATETIME DEFAULT NULL COMMENT '令牌过期时间',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `verified` (`verified`),
  KEY `vip_level` (`vip_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 帖子表
CREATE TABLE `posts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `section` ENUM('qiming','lighthouse','wenxuan') NOT NULL DEFAULT 'qiming' COMMENT '所属板块',
  `category` VARCHAR(30) DEFAULT NULL COMMENT '分类(思考/闲聊/水贴/问答/其他/自定义)',
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL COMMENT 'Markdown内容',
  `content_html` TEXT NOT NULL COMMENT '渲染后的HTML',
  `is_pinned` TINYINT(1) DEFAULT 0 COMMENT '是否置顶',
  `is_locked` TINYINT(1) DEFAULT 0 COMMENT '是否锁定(禁止评论)',
  `view_count` INT(11) DEFAULT 0,
  `like_count` INT(11) DEFAULT 0,
  `comment_count` INT(11) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `section` (`section`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 评论表
CREATE TABLE `comments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `parent_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '父评论ID(支持二级回复)',
  `content` TEXT NOT NULL,
  `content_html` TEXT NOT NULL,
  `like_count` INT(11) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知表
CREATE TABLE `notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL COMMENT '类型: reply/like/system/verify',
  `content` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `link` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 操作日志表(管理员审计)
CREATE TABLE `admin_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_id` INT(11) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 公告表
CREATE TABLE `announcements` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL COMMENT '公告标题',
  `content` TEXT NOT NULL COMMENT 'Markdown内容',
  `content_html` TEXT NOT NULL COMMENT '渲染后的HTML',
  `type` ENUM('info','success','warning','danger','maintenance') NOT NULL DEFAULT 'info' COMMENT '公告类型',
  `is_pinned` TINYINT(1) DEFAULT 0 COMMENT '是否置顶',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '是否启用',
  `created_by` INT(11) UNSIGNED NOT NULL COMMENT '创建者(管理员ID)',
  `updated_by` INT(11) UNSIGNED DEFAULT NULL COMMENT '最后更新者',
  `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间(NULL为永不过期)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `is_pinned` (`is_pinned`),
  KEY `type` (`type`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
