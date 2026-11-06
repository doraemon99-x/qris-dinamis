# QRIS Dinamis & Digital Store Generator

Aplikasi web modern berbasis **PHP Native** dan **SQLite** untuk konversi QRIS Statis menjadi **QRIS Dinamis** (standar EMVCo / Bank Indonesia) secara otomatis, dilengkapi dengan **Katalog Toko Digital**, **Manajemen Stok & Produk**, **Konfirmasi Multi-Channel (WhatsApp & Telegram)**, dan **Dashboard Admin Terintegrasi**.

---

## 🌟 Fitur Unggulan

### 1. 🛍️ Katalog Toko Digital (`index.php`)
- **Tampilan Modern & Responsif**: Menggunakan Tailwind CSS Dark Mode, dirancang optimal untuk layar HP, tablet, maupun desktop.
- **Pencarian Produk Real-Time**: Temukan item secara instan tanpa reload halaman.
- **Manajemen Kuantitas & Stok**: Indikator stok tersedia dengan kalkulasi total harga otomatis.
- **Modal Checkout Instan**: Alur checkout cepat yang langsung menghasilkan tagihan QRIS dinamis.

### 2. ⚡ QRIS Dinamis Generator (`generator.php` & `includes/QrisHelper.php`)
- **Standar EMVCo / QRIS Nasional**: Sesuai dengan spesifikasi Bank Indonesia (PADG No. 21/18/PADG/2019) dengan kalkulasi **CRC16-CCITT** yang valid.
- **Tanpa Batas Minimum**: Mendukung nominal berapapun (> Rp 0).
- **Generator Khusus Admin**: Terproteksi sesi autentikasi untuk pembuatan QRIS dengan nominal kustom, catatan, dan biaya layanan (*convenience fee* fixed / percentage).
- **Kode Unik Acak**: Fitur penambahan kode unik (misal Rp 1 - Rp 499) untuk mempermudah pengecekan mutasi manual pada rekening/e-wallet merchant (DANA, Gopay, dsb).

### 3. 💳 Halaman Pembayaran Interaktif (`pay.php`)
- **Tampilan QR Interaktif**: QR Code dihasilkan secara client-side via `qrcode.min.js` dengan opsi unduh gambar QR atau salin string teks QRIS.
- **Hitung Mundur Kedaluwarsa**: Timer 15 menit dengan overlay otomatis jika sesi pembayaran habis.
- **Konfirmasi Pembayaran Multi-Channel**:
  - **WhatsApp**: Tautan langsung `wa.me/?text=...` dengan format pesan rincian pesanan yang sudah otomatis terisi.
  - **Telegram**: Tautan langsung `t.me/?text=...` dengan pesan konfirmasi otomatis yang langsung masuk ke chat admin/bot.
- **Zona Waktu WIB (`Asia/Jakarta`)**: Seluruh pencatatan waktu dan detail pesan konfirmasi akurat sesuai waktu lokal.

### 4. 🛠️ Dashboard Admin Lengkap (`admin.php`)
- **Manajemen Produk & Stok**: Tambah item, ubah harga, update stok produk, dan hapus item.
- **Pengurangan Stok Otomatis**: Stok produk berkurang secara otomatis ketika pesanan baru dibuat.
- **Ekstraksi QRIS Lokal (`jsqr.min.js`)**: Fitur unggah foto/screenshot QRIS statis yang didekode langsung di browser (offline / tanpa ketergantungan CDN luar), otomatis mengisi String QRIS Dasar, Nama Toko, Kota, dan NMID.
- **Navigasi Tab Cerdas**: Menggunakan pola *Post-Redirect-Get* dengan query URL (`?tab=items` & `?tab=settings`) sehingga posisi tab tidak berpindah saat melakukan update data.
- **Riwayat Transaksi**: Pantau transaksi masuk beserta statusnya (*pending* / *paid*).

### 5. 🔌 RESTful API Endpoint (`api.php`)
- Mendukung integrasi pihak ketiga untuk generate QRIS, cek riwayat, update status, dan katalog produk.
- Dukungan autentikasi opsional via **API Key** (Bearer Token / Request Parameter).

---

## 📁 Struktur Direktori

```text
qris_dinamis/
├── admin.php              # Dashboard pengelolaan admin (produk, transaksi, setting)
├── api.php                # REST API Endpoint QRIS & Toko
├── config.php             # Konfigurasi dasar, zona waktu, & default merchant
├── generator.php          # Halaman generator custom nominal (khusus admin)
├── index.php              # Halaman depan / katalog toko digital untuk publik
├── login.php              # Halaman masuk admin
├── logout.php             # Handler logout sesi admin
├── pay.php                # Halaman pembayaran QRIS pembeli
├── qr.php                 # Generator gambar QR Code server-side
├── shop.php               # Redirect shortcut ke index.php
├── jsqr.min.js            # Library lokal scanner QR code gambar (offline)
├── qrcode.min.js          # Library lokal render QR code DOM (offline)
├── data/
│   ├── .htaccess          # Proteksi folder data dari akses web langsung
│   └── database.sqlite    # Basis data SQLite lokal (auto-create)
└── includes/
    ├── auth.php           # Helper autentikasi sesi admin & proteksi CSRF
    ├── db.php             # Koneksi PDO SQLite, auto-migrasi skema, & settings helper
    ├── qrcode.php         # Library QR code backend helper
    └── QrisHelper.php     # Engine parser EMVCo TLV & konversi QRIS statis ke dinamis
```

---

## 🚀 Kebutuhan Sistem (Requirements)

- **PHP**: Versi **8.0** ke atas (Direkomendasikan PHP 8.1 / 8.2 / 8.3).
- **Ekstensi PHP**:
  - `pdo_sqlite` (Wajib untuk database)
  - `curl` (Opsional untuk integrasi webhook)
  - `json` & `mbstring`
- **Web Server**: Apache (XAMPP / Laragon), Nginx, atau PHP Built-in Server.

---

## 🛠️ Panduan Instalasi & Menjalankan

### Menggunakan Web Server Lokal (XAMPP / Laragon)

1. Letakkan folder project ini di direktori web root Anda:
   - **XAMPP**: `C:\xampp\htdocs\qr` (atau `qris_dinamis`)
   - **Laragon**: `C:\laragon\www\qr`
2. Pastikan ekstensi `pdo_sqlite` aktif di file `php.ini`.
3. Buka browser dan akses:
   - **Katalog Toko**: `http://localhost/qr/`
   - **Dashboard Admin**: `http://localhost/qr/admin.php`

### Menggunakan PHP Built-in Server

Jalankan perintah berikut pada terminal di dalam folder project:

```bash
php -S localhost:8080
```

Lalu buka di browser: `http://localhost:8080/`

---

## 🔐 Akun Default Admin

Saat database pertama kali diinisialisasi otomatis oleh sistem, kredensial default admin adalah:

| Parameter | Kredensial Default |
|-----------|--------------------|
| **URL Login** | `/login.php` atau `/admin.php` |
| **Username** | `admin` |
| **Password** | `admin123` |

> ⚠️ **PENTING**: Segera ganti kata sandi admin Anda setelah login pertama kali melalui tab **Pengaturan QRIS & Merchant** pada dashboard admin.

---

## ⚙️ Konfigurasi Awal QRIS Merchant

1. Login ke **Dashboard Admin** (`/admin.php`).
2. Masuk ke tab **Pengaturan QRIS & Merchant**.
3. Unggah foto / screenshot QRIS Statis toko Anda pada kotak **Unggah Foto QRIS Statis**.
4. Sistem akan otomatis mendeteksi kode QR, mengekstrak **String QRIS**, **Nama Merchant**, **Kota**, serta **NMID**, lalu mengisinya ke formulir.
5. Lengkapi nomor **WhatsApp Admin** (format internasional tanpa tanda `+`, contoh: `6281234567890`) dan username **Telegram Admin** (tanpa `@`).
6. Klik **Simpan Pengaturan**.

---

## 📡 Dokumentasi Singkat REST API (`api.php`)

Endpoint API dapat diakses melalui metode `GET` atau `POST` (JSON / Form Data).

### 1. Generate QRIS Dinamis
- **Endpoint**: `/api.php?action=generate`
- **Parameter**:
  - `amount` *(integer/float)*: Nominal pembayaran (wajib jika tanpa `item_id`).
  - `item_id` *(integer, opsional)*: ID produk dari katalog (stok akan otomatis berkurang).
  - `order_id` *(string, opsional)*: Nomor referensi kustom (otomatis dibuat jika kosong).
  - `note` *(string, opsional)*: Catatan pesanan / nama produk.
  - `customer` *(string, opsional)*: Nama pembeli.
  - `unique` *(boolean / 1, opsional)*: Tambahkan kode unik acak.
- **Contoh Response**:
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "order_id": "QRIS-261107-A1B2C",
      "merchant_name": "AXLINESID Shop",
      "merchant_city": "Kab. Rokan Hili",
      "total_amount": 28145,
      "formatted_total": "Rp 28.145",
      "qris_string": "0002010102122657...6304ABCD",
      "pay_url": "http://localhost/pay.php?amount=28145&order_id=QRIS-261107-A1B2C",
      "expired_at": "2026-11-07 12:45:00"
    }
  }
  ```

### 2. Daftar Produk Katalog
- **Endpoint**: `/api.php?action=items`
- **Metode**: `GET`

### 3. Update Status Transaksi
- **Endpoint**: `/api.php?action=update_status`
- **Parameter**: `order_id`, `status` (`paid` / `pending` / `cancelled`)

---

## 🛡️ Keamanan & Kinerja

- **Database SQLite Mode WAL**: Mengaktifkan *Write-Ahead Logging* (`PRAGMA journal_mode = WAL;`) untuk performa tinggi, konkurensi optimal, dan mencegah *database locked*.
- **Proteksi Direktori Basis Data**: Folder `data/` dilengkapi dengan `.htaccess` untuk mencegah pengunduhan file SQLite secara langsung dari web.
- **CSRF Token & Sesi Aman**: Form login dan aksi admin dilindungi token verifikasi anti pemalsuan permintaan antar-situs (*Cross-Site Request Forgery*).
- **Proses QR Lokal**: Pembacaan dan ekstraksi foto QRIS diproses sepenuhnya di browser klien (`jsqr.min.js`), menjaga kerahasiaan data tanpa upload ke server luar.

---

## 📄 Lisensi

Project ini dirancang untuk penggunaan personal maupun komersial dalam mendukung ekosistem pembayaran digital UMKM dan toko online.
