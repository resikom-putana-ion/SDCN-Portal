# Pemulihan akun melalui Gmail

Halaman `/forgot-password` mengirim tautan untuk membuat kata sandi baru. Berlaku untuk akun Owner, Admin, Keuangan, dan Akademik yang aktif. Ini adalah alur reset kata sandi; setelah sandi diperbarui, pengguna masuk melalui `/login`.

## Hubungkan email pengirim (satu kali)

1. Masuk ke akun Gmail/Google Workspace yang akan menjadi pengirim email sekolah.
2. Aktifkan **Verifikasi 2 Langkah**, lalu buat **App Password** dengan nama `Dashboard SDCN` di https://myaccount.google.com/apppasswords. Petunjuk resmi: https://support.google.com/accounts/answer/185833?hl=id. Jika menu tidak tersedia pada Workspace, minta administrator Google Workspace mengizinkan metode ini atau gunakan akun pengirim lain yang mendukungnya.
3. Klik dua kali **setup-email.bat** di folder proyek. Masukkan alamat Gmail dan App Password 16 karakter. Input password disembunyikan. Spasi pada App Password boleh disertakan. Jangan masukkan kata sandi login Gmail dan jangan mengirimkan App Password ke chat.
4. Skrip menyimpan konfigurasi di `.env`, membersihkan cache konfigurasi, dan mengirim **email uji ke alamat pengirim sendiri**. Periksa Inbox/Spam. Pesan sukses terminal berarti server Gmail menerima email untuk dikirim; penerimaan di kotak masuk tetap perlu diperiksa.
5. Hentikan server yang sedang berjalan, lalu jalankan kembali **run.bat**. Buka http://127.0.0.1:8017/forgot-password dan masukkan email **akun dashboard yang terdaftar dan aktif**, bukan sembarang alamat. Email penerima boleh berbeda dari email pengirim Gmail.
6. Buka email, klik **Buat kata sandi baru**, simpan, lalu login menggunakan kata sandi baru.

Alternatif dari PowerShell di folder proyek:

```powershell
& 'C:\xampp\php\php.exe' artisan school:setup-gmail
```

## Konfigurasi manual

Isi nilai berikut di `.env` (contoh alamat dan password harus diganti):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_URL=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=alamat-pengirim@gmail.com
MAIL_PASSWORD=APP_PASSWORD_16_KARAKTER
MAIL_FROM_ADDRESS=alamat-pengirim@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

Gunakan alamat pengirim yang sama dengan akun Gmail untuk autentikasi SMTP. Jalankan `php artisan config:clear` setelah mengubah `.env`, lalu mulai ulang server. Kredensial `.env` tidak boleh dimasukkan ke Git.

`APP_URL` menentukan alamat pada tautan email. Selama nilainya `http://127.0.0.1:8017`, tautan hanya dapat dibuka pada komputer yang menjalankan website. Membuka tautan dari ponsel tidak akan mengarah ke komputer tersebut. Setelah deploy, isi `APP_URL` dengan domain HTTPS dashboard yang bisa diakses pengguna.

## Perilaku dan pemecahan masalah

- Tautan kedaluwarsa setelah 60 menit dan tidak dapat digunakan kembali setelah berhasil. Meminta tautan baru setelah masa tunggu menggantikan tautan sebelumnya.
- Pengiriman ke akun yang sama dibatasi satu menit, ditambah pembatasan permintaan pada formulir. Akun tidak terdaftar/nonaktif mendapatkan pesan umum yang sama untuk menjaga kerahasiaan daftar akun.
- Jika SMTP belum dikonfigurasi atau pengiriman gagal, formulir menampilkan pesan gagal. `MAIL_MAILER=log` tidak mengirim email dan tidak lagi dianggap sebagai keberhasilan pemulihan.
- Jika login Gmail ditolak, periksa App Password, status Verifikasi 2 Langkah, dan kebijakan Workspace. Mengganti kata sandi akun Google dapat mencabut App Password; buat ulang bila perlu.
- Jika koneksi gagal, periksa internet/firewall port 465 dan konfigurasi sertifikat CA PHP. Jangan menonaktifkan verifikasi TLS.
- Jika formulir berhasil tetapi email belum masuk, periksa Spam dan gunakan email terakhir. Pengujian otomatis menggunakan transport email memori dan tidak mengirim email sungguhan.

Belum ada email eksternal yang dikirim sampai kredensial pengirim diisi dan pengiriman diuji melalui Gmail.
