# CargoTrace Frontend Final v1.0 — Panduan pemasangan

Paket ini adalah **overlay frontend** untuk project Laravel CargoTrace yang sudah kamu jalankan. Ini bukan project Laravel baru dan tidak berisi database, `.env`, `.git`, `vendor`, atau `node_modules`.

Frontend dibuat berdasarkan `Untitled (2).pdf` (18 halaman):
- Halaman 1–6: Administrator — dashboard global, Users, Carriers, Locations, All Shipments, Audit Logs.
- Halaman 7–12: Logistics Operator — dashboard operasional, Shipments, Create/Edit Shipment, detail shipment, Tracking, Exceptions.
- Halaman 13–17: Customer — dashboard pribadi, My Shipments, detail tracking, Notifications, Profile.
- Halaman 18: design system, warna, status, tabel, peta, dialog, dan system states.

Design tokens utama:
- Deep Navy: `#142D4E`
- Royal Blue: `#2563EB`
- Cloud White: `#F8FAFC`
- Font: Inter

## Cara memasang ke project yang sudah ada

1. Pastikan folder project tetap `D:\laragon\www\cargotrace`.
2. Ekstrak ZIP ini.
3. Buka folder `CargoTrace_Frontend_Final_Pack` hasil ekstrak.
4. Pilih seluruh isi folder tersebut (`Ctrl + A`, `Ctrl + C`), lalu tempel ke `D:\laragon\www\cargotrace` (`Ctrl + V`).
5. Saat Windows bertanya, izinkan file yang disertakan untuk digabung/diperbarui.

File yang ditambahkan/diperbarui:
- `routes/web.php` — mengganti halaman welcome Laravel dengan shell CargoTrace.
- `routes/api.php` — mempertahankan route API backend v2.0 dan menambahkan `GET /api/customers` yang read-only.
- `app/Http/Controllers/Api/CustomerDirectoryController.php` — endpoint daftar customer aktif untuk dropdown Create Shipment.
- `resources/views/cargotrace/app.blade.php` — halaman Blade utama.
- `public/cargotrace/css/app.css` dan `public/cargotrace/js/app.js` — tampilan dan interaksi frontend.

**Jangan mengganti** `.env`, `.git`, `vendor`, `node_modules`, folder `database`, atau seluruh project Laravel.

## Menjalankan

1. Di terminal yang menjalankan `php artisan serve`, tekan `Ctrl + C` untuk menghentikan server.
2. Buka terminal VS Code pada folder `D:\laragon\www\cargotrace`.
3. Pastikan terminal menggunakan PHP 8.3.36 Laragon (pada terminal baru, jika masih perlu, jalankan perintah PATH Laragon yang sudah digunakan sebelumnya).
4. Jalankan:

```powershell
php artisan optimize:clear
php artisan route:list --path=api
php artisan serve
```

5. Buka `http://127.0.0.1:8000` dan refresh browser.
6. Login menggunakan akun demo berikut. Password seluruh akun demo: `CargoTraceDemo!2026`.

| Role | Email |
|---|---|
| Administrator | `admin@cargotrace.test` |
| Logistics Operator | `operator@cargotrace.test` |
| Customer / Aster Retail | `customer@cargotrace.test` |
| Customer / Northstar Labs | `northstar@cargotrace.test` |
| Customer / Vertex Motors | `vertex@cargotrace.test` |

Tidak perlu `npm install`, `npm run build`, migration, atau seeder tambahan untuk paket frontend ini. Tes fitur tambahan untuk endpoint direktori customer juga disertakan di `tests/Feature/CustomerDirectoryTest.php` (suite sekarang mencakup 24 tes jika seluruh backend tests lama tetap ada). Frontend memakai Blade dan JavaScript biasa serta mengambil data dari API yang telah ada.

## Endpoint tambahan yang disertakan

Operator perlu memilih customer saat membuat shipment. Endpoint `/api/users` memang khusus Administrator, jadi paket ini menambahkan endpoint terpisah:

- `GET /api/customers?per_page=100`
- Akses: Administrator dan Logistics Operator yang sudah login.
- Data: hanya akun CUSTOMER yang aktif (`id`, `name`, `email`, `role`, `is_active`).
- Read-only; endpoint ini tidak memberi Operator akses ke fitur manajemen user.

Endpoint ini tidak membutuhkan migration baru.

## Fitur yang sudah terhubung ke backend

- Login, logout, dan identifikasi role melalui Laravel Sanctum.
- Dashboard berbasis statistik dan daftar data dari API.
- Shipment directory dengan pencarian, filter status/carrier/delay, pagination, detail, dan lifecycle.
- Operator: create/edit shipment, update status berurutan, update ETA, append tracking event, create/manage exception.
- Administrator: user management, carrier directory, location directory, geocoding, daftar shipment read-only, audit log read-only.
- Customer: hanya shipment miliknya, timeline, peta dari koordinat tersimpan, exception yang terkait, notifikasi, dan profil read-only.
- Responsive layout untuk desktop, tablet, dan mobile.
- Loading/error/empty states dan toast feedback.

## Batasan yang sengaja dipertahankan

- Tidak ada carrier login, physical live GPS, integrasi langsung carrier/pelabuhan, pembayaran, atau AI/ML.
- Peta hanya memplot origin, destination dan checkpoint yang koordinatnya tersimpan. Peta tidak menunjukkan posisi kapal live.
- Profile disajikan read-only karena API backend saat ini belum menyediakan endpoint update profil/password/preferensi.
- Data organisasi perusahaan customer belum disimpan sebagai entitas terpisah oleh backend; UI memakai nama akun customer yang tersedia.
- Leaflet/OpenStreetMap tiles dan Google Fonts dimuat dari CDN. Bila internet/CDN tidak tersedia, data/timeline tetap dapat digunakan; peta menggunakan fallback atau tidak memuat tile.

## Jika halaman masih menampilkan Laravel welcome

- Pastikan `routes/web.php` di folder project sudah terganti dengan file dari paket ini.
- Jalankan `php artisan optimize:clear`.
- Restart `php artisan serve`, lalu refresh browser dengan `Ctrl + F5`.

## Setelah pemasangan

Tes login ketiga role, navigasi, buat shipment menggunakan Operator, lihat shipment sebagai Customer, dan pastikan Admin tidak melihat tombol aksi operasional. Backend tetap menjadi sumber otorisasi utama; pembatasan menu frontend bukan pengganti middleware API.
