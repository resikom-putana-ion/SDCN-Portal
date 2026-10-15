@extends('layouts.app')
@section('content')
@php
$school=\App\Support\School::class;
$role=auth()->user()->role;
@endphp
<div class="welcome"><div><h2>{{ session('locale') === 'en' ? 'Good morning,' : 'Selamat pagi,' }} {{ auth()->user()->name }}</h2><p class="muted">{{ now()->locale(session('locale','id'))->translatedFormat('l, j F Y') }}</p></div><div class="actions"><a class="btn" href="/admin/laporan">{{ __('Unduh laporan') }}</a></div></div>

@if($role==='keuangan')
@php
$invoiceRows=\App\Models\SchoolRecord::ofKind('invoices')->get()->map(fn($invoice)=>$school::invoice($invoice));
$transactions=\App\Models\SchoolRecord::ofKind('transactions')->get();
$pendingPayments=\App\Support\CloudData::table('school_payments')->where('status','Menunggu')->get();
@endphp
<x-stats :items="[[$school::money($invoiceRows->sum('amount')),'Total tagihan','wallet'],[$school::money($invoiceRows->sum('paid')),'Pembayaran terverifikasi','wallet'],[$school::money($invoiceRows->sum('balance')),'Sisa tagihan','file'],[$pendingPayments->count(),'Menunggu verifikasi','clock']]"/>
<div class="actions"><a class="btn secondary" href="/admin/tagihan">{{ __('Kelola tagihan') }}</a><a class="btn secondary" href="/admin/pembayaran">{{ __('Catat pembayaran') }}</a><a class="btn secondary" href="/admin/kas">{{ __('Kas & transaksi') }}</a></div>
<div class="grid-2 dashboard-grid-2">
    <x-card title="Tagihan perlu ditindaklanjuti">@forelse($invoiceRows->filter(fn($row)=>$row['balance']>0)->take(5) as $row)<div class="detail-line"><x-icon name="wallet"/><div><strong>{{ $row['name'] }}</strong><small>Sisa {{ $school::money($row['balance']) }} · {{ $row['status'] }}</small></div></div>@empty<div class="empty">Tidak ada sisa tagihan.</div>@endforelse<a class="card-link" href="/admin/tagihan">Lihat semua tagihan</a></x-card>
    <x-card title="Kas terbaru">@forelse($transactions->take(5) as $transaction)<div class="detail-line"><x-icon name="wallet"/><div><strong>{{ $transaction->value('name') }}</strong><small>{{ $transaction->value('type') }} · {{ $school::money($transaction->value('amount',0)) }}</small></div></div>@empty<div class="empty">Belum ada transaksi kas.</div>@endforelse<a class="card-link" href="/admin/kas">Lihat kas</a></x-card>
</div>
<x-notice>Dashboard ini hanya menampilkan data keuangan yang menjadi tanggung jawab role Keuangan.</x-notice>

@elseif($role==='akademik')
@php
$activeStudents=\App\Models\SchoolRecord::ofKind('students')->get()->filter(fn($student)=>$student->value('status','Aktif')==='Aktif');
$staffCount=\App\Models\SchoolRecord::ofKind('staff')->get()->count();
$classCount=\App\Models\SchoolRecord::ofKind('classes')->get()->count();
$gradeCount=\App\Models\SchoolRecord::ofKind('grades')->get()->count();
@endphp
<x-stats :items="[[$activeStudents->count(),'Siswa aktif','users'],[$staffCount,'Guru & staf','briefcase'],[$classCount,'Kelas','school'],[$gradeCount,'Catatan nilai','book']]"/>
<div class="actions"><a class="btn secondary" href="/admin/siswa">{{ __('Kelola siswa') }}</a><a class="btn secondary" href="/admin/absensi">{{ __('Isi absensi') }}</a><a class="btn secondary" href="/admin/nilai">{{ __('Nilai & rapor') }}</a><a class="btn secondary" href="/admin/kelas">{{ __('Kelas') }}</a></div>
<div class="grid-2 dashboard-grid-2">
    <x-card title="Kegiatan akademik"><a href="/admin/absensi" class="detail-line"><x-icon name="calendar"/><div><strong>Absensi siswa</strong><small>Catat dan periksa kehadiran harian</small></div></a><a href="/admin/nilai" class="detail-line"><x-icon name="book"/><div><strong>Nilai & rapor</strong><small>Kelola nilai dan terbitkan rapor</small></div></a><a href="/admin/kenaikan" class="detail-line"><x-icon name="users"/><div><strong>Kenaikan kelas</strong><small>Perbarui kelas dan tahun ajaran siswa</small></div></a></x-card>
    <x-card title="Data sekolah"><a href="/admin/siswa" class="detail-line"><x-icon name="users"/><div><strong>{{ $activeStudents->count() }} siswa aktif</strong><small>Profil, keluarga, akademik, dan dokumen siswa</small></div></a><a href="/admin/guru" class="detail-line"><x-icon name="briefcase"/><div><strong>{{ $staffCount }} guru & staf</strong><small>Profil dan penugasan pengajar</small></div></a><a href="/admin/kelas" class="detail-line"><x-icon name="school"/><div><strong>{{ $classCount }} kelas</strong><small>Penempatan kelas dan tahun ajaran</small></div></a></x-card>
</div>
<x-notice>Dashboard ini hanya menampilkan data siswa dan akademik yang menjadi tanggung jawab role Akademik.</x-notice>

@else
<div class="actions"><a class="btn secondary" href="/admin/kelas">{{ __('Tahun ajaran 2026/27') }}</a><a class="btn secondary" href="/admin/pendaftar">{{ __('Periksa pendaftar') }}</a><a class="btn secondary" href="/admin/website">{{ __('Kelola website') }}</a><a class="btn secondary" href="/admin/siswa">{{ __('Lihat siswa') }}</a></div>
<x-stats :items="[[$school::metric('students',360,4),'Siswa aktif','users'],[$school::metric('staff',28,4),'Guru & staf','briefcase'],[$school::metric('applicants',68,4),'Pendaftar PPDB','file'],[12,'Menunggu daftar ulang','calendar']]"/>
<div class="grid-2 dashboard-grid-2"><x-card title="Progres PPDB 2027/2028"><div class="progress-flex"><div class="donut"><div><strong>{{ $school::metric('applicants',68,4) }}</strong><small>{{ __('Pendaftar') }}</small></div></div><div class="progress-legend">@foreach([['Sedang diperiksa',14],['Perlu perbaikan',8],['Menunggu daftar ulang',12],['Tahap lain',34]] as [$label,$count])<div><span>{{ __($label) }}</span><strong>{{ $count }}</strong></div>@endforeach</div></div><a class="card-link" href="/admin/pendaftar">{{ __('Lihat pendaftar') }}</a></x-card>
<x-card title="Perlu ditindaklanjuti">@foreach([['14 pendaftar belum diperiksa','Periksa data anak, wali, dan berkas','pendaftar'],['12 daftar ulang belum selesai','Lengkapi dokumen asli dan pembayaran','daftar-ulang'],['2 draf website menunggu tinjauan','Periksa sebelum publikasi','publikasi']] as [$name,$desc,$link])<a href="/admin/{{ $link }}" class="detail-line"><x-icon name="file"/><div><strong>{{ __($name) }}</strong><small>{{ __($desc) }}</small></div></a>@endforeach</x-card></div>
<div class="grid-3 dashboard-grid-3"><x-card title="Kehadiran siswa hari ini"><div class="big-number">96,1%</div><p class="muted">{{ __('346 dari 360 siswa hadir') }}</p><div class="attendance-bar"></div>@foreach([['Hadir',346],['Izin / sakit',9],['Alpa',5]] as [$label,$count])<div class="detail-line"><x-icon name="file"/><div><strong>{{ __($label) }}</strong><small>{{ $count }}</small></div></div>@endforeach<a class="card-link" href="/admin/absensi">{{ __('Lihat absensi') }}</a></x-card>
<x-card title="Website sekolah"><img src="/assets/classroom.jpeg" class="classroom-thumb" alt="Ruang belajar SD Ceria Nusantara"><x-badge value="Terbit"/><p style="margin:16px 0;font-weight:500">Belajar Ceria, Tumbuh Percaya Diri</p><a class="card-link" href="/admin/website">{{ __('Kelola website') }}</a></x-card>
<x-card title="Pengumuman & agenda">@foreach([['Pekan Literasi Ceria','8–12 Oktober · Semua kelas'],['Pertemuan orang tua','17 Oktober · Aula sekolah'],['PPDB dibuka','Oktober 2026–Mei 2027']] as [$label,$date])<a href="/admin/agenda" class="detail-line"><x-icon name="file"/><div><strong>{{ __($label) }}</strong><small>{{ __($date) }}</small></div></a>@endforeach</x-card></div>
<x-card title="Ringkasan keuangan"><div class="money-summary"><div><p>{{ __('Tagihan SPP Oktober') }}</p><strong>Rp108 jt</strong></div><div><p>{{ __('Pembayaran terkonfirmasi') }}</p><strong>Rp81 jt</strong></div><div><p>{{ __('Sisa tagihan') }}</p><strong>Rp27 jt</strong></div></div><a href="/admin/pembayaran" class="card-link">{{ __('Lihat pembayaran') }}</a></x-card>
@endif
<footer class="footer">© 2026 SD Ceria Nusantara · {{ \App\Support\SchoolAccess::roleLabel(auth()->user()) }}</footer>
@endsection
