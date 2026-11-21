# CargoTrace — Paket Revisi Terintegrasi (Layer 1, 2, dan 3)

Paket ini mempertahankan project Laravel CargoTrace yang sudah ada dan mengintegrasikan revisi pada Customer Directory, Booking, Payment, Shipment, Tracking, ETA, Exceptions, Locations, serta Transport. Struktur Laravel, tampilan utama, dan modul lain yang tidak termasuk revisi tetap dipertahankan.

## Isi paket revisi

1. **Customer Booking** — Customer membuat permintaan pengiriman dari website.
2. **Booking Review** — Operator memeriksa permintaan, meminta revisi, menyetujui, atau menolak.
3. **Quotation Management** — Operator menentukan carrier, biaya, jadwal, masa berlaku, dan ketentuan penawaran.
4. **Customer Quotation Approval** — Customer menerima atau menolak penawaran.
5. **Midtrans Checkout** — Customer membayar melalui Midtrans Snap Sandbox/Production; status PAID hanya ditetapkan oleh webhook terverifikasi.
6. **Booking-to-Shipment Conversion** — Operator mengonversi booking yang telah dibayar menjadi shipment resmi.
7. **Booking History & Notifications** — Riwayat perubahan booking dan notifikasi in-app; email bersifat opsional.

## Penting: pembayaran

Pembayaran tidak lagi menggunakan tombol konfirmasi simulasi. Customer hanya dapat memulai checkout melalui Midtrans Snap (gunakan Sandbox untuk demo). Status `PAID` hanya ditetapkan setelah notifikasi server-to-server Midtrans lolos verifikasi signature dan nominal. Tanpa `MIDTRANS_SERVER_KEY`, endpoint menolak membuat payment intent dengan HTTP 503. Tidak ada fallback mock dan Operator tidak dapat menandai transaksi sebagai lunas secara manual.

Transaksi yang belum dibayar berlaku 24 jam. Scheduler menandai transaksi kedaluwarsa dan mengembalikan Booking Request ke `QUOTE_ACCEPTED` bila tidak ada pembayaran lain yang berhasil atau masih aktif. Notifikasi webhook yang datang terlambat diproses secara terkunci; notifikasi `PENDING` yang terlambat tidak dapat menghidupkan kembali transaksi `EXPIRED` atau `FAILED`.

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

6. Pastikan `.env` lokal memiliki konfigurasi database dan `APP_KEY`. Untuk memakai Midtrans Sandbox, tambahkan kredensial Sandbox berikut:

```dotenv
MIDTRANS_SERVER_KEY=your-sandbox-server-key
MIDTRANS_CLIENT_KEY=your-sandbox-client-key
MIDTRANS_IS_PRODUCTION=false
CARGOTRACE_EMAIL_NOTIFICATIONS=false
```

Jangan pernah memasukkan kredensial ke GitHub atau ZIP yang dibagikan.

7. Jalankan aplikasi:

```bash
php artisan serve
```

8. Buka `http://127.0.0.1:8000`, login sebagai Customer untuk membuat request, lalu login sebagai Operator untuk memprosesnya.

**Jangan menjalankan `php artisan migrate:fresh` pada database project yang sudah berisi data.** Migration baru bersifat additive: menambahkan tabel `customers`, menghubungkan record customer lama, serta menambah relasi/source type tanpa menghapus tabel shipment, booking, atau transport yang ada.

## Alur uji manual

1. Customer login dan buka **Request Shipment**.
2. Isi origin, destination, cargo, berat, paket, item, dan jadwal yang diinginkan. Submit akan membuat nomor `BR-...` dengan status `PENDING_REVIEW`.
3. Operator buka **Booking Requests**, lalu pilih **Review request**.
4. Operator dapat meminta revisi, menolak, atau menyetujui. Jika meminta revisi, Customer dapat mengubah form dan mengirim ulang.
5. Setelah disetujui, Operator pilih **Prepare quotation**, memilih carrier aktif, mengisi biaya, mata uang, jadwal, dan masa berlaku.
6. Customer buka **My Shipments** lalu tab **Requests**, tinjau penawaran, kemudian accept atau decline.
7. Jika diterima, Customer memilih **Continue to payment** dan diarahkan ke Midtrans Sandbox. Status pembayaran berubah menjadi `PAID` hanya setelah webhook resmi tervalidasi. Browser redirect saja bukan bukti pembayaran.
8. Operator melihat booking berstatus **READY_FOR_CONVERSION**, lalu pilih **Create shipment**.
9. Sistem membuat satu shipment bernomor `CT-...`, membuat tracking event awal `CREATED`, menghubungkannya ke request, dan menampilkan shipment tersebut pada **My Shipments** Customer.

## Status utama

- Booking: `PENDING_REVIEW`, `NEEDS_REVISION`, `APPROVED`, `QUOTED`, `QUOTE_ACCEPTED`, `QUOTE_DECLINED`, `PENDING_PAYMENT`, `READY_FOR_CONVERSION`, `REJECTED`, `CANCELLED`, `CONVERTED`.
- Quotation: `SENT`, `ACCEPTED`, `DECLINED`, `EXPIRED`, `SUPERSEDED`.
- Payment: `PENDING`, `PAID` (hanya webhook Midtrans terverifikasi), `FAILED`, `EXPIRED`.
- Shipment tetap menggunakan lifecycle Layer 1: `CREATED`, `BOOKED`, `PICKED_UP`, `IN_TRANSIT`, `DELIVERED`.

## Database dan migrasi

Migration `2026_11_12_090001_create_booking_management_tables.php` menambahkan:

- `booking_requests`
- `booking_request_items`
- `booking_request_histories`
- `quotations`
- `booking_payments`

Tabel Layer 1 yang ada tetap dipakai. Konversi booking ke shipment menggunakan database transaction dan pemeriksaan status agar request yang sama tidak menghasilkan shipment duplikat.

## Customer directory, manual shipments, dan penjadwalan

Tabel `customers` menyimpan data pelanggan secara terpisah dari akun login `users`. Customer yang mendaftar memiliki record pelanggan tertaut; Operator juga dapat menambahkan pelanggan walk-in tanpa akun. `shipments.customer_record_id` menunjuk ke record pelanggan, sedangkan `shipments.customer_id` tetap menyimpan identitas login opsional untuk pembatasan akses dan notifikasi. `source_type` membedakan `MANUAL` dan `BOOKING`. Menu Customer **My Shipments** menggabungkan tampilan Booking Request dan Shipment dalam tab All, Requests, Active Shipments, dan Delivered; tabel data tetap terpisah.

Untuk menjalankan expiry otomatis pada komputer lokal, buka terminal kedua di root project dan jalankan `php artisan schedule:work`. Server production perlu menjalankan Laravel Scheduler setiap menit.

## Pengujian

Jalankan pada environment development yang memiliki ekstensi PHP dan driver database yang dibutuhkan Laravel:

```bash
php artisan test --filter=BookingWorkflowTest
php artisan test
```

Paket ini menambahkan/menyesuaikan feature tests untuk submit booking, pemisahan akses customer, approval/quotation, verifikasi notifikasi Midtrans, penolakan pembayaran tanpa gateway, konversi ke shipment, dan perlindungan dari konversi duplikat.

## Batasan yang diketahui

- Midtrans memerlukan kredensial Sandbox dan URL webhook yang benar; lingkungan tanpa kredensial tidak dapat menguji pembayaran end-to-end.
- Email notification menggunakan Laravel Mail dan dinonaktifkan secara default. Untuk mengirim email sungguhan, konfigurasi SMTP/mail provider di `.env`, uji koneksi, lalu aktifkan `CARGOTRACE_EMAIL_NOTIFICATIONS=true`.
- Booking request dan alokasi Transport Asset/Trip merupakan proses terpisah. Operator menghubungkan shipment ke trip setelah shipment dikonversi.
- Tracking menggunakan event manual/terkonfirmasi dan peta dari koordinat yang tersimpan. Asset, trip, assignment, posisi manual/provider, serta integrasi webhook tetap tersedia sebagai Layer 3; tidak ada klaim GPS fisik real-time bawaan.

## Catatan keamanan

- Jangan pernah mengunggah `.env`, token, password, atau API secret ke repository publik.
- Gunakan kredensial Sandbox untuk demo akademik dan jangan memasukkan secret ke GitHub.
- Jangan menganggap status pembayaran sukses hanya berdasarkan perubahan UI; status harus diverifikasi backend/provider.

## Status pemeriksaan paket

Pemeriksaan statis pada proses penyusunan paket: sintaks seluruh file PHP (`php -l`), sintaks JavaScript (`node --check`), JSON Postman, dan registrasi route Laravel diperiksa. Suite PHPUnit dan eksekusi migration belum dapat dijalankan pada lingkungan penyusunan paket karena ekstensi PHP `dom`, `mbstring`, `xmlwriter` serta driver PDO SQLite tidak tersedia. Jalankan migration dan test pada instalasi Laragon lokal sebelum dipakai untuk demo final.


## Pembayaran Midtrans dan kedaluwarsa otomatis

Tambahkan `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` Sandbox pada `.env`, lalu atur notification URL Midtrans ke `/api/integrations/payments/midtrans/notification`. Status pembayaran hanya menjadi `PAID` setelah webhook Midtrans tervalidasi. Transaksi yang belum dibayar kedaluwarsa setelah 24 jam. Jalankan scheduler lokal dengan `php artisan schedule:work` pada terminal terpisah. Tanpa kredensial Midtrans, sistem menolak membuat transaksi; tidak ada tombol simulasi sukses.
