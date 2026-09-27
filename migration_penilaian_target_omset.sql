-- ══════════════════════════════════════════════════════════════════════
-- Migration: Target Omset per staff per periode — dipakai untuk hitung
-- Skor Omset (bagian dari Skor Total = Absensi 30% + Omset 25% + Peer 45%).
--
-- Backward compatible: cuma nambah 1 tabel baru, TIDAK mengubah tabel
-- existing. Aman dijalankan berkali-kali (CREATE TABLE IF NOT EXISTS).
--
-- JANGAN jalankan file ini ke database production sebelum ada
-- konfirmasi eksplisit — ini murni untuk development/testing lokal.
-- ══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS penilaian_target_omset (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    staff_id       INT NOT NULL,
    periode_id     INT NOT NULL,
    target_nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_periode (staff_id, periode_id),
    KEY idx_periode (periode_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
