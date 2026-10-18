# Editor visual, PPDB, dan integrasi landing page

Pembaruan lokal 17 Oktober 2026. Dashboard menggunakan branch `main2-juan`. Acuan landing page adalah [SD-Ceria-Nusantara---Landing-Page](https://github.com/dovahkiinn-5/SD-Ceria-Nusantara---Landing-Page), revisi `cb613dc` beserta perbaikan kontrak konten pada sesi ini.

## Mengelola website

1. Buka **Website sekolah → Ringkasan website** (`/admin/website`). Halaman yang memiliki perubahan tersimpan ditandai **Draf**; ringkasan di atas kartu membuka tinjauan.
2. Pilih **Edit halaman**, lalu bagian yang ingin diubah. Formulir menggunakan ruang utama. **Tampilkan pratinjau** membuka tampilan pendamping; pada layar laptop yang sempit pratinjau disembunyikan terlebih dahulu agar isian mudah digunakan.
3. Ubah teks atau foto. **Simpan draf** menyimpan tanpa keluar dari editor; **Ctrl/Cmd+S** juga tersedia. Pilihan halaman pada editor menyimpan perubahan sebelum berpindah. Jika penyimpanan gagal, isian tetap tersedia dan pesan kesalahan muncul di formulir.
4. Gunakan **Desktop**, **Tablet**, atau **Ponsel** pada pratinjau. Gambar memakai crop grid dan identitas field yang tetap, sehingga perubahan urutan field Firestore tidak memindahkan unggahan ke bagian lain.
5. Klik **Simpan & tinjau**. Isian disimpan terlebih dahulu, kemudian `/admin/publikasi` menampilkan perbandingan **Sedang tayang** dan **Setelah diterbitkan** untuk setiap perubahan, termasuk gambar. Pratinjau penuh juga menyediakan pilihan **Draf tersimpan** dan **Sedang tayang**.
6. Periksa perubahan, centang satu persetujuan, lalu **Terbitkan … halaman**. Catatan bersifat opsional. Jika draf atau konten publik berubah sejak tinjauan dibuka, publikasi ditolak sampai versi terbaru diperiksa. Halaman hasil menampilkan halaman, jumlah perubahan, pengelola, dan waktu publikasi yang benar-benar berhasil.

Formulir yang tidak berubah tidak membuat draf baru. Halaman publikasi kosong menjelaskan cara membuat draf dan tidak menampilkan tombol terbitkan. **Batalkan draf halaman ini** menghapus draf yang ditinjau dengan konfirmasi; versi terbit tetap digunakan. Tombol tambah, hapus, naik, dan turun juga menyimpan isian yang sedang diedit.

Pengujian `WebsitePublicationTest` mencakup simpan–tinjau–terbit seluruh delapan halaman, invalidasi cache landing, draf kosong, penolakan versi tinjauan lama, pembatalan draf, dan pemetaan unggahan setelah urutan field berubah. Pemeriksaan browser editor mencakup kegagalan simpan, percobaan ulang, simpan sebelum berpindah, konfirmasi publikasi, serta tata letak laptop dan ponsel. Seluruh penulisan pengujian memakai Firestore memori.

Pratinjau memakai template, CSS, dan aset landing page dalam iframe terpisah. Endpoint berada di balik autentikasi serta akses pengelola website. Formulir pendaftaran dan kunjungan dalam pratinjau tidak mengirim data. Preview isian tidak membuat dokumen draf atau menyimpan file ke Firestore.

## Guru, staf, dan pembagian tim

Pada editor **Guru & Staf → Direktori Guru & Staf**, cari nama/peran atau pilih tim, misalnya Wali Kelas, Guru Bidang Studi, Administrasi & Data, atau Lingkungan Aman & Nyaman. Pengelompokan mengikuti struktur organisasi, bukan urutan nomor kartu. Tombol **Edit profil** pada kartu di pratinjau membuka isian anggota tersebut.

Gunakan **Tambah anggota** untuk membuat profil baru. Pilih foto, nama, jabatan, kategori, dan deskripsi pada kartunya. Penempatan anggota dalam tim diatur di **Struktur organisasi**. Penghapusan anggota memperbarui indeks referensi organisasi; perpindahan urutan mempertahankan hubungan dengan orang yang sama. Kepala sekolah atau koordinator perlu diganti pada struktur sebelum profilnya dapat dihapus.

Direktori dan struktur organisasi memakai satu daftar profil `people`. Panel **Guru di beranda** memilih referensi profil yang sama melalui `featured_people`; tidak menyimpan nama, jabatan, atau foto kedua. Nama dan foto sambutan beranda serta Tentang Kami mengikuti `people[structure.head]`. Rekaman kepegawaian operasional `/admin/guru` tetap tidak otomatis diterbitkan sebagai profil publik.

| Field `content/teachers` | Penggunaan |
| --- | --- |
| `featured_people[]` | Indeks profil yang dipilih untuk beranda; kepala sekolah dari `structure.head` otomatis tampil pertama tanpa duplikasi |
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

Perubahan dashboard disimpan pada branch lokal `main3_dim`. Checkout source landing di `.tools/landing-reference/` juga memiliki branch lokal `main3_dim`, dengan commit `ffa15d7` untuk sinkronisasi konten dan tim guru. Patch portabel di dokumen ini tetap dapat dipakai pada checkout landing yang terpisah; folder `.tools/` tidak ikut commit dashboard. Kedua branch belum dipush.

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

## Penyatuan profil website — 18 Oktober 2026

Field lama `teachers.directory`, `teachers.team`, dan `home.principal_name` tidak digunakan lagi. Tautan editor lama `?block=other` membuka direktori. Gunakan `php artisan school:normalize-teachers` untuk meninjau perapian, lalu `php artisan school:normalize-teachers --apply` untuk menerapkan. Perintah mempertahankan `people` dan struktur yang ada, mengubah pilihan beranda menjadi referensi, serta merapikan `base`/`data` draf tanpa menerbitkannya. Dokumen asli dicadangkan di `storage/app/private/content-backups/`; pemeriksaan versi dan transaksi mencegah penimpaan perubahan yang terjadi selama migrasi. Cache landing dibersihkan dalam transaksi yang sama. Kode `TeacherDirectory`, `SchoolContent`, template beranda/Tentang Kami, dan komponen foto/tim landing harus diperbarui bersama dashboard.
