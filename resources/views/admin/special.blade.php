@extends($screen==='audit'?'layouts.audit':'layouts.app')
@section('content')
@if($screen==='bahasa')<x-card title="Pilih bahasa"><div class="language-options">@foreach(['id'=>'Indonesia','en'=>'English'] as $locale=>$label)<form method="post" action="/language">@csrf<input type="hidden" name="locale" value="{{ $locale }}"><button class="{{ session('locale','id')===$locale?'selected':'' }}"><span class="flag {{ $locale }}">@if($locale==='en')🇬🇧@endif</span>{{ $label }}</button></form>@endforeach</div><small class="muted">Pilih bahasa antarmuka. Nominal pembayaran tetap dalam Rupiah.</small></x-card>
@elseif($screen==='notifikasi')@php
$notifications=match(auth()->user()->role){
    'keuangan'=>[['Pembayaran transfer menunggu verifikasi','Periksa nominal dan referensi pembayaran','pembayaran']],
    'akademik'=>[['Absensi hari ini','Periksa pencatatan kehadiran siswa','absensi'],['Nilai & rapor','Periksa nilai yang siap diterbitkan','nilai']],
    default=>[['Pendaftar baru: Raka Aditya','Periksa data dan berkas pendaftaran','pendaftar'],['Draf Hero telah disimpan','Tinjau sebelum publikasi','publikasi'],['Pembayaran transfer menunggu verifikasi','Alya Putri · SPP Oktober','pembayaran']],
};
@endphp<x-card title="Perlu perhatian">@foreach($notifications as [$name,$desc,$link])<a class="detail-line" href="/admin/{{ $link }}"><x-icon name="file"/><div><strong>{{ $name }}</strong><small>{{ $desc }}</small></div></a>@endforeach</x-card>
@elseif($screen==='laporan')@php
$reportDefinitions=[
    'students'=>['Laporan siswa & kelas','Siswa aktif, pindah, lulus; penempatan kelas'],
    'applicants'=>['PPDB & daftar ulang','Pendaftar, pemeriksaan, daftar ulang dan penerimaan'],
    'attendance'=>['Kehadiran siswa','Rekap kehadiran dan catatan absensi'],
    'grades'=>['Nilai & rapor','Nilai mata pelajaran dan status publikasi rapor'],
    'invoices'=>['Laporan keuangan','Tagihan, pembayaran, dan sisa tagihan'],
];
$reportDefinitions=collect($reportDefinitions)->only(\App\Support\SchoolAccess::reportKinds(auth()->user()));
$reportStats=match(auth()->user()->role){
    'keuangan'=>[['Rp81 jt','Pembayaran terkonfirmasi','wallet'],['Rp27 jt','Sisa tagihan','file']],
    'akademik'=>[[\App\Support\School::metric('students',360,4),'Siswa aktif','users'],[\App\Support\School::metric('attendance',346,4),'Catatan kehadiran','calendar']],
    default=>[[\App\Support\School::metric('students',360,4),'Siswa aktif','users'],[\App\Support\School::metric('applicants',68,4),'Pendaftar PPDB','file'],['Rp81 jt','SPP terkonfirmasi','wallet']],
};
@endphp<x-stats :items="$reportStats"/><x-card title="Pilih laporan"><form id="report-form" action="/admin/export/{{ $reportDefinitions->keys()->first() }}">@foreach($reportDefinitions as $key=>[$label,$desc])<label class="report-option"><input type="radio" name="report" value="{{ $key }}" @checked($loop->first)><span><strong>{{ $label }}</strong><small>{{ $desc }}</small></span></label>@endforeach<button class="btn">Ekspor laporan</button></form></x-card>
@elseif($screen==='kenaikan')<x-card title="Informasi kenaikan kelas & kelulusan"><form action="/admin/kenaikan-kelas" method="post" data-confirm="Pindahkan siswa dari kelas asal ke kelas tujuan dan tahun ajaran yang dipilih?">@csrf<div class="form-grid"><label class="field">Tahun asal<input value="2026/2027" readonly></label><label class="field">Tahun tujuan<input name="year" value="2027/2028" required></label><label class="field">Kelas asal<select name="from">@foreach(['1A','1B','2A','3A','4A','5A','6A'] as $cl)<option>{{ $cl }}</option>@endforeach</select></label><label class="field">Kelas tujuan<select name="to">@foreach(['2A','2B','3A','4A','5A','6A','Lulus'] as $cl)<option>{{ $cl }}</option>@endforeach</select></label></div><label class="check-row"><input type="checkbox" name="confirm" value="1" required>Saya telah memeriksa rapor dan mengonfirmasi siswa yang naik kelas.</label><div class="actions"><button class="btn">Simpan perubahan</button><a href="/admin/kelas" class="btn secondary">Batal</a></div></form></x-card>
@elseif($screen==='aktivasi')@php
$student=\App\Models\SchoolRecord::ofKind('students')->findOrFail($id);
@endphp<x-card class="success-state"><h2>Penerimaan {{ $student->value('name') }} selesai</h2><x-badge :value="'Aktif'"/><p>Data pendaftar menjadi data siswa. Data wali dihubungkan, dokumen dan kuitansi tetap tersimpan.</p><dl class="definition"><dt>Nomor induk baru</dt><dd>{{ $student->code }}</dd><dt>Tahun ajaran</dt><dd>{{ $student->value('year') }}</dd><dt>Wali terhubung</dt><dd>{{ $student->value('guardian') }}</dd><dt>Kelas penempatan</dt><dd>{{ $student->value('class') }}</dd></dl><div class="actions" style="margin-top:24px"><a href="/admin/siswa/{{ $student->id }}" class="btn">Lihat profil siswa</a><a href="/admin/pendaftar" class="btn secondary">Kembali ke PPDB</a></div></x-card>
@elseif($screen==='cari')
@php
$q=mb_strtolower(request('q',''));
$results=\App\Models\SchoolRecord::whereIn('kind',\App\Support\SchoolAccess::searchKinds(auth()->user()))->get()->filter(fn($r)=>$q!==''&&str_contains(mb_strtolower($r->code.' '.implode(' ',array_filter($r->data,'is_scalar'))),$q));
$paths=['students'=>'siswa','staff'=>'guru','applicants'=>'pendaftar','invoices'=>'tagihan','transactions'=>'kas'];
@endphp<x-card :title="'Hasil untuk “'.request('q').'”'">@forelse($results as $result)<a class="detail-line" href="{{ $result->kind==='content'?'/admin/website/'.$result->value('section').'/edit':'/admin/'.$paths[$result->kind].'/'.$result->id }}"><x-icon name="search"/><div><strong>{{ $result->value('name', $result->code) }}</strong><small>{{ $result->code }}</small></div></a>@empty<div class="empty">Tidak ada data yang sesuai dengan akses akun Anda.</div>@endforelse</x-card>
@elseif($screen==='audit')<article class="audit-page"><h2>Audit Admin · Usulan penyederhanaan</h2><p class="muted">Keputusan akhir tetap pada Anda. Rancangan V3 memperlihatkan navigasi yang lebih ringkas; fitur lama masih tercatat dan desain V2 tetap tersedia.</p><h3>Perubahan yang sudah diminta</h3><p>Admin desktop saja · Kelola sekolah + website dalam satu akun · Chevron dan highlight sidebar · Ikon bendera bahasa · Detail siswa, guru dan staf mengikuti EduDash · Daftar ulang PPDB offline sebelum aktivasi siswa.</p>
@foreach([
['Gabungkan','Pembayaran + riwayat + kuitansi','Satu menu, tab terpisah. Mengurangi perpindahan saat memeriksa pembayaran.'],
['Pindahkan','Kartu identitas','Jadikan aksi pada profil siswa / guru. Tidak perlu menu tersendiri.'],
['Gabungkan','Periode & kuota PPDB','Sebagai pengaturan PPDB, dekat dengan pendaftar dan daftar ulang.'],
['Pindahkan','Impor & ekspor','Aksi pada data tabel terkait, sehingga konteks data lebih jelas.'],
['Gabungkan','Ujian + perkembangan + rapor','Tab di akademik / profil siswa, menghindari input dan laporan berulang.'],
['Tahap berikutnya','Penggajian, departemen & cuti','Diaktifkan bila proses kepegawaian memang dikelola melalui portal ini.'],
['Tahap berikutnya','Perpustakaan & sertifikat','Perlu alur operasional sekolah; bukan kebutuhan utama PPDB dan website.'],
['Sederhanakan','Kunjungan & percakapan penuh','Gunakan kontak / janji dulu bila belum perlu kalender pemesanan atau chat internal.'],
['Tahap berikutnya','Integrasi data & kustom tema','Tunda bila belum ada sumber data dan kebutuhan tema. Bahasa tetap tersedia.']
] as [$type,$name,$description])<div class="audit-item"><div><span class="badge">{{ $type }}</span><p><strong>{{ $name }}</strong></p></div><div><p>{{ $description }}</p><p class="audit-status">Belum diputuskan · Pertahankan / Gabungkan / Tunda</p></div></div>@endforeach<p class="muted" style="font-size:12px">Fitur yang sudah dihapus sebelumnya: diskon sekolah, paket langganan, RTL, dan peringkat guru / siswa. Admin mobile tidak disertakan pada V3 sesuai permintaan.</p></article>@endif
@endsection
