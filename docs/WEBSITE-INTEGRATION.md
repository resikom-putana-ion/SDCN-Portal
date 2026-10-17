# Editor visual, PPDB, dan integrasi landing page

Pembaruan lokal 17 Oktober 2026. Dashboard menggunakan branch `main2-juan`. Acuan landing page adalah [SD-Ceria-Nusantara---Landing-Page](https://github.com/dovahkiinn-5/SD-Ceria-Nusantara---Landing-Page), revisi `cb613dc` beserta perbaikan kontrak konten pada sesi ini.

## Mengelola website

1. Buka **Website sekolah → Ringkasan website** (`/admin/website`). Kartu halaman menggantikan tabel field panjang.
2. Pilih **Edit halaman**. Susunan bagian ada di kiri, pratinjau landing page di tengah, dan isian bagian terpilih di kanan. Klik **Edit bagian ini** pada halaman atau pilih bagian dari daftar kiri.
3. Ubah teks atau foto. Pratinjau diperbarui dari isian yang belum disimpan; tombol **Perbarui pratinjau** tersedia untuk pemeriksaan manual. Gambar memakai crop grid yang sama dengan dashboard.
4. Pilih **Desktop**, **Tablet**, atau **Ponsel** untuk memeriksa responsivitas. **Pratinjau penuh** membuka area peninjauan yang lebih besar.
5. **Simpan draf**, lalu buka **Tinjau & terbitkan**. Konten publik baru berubah setelah checklist dan publikasi selesai. Tombol tambah, hapus, naik, dan turun menyimpan susunan item ke draf.

Pratinjau memakai template, CSS, dan aset landing page dalam iframe terpisah. Endpoint berada di balik autentikasi serta akses pengelola website. Formulir pendaftaran dan kunjungan dalam pratinjau tidak mengirim data. Preview isian tidak membuat dokumen draf atau menyimpan file ke Firestore.

## Guru, staf, dan pembagian tim

Pada editor **Guru & Staf → Direktori Guru & Staf**, cari nama/peran atau pilih tim, misalnya Wali Kelas, Guru Bidang Studi, Administrasi & Data, atau Lingkungan Aman & Nyaman. Pengelompokan mengikuti struktur organisasi, bukan urutan nomor kartu. Tombol **Edit profil** pada kartu di pratinjau membuka isian anggota tersebut.

Gunakan **Tambah anggota** untuk membuat profil baru. Pilih foto, nama, jabatan, kategori, dan deskripsi pada kartunya. Penempatan anggota dalam tim diatur di **Struktur organisasi**. Penghapusan anggota memperbarui indeks referensi organisasi; perpindahan urutan mempertahankan hubungan dengan orang yang sama. Kepala sekolah atau koordinator perlu diganti pada struktur sebelum profilnya dapat dihapus.

Ringkasan guru pada beranda, direktori lengkap, mata pelajaran, dan struktur organisasi memiliki panel terpisah. Rekaman kepegawaian operasional `/admin/guru` tidak otomatis diterbitkan sebagai profil publik.

| Field `content/teachers` | Penggunaan |
| --- | --- |
| `team[]` | Ringkasan guru pada beranda versi tablet; foto pertama juga dipakai untuk sambutan |
| `people[]` | Profil publik: `name`, `role`, `group`, `description`, `portrait`, `photo` opsional |
| `subjects[]` | Nama pelajaran, pengajar, dan penjelasan |
| `structure.head` | Indeks kepala sekolah dalam `people` |
| `structure.branches[].lead` | Indeks koordinator |
| `structure.branches[].teams[].members[]` | Indeks anggota tiap tim |
| `directory_eyebrow`, `directory_title`, `directory_description` | Pengantar halaman Guru & Staf |
| `demo_profiles` | Pemberitahuan profil contoh |
| `values[]` | Nilai pendampingan |

`home.campus_image` mengatur foto kampus pada slide pertama beranda; dokumen lama memakai foto kampus bawaan. `home.image` tetap mengatur foto kegiatan pada slide berikutnya.

## Nomor pendaftaran dan profil siswa

Nomor pendaftaran berasal dari `applications.reference`, misalnya `CN-2026-XXXXXXXXXX`, dan dipertahankan sesudah penerimaan. Aktivasi membuat NIS dari urutan siswa tersendiri, misalnya `SCN-2026-005`, bukan memotong nomor pendaftaran. Proses menggunakan transaksi workflow yang sama dengan aktivasi sehingga nomor dan rekaman tidak ganda ketika aktivasi diulang. Nomor yang terdapat pada arsip penghapusan juga diperhitungkan.

Profil siswa menyimpan `data.registration_number`, `data.nis`, dan `data.applicant_id` secara terpisah. Profil lama dapat membaca nomor pendaftaran dari `data.number` atau pendaftar yang ditautkan. NIS lama tidak diubah otomatis. Tahun ajaran pendaftar yang belum tersedia dilengkapi pada daftar ulang dari periode PPDB. Tampilan profil menangani field lama yang kosong sehingga kartu tetap dapat dibuka.

## Dua repositori dan perubahan landing page

Salinan landing page tersedia di `.tools/landing-reference/` sebagai repositori Git tersendiri; folder ini diabaikan Git dashboard. Perubahannya mencakup:

- `SchoolContent`: mengambil direktori dari dokumen bersama, mengganti daftar sebagai satu kesatuan, dan melengkapi bentuk data dari cache lama.
- `TeacherDirectory` serta halaman Guru & Staf: memakai konten bersama, kepala sekolah terpilih, foto unggahan, dan penanda profil contoh.
- Hero beranda: membaca `campus_image` dari konten.
- `SharedTeacherContentTest`: memeriksa sinkronisasi konten, penghapusan kartu, foto, dan teks ter-escape.

Patch portabel disimpan pada `docs/patches/landing-content-sync.patch`. Untuk checkout landing bersih pada revisi acuan, jalankan dari folder landing:

```powershell
git apply --check C:\P02_Dashboard_SDcerianusantra\docs\patches\landing-content-sync.patch
git apply C:\P02_Dashboard_SDcerianusantra\docs\patches\landing-content-sync.patch
```

Salinan lokal `.tools/landing-reference` sudah memuat perubahan tersebut sehingga patch tidak perlu diterapkan lagi di sana. Deploy perubahan dashboard dan landing bersama. Pastikan `LANDING_PAGE_URL` dashboard mengarah ke landing yang sesuai dan `LANDING_CACHE_PREFIX` sama dengan `cache.prefix` landing. Publikasi membatalkan key `school-content-firestore` pada namespace tersebut; tidak perlu menyemai ulang konten.

Default saat sesi ini: dashboard `http://127.0.0.1:8017`, landing `http://127.0.0.1:8000`. Kredensial landing menunjuk file privat dashboard melalui konfigurasi lokal; kredensial tidak disertakan dalam patch.

Template pratinjau berada di `resources/views/website/site/` dan komponen `resources/views/components/landing-preview/`; aset berada di `public/landing-preview/`. Tidak ada pembacaan folder repositori lain saat runtime dashboard. Untuk memperbarui salinan template setelah perubahan landing ditinjau:

```powershell
python tools/sync-landing-preview.py C:\path\checkout-landing
```

## Verifikasi

Dashboard:

```powershell
& 'C:\xampp\php\php.exe' artisan test --compact
npm ci
$env:BROWSER_PATH = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
npm run test:editor
npm run test:crop
```

Landing, dari folder checkout landing:

```powershell
& 'C:\xampp\php\php.exe' artisan test --compact
```

Tes dashboard memakai Firestore memori dengan HTTP eksternal diblokir; tes landing memakai database uji terisolasi. Pemeriksaan editor di browser mengintersepsi seluruh permintaan dan memakai HTML hasil render dari fixture memori. Screenshot berada di `storage/app/editor-browser-check/`.
