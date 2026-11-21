# Panduan CargoTrace — Paket Revisi Terintegrasi

## Ruang lingkup yang dipertahankan

Paket ini berangkat dari project CargoTrace yang diberikan dan mempertahankan modul yang tidak masuk daftar revisi: autentikasi dan role, dashboard, data carrier/lokasi, audit log, lifecycle shipment, tracking timeline, exception, notifikasi, REST API, Booking/Quotation, serta Transport Asset/Trip/Assignment/Position dan webhook provider. Revisi bersifat terarah, bukan penggantian project dari nol.

## Perubahan yang diterapkan

- **Customer Directory:** akun pada `users` dipisahkan dari data pelanggan pada `customers`. Registrasi Customer membuat keduanya tertaut. Operator dapat menambahkan pelanggan walk-in tanpa akun.
- **Shipment:** Operator memilih data pelanggan dari directory. Nomor Shipment dibuat otomatis bila tidak diberikan lewat API. `source_type` membedakan `MANUAL` dan `BOOKING`. Input PO/Customer Reference tidak ditampilkan pada form Create Shipment.
- **Booking Customer:** form menggunakan lokasi master dari Admin, tanggal keberangkatan harus di masa depan, tanggal tiba sesudah keberangkatan, dan cargo meminta jumlah paket, berat, volume serta item. Menu Customer menampilkan Requests dan Shipments bersama di My Shipments, tetapi record Booking dan Shipment tetap terpisah.
- **Quotation dan pembayaran:** alur booking tetap dipertahankan. Pembayaran hanya memakai Midtrans Snap. Tombol/endpoint pembayaran demo lokal dihapus. Status `PAID` hanya berasal dari notifikasi server-to-server dengan signature valid dan nominal yang cocok.
- **Expiry:** transaksi yang belum dibayar berakhir setelah 24 jam. Command `cargotrace:expire-booking-payments` dijalankan oleh Laravel Scheduler setiap menit. Transaksi `PAID` tidak akan di-expire.
- **ETA:** `expected_arrival_at` merupakan baseline asli. Operator memperbarui `current_eta` dari informasi terbaru carrier; sistem menghitung durasi dan severity keterlambatan terhadap baseline asli.
- **Locations:** Nominatim membantu saran alamat, kota/region, negara dan koordinat. UN/LOCODE ditawarkan sebagai saran dari direktori UNECE; Admin harus memeriksa dan mengonfirmasi kode. Jika layanan tidak tersedia atau tidak ada kecocokan, kode tidak dibuat secara otomatis.
- **Transport dan map:** Asset/Trip/Assignment/Position dipertahankan. Posisi manual/provider harus berasal dari laporan yang tercatat. Peta menggunakan koordinat asal, tujuan, dan checkpoint yang tersimpan; bukan GPS fisik real-time.

## Cara memasang pada Laragon

1. Backup folder `D:\laragon\www\cargotrace` dan database yang sekarang.
2. Extract folder `cargotrace` dari ZIP ke `D:\laragon\www`, lalu gabungkan/replace file sumber project lama. Jangan menghapus seluruh folder lama terlebih dahulu.
3. ZIP tidak menyertakan `.env`, `.git`, `vendor`, atau `node_modules`. Pertahankan `.env` dan `.git` lokal. Bila folder `vendor` sudah ada dan sesuai `composer.lock`, pertahankan; jika dependency berubah, jalankan `composer install` dari root project. Jalankan `npm install` bila ingin membangun ulang asset melalui Vite.
4. Pastikan `.env` lokal tetap memiliki `APP_KEY`, konfigurasi MySQL Laragon yang benar, serta `APP_URL` sesuai alamat lokal.
5. Dari terminal di root project jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

Jangan gunakan `php artisan migrate:fresh` atau `db:wipe` pada database yang berisi data lama.

6. Untuk pembayaran Sandbox, tambahkan kredensial merchant Sandbox milik sendiri ke `.env`:

```dotenv
MIDTRANS_SERVER_KEY=your-sandbox-server-key
MIDTRANS_CLIENT_KEY=your-sandbox-client-key
MIDTRANS_IS_PRODUCTION=false
CARGOTRACE_EMAIL_NOTIFICATIONS=false
```

Atur URL notifikasi pada dashboard Midtrans ke `https://alamat-domain-atau-tunnel/api/integrations/payments/midtrans/notification`. Tanpa `MIDTRANS_SERVER_KEY`, sistem sengaja menolak membuat transaksi. Jangan mengisi key palsu dan jangan menganggap redirect checkout sebagai bukti pembayaran.

7. Jalankan aplikasi:

```bash
php artisan serve
```

8. Buka terminal kedua pada root project untuk menjalankan scheduler lokal:

```bash
php artisan schedule:work
```

Scheduler inilah yang menjalankan expiry payment otomatis setiap menit. Pada server production, siapkan Laravel Scheduler sesuai instruksi deployment Laravel.

## Urutan demo final yang disarankan

1. Admin: cek Users, Carriers, Locations, UN/LOCODE/geocoding, Transport Assets dan Tracking Providers.
2. Customer: ajukan Request Shipment dengan Origin/Destination dari master lokasi, item, paket, berat, volume dan tanggal.
3. Operator: review request, minta revisi atau setujui, lalu buat Quotation.
4. Customer: terima/tolak Quotation; bila menerima, lanjutkan ke Midtrans Sandbox.
5. Operator: setelah webhook Midtrans memverifikasi pembayaran, konversi Booking menjadi Shipment.
6. Operator: pilih Transport Asset/Trip, assign Shipment, lalu masukkan tracking checkpoint terkonfirmasi dan update status berurutan.
7. Operator: masukkan ETA baru dari carrier. Sistem membandingkan ETA itu dengan `expected_arrival_at` yang asli. Buat dan tindak lanjuti Exception bila ada gangguan.
8. Customer: lihat status, ETA, timeline, map, exception dan notifikasi untuk Shipment miliknya.
9. Admin: periksa dashboard global dan Audit Logs. Admin hanya melihat portfolio shipment; operasi shipment tetap milik Operator.

## Pemeriksaan dan batasan verifikasi paket

Sintaks PHP, sintaks JavaScript, parsing JSON Postman, dan registrasi route Laravel diperiksa saat paket dibuat. PHPUnit dan migration tidak dapat dijalankan di lingkungan pembuat ZIP karena beberapa ekstensi PHP/driver database tidak tersedia. Jalankan `php artisan migrate` dan `php artisan test` pada Laragon setelah backup. Hasil dari pemeriksaan statis bukan klaim bahwa alur telah berhasil diuji end-to-end pada database lokal.
