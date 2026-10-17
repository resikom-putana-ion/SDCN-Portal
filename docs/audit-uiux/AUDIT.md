# Audit navigasi, header, dan performa

18 Oktober 2026 · dashboard `main2-juan`

**Status:** navigasi kembali sudah diperbaiki. Header baru dan optimasi performa masih berupa usulan, menunggu persetujuan pengguna. Pemeriksaan dilakukan pada kode dan halaman dengan data uji terisolasi; belum ada pengukuran performa dashboard setelah login ke Firebase produksi.

## 1. Navigasi kembali — diterapkan

Sebelumnya layout utama hanya menyediakan tautan Beranda pada breadcrumb; tombol kembali muncul di beberapa halaman saja. Sekarang setiap halaman dashboard selain Beranda mendapat tautan kembali dengan label tujuan di atas judul.

| Kondisi | Tujuan |
| --- | --- |
| Profil siswa atau guru | Daftar siswa atau guru, dengan filter terakhir |
| Tab profil, edit, konfirmasi hapus siswa | Profil yang sedang dikelola |
| Tambah data, form data lain | Daftar asal |
| Pendaftar dan pemeriksaan daftar ulang | Daftar asal, dengan filter terakhir |
| Kuitansi atau pemeriksaan pembayaran | Pembayaran dengan tagihan yang sama dan tab daftar terakhir bila tersedia |
| Form pengguna | Daftar pengguna |
| Editor landing page | Kelola website |
| Pratinjau landing page | Editor halaman yang dipratinjau; kebijakan ke editor informasi sekolah |
| Media, publikasi, hasil publikasi | Kelola website |
| Audit terpisah | Pengaturan |
| Halaman menu utama lain | Beranda |

Tautan memakai hierarki halaman, sehingga akses langsung, refresh, atau selesai menyimpan tidak membuat pengguna terjebak bolak-balik ke form edit. Filter yang disimpan hanya parameter daftar yang dikenal. Referer dari domain/port lain tidak dipakai. Fitur ini tidak membaca data tambahan dari Firebase. Tombol Batal pada form edit profil juga mengikuti tujuan kembali ke profil.

Implementasi: `app/Support/DashboardNavigation.php`, `resources/views/components/dashboard-back.blade.php`, kedua layout, dan form admin. Uji fitur mencakup role terbatas, filter, URL asing, akses langsung, dan alur pembayaran. Uji browser memakai data contoh di memori, termasuk klik Batal, keyboard Enter, kembali dari dokumen, serta target kembali pada viewport sempit setelah sidebar diciutkan. Dashboard secara keseluruhan masih memakai layout desktop; uji ini tidak menyatakan bahwa seluruh halaman sudah responsif.

Hasil verifikasi: 133 tes Laravel lulus (1.383 assertion), uji navigasi di Chrome lulus, dan kedua mockup sudah dirender serta diperiksa. Halaman login lokal merespons HTTP 200 di port 8017. Hasil ini memverifikasi perilaku dan tampilan yang diperiksa, bukan skor performa layanan cloud.

## 2. Header — usulan untuk disetujui

Lihat [mockup desktop](header-desktop.png), [mockup ponsel](header-mobile.png), atau buka [HTML penjelasan dan mockup](header-proposal.html). Semua nama dan jumlah dalam gambar merupakan ilustrasi.

Dasar referensi: Dwinawan membagi dashboard menjadi header, sidebar, dan area kerja, lalu menjelaskan penyesuaian grid saat ruang layar menyempit. Artikel tersebut membahas grid, sehingga pengelompokan kontrol di bawah adalah rekomendasi khusus proyek ini, bukan aturan yang dikutip dari penulis. [Layout: Grid Dashboard](https://www.dwinawan.com/layout-grid-dashboard).

| Temuan pada proyek | Usulan | Manfaat |
| --- | --- | --- |
| Pencarian global memakai seluruh sisa lebar | Batasi lebar pencarian sekitar 320–400 px, tetap adaptif | Konteks dan akun tidak terlihat sebagai ikon tambahan di ujung layar |
| Akun tampil di sidebar dan header | Satu menu akun di header, dengan nama dan role | Pengguna tahu sedang memakai akses yang mana |
| Tema, bahasa, dan notifikasi punya bobot visual serupa | Notifikasi tetap langsung; tema dan bahasa masuk menu akun berlabel | Tindakan utama lebih mudah dipindai |
| Tahun ajaran footer ditulis tetap | Tampilkan konteks tahun ajaran dari konfigurasi sekolah | Mengurangi risiko mengira sedang mengelola periode lain; ini informasi, bukan pemilih tahun baru |
| Titik notifikasi selalu muncul, isi notifikasi masih berupa daftar statis | Indikator hanya saat data belum dibaca tersedia; gunakan ikon netral sebelum integrasi data | Indikator memiliki arti yang dapat dipercaya |
| Hubungan header dan judul belum memberi hierarki kuat | Header untuk kontrol global; kembali, breadcrumb, judul, dan aksi halaman di area kerja | “Cari semua data” berbeda dari filter daftar dan “Tambah siswa” |
| CSS menetapkan minimum lebar dashboard 1.060 px di bawah 1.100 px | Header responsif dengan pencarian di baris kedua pada ponsel; sidebar melalui menu | Header lebih terjangkau di layar kecil |

Spesifikasi awal: tinggi header sekitar 72 px termasuk shell, padding area kerja 24–32 px, ritme jarak 8 px, target tindakan utama minimal 44 px, warna biru sekolah dan Poppins tetap digunakan. Nama akun boleh dipendekkan pada lebar menengah. Menu harus mendukung keyboard, fokus yang jelas, Escape, dan pembaca layar. Animasi ringan hanya untuk transform/opacity dan menghormati reduced motion. Kontras perlu diverifikasi pada tema terang dan gelap saat implementasi.

**Cakupan persetujuan header:** penataan header, menu akun, penempatan konteks tahun ajaran, serta penyelarasan area judul/breadcrumb. Tabel, statistik, model data, dan seluruh sidebar tidak ikut didesain ulang hanya karena terlihat dalam gambar. Sidebar di mockup dipakai untuk menjelaskan proporsi; perubahan fungsi sidebar dibatasi pada pemindahan identitas akun dan akses navigasi sempit yang disetujui. Perubahan penuh layout ponsel untuk tabel/profil/form adalah pekerjaan lanjutan yang perlu dipilih terpisah. Integrasi sumber notifikasi nyata juga perlu ditetapkan; desain header dapat memakai ikon netral lebih dulu.

## 3. Performa — temuan kode dan rencana, belum diterapkan

Belum ada skor Lighthouse, p50/p95, atau angka penghematan yang dapat diklaim. Uji dengan Firestore palsu memeriksa perilaku, bukan latensi cloud. Temuan berikut adalah kandidat penyebab dan harus dibuktikan melalui pengukuran.

| Bukti kode | Dampak yang perlu diukur | Perbaikan yang diusulkan |
| --- | --- | --- |
| `CloudData::rows('records')` menggabungkan seluruh `dashboard_records` dan `applications`; `DocumentQuery::find()` juga melalui `get()` | Membuka satu profil dapat memuat koleksi yang jauh lebih besar dari kebutuhan | Jalur baca satu dokumen berdasarkan ID, tetap memvalidasi kind dan hak akses; pisahkan proyeksi pendaftar dari data sekolah |
| `FirestoreDocumentStore::all()` mengambil halaman 300 dokumen sampai habis; `AdminController::records()` menyaringnya di PHP | Data yang ditransfer dan diproses tumbuh bersama seluruh koleksi | Query Firestore dengan filter/index yang sesuai, urutan stabil, batas halaman, dan cursor. Ekspor tetap memiliki jalur lengkap sendiri |
| `app.js` hanya menyembunyikan baris HTML per 10 baris | Semua baris tetap dibaca dan dirender sebelum pagination | Pagination server yang mempertahankan pencarian/filter, lalu muat halaman yang diminta saja |
| `FirebaseCredentials::$tokens` hanya array statis dalam proses request PHP | Pengambilan OAuth token dapat terjadi lagi pada request berikutnya | Cache token service account antar-request dengan TTL, lock, dan pemisahan project/scope. Cache lokal/Redis berizin terbatas; hindari driver Firestore yang memerlukan token itu sendiri |
| Session aktif memakai Firestore; autentikasi mengecek akun admin dan akun Firebase | Ada perjalanan jaringan yang diperlukan sebelum konten tampil | Ukur waktu tiap tahap. Pertimbangkan Redis untuk session/cache jika sesuai hosting; tetap pertahankan pemeriksaan akun nonaktif, role, reset password, dan pencabutan sesi |
| `run.ps1` menjalankan PHP built-in server di Windows | Request yang lama dapat menahan request lain pada server pengembangan | Bandingkan dengan Apache/PHP lokal atau lingkungan staging dengan worker yang sesuai sebelum menyimpulkan kapasitas produksi |
| Font TTF tiga bobot sekitar 476 KB total; CSS sekitar 25 KB dan JS utama sekitar 6 KB | Font lebih layak ditinjau daripada menganggap JS utama terlalu besar | Ukur transfer aktual dan cache browser; pertimbangkan WOFF2/subset yang tetap memuat karakter Indonesia, thumbnail, dimensi gambar, dan lazy loading di bawah lipatan |

Cache koleksi saat ini sudah berlaku dalam satu request. Jadi setiap pemanggilan filter bukan otomatis satu panggilan jaringan baru; masalah utamanya pembacaan awal yang terlalu luas dan pekerjaan yang berulang antar-request. Total folder aset landing juga bukan ukuran download satu halaman, karena browser hanya mengambil aset yang dipakai.

### Urutan kerja yang disarankan

1. **Ukur baseline.** Jalankan alur masuk → daftar siswa → cari/filter → profil → edit/Batal, daftar ulang, pembayaran, dan editor website. Pisahkan waktu respons server, OAuth, sesi, autentikasi, baca Firestore, rendering, dan browser. Catat request/dokumen, ukuran transfer, cold/warm cache, p50/p95, serta waktu klik sampai konten tujuan tampil. Pakai data uji representatif dan peran admin/akademik/keuangan; jangan merekam isi kredensial atau data pribadi pada log.
2. **Kurangi pekerjaan backend.** Mulai dari direct document lookup dan cache OAuth, kemudian pagination/query pada daftar siswa sebagai pilot. Tentukan skema pencarian nama: Firestore tidak menyediakan substring arbitrer seperti penyaringan PHP saat ini. Pertahankan perilaku pencarian atau sepakati perubahan secara eksplisit sebelum menggantinya. Pastikan indeks dan kompatibilitas dokumen lama, metrik jumlah siswa, NIS/nomor pendaftaran, arsip, ekspor, serta transaksi aktivasi tidak rusak.
3. **Tinjau runtime dan aset.** Session/cache Redis bila dibutuhkan dan disetujui sesuai hosting; OPcache dan cache konfigurasi/routes/views pada proses deployment yang sesuai. `run.ps1` sudah mengaktifkan OPcache untuk server lokal. Jangan menganggap semua cache Laravel otomatis menyelesaikan waktu jaringan.
4. **Evaluasi navigasi parsial jika masih perlu.** Turbo/Livewire atau fetch untuk bagian tertentu dapat mengurangi pemuatan ulang layout. Ini tahap berikutnya setelah backend terukur, dengan pengujian CSRF, browser back/forward, role, cropper, dan editor iframe. Prefetch hanya GET yang aman; hindari prefetch semua menu karena dapat menambah pembacaan Firestore.
5. **Ukur ulang dengan kondisi sama.** Bandingkan p50/p95 dan pembacaan dokumen terhadap baseline. Tetapkan target waktu perpindahan setelah baseline; jangan menjanjikan skor 100 atau semua klik di bawah angka tertentu tanpa data.

Sebagai acuan kualitas web: LCP ≤ 2,5 detik, INP ≤ 200 ms, CLS ≤ 0,1 pada persentil 75 data pengguna, dipisahkan mobile/desktop. Ini bukan angka hasil proyek ini. Lighthouse membantu pemeriksaan laboratorium; untuk dashboard, waktu tunggu konten setelah klik dan alur pengguna setelah login juga harus diukur. [Web Vitals](https://web.dev/articles/vitals).

Rujukan teknis untuk rencana: [Firestore cursor pagination](https://firebase.google.com/docs/firestore/query-data/query-cursors) mendukung pembacaan bertahap menggunakan batas dan cursor. [PHP built-in server](https://www.php.net/manual/en/features.commandline.webserver.php) menjelaskan proses single-thread serta keterbatasan worker di Windows. [Laravel deployment](https://laravel.com/docs/12.x/deployment) menjelaskan cache konfigurasi, routes, dan views untuk deployment. Rekomendasi prioritas di atas adalah hasil membaca kode proyek, bukan hasil benchmark dari dokumentasi tersebut.

**Persetujuan yang dibutuhkan:** desain header dan tahap optimasi dapat dipilih terpisah. Rekomendasi awal adalah menyetujui baseline performa, kemudian pilot backend pada daftar/profil siswa beserta cache token. Migrasi session/hosting, seluruh layout ponsel, dan navigasi parsial tetap tahap tersendiri. Tidak ada perubahan konfigurasi performa atau infrastruktur yang dilakukan dalam sesi audit ini.

## Menjalankan pemeriksaan

```powershell
Set-Location C:\P02_Dashboard_SDcerianusantra
& C:\xampp\php\php.exe artisan test --compact
node tests/Browser/navigation.cjs
node tools/render-uiux-audit.cjs
```

Dua perintah browser membutuhkan Chrome lokal dan dependency npm proyek. Fixture dan tangkapan layar navigasi tersimpan di `storage/app/navigation-browser-check/`; screenshot mockup di direktori dokumen ini. Untuk menjalankan dashboard seperti sebelumnya: `powershell -ExecutionPolicy Bypass -File .\run.ps1 -Port 8017`, lalu buka `http://127.0.0.1:8017/login`.
