<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal. Pastikan database "' . DB_NAME . '" sudah dibuat dan import database/schema.sql. Detail: ' . htmlspecialchars($e->getMessage()));
        }

        // Migrasi aman untuk DB lama: tambahkan kolom yang belum ada tanpa perlu import ulang schema.sql.
        if (!$pdo->query("SHOW COLUMNS FROM products LIKE 'urutan'")->fetch()) {
            $pdo->exec('ALTER TABLE products ADD COLUMN urutan INT DEFAULT 0');
            $pdo->exec('UPDATE products SET urutan = id');
        }
        if (!$pdo->query("SHOW COLUMNS FROM products LIKE 'pos_sku'")->fetch()) {
            $pdo->exec('ALTER TABLE products ADD COLUMN pos_sku VARCHAR(20) NULL');
        }
        if (!$pdo->query("SHOW COLUMNS FROM branches LIKE 'qris_image'")->fetch()) {
            $pdo->exec('ALTER TABLE branches ADD COLUMN qris_image VARCHAR(255) NULL');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS branch_hours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id INT NOT NULL,
            hari TINYINT NOT NULL,
            buka TIME NULL,
            tutup TIME NULL,
            is_closed TINYINT(1) DEFAULT 0,
            UNIQUE KEY uniq_branch_hari (branch_id, hari),
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        if (!$pdo->query("SHOW COLUMNS FROM products LIKE 'nama_display'")->fetch()) {
            $pdo->exec('ALTER TABLE products ADD COLUMN nama_display VARCHAR(150) NULL');
        }
        if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'synced_to_pos'")->fetch()) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN synced_to_pos TINYINT(1) DEFAULT 0');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_branch_unavailable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            branch_id INT NOT NULL,
            UNIQUE KEY uniq_product_branch (product_id, branch_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        if (!$pdo->query("SHOW COLUMNS FROM product_categories LIKE 'urutan'")->fetch()) {
            $pdo->exec('ALTER TABLE product_categories ADD COLUMN urutan INT DEFAULT 0');
            $pdo->exec('UPDATE product_categories SET urutan = id');
        }
    }
    return $pdo;
}
