# Firebase dan Cloud Firestore

Status diperiksa pada 9 Oktober 2026. Dashboard tersambung ke Firestore proyek yang sama dengan landing page.

| Pengaturan | Nilai |
| --- | --- |
| Akun CLI yang dipilih | `goreskom5@gmail.com` |
| Nama proyek | SD Ceria Nusantara |
| Project ID / alias lokal | `sd-ceria-nusantara` / `default` |
| Database | Cloud Firestore, `(default)` |
| Mode / wilayah | Firestore Native / `asia-southeast2` (Jakarta) |
| Realtime Database | Tidak ada instance saat pemeriksaan |
| Aplikasi Web Firebase terdaftar | Tidak ada saat pemeriksaan |

[Firebase Console](https://console.firebase.google.com/project/sd-ceria-nusantara/overview) · [Firestore](https://console.firebase.google.com/project/sd-ceria-nusantara/firestore)

## Verifikasi koneksi

Dari folder dashboard, jalankan:

```powershell
firebase.cmd login:list
firebase.cmd use
firebase.cmd firestore:databases:list --project sd-ceria-nusantara --account goreskom5@gmail.com
```

Ketiga pemeriksaan berhasil: akun yang aktif sesuai pilihan, alias mengarah ke proyek tersebut, dan database Firestore dapat ditemukan. Gunakan `firebase.cmd` pada PowerShell komputer ini karena pemanggilan `firebase` memilih skrip `.ps1` yang diblokir execution policy.

`.firebaserc` menyimpan alias proyek. `firebase.json` sengaja belum mendefinisikan target deploy; koneksi ini tidak menerapkan rules, indeks, atau hosting. Login CLI tersimpan pada profil pengguna komputer dan bukan kredensial runtime Laravel. Referensi perintah: [Firebase CLI](https://firebase.google.com/docs/cli).

## Integrasi dengan landing page

Folder `E:\Landing Page Website` memiliki `.firebaserc` dengan Project ID yang sama. Berdasarkan kode dan dokumentasi lokal landing page:

- Konten website menggunakan koleksi `content`; akun pengelola menggunakan `admins`.
- Pendaftaran menggunakan `applications`; permintaan kunjungan menggunakan `visits`.
- Berkas menggunakan `uploaded_files` dan `uploaded_file_chunks`.
- Laravel mengakses Firestore dari server dengan service account; rules lokal menolak akses browser langsung.

Project ID service account dashboard telah diverifikasi cocok dengan `sd-ceria-nusantara`, dan pembacaan Firestore berhasil. Kredensial disimpan di luar source control pada `storage/app/private/firebase-service-account.json` (atau disediakan melalui `FIREBASE_CREDENTIALS` / `FIREBASE_CREDENTIALS_JSON_BASE64`). Jangan menyalin atau menampilkan private key. Runtime Laravel memakai service account; login Firebase CLI saja tidak memberi kredensial runtime.

## Penyimpanan dashboard

`DB_CONNECTION=disabled` mencegah fallback diam-diam ke SQLite. Rekaman, akun admin, pembayaran, sesi, cache, dan berkas aplikasi menggunakan Firestore. Koleksi dashboard meliputi `dashboard_records`, `dashboard_content_drafts`, `dashboard_runtime`, `school_payments`, `school_documents`, dan `admission_activations`, selain koleksi bersama landing page. Antrean memakai driver `sync`.

Database lokal sebelumnya memiliki 1 akun, 57 rekaman sekolah, dan 3 pembayaran; semua rekaman lokal yang tersedia sudah diimpor tanpa menimpa dokumen cloud. Verifikasi Firestore menemukan 46 `dashboard_records`, 5 `applications`, 7 `dashboard_legacy_content`, dan 3 `school_payments`. Akun demo yang memakai kata sandi contoh dinonaktifkan saat impor. Seluruh file SQLite lokal (database aktif, dua backup pra-Firestore, dan scaffold) telah dihapus sesuai persetujuan; berkas migration dipertahankan sebagai riwayat skema lama. Pengujian memakai data uji terisolasi, bukan Firestore produksi.

## Sinkronisasi editor website

- Halaman publik `/website` tidak disediakan dashboard; landing page berjalan sebagai aplikasi terpisah. Admin tetap mengelola konten dari editor dashboard.
- Editor memuat delapan dokumen (`settings`, `home`, `about`, `program`, `facilities`, `teachers`, `gallery`, `contact`) langsung dari koleksi `content`, format yang sama dengan landing page.
- Simpan membuat/memperbarui draf pada `dashboard_content_drafts`; landing page tetap membaca konten terbit.
- Publikasi menggabungkan perubahan draf ke dokumen `content` yang sama. Perubahan landing page yang tidak bertabrakan dipertahankan, konflik pada field yang sama menolak publikasi, dan cache `school-content-firestore` landing page dibersihkan.
- PPDB dashboard membaca serta memperbarui dokumen pada koleksi bersama `applications`. Unggahan website/PPDB memakai `uploaded_files` dan `uploaded_file_chunks`.

Pengujian `SharedWebsiteTest` dan `FirestoreRuntimeStoreTest` lulus untuk perilaku baca konten bersama, penyimpanan draf, publikasi, pelestarian field yang tidak dikenal, konflik, dan aplikasi PPDB. Perubahan atau deployment rules, indeks, hosting, maupun lingkungan produksi tidak dilakukan.
