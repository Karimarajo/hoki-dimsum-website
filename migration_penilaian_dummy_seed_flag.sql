-- ══════════════════════════════════════════════════════════════════════
-- Migration kecil: kolom penanda `is_dummy_seed` di penilaian_assignment
-- & penilaian_submission - supaya baris yang dibuat seed_dummy_penilaian.php
-- bisa dibedakan & dihapus bersih lewat rollback_dummy_penilaian.php tanpa
-- tercampur data asli.
--
-- Aman dijalankan berkali-kali (cek SHOW COLUMNS dulu, mengikuti pola yang
-- sudah dipakai di api.php untuk migrasi kolom logs_login/bahan_baku dkk).
-- Script seeder & rollback JUGA menjalankan pengecekan yang sama secara
-- otomatis, jadi migration file ini sifatnya dokumentasi/opsional dijalankan
-- manual - tidak wajib sebelum jalankan seeder.
-- ══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- Kolom di penilaian_assignment
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penilaian_assignment' AND COLUMN_NAME = 'is_dummy_seed'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE penilaian_assignment ADD COLUMN is_dummy_seed TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Kolom is_dummy_seed sudah ada di penilaian_assignment"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kolom di penilaian_submission
SET @col_exists2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penilaian_submission' AND COLUMN_NAME = 'is_dummy_seed'
);
SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE penilaian_submission ADD COLUMN is_dummy_seed TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "Kolom is_dummy_seed sudah ada di penilaian_submission"');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
