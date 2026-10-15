# Login dan pemulihan akun melalui Firebase Authentication

Firebase Authentication memverifikasi kata sandi dan mengirim email pemulihan. Pengguna tidak perlu membuat App Password Gmail atau menjalankan `setup-email.bat`. Aplikasi menggunakan Firebase Auth REST API di server; API key, service account, ID token, dan refresh token tidak dimasukkan ke halaman browser.

## Pemakaian

1. Buka `/forgot-password` dan masukkan email akun dashboard yang aktif.
2. Buka email dari Firebase, termasuk memeriksa folder Spam.
3. Klik tautan dan buat kata sandi baru di halaman pemulihan yang dihosting Firebase.
4. Kembali ke `/login` dashboard dan masuk dengan kata sandi baru.

Tautan pemulihan ditangani oleh Firebase, sehingga dapat dibuka di ponsel meskipun dashboard masih berjalan lokal. Untuk membuka dashboard lokal, tetap gunakan komputer yang menjalankan `http://127.0.0.1:8017`. Setelah deploy, buka domain dashboard sekolah.

Pengguna yang tidak terdaftar atau nonaktif mendapat pesan umum yang sama, tetapi aplikasi tidak mengirim email. Permintaan dibatasi satu kali per menit untuk setiap email dan tiga kali per menit untuk setiap IP. Firebase juga menerapkan kuota dan perlindungan penyalahgunaan sendiri.

## Menyiapkan proyek atau server lain

1. Di Firebase Console, pilih proyek sekolah yang sama dengan Firestore.
2. Aktifkan **Authentication → Sign-in method → Email/Password**. Email link/passwordless tidak diperlukan.
3. Pasang service account server seperti dijelaskan dalam `docs/FIREBASE.md`. Akun layanan memerlukan izin Firebase Authentication untuk membaca, membuat, mengubah, dan mengimpor pengguna, serta membaca konfigurasi proyek; izin Firestore saja tidak cukup.
4. Periksa tanpa mengubah akun:

```powershell
& 'C:\xampp\php\php.exe' artisan school:firebase-auth
```

5. Setelah hasil pemeriksaan sesuai, migrasikan dan aktifkan:

```powershell
& 'C:\xampp\php\php.exe' artisan school:firebase-auth --apply
```

Perintah mengambil Web API Key dari konfigurasi proyek, menulis `FIREBASE_API_KEY` dan `FIREBASE_AUTH_ENABLED=true` ke `.env`, lalu membersihkan cache konfigurasi. Jika server dikelola dengan environment variables, pasang nilai yang sama pada pengaturan server. Mulai ulang proses aplikasi setelah memperbarui konfigurasi pada lingkungan produksi.

Perintah memindahkan hash bcrypt yang ada tanpa meminta atau mengganti kata sandi pengguna. UID ditautkan pada `admins.firebase_uid`; nama, role, status, foto, dan ID profil sekolah dipertahankan. Hash kata sandi di Firestore dikosongkan setelah identitas di Firebase berhasil diverifikasi. Snapshot akun sebelum migrasi disimpan terenkripsi dalam `storage/app/private/auth-migration/`, memakai `APP_KEY` aplikasi. Jaga akses berkas dan APP_KEY tersebut.

Akun Firebase yang sudah ada dengan email/UID berbeda menyebabkan migrasi berhenti; akun tersebut tidak ditimpa atau otomatis mendapat role sekolah. Migrasi dapat dilanjutkan setelah kegagalan tanpa menimpa kata sandi pengguna yang sudah berhasil dipindahkan. Sesi lama perlu login ulang setelah migrasi.

## Akses dan konsistensi akun

- Owner membuat akun dari **Pengaturan → Pengguna & izin**. Role Owner, Admin, Keuangan, dan Akademik tetap ditentukan server melalui Firestore.
- Memiliki akun Firebase saja tidak cukup untuk masuk dashboard: profil sekolah harus terdaftar, UID/email cocok, dan akun aktif.
- Nonaktifkan/aktifkan akun dari dashboard agar status sekolah dan Firebase disinkronkan. Jika sinkronisasi gagal, halaman menampilkan pesan dan penyimpanan dapat diulang. Status nonaktif di Firestore langsung menolak akses dashboard.
- Perubahan kata sandi atau pencabutan sesi Firebase membatalkan sesi dan cookie “Ingat saya” pada pemeriksaan berikutnya. Firebase yang tidak dapat dihubungi menyebabkan akses ditolak sementara, tanpa kembali memeriksa kata sandi lama.
- Jangan memindahkan email atau menghapus pengguna langsung dari Firebase Console tanpa menyesuaikan profil sekolahnya; ketidakcocokan identitas akan ditolak.

## Pengujian

```powershell
& 'C:\xampp\php\php.exe' artisan test --compact --filter=FirebaseAuthenticationTest
```

Pengujian otomatis memakai Firestore memori dan respons HTTP Firebase tiruan, tanpa mengubah akun asli atau mengirim email. Pemeriksaan integrasi langsung menggunakan akun sementara dapat menguji login, impor bcrypt, dan kode reset tanpa mengirim email dengan `returnOobLink=true`; penerimaan email nyata tetap perlu diperiksa dari formulir menggunakan akun milik penguji.

Implementasi SMTP lama masih tersedia untuk instalasi yang belum dimigrasikan. Setelah Firebase Authentication aktif, `setup-email.bat` hanya memberi informasi bahwa konfigurasi Gmail tidak diperlukan. Menonaktifkan flag tidak mengembalikan autentikasi lokal bagi akun yang sudah mempunyai `firebase_uid`.

Referensi resmi:

- https://firebase.google.com/docs/reference/rest/auth
- https://firebase.google.com/docs/auth/limits
- https://docs.cloud.google.com/identity-platform/docs/reference/rest/v1/projects.accounts/batchCreate
