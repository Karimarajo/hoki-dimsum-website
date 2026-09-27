-- ══════════════════════════════════════════════════════════════════════
-- Migration: Peer Review (Penilaian Rekan Kerja) — 45% dari sistem
-- penilaian karyawan (Absensi 30% + Omset 25% + Peer Review 45%).
--
-- Versi ini pakai TEMPLATE PERTANYAAN PER ROLE (data-driven, bukan
-- hardcode): Template A (Staff), Template B (Senior Staff), Template C
-- (SPV) — masing-masing 4 kategori x 5 pertanyaan. Mapping role->template
-- disimpan di tabel penilaian_role_template_map supaya bisa diubah dari
-- UI tanpa ubah kode.
--
-- Backward compatible: cuma nambah tabel baru, TIDAK mengubah tabel
-- existing (users, transaksi, dll). Aman dijalankan berkali-kali
-- (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
--
-- JANGAN jalankan file ini ke database production sebelum ada
-- konfirmasi eksplisit — ini murni untuk development/testing lokal.
-- ══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Periode penilaian ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS penilaian_periode (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_mulai   DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status          ENUM('aktif','tutup') NOT NULL DEFAULT 'aktif',
    dibuat_oleh     VARCHAR(100) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Assignment: siapa menilai siapa (persisten lintas periode) ──────
-- Hanya untuk staff biasa (bukan Owner/VIP - mereka auto-rate-all).
CREATE TABLE IF NOT EXISTS penilaian_assignment (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    penilai_user_id  INT NOT NULL,
    dinilai_user_id  INT NOT NULL,
    aktif            TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assignment (penilai_user_id, dinilai_user_id),
    KEY idx_penilai (penilai_user_id),
    KEY idx_dinilai (dinilai_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Template pertanyaan (A/B/C) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS penilaian_template (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    kode        VARCHAR(10) NOT NULL UNIQUE,
    nama        VARCHAR(150) NOT NULL,
    deskripsi   VARCHAR(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Mapping role -> template (data-driven, bukan hardcode if/else) ──
CREATE TABLE IF NOT EXISTS penilaian_role_template_map (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role        VARCHAR(50) NOT NULL,
    template_id INT NOT NULL,
    aktif       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Pertanyaan, terikat ke template_id ────────────────────────────────
CREATE TABLE IF NOT EXISTS penilaian_pertanyaan (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    kategori    VARCHAR(100) NOT NULL,
    urutan      INT NOT NULL DEFAULT 0,
    teks        VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_template_urutan (template_id, urutan),
    KEY idx_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Submission: 1 penilaian dari 1 penilai ke 1 target per periode ──
-- template_id_snapshot = template yang BERLAKU SAAT submit dibuat (bukan
-- di-lookup ulang dari role terkini) - supaya perubahan role staff di
-- tengah jalan tidak mengubah submission lama secara retroaktif.
CREATE TABLE IF NOT EXISTS penilaian_submission (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    periode_id           INT NOT NULL,
    penilai_user_id      INT NOT NULL,
    dinilai_user_id      INT NOT NULL,
    template_id_snapshot INT NOT NULL,
    total_skor           INT NOT NULL DEFAULT 0,
    submitted_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked               TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_submission (periode_id, penilai_user_id, dinilai_user_id),
    KEY idx_periode_dinilai (periode_id, dinilai_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Detail skor per pertanyaan (1-5) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS penilaian_submission_detail (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    submission_id  INT NOT NULL,
    pertanyaan_id  INT NOT NULL,
    skor           TINYINT NOT NULL,
    KEY idx_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════════════
-- SEED: 3 template + mapping role default + 60 pertanyaan (20/template)
-- ══════════════════════════════════════════════════════════════════════
INSERT IGNORE INTO penilaian_template (id, kode, nama, deskripsi) VALUES
(1, 'A', 'Operasional', 'Untuk role Staff'),
(2, 'B', 'Operasional + Kepemimpinan Dasar', 'Untuk role Senior Staff'),
(3, 'C', 'Manajemen & Kepemimpinan', 'Untuk role SPV');

INSERT IGNORE INTO penilaian_role_template_map (role, template_id, aktif) VALUES
('Staff', 1, 1),
('Senior Staff', 2, 1),
('SPV', 3, 1);

-- ── Template A (id=1) — Operasional (Staff) ──────────────────────────
INSERT IGNORE INTO penilaian_pertanyaan (template_id, kategori, urutan, teks) VALUES
(1, 'Kerja Sama Tim', 1, 'Mau bantu rekan kerja tanpa diminta saat lagi sibuk'),
(1, 'Kerja Sama Tim', 2, 'Komunikasi jelas saat serah terima shift'),
(1, 'Kerja Sama Tim', 3, 'Tidak lempar tanggung jawab ke rekan lain saat ada masalah'),
(1, 'Kerja Sama Tim', 4, 'Bisa diajak kerja bareng tanpa bikin suasana kerja tegang'),
(1, 'Kerja Sama Tim', 5, 'Terbuka menerima masukan dari rekan kerja'),
(1, 'Kedisiplinan & Tanggung Jawab', 6, 'Datang tepat waktu sesuai jadwal shift'),
(1, 'Kedisiplinan & Tanggung Jawab', 7, 'Menyelesaikan tugas sampai tuntas tanpa perlu diingatkan berkali-kali'),
(1, 'Kedisiplinan & Tanggung Jawab', 8, 'Menjaga kebersihan & kerapian area kerja'),
(1, 'Kedisiplinan & Tanggung Jawab', 9, 'Mengikuti SOP yang berlaku'),
(1, 'Kedisiplinan & Tanggung Jawab', 10, 'Bertanggung jawab kalau ada kesalahan'),
(1, 'Sikap & Attitude', 11, 'Ramah dan sopan ke pelanggan'),
(1, 'Sikap & Attitude', 12, 'Sabar menghadapi komplain'),
(1, 'Sikap & Attitude', 13, 'Jujur soal uang/stok/transaksi'),
(1, 'Sikap & Attitude', 14, 'Tidak menyebar drama/gosip negatif'),
(1, 'Sikap & Attitude', 15, 'Menerima teguran dengan sikap baik'),
(1, 'Inisiatif & Kualitas Kerja', 16, 'Cepat tanggap membenahi masalah tanpa disuruh'),
(1, 'Inisiatif & Kualitas Kerja', 17, 'Menjaga kualitas produk konsisten'),
(1, 'Inisiatif & Kualitas Kerja', 18, 'Punya ide perbaikan operasional'),
(1, 'Inisiatif & Kualitas Kerja', 19, 'Bisa handle situasi ramai/tekanan tanpa panik'),
(1, 'Inisiatif & Kualitas Kerja', 20, 'Terus belajar & mau ditingkatkan skill-nya');

-- ── Template B (id=2) — Operasional + Kepemimpinan Dasar (Senior Staff) ──
INSERT IGNORE INTO penilaian_pertanyaan (template_id, kategori, urutan, teks) VALUES
(2, 'Kepemimpinan & Pengambilan Keputusan', 1, 'Memberi arahan yang jelas, bukan membingungkan'),
(2, 'Kepemimpinan & Pengambilan Keputusan', 2, 'Adil, tidak pilih kasih ke staff tertentu'),
(2, 'Kepemimpinan & Pengambilan Keputusan', 3, 'Bisa ambil keputusan cepat saat situasi mendesak'),
(2, 'Kepemimpinan & Pengambilan Keputusan', 4, 'Mau dengar masukan dari staff sebelum memutuskan'),
(2, 'Kepemimpinan & Pengambilan Keputusan', 5, 'Bertanggung jawab atas keputusan yang diambil'),
(2, 'Kedisiplinan & Tanggung Jawab', 6, 'Datang tepat waktu sesuai jadwal shift'),
(2, 'Kedisiplinan & Tanggung Jawab', 7, 'Menyelesaikan tugas sampai tuntas tanpa perlu diingatkan berkali-kali'),
(2, 'Kedisiplinan & Tanggung Jawab', 8, 'Menjaga kebersihan & kerapian area kerja'),
(2, 'Kedisiplinan & Tanggung Jawab', 9, 'Mengikuti SOP yang berlaku'),
(2, 'Kedisiplinan & Tanggung Jawab', 10, 'Bertanggung jawab kalau ada kesalahan'),
(2, 'Sikap & Attitude', 11, 'Ramah dan sopan ke pelanggan'),
(2, 'Sikap & Attitude', 12, 'Sabar menghadapi komplain'),
(2, 'Sikap & Attitude', 13, 'Jujur soal uang/stok/transaksi'),
(2, 'Sikap & Attitude', 14, 'Tidak menyebar drama/gosip negatif'),
(2, 'Sikap & Attitude', 15, 'Menerima teguran dengan sikap baik'),
(2, 'Membina & Mengembangkan Tim', 16, 'Mau mengajari/membimbing staff baru'),
(2, 'Membina & Mengembangkan Tim', 17, 'Memberi teguran dengan cara mendidik'),
(2, 'Membina & Mengembangkan Tim', 18, 'Menjaga suasana kerja kondusif'),
(2, 'Membina & Mengembangkan Tim', 19, 'Cepat tanggap kalau ada konflik antar staff'),
(2, 'Membina & Mengembangkan Tim', 20, 'Jadi contoh baik dalam kedisiplinan dan kerja');

-- ── Template C (id=3) — Manajemen & Kepemimpinan (SPV) ────────────────
INSERT IGNORE INTO penilaian_pertanyaan (template_id, kategori, urutan, teks) VALUES
(3, 'Pengawasan & Manajemen Operasional', 1, 'Memastikan SOP dijalankan konsisten di semua staff/cabang yang diawasi'),
(3, 'Pengawasan & Manajemen Operasional', 2, 'Cepat tanggap menangani masalah operasional sebelum jadi besar'),
(3, 'Pengawasan & Manajemen Operasional', 3, 'Rutin memantau kualitas kerja tim tanpa harus turun tangan langsung'),
(3, 'Pengawasan & Manajemen Operasional', 4, 'Mengatur jadwal & pembagian kerja secara efisien'),
(3, 'Pengawasan & Manajemen Operasional', 5, 'Mengambil tindakan tegas kalau ada staff tidak sesuai standar'),
(3, 'Delegasi & Kepemimpinan', 6, 'Membagi tugas sesuai kemampuan masing-masing staff'),
(3, 'Delegasi & Kepemimpinan', 7, 'Memberi arahan yang jelas dan mudah dieksekusi'),
(3, 'Delegasi & Kepemimpinan', 8, 'Adil, tidak pilih kasih dalam menilai/memberi tugas'),
(3, 'Delegasi & Kepemimpinan', 9, 'Bisa mengambil keputusan cepat tanpa harus menunggu atasan'),
(3, 'Delegasi & Kepemimpinan', 10, 'Bertanggung jawab penuh atas hasil kerja tim yang dipimpin'),
(3, 'Membina & Mengembangkan Tim', 11, 'Mau melatih/membimbing Senior Staff dan staff baru'),
(3, 'Membina & Mengembangkan Tim', 12, 'Memberi teguran/masukan dengan cara mendidik'),
(3, 'Membina & Mengembangkan Tim', 13, 'Membangun suasana kerja sehat di seluruh tim yang diawasi'),
(3, 'Membina & Mengembangkan Tim', 14, 'Menyelesaikan konflik antar staff dengan bijak'),
(3, 'Membina & Mengembangkan Tim', 15, 'Jadi panutan dalam kedisiplinan dan etos kerja'),
(3, 'Komunikasi & Koordinasi', 16, 'Komunikasi lancar dua arah dengan Owner/VIP soal kondisi lapangan'),
(3, 'Komunikasi & Koordinasi', 17, 'Menyampaikan info/kebijakan baru ke staff dengan jelas dan tepat waktu'),
(3, 'Komunikasi & Koordinasi', 18, 'Responsif terhadap laporan/keluhan dari staff maupun pelanggan'),
(3, 'Komunikasi & Koordinasi', 19, 'Bisa koordinasi lintas cabang kalau ada kebutuhan mendesak'),
(3, 'Komunikasi & Koordinasi', 20, 'Transparan soal progres dan kendala pekerjaan ke atasan');
