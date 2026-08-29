-- ══════════════════════════════════════════════════════════════════════
-- Skema Database Marajo (PT Marajo Barokah) — marajo.pos-hokidimsum.com
--
-- Numpang di database u173485424_Order_Hoki (bukan database baru — kena
-- limit jumlah database di Hostinger), makanya semua tabel diberi prefix
-- "marajo_" supaya tidak bentrok dengan tabel order (orders, products, dst).
--
-- Cara pakai:
--   1. Import file ini ke database u173485424_Order_Hoki lewat phpMyAdmin
--      (atau `mysql -u u173485424_Order_Hoki -p u173485424_Order_Hoki < schema.sql`).
--   2. Kredensial database sudah diisi di marajo/includes/db.php (production).
-- ══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Input Barang / List Track Barang ────────────────────────────────
-- Satu baris = satu batch input barang (restock). "Harga Barang" = total
-- harga beli batch ini (bukan satuan) sesuai form Input Barang di PDF.
CREATE TABLE IF NOT EXISTS marajo_barang_masuk (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang         VARCHAR(150) NOT NULL,
    harga_barang        DECIMAL(14,2) NOT NULL DEFAULT 0,
    qty                 INT NOT NULL DEFAULT 0,
    harga_modal_satuan  DECIMAL(14,2) NOT NULL DEFAULT 0,
    harga_jual_satuan   DECIMAL(14,2) NOT NULL DEFAULT 0,
    profit              DECIMAL(14,2) NOT NULL DEFAULT 0,
    margin              DECIMAL(6,2)  NOT NULL DEFAULT 0,
    created_by          VARCHAR(100) DEFAULT '',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_nama_barang (nama_barang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Riwayat Stock (ledger masuk/keluar per barang) ──────────────────
CREATE TABLE IF NOT EXISTS marajo_stock_ledger (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang  VARCHAR(150) NOT NULL,
    keterangan   VARCHAR(255) NOT NULL DEFAULT '',
    masuk        INT NOT NULL DEFAULT 0,
    keluar       INT NOT NULL DEFAULT 0,
    sisa         INT NOT NULL DEFAULT 0,
    ref_type     VARCHAR(30) NOT NULL DEFAULT '',   -- 'barang_masuk' | 'penjualan' | 'adjustment'
    ref_id       INT NULL,
    created_by   VARCHAR(100) DEFAULT '',
    waktu        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nama_barang (nama_barang),
    KEY idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── History Penjualan (stock yang keluar karena terjual) ────────────
CREATE TABLE IF NOT EXISTS marajo_penjualan (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang  VARCHAR(150) NOT NULL,
    qty          INT NOT NULL DEFAULT 0,
    profit       DECIMAL(14,2) NOT NULL DEFAULT 0,
    keterangan   VARCHAR(255) DEFAULT '',
    created_by   VARCHAR(100) DEFAULT '',
    waktu        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nama_barang (nama_barang),
    KEY idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Transaksi (Panel Saldo — Debit/Kredit) ──────────────────────────
CREATE TABLE IF NOT EXISTS marajo_transaksi (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    keterangan   VARCHAR(255) NOT NULL DEFAULT '',
    tipe         ENUM('debit','kredit') NOT NULL,
    nominal      DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by   VARCHAR(100) DEFAULT '',
    waktu        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
