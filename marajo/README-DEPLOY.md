# Deploy Marajo (marajo.pos-hokidimsum.com)

Semua kode sudah selesai dibuat & sudah dites lokal (login, SSO, input barang,
stock, penjualan, transaksi, dashboard — semua jalan).

## 1. Database — SUDAH BERES ✅

Karena limit jumlah database di Hostinger sudah penuh, Marajo numpang di
database `u173485424_Order_Hoki` yang sudah ada (bukan bikin baru). Semua
tabel Marajo diberi prefix `marajo_` (`marajo_barang_masuk`,
`marajo_stock_ledger`, `marajo_penjualan`, `marajo_transaksi`) supaya tidak
bentrok dengan tabel order yang sudah ada di situ (`orders`, `products`, dst).
Kredensialnya sudah diisi di `marajo/includes/db.php`.

Yang masih perlu dilakukan pas deploy: **import `marajo/schema.sql` ke
database `u173485424_Order_Hoki`** lewat phpMyAdmin di hPanel (cukup sekali,
isinya cuma `CREATE TABLE IF NOT EXISTS` jadi aman dijalankan meski tabel
order lain sudah ada isinya).

## 2. FTP — BELUM BISA KONEK ⚠️

Kredensial yang dikirim (`u173485424` / port 21 / `153.92.9.192`) saya coba
dan hasilnya **530 Login incorrect** (sudah dicoba mode biasa & lewat TLS).
Saya stop supaya tidak kena lockout percobaan berulang.

Tolong dicek lagi di hPanel → **Files → FTP Accounts**:
- Pastikan password FTP-nya (kemungkinan ketuker sama password database di
  atasnya) — bisa juga klik "Reset Password" di situ kalau lupa
- Konfirmasi juga folder upload-nya: `public_html` yang dikirim itu biasanya
  root domain utama (pos-hokidimsum.com) — kalau subdomain `marajo` dibuat
  lewat hPanel dengan cara standar, folder upload-nya harusnya
  `public_html/marajo/` (bisa dicek di hPanel → **Websites** →
  `marajo.pos-hokidimsum.com` → **Manage**, lihat "Document Root"-nya)

## 3. Yang akan saya lakukan setelah FTP-nya konek

1. Upload semua isi folder `marajo/` (bukan foldernya, tapi ISI di dalamnya)
   langsung ke document root subdomain tsb via FTP
2. Import `schema.sql` ke database `u173485424_Order_Hoki` lewat phpMyAdmin
3. Tes buka `https://marajo.pos-hokidimsum.com/login.php` langsung, dan tes
   klik menu "🏢 PT Marajo Barokah" dari sidebar Executive di dashboard utama
   (muncul otomatis untuk akun `kurniarp` & `hanazaf`)
4. Upload juga file-file `.html` di root project yang sudah diupdate
   (sidebar-nya, 22 file) ke hosting utama pos-hokidimsum.com, supaya menu
   barunya muncul di sana

## Cara kerja login (SSO)

- Klik menu "PT Marajo Barokah" di sidebar Executive dashboard utama →
  otomatis terbawa ke `marajo.pos-hokidimsum.com/sso.php` dengan token sesi
  yang sedang aktif → diverifikasi ke database Hoki Dimsum (`users` table) →
  kalau valid & username-nya `kurniarp`/`hanazaf`, langsung masuk ke
  dashboard Marajo tanpa login ulang.
- Kalau buka `marajo.pos-hokidimsum.com` langsung (tanpa lewat dashboard
  utama), ada halaman `login.php` sebagai jalan masuk manual — pakai
  username & password yang sama dengan akun Hoki POS kamu.
- Hanya `kurniarp` dan `hanazaf` yang bisa masuk — diatur di
  `marajo/includes/config.php` (konstanta `ALLOWED_USERS`).

## Testing lokal (XAMPP)

Sudah saya siapkan database `marajo_dev` di MySQL lokal kamu (schema sudah
diimport, datanya kosong/bersih). Untuk coba-coba di lokal:

- Buka `http://localhost/hoki-dimsum-website-main/marajo/login.php`
- Login pakai akun `kurniarp` (password sama dengan yang dipakai di Hoki POS
  lokal kamu)

## Catatan / asumsi yang saya ambil (boleh diubah kalau kurang pas)

- **"Harga Barang"** di form Input Barang = total harga beli untuk satu batch
  restock (bukan harga satuan) — mengikuti data & Harga Modal/Jual Satuan yang
  memang sudah dipisah per satuan.
- **Dropdown Nama Barang** dibuat sebagai input dengan autocomplete
  (menyarankan nama yang sudah pernah diinput), tapi tetap bisa ketik nama
  baru untuk barang baru.
- PDF tidak menggambar tombol input eksplisit untuk "Keluar" (barang
  terjual) — saya taruh sebagai aksi **"Catat Stock Keluar / Penjualan"** di
  dalam modal Riwayat Stock (halaman Stock Barang), karena itu yang menjadi
  sumber data History Penjualan & pengurang Stock Qty.
- **Panel Estimasi Saldo/Profit Keseluruhan** = saldo/profit yang sudah
  terealisasi + estimasi kalau seluruh sisa stock terjual sesuai harga
  jual/modal terakhir barang tsb.
