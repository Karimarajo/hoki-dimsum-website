-- =========================================================
-- Hoki Dimsum - Order Online Database Schema
-- Import via phpMyAdmin: pilih dulu database tujuan (mis. u173485424_Order_Hoki)
-- di sidebar phpMyAdmin, baru import file ini lewat tab "Import".
-- Jangan jalankan CREATE DATABASE/USE manual - shared hosting biasanya
-- membatasi nama database harus pakai prefix akun (mis. u173485424_...).
-- =========================================================

-- Admin (untuk login dashboard)
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Setting umum (key-value, fleksibel)
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
) ENGINE=InnoDB;

-- Cabang
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    jam_operasional VARCHAR(100),
    wa_number VARCHAR(20) NULL,
    gofood_link VARCHAR(255) NULL,
    grabfood_link VARCHAR(255) NULL,
    shopeefood_link VARCHAR(255) NULL,
    qris_image VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO branches (nama, alamat, jam_operasional, is_active) VALUES
('Pusat', 'ALAMAT_PUSAT_DI_SINI', '08:00 - 21:00', 1),
('Cabang A', 'ALAMAT_CABANG_A_DI_SINI', '08:00 - 21:00', 1),
('Cabang B', 'ALAMAT_CABANG_B_DI_SINI', '08:00 - 21:00', 1);

-- Kategori produk
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Produk
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    nama VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(10,2) NOT NULL,
    foto VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    pos_sku VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Order
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,
    nama_customer VARCHAR(100) NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    branch_id INT NOT NULL,
    pickup_method ENUM('sendiri', 'ojol') NOT NULL,
    pickup_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    catatan TEXT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    kode_unik INT NOT NULL,
    kupon_kode VARCHAR(50) NULL,
    diskon DECIMAL(10,2) DEFAULT 0,
    total_bayar DECIMAL(10,2) NOT NULL,
    status ENUM('pending_payment','paid','preparing','ready','completed','cancelled') DEFAULT 'pending_payment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

-- Detail item per order
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    nama_produk_snapshot VARCHAR(100) NOT NULL,
    harga_snapshot DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Promo
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    gambar VARCHAR(255),
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Artikel/blog
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    konten LONGTEXT NOT NULL,
    gambar_cover VARCHAR(255),
    is_published TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_articles_published ON articles(is_published, published_at);

-- =========================================================
-- Seed data awal
-- =========================================================

-- Admin default -> username: admin / password: admin123 (WAJIB GANTI setelah login pertama!)
INSERT INTO admin_users (username, password_hash, nama) VALUES
('admin', '$2y$10$PTfCjP9BuH0ZInsVD.SAgOpH5in4Bn6EshQ1pWd.OlNXREePLAGxO', 'Admin Hoki Dimsum');

-- Settings default
INSERT INTO site_settings (setting_key, setting_value) VALUES
('wa_pusat', '6281234567890'),
('no_rekening', '1234567890'),
('nama_bank', 'BCA'),
('nama_rekening', 'PT Hoki Dimsum Sejahtera'),
('qris_image_path', ''),
('tagline', 'Dimsum Homemade, Hoki Setiap Gigitan'),
('tentang', 'Hoki Dimsum menghadirkan dimsum homemade dengan resep rahasia turun-temurun, dibuat fresh setiap hari tanpa pengawet. Dari kukusan hangat kami ke meja makanmu.'),
('instagram', 'https://instagram.com/hokidimsum'),
('meta_description', 'Order dimsum homemade Hoki Dimsum secara online. Fresh, halal, dan hoki di setiap gigitan.');

-- Kategori
INSERT INTO product_categories (nama) VALUES
('Dimsum Kukus'), ('Dimsum Goreng'), ('Paket Hemat'), ('Minuman');

-- Produk contoh
INSERT INTO products (category_id, nama, deskripsi, harga, foto, is_available) VALUES
(1, 'Hakau Udang', 'Dimsum kukus kulit bening isi udang segar pilihan, 5 pcs per porsi.', 28000, '', 1),
(1, 'Siomay Ayam Udang', 'Siomay klasik isi ayam & udang, disiram saus sambal spesial, 5 pcs.', 22000, '', 1),
(1, 'Kaki Naga', 'Dimsum kukus bentuk cakar naga, isi ayam cincang gurih, 5 pcs.', 24000, '', 1),
(2, 'Lumpia Udang Goreng', 'Lumpia crispy isi udang, digoreng garing, 5 pcs.', 25000, '', 1),
(2, 'Gyoza Ayam', 'Gyoza pan-fried isi ayam, disajikan dengan saus ponzu, 6 pcs.', 26000, '', 1),
(3, 'Paket Hoki Berdua', '2 porsi dimsum pilihan + 2 minuman, hemat untuk berbagi.', 65000, '', 1),
(3, 'Paket Hoki Keluarga', '5 porsi dimsum campur + 4 minuman, pas untuk keluarga.', 150000, '', 1),
(4, 'Es Lemon Tea', 'Lemon tea segar dengan es batu, 500ml.', 12000, '', 1),
(4, 'Es Jeruk', 'Jeruk peras asli tanpa pengawet, 500ml.', 12000, '', 1);

-- Promo contoh
INSERT INTO promotions (judul, deskripsi, gambar, tanggal_mulai, tanggal_selesai, is_active) VALUES
('Promo Buy 1 Get 1 Hakau Udang', 'Setiap pembelian Paket Hoki Keluarga, dapatkan tambahan 1 porsi Hakau Udang gratis!', '', '2026-07-01', '2026-08-31', 1),
('Diskon 10% Order via Website', 'Order online lewat website dapat diskon 10% untuk semua menu, khusus bulan ini.', '', '2026-07-01', '2026-07-31', 1);

-- Artikel contoh
INSERT INTO articles (judul, slug, konten, gambar_cover, is_published, published_at) VALUES
('5 Fakta Menarik Tentang Dimsum yang Jarang Diketahui', '5-fakta-menarik-tentang-dimsum', '<p>Dimsum bukan sekadar makanan, tapi juga budaya. Berikut 5 fakta menarik seputar dimsum yang mungkin belum kamu ketahui...</p><p>1. Dimsum berasal dari tradisi minum teh di Tiongkok Selatan.</p><p>2. Kata "dimsum" berarti "menyentuh hati".</p><p>3. Ada ratusan variasi dimsum di seluruh dunia.</p>', '', 1, NOW()),
('Tips Menyimpan Dimsum Agar Tetap Fresh di Rumah', 'tips-menyimpan-dimsum-agar-tetap-fresh', '<p>Dimsum homemade dari Hoki Dimsum bisa tetap enak walau disimpan, asal tahu caranya. Simak tipsnya di sini...</p>', '', 1, NOW());
