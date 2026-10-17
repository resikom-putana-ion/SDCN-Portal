# Kontrak konten dashboard dan landing page

Perbaikan lokal 17 Oktober 2026. Dokumen `FIREBASE-CONNECTION-HANDOFF.md` menjelaskan kondisi sebelum perubahan ini. Tidak ada publikasi draf atau penggantian data produksi selama implementasi/pengujian.

## Penyebab ketidaksesuaian

Dashboard memakai `content/teachers.team` untuk enam kartu beranda. Landing `/guru-staf` sebelumnya hanya membaca 30 profil lokal dari `resources/content/staff-directory.php`. Data operasional `/admin/guru` adalah sumber lain lagi. Pratinjau lama dashboard meratakan setiap properti menjadi kolom, bukan merender desain landing. Gambar `/assets/team/` juga belum didukung dan URL aset relatif dibaca dari server dashboard yang berbeda.

## Kontrak setelah perbaikan

Kedua proyek memakai `content/teachers`:

| Field | Tampilan |
| --- | --- |
| `team[]` | Kartu guru pada beranda, tetap terpisah dari direktori |
| `people[]` | Direktori Guru & Staf: `name`, `role`, `group`, `description`, `portrait`, `photo` opsional |
| `subjects[]` | Kartu mata pelajaran: `title`, `by`, `text` |
| `structure.head`, `.branches[].lead`, `.branches[].teams[].members[]` | Indeks anggota dalam `people`; editor memilih nama anggota dan menyimpan angka |
| `directory_eyebrow`, `directory_title`, `directory_description` | Pengantar halaman Guru & Staf |
| `demo_profiles` | Pemberitahuan bahwa profil masih contoh; matikan setelah konten benar-benar diganti dan diverifikasi sekolah |
| `values[]` | Kartu nilai sekolah |

Field hero guru lama `eyebrow/title/description/image` dipertahankan untuk kompatibilitas, tetapi tidak ditawarkan di editor dashboard karena halaman baru tidak memakainya. Identitas kepala pada beranda (`home.principal_name`) tetap terpisah. Tidak ada penyalinan data pribadi pegawai operasional ke halaman publik.

`TeacherDirectory.php` dan `resources/content/staff-directory.php` tersedia di kedua repositori sebagai kontrak validasi/default yang sama. Tidak ada pembacaan folder proyek tetangga saat runtime. Pertahankan keduanya selaras ketika mengubah skema. Tanpa field baru di Firestore, direktori tetap menampilkan data bawaan; penyimpanan draf pertama menyertakan field baru dan publikasi menyimpannya ke dokumen bersama.

## Mengedit dan menjalankan

1. Jalankan kedua aplikasi. Default dashboard `http://127.0.0.1:8017`, landing `http://127.0.0.1:8000`.
2. Buka `/admin/website/teachers/edit` di dashboard. Buka kartu sesuai kelompok: ringkasan beranda, direktori, mata pelajaran, atau organisasi.
3. Simpan draf, lalu tinjau melalui `/admin/website/preview`. Halaman ini meninjau isi per kartu, bukan pratinjau desain landing yang identik.
4. Terbitkan melalui `/admin/publikasi`. Landing membaca hasil publikasi di Firestore dan cache konten landing dibatalkan.
5. Periksa `/guru-staf` dan beranda di landing. Sebelum publikasi, hanya isi lama yang terlihat di sana.

Perubahan nama/foto direktori muncul pada kartu dan bagan organisasi; foto hero mengikuti anggota `structure.head`, termasuk foto unggahan pribadi. `portrait` adalah posisi pada sprite potret bawaan, tidak diedit sebagai teks biasa. `photo` kosong berarti gunakan sprite. Gambar yang diizinkan: `/assets/design/`, `/assets/team/`, atau `/media/` dengan format JPG/JPEG/PNG/WebP. Aset bawaan pada dashboard ditampilkan melalui `LANDING_PAGE_URL`; unggahan `/media/` tersedia melalui penyimpanan Firestore bersama. Crop tetap menggunakan grid.

Daftar disimpan sebagai satu kesatuan, sehingga pemendekan tidak meninggalkan kartu bawaan di landing. Publikasi menolak perubahan bersamaan pada daftar yang sama agar nama, foto, dan jabatan tidak tercampur akibat pengurutan ulang. Field lain yang tidak berubah tetap dipertahankan.

## Deploy

Deploy perubahan kedua repositori bersama, termasuk kelas kontrak dan resource default. Set `LANDING_PAGE_URL` pada dashboard ke URL HTTPS landing sebenarnya. `LANDING_CACHE_PREFIX` harus sama dengan `cache.prefix` landing. Jangan memakai path folder Windows untuk menghubungkan dua aplikasi saat deploy.

Sesudah deploy, jalankan `php artisan view:clear` pada kedua aplikasi. Landing masih memakai cache konten maksimal 300 detik; publikasi dari dashboard membatalkan key `school-content-firestore` pada namespace yang dikonfigurasi. Jangan menyemai ulang koleksi atau menimpa konten yang sudah terbit.

## Verifikasi

- Dashboard: `php artisan test --compact` — termasuk `WebsiteDirectoryTest` dan `SharedWebsiteTest` untuk draf, publikasi, konflik daftar, tipe numerik/boolean, gambar, dan referensi organisasi.
- Landing: `php artisan test --compact` — termasuk `SharedTeacherContentTest` untuk konsumsi konten bersama, fallback dokumen lama, foto hero, teks ter-escape, daftar pendek, serta validasi bagan.
- Pemeriksaan browser editor dan tinjauan draf menggunakan data uji terisolasi; tidak menerbitkan atau mengubah profil asli.
