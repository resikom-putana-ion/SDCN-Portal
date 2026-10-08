# Peta implementasi desain

Desain PDF digunakan sebagai spesifikasi tampilan. Teks dalam dokumen audit dibedakan dari keputusan pengguna: usulan penggabungan/penundaan tidak dianggap sebagai persetujuan baru.

| Dokumen | Halaman/alur Laravel |
| --- | --- |
| A01 Autentikasi | `/login`, `/forgot-password`, `/reset-password/{token}` |
| A02 Beranda & bahasa | `/admin`, `/admin/bahasa`, `/admin/notifikasi` |
| A03 Siswa & keluarga | `/admin/siswa`, detail/edit/tab siswa, dokumen privat, kartu identitas |
| A04 Guru & staf | `/admin/guru`, profil guru/staf, jadwal, kehadiran, cuti contoh, dokumen |
| A05 PPDB | `/admin/pendaftar`, detail pemeriksaan, `/admin/daftar-ulang`, checklist offline, aktivasi, `/admin/periode` |
| A06 Tagihan & pembayaran | `/admin/tagihan`, `/admin/pembayaran`, pemeriksaan pembayaran, riwayat/kuitansi, `/admin/kas` |
| A07 Akademik & laporan | `/admin/kelas`, `/admin/kenaikan`, `/admin/absensi`, `/admin/nilai`, `/admin/pengumuman`, `/admin/agenda`, `/admin/laporan` |
| A08 Pengaturan & audit | `/admin/pengaturan`, `/admin/pengguna`, `/admin/aktivitas`, `/admin/alat`, `/admin/audit` |
| W01 Ringkasan & halaman | `/admin/website`, `/admin/halaman`, editor Hero |
| W02 Profil, program, fasilitas | `/admin/website/{profil,program,fasilitas,guru}/edit` |
| W03 PPDB, berita, kontak | `/admin/website/ppdb/edit`, `/admin/berita`, `/admin/website/kontak/edit` |
| W04 Media, tinjauan, pratinjau | `/admin/media`, `/admin/publikasi`, `/admin/website/preview`, `/admin/terbit` |
| W05 Formulir PPDB publik | `/ppdb`, `/ppdb/berhasil`, unggah akta/KK/pasfoto |
| Z01 Navigasi prototype | Sidebar, chevron, submenu aktif, pencarian, bendera bahasa, breadcrumb, tautan antarhalaman |
| PNG Audit keputusan pengguna | `/admin/audit`, dengan urutan kartu dan status keputusan dari gambar |

## Ketentuan alur

1. Pendaftar baru tidak menjadi siswa aktif saat mengirim formulir.
2. Aktivasi memerlukan lolos administrasi, empat pemeriksaan berkas/data asli, konfirmasi pembayaran, serta nominal sesuai biaya periode PPDB.
3. Aktivasi, pembuatan siswa, tagihan, dan pembayaran terjadi dalam transaksi database. Indeks unik mencegah aktivasi ganda.
4. Sisa tagihan hanya dikurangi oleh pembayaran berstatus Terverifikasi. Kuitansi untuk pembayaran menunggu tidak dapat diunduh.
5. Berkas keluarga disimpan pada disk lokal privat, diakses hanya melalui route yang memeriksa sesi admin.
6. Website menyimpan draf dan versi terbit terpisah. Publikasi memerlukan lima checklist dan catatan tinjauan.
7. Rapor PDF hanya memuat nilai Terbit. Kartu identitas menggunakan tampilan cetak browser.

## Tampilan dan cakupan

Lebar acuan desktop adalah 1440 px. Gaya memakai Poppins, sidebar 248 px, header 72 px, kartu dan warna dari layar sumber. Gambar ruang kelas, logo, dan ilustrasi profil berasal dari dokumen desain. Pratinjau Hero memiliki panel biru tua, tombol kuning, pemilih media, serta pembaruan teks langsung.

Data dan metrik contoh dipertahankan untuk memeriksa kesesuaian visual. Beberapa panel kepegawaian dan ringkasan masih berupa contoh, sebagaimana dijelaskan dalam README; panel tersebut bukan integrasi HR atau akuntansi penuh. Halaman publik mengikuti pratinjau yang disertakan W04. Implementasi ini tidak mencakup penerbitan ke domain luar atau layanan pembayaran eksternal.

Gunakan `php artisan test --compact` dan `npm run test:browser` untuk memeriksa ulang hasil. Screenshot browser terbaru tersimpan dalam `storage/app/browser-check/`.
