# SD Ceria Nusantara — Dashboard Admin

Implementasi Laravel dari paket desain A01–A08, W01–W05, dan Z01. Tata letak, warna, Poppins, navigasi, gambar kelas, profil, serta isi contoh mengikuti desain yang diberikan. Admin ditujukan untuk desktop; formulir PPDB publik mendukung ponsel.

## Jalankan di komputer ini

Dari `E:\Admin Dashboard SDCN`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\run.ps1
```

Skrip mencari PHP pada PATH atau `C:\xampp\php\php.exe`, menggunakan port **8017**, dan menyiapkan batas unggah 5 MB per berkas. Opsi `ExecutionPolicy` berlaku hanya pada proses ini. Hentikan dengan Ctrl+C. Port lain: tambahkan `-Port 8018`; PHP lain: tambahkan `-PhpPath C:\lokasi\php.exe`.

- Admin: http://127.0.0.1:8017/login
- Website publik: http://127.0.0.1:8017/website
- Formulir PPDB: http://127.0.0.1:8017/ppdb
- Akun demo: **admin@sdcerianusantara.sch.id**
- Kata sandi demo: **Ceria2026!**

Database lokal sudah berisi data contoh. Tidak perlu menjalankan pemasangan awal lagi pada workspace ini.

## Pemasangan awal pada komputer lain

Memerlukan PHP 8.2+, Composer, ekstensi Laravel standar, `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`, `fileinfo`, dan `zip`. Database bawaan SQLite. Node hanya dibutuhkan untuk pengujian browser; tampilan menggunakan Blade serta CSS/JavaScript lokal dan tidak memerlukan proses build frontend.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate
php artisan db:seed
php -d upload_max_filesize=5M -d post_max_size=20M artisan serve --host=127.0.0.1 --port=8017
```

Jangan menyalin `.env.example` di atas konfigurasi yang sudah ada. `db:seed` hanya diizinkan pada lingkungan local/testing dan tidak menimpa rekaman contoh yang sudah diedit. Untuk perubahan skema berikutnya cukup jalankan `php artisan migrate`.

Pada komputer ini Composer juga tersedia sebagai `php .tools/composer.phar`. Folder `.tools` tidak menjadi bagian distribusi proyek.

## Alur yang tersedia

- Autentikasi admin, keluar akun, reset kata sandi melalui Laravel, pengalihan bahasa navigasi, pencarian, dan tema.
- Daftar, detail, edit, pencarian, filter, ekspor CSV, dan dokumen privat siswa/guru/staf. Kartu identitas dapat dicetak.
- PPDB publik mengirim data anak/wali dan tiga berkas privat. Admin memeriksa pendaftar, menyelesaikan checklist dokumen asli serta pembayaran offline, lalu mengaktifkan siswa. Aktivasi berulang tidak membuat siswa atau pembayaran ganda.
- Tagihan menghitung sisa dari pembayaran terverifikasi. Pencatatan menolak referensi ganda dan kelebihan bayar. Verifikasi menyimpan catatan, petugas, dan waktu pemeriksaan. Kuitansi terverifikasi serta rapor dengan nilai berstatus Terbit dapat diunduh sebagai PDF.
- Kelas, kenaikan kelas/kelulusan, absensi, nilai, pengumuman, agenda, dan ekspor laporan dari data yang tersimpan.
- Editor website, media, pratinjau draf, checklist tinjauan, dan publikasi ke halaman publik lokal. Perubahan draf tidak muncul di halaman publik sebelum diterbitkan. Pengaturan kontak sekolah disalin ke draf website.
- Audit fitur mengikuti dokumen; usulan yang tertulis **Belum diputuskan** tetap ditampilkan dengan status tersebut.

Peta desain ke halaman tersedia di [docs/IMPLEMENTASI.md](docs/IMPLEMENTASI.md).

## Pengujian

```powershell
php artisan test --compact
```

Pengujian fitur menggunakan SQLite di memori, terpisah dari database demo. Meliputi akses admin, validasi dan aktivasi PPDB, penyimpanan berkas privat, saldo pembayaran, verifikasi berulang, filter/ekspor, draf/publikasi, serta unduhan PDF.

Untuk pemeriksaan browser, jalankan server lokal terlebih dahulu:

```powershell
npm ci
npx playwright install chromium
npm run test:browser
```

Skrip memakai Brave jika tersedia pada lokasi standar komputer ini; atau atur `$env:BROWSER_PATH` ke browser Chromium. `$env:APP_TEST_URL` dapat mengubah alamat server. Skrip mengharapkan data contoh awal dari seeder; gunakan database lokal khusus pengujian jika data sudah diubah. Skrip hanya membaca data serta menguji formulir tanpa menyimpan perubahan. Hasil dan screenshot disimpan di `storage/app/browser-check/`.

## Data contoh dan batas integrasi

Angka ringkasan seperti 360 siswa, 28 guru/staf, grafik kehadiran, jadwal/cuti contoh, serta ringkasan keuangan mengikuti data tampilan PDF. Tabel awal memuat sampel rekaman, bukan seluruh populasi tersebut. Beberapa angka ringkasan menambahkan rekaman baru di atas angka contoh; jangan gunakan ringkasan demo sebagai laporan operasional. Ekspor, saldo tagihan, dan PDF memakai rekaman database yang tersedia.

Pembayaran Virtual Account pada seeder adalah contoh tampilan. Payment gateway, WhatsApp, dan pengiriman email eksternal belum dikonfigurasi. `MAIL_MAILER=log` menulis pesan reset ke `storage/logs/laravel.log`; isi konfigurasi SMTP untuk pengiriman email sesungguhnya. Tidak ada pesan yang dikirim melalui WhatsApp dari aplikasi ini.

Bahasa Inggris tersedia untuk navigasi dan label umum; teks isi sekolah serta sebagian formulir tetap dalam bahasa Indonesia sesuai desain. Halaman pengguna menampilkan akun/role yang tersimpan; editor matriks izin dan portal peran lain belum tersedia. Modul penggajian, perpustakaan, sertifikat, integrasi eksternal, dan pengelolaan cuti penuh masih tercatat sebagai usulan audit.

Aplikasi berjalan lokal dan belum dideploy ke hosting. Untuk lingkungan produksi gunakan akun/data sebenarnya, konfigurasi database dan layanan email, `APP_ENV=production`, `APP_DEBUG=false`, serta document root ke `public/`. Jangan menjalankan seeder demo pada produksi.

## Struktur

- `app/Http/Controllers`: autentikasi, data admin, alur PPDB/pembayaran, dan CMS.
- `app/Support/School.php`: metadata halaman, saldo, konten, dan audit aktivitas.
- `resources/data/school.json`: menu, kolom tabel, dan definisi formulir.
- `resources/views`: halaman Blade dari desain.
- `public/css/app.css`, `public/js/app.js`, `public/assets`: gaya, interaksi, font dan gambar lokal.
- `database/seeders/demo.json`: isi contoh desain.
- `tests/Feature/SchoolWorkflowTest.php`, `tests/Browser/check.cjs`: pemeriksaan alur server dan browser.

Dokumen asli di Downloads tidak diubah. Ekstraksi PDF dan screenshot kerja berada di `docs/design/` dan tidak disertakan dalam Git.
