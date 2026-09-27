-- ══════════════════════════════════════════════════════════════════════
-- Migration: Foto Laporan Lapak (sesi kamera in-browser setelah staff
-- simpan Stok Harian) + setting global "Nomor/Link Grup WA Tujuan
-- Laporan Stok". Fitur ini INDEPENDEN dari Peer Review - tidak menyentuh
-- tabel penilaian_* sama sekali.
--
-- laporan_stok_foto = 1 baris per FOTO yang diambil (bukan cuma yang
-- akhirnya dikirim), supaya ada jejak riwayat walau fotonya cuma dikirim
-- manual lewat WA lalu "hilang" dari sana. cabang_id nullable & cabang_nama
-- didenormalisasi (sama seperti pola stok_history.cabang) karena cabang
-- disimpan sebagai string di alur input stok yang sudah ada - cabang_id
-- cuma diisi kalau namanya cocok dengan master hoki_cabang.
--
-- pengaturan_sistem = tabel key-value sederhana untuk setting global lain
-- di masa depan (bukan cuma WA tujuan laporan stok), supaya tidak perlu
-- bikin tabel baru tiap ada 1 setting baru.
--
-- Backward compatible: cuma nambah tabel baru. Aman dijalankan berkali-kali
-- (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
--
-- JANGAN jalankan file ini ke database production sebelum ada konfirmasi
-- eksplisit — ini murni untuk development/testing lokal.
-- ══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS laporan_stok_foto (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    staff_id         INT NOT NULL,
    cabang_id        INT NULL,
    cabang_nama      VARCHAR(100) NOT NULL DEFAULT '',
    nama_file        VARCHAR(255) NOT NULL,
    waktu_ambil      DATETIME NOT NULL,
    lokasi_koordinat VARCHAR(60) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_staff (staff_id),
    KEY idx_cabang (cabang_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengaturan_sistem (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_setting VARCHAR(100) NOT NULL UNIQUE,
    nilai        TEXT,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO pengaturan_sistem (nama_setting, nilai) VALUES ('wa_tujuan_laporan_stok', '');
