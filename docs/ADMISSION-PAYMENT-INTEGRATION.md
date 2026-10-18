# Kontrak pembayaran PPDB dan email hasil

Koleksi bersama: `applications/{id}`. Dashboard membaca langsung koleksi ini pada permintaan halaman berikutnya. Tidak perlu menyalin data ke `dashboard_records`.

```json
{
  "reference": "CN-2026-EXAMPLE",
  "payment_method": "transfer",
  "parent": { "parent_name": "Nama wali", "email": "wali@example.com" },
  "documents": {
    "payment_proof": {
      "path": "applications/CN-2026-EXAMPLE/uuid.pdf",
      "name": "Bukti transfer.pdf",
      "mime": "application/pdf",
      "size": 12345
    }
  }
}
```

- `payment_method`: wajib `offline` (bayar di sekolah) atau `transfer` (online/rekening). Untuk offline, jangan unggah `documents.payment_proof`.
- `documents.payment_proof`: wajib untuk transfer; PDF/JPG/PNG maksimal 5 MB. Gabungkan dengan dokumen akta/KK/foto yang sudah ada, jangan mengganti seluruh map dokumen.
- `parent.email`: alamat tujuan hasil administrasi.
- Metode tidak dipilih admin. Data lama tanpa metode ditampilkan sebagai belum tersedia; pembayaran/aktivasi harus menunggu data sumber dilengkapi. Unggahan bukti bukan konfirmasi dana telah diterima. Admin tetap mencocokkan dana masuk.
- Perubahan admin mempertahankan field pembayaran dan berkas dari landing. Jangan izinkan klien publik menulis `dashboard`, `admin_notes`, atau status keputusan.

## Penyimpanan file

`path` menunjuk berkas privat pada **FirestoreFileStorage**, bukan URL publik dan bukan otomatis objek Firebase Storage. Landing harus memakai implementasi penyimpanan yang sama dengan `app/Services/FirestoreFileStorage.php`. Metadata saja tidak cukup untuk mengunduh berkas.

Implementasi bersama menyimpan metadata di `uploaded_files/{sha256(path)}` dengan `path`, `content_type`, `size`, `chunk_count`, `created_at`. Isi dipotong per 524288 byte, disimpan dalam `uploaded_file_chunks/{sha256(path + ':' + index)}` dengan `file_id` (hash path), `index` (integer mulai 0), dan `data` (base64 potongan). Gunakan service yang sama untuk validasi dan penulisan atomik, jangan membuat URL unduhan publik. Jika landing menggunakan Firebase Storage bucket, integrasi pembacaan bucket perlu ditambahkan terlebih dahulu.

Admin mengunduh melalui `/admin/dokumen/{id-dokumen}` yang dilindungi login dan peran. ID dibuat dashboard dari ID aplikasi serta nama field; landing tidak perlu menyimpannya.

Endpoint lokal `POST /ppdb` juga menerima `payment_method` dan file multipart `payment_proof`. Landing terpisah dapat menulis kontrak Firestore di atas dengan validasi server yang setara.

## Keputusan dan email

Status bersama: `diterima` = lolos administrasi, `ditolak` = ditolak, `perlu_perbaikan` = minta perbaikan. Landing perlu mengenali status `perlu_perbaikan` yang terpisah dari penolakan. Lolos administrasi belum berarti siswa aktif.

Email dikirim setelah keputusan lolos/ditolak disimpan, memakai `parent.email`. Isi memuat nama, nomor pendaftaran, keputusan, catatan petugas, dan keterangan pesan otomatis. Keputusan tetap tersimpan jika SMTP gagal; admin mendapat pesan kegagalan dan bisa menyimpan ulang keputusan untuk mencoba kembali. Pengulangan berurutan dengan email/status/catatan yang sama tidak mengirim ulang setelah sukses. Pengiriman sinkron bukan antrean pengiriman dengan jaminan exactly-once; gangguan setelah SMTP menerima email tetapi sebelum pencatatan sukses dapat menyebabkan duplikasi saat dicoba lagi.

Konfigurasi server (jangan commit password):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=goreskom5@gmail.com
MAIL_PASSWORD=<app-password-di-server>
MAIL_FROM_ADDRESS=goreskom5@gmail.com
ADMISSION_MAIL_FROM_ADDRESS=goreskom5@gmail.com
ADMISSION_MAIL_ENABLED=true
```

Alamat Gmail adalah alamat pengirim biasa, bukan mailbox no-reply khusus. Template meminta penerima tidak membalas. Pengiriman belum aktif pada konfigurasi lokal karena SMTP belum siap; aktifkan setelah kredensial tersedia dan hapus cache konfigurasi dengan `php artisan config:clear`. Jangan menandai pengiriman berhasil menggunakan mailer `log`/`array` pada produksi.
