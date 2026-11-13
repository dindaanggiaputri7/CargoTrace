# CargoTrace — Layer 2 Update (v2.1.0)

Paket ini mempertahankan project Laravel CargoTrace yang sudah ada dan menambahkan modul **Customer & Booking Management**. Paket ini tidak mengganti identitas visual CargoTrace maupun menghapus modul Layer 1.

## Isi Layer 2

1. **Customer Booking** — Customer membuat permintaan pengiriman dari website.
2. **Booking Review** — Operator memeriksa permintaan, meminta revisi, menyetujui, atau menolak.
3. **Quotation Management** — Operator menentukan carrier, biaya, jadwal, masa berlaku, dan ketentuan penawaran.
4. **Customer Quotation Approval** — Customer menerima atau menolak penawaran.
5. **Payment Record & Demo Checkout** — Sistem menyimpan payment intent dan status transaksi.
6. **Booking-to-Shipment Conversion** — Operator mengonversi booking yang telah dibayar menjadi shipment resmi.
7. **Booking History & Notifications** — Riwayat perubahan booking dan notifikasi in-app; email bersifat opsional.

## Penting: pembayaran

Checkout yang disertakan dalam paket ini adalah **simulasi lokal (DEMO)** agar alur Layer 2 bisa diuji tanpa mengirim atau menagihkan uang sungguhan. Endpoint konfirmasi demo hanya aktif pada environment `local` atau `testing` dan menampilkan peringatan bahwa transaksi tidak nyata.

Belum ada provider pembayaran sungguhan yang dihubungkan. Untuk transaksi riil, pilih penyedia pembayaran, buat akun sandbox/merchant, lalu implementasikan adapter dan verifikasi callback/webhook berdasarkan dokumentasi resmi provider tersebut. Jangan mengubah status pembayaran menjadi `PAID` hanya dari frontend.

## Cara memasang ke project yang sudah ada

1. Buat salinan cadangan folder project CargoTrace dan database lokal.
2. Ekstrak folder `cargotrace` dari ZIP ke folder induk project, misalnya `D:\laragon\www`. Gabungkan ke folder `D:\laragon\www\cargotrace` dan izinkan file sumber yang sama diperbarui.
3. **Jangan hapus atau mengganti `.env`, `.git`, atau `vendor` milik project lokal.** Ketiganya sengaja tidak disertakan dalam ZIP.
4. Buka terminal di root project `D:\laragon\www\cargotrace`.
5. Jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

6. Pastikan `.env` lokal menggunakan `APP_ENV=local`. Untuk mengaktifkan simulasi checkout lokal, tambahkan:

```dotenv
CARGOTRACE_ALLOW_DEMO_PAYMENTS=true
CARGOTRACE_EMAIL_NOTIFICATIONS=false
```

7. Jalankan aplikasi:

```bash
php artisan serve
```

8. Buka `http://127.0.0.1:8000`, login sebagai Customer untuk membuat request, lalu login sebagai Operator untuk memprosesnya.

**Jangan menjalankan `php artisan migrate:fresh` pada database project yang sudah berisi data.** Migration Layer 2 bersifat additive dan menambahkan tabel booking tanpa menghapus tabel Layer 1.

## Alur uji manual Layer 2

1. Customer login dan buka **Request Shipment**.
2. Isi origin, destination, cargo, berat, paket, item, dan jadwal yang diinginkan. Submit akan membuat nomor `BR-...` dengan status `PENDING_REVIEW`.
3. Operator buka **Booking Requests**, lalu pilih **Review request**.
4. Operator dapat meminta revisi, menolak, atau menyetujui. Jika meminta revisi, Customer dapat mengubah form dan mengirim ulang.
5. Setelah disetujui, Operator pilih **Prepare quotation**, memilih carrier aktif, mengisi biaya, mata uang, jadwal, dan masa berlaku.
6. Customer buka **My Requests**, tinjau penawaran, lalu accept atau decline.
7. Jika diterima, Customer pilih **Continue to payment**. Dalam local mode, halaman checkout akan menyebutkan secara jelas bahwa ini DEMO dan tidak ada uang sungguhan yang dibebankan. Tekan **Confirm demo payment** untuk menguji alur.
8. Operator melihat booking berstatus **READY_FOR_CONVERSION**, lalu pilih **Create shipment**.
9. Sistem membuat satu shipment bernomor `CT-...`, membuat tracking event awal `CREATED`, menghubungkannya ke request, dan menampilkan shipment tersebut pada **My Shipments** Customer.

## Status utama

- Booking: `PENDING_REVIEW`, `NEEDS_REVISION`, `APPROVED`, `QUOTED`, `QUOTE_ACCEPTED`, `QUOTE_DECLINED`, `PENDING_PAYMENT`, `READY_FOR_CONVERSION`, `REJECTED`, `CANCELLED`, `CONVERTED`.
- Quotation: `SENT`, `ACCEPTED`, `DECLINED`, `EXPIRED`, `SUPERSEDED`.
- Payment: `PENDING`, `PAID` (demo only), `FAILED`, `EXPIRED`, `REFUNDED` (status lain disiapkan untuk integrasi provider berikutnya).
- Shipment tetap menggunakan lifecycle Layer 1: `CREATED`, `BOOKED`, `PICKED_UP`, `IN_TRANSIT`, `DELIVERED`.

## Database baru

Migration `2026_11_12_090001_create_booking_management_tables.php` menambahkan:

- `booking_requests`
- `booking_request_items`
- `booking_request_histories`
- `quotations`
- `booking_payments`

Tabel Layer 1 yang ada tetap dipakai. Konversi booking ke shipment menggunakan database transaction dan pemeriksaan status agar request yang sama tidak menghasilkan shipment duplikat.

## Pengujian

Jalankan pada environment development yang memiliki ekstensi PHP dan driver database yang dibutuhkan Laravel:

```bash
php artisan test --filter=BookingWorkflowTest
php artisan test
```

Paket ini menambahkan feature tests untuk submit booking, pemisahan akses customer, approval/quotation/payment demo, konversi ke shipment, dan perlindungan dari konversi duplikat.

## Batasan yang diketahui

- Payment gateway sungguhan belum dihubungkan; checkout saat ini adalah simulasi lokal yang diberi label DEMO.
- Email notification menggunakan Laravel Mail dan dinonaktifkan secara default. Untuk mengirim email sungguhan, konfigurasi SMTP/mail provider di `.env`, uji koneksi, lalu aktifkan `CARGOTRACE_EMAIL_NOTIFICATIONS=true`.
- Booking request belum memilih kapal/voyage. Penugasan transport adalah Layer 3 dan belum termasuk paket ini.
- Tracking Layer 1 tetap menggunakan event manual dan peta dari koordinat yang tersimpan; tidak ada GPS/AIS real-time pada paket Layer 2.

## Catatan keamanan

- Jangan pernah mengunggah `.env`, token, password, atau API secret ke repository publik.
- Jangan mengaktifkan simulasi pembayaran untuk production.
- Jangan menganggap status pembayaran sukses hanya berdasarkan perubahan UI; status harus diverifikasi backend/provider.

## Status pemeriksaan paket

Pemeriksaan statis pada proses penyusunan paket: sintaks seluruh file PHP (`php -l`), sintaks JavaScript (`node --check`), JSON Postman, dan registrasi route Laravel telah diperiksa. Suite PHPUnit dan eksekusi migration belum dapat dijalankan pada lingkungan penyusunan paket karena ekstensi PHP `dom`, `mbstring`, `xmlwriter` serta driver PDO SQLite tidak tersedia di lingkungan tersebut. Karena itu, jalankan migration dan test pada instalasi Laragon lokal sebelum menganggap Layer 2 siap dipakai.
