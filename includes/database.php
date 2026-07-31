<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            // 不向用户泄漏连接详情，记录到错误日志
            error_log('数据库连接失败: ' . $e->getMessage());
            if (function_exists('renderErrorPage')) {
                renderErrorPage('服务暂时不可用', '数据库连接失败，请稍后再试。', 500);
            } else {
                http_response_code(500);
                echo '<!DOCTYPE html><meta charset="UTF-8"><title>服务暂时不可用</title><style>body{font-family:-apple-system,sans-serif;background:#f5f5f7;color:#1d1d1f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}h1{font-size:48px;font-weight:600;color:#86868b;margin-bottom:8px}p{color:#6e6e73;font-size:17px}a{color:#0071e3;text-decoration:none}</style><div><h1>500</h1><p>数据库连接失败，请稍后再试。</p><p style="margin-top:24px"><a href="/">返回首页</a></p></div>';
            }
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO() {
        return $this->pdo;
    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    // 事务辅助
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
