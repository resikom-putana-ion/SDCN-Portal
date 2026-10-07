@extends('layouts.app')
@section('content')
@php
$school=\App\Support\School::class;
@endphp
@if($screen==='siswa')<x-stats :items="[[$school::metric('students',360,4),'Siswa aktif','users'],[$school::metric('staff',28,4),'Guru & staf','briefcase'],[12,'Kelas aktif','school']]"/>
@elseif($screen==='guru')<x-stats :items="[[22,'Guru aktif','users'],[6,'Staf aktif','briefcase'],[$school::metric('staff',28,4),'Total pendidik & staf','users']]"/>
@elseif($screen==='pendaftar')<x-stats :items="[[$school::metric('applicants',68,4),'Pendaftar','file'],[14,'Sedang diperiksa','search'],[8,'Perlu perbaikan','file'],[12,'Menunggu daftar ulang','calendar']]"/><x-notice>Alur PPDB: daftar online → pemeriksaan Admin → daftar ulang offline → diterima → siswa aktif.</x-notice>
@elseif($screen==='kas')<x-stats :items="[['Rp81 jt','Pemasukan bulan ini','wallet'],['Rp56 jt','Pengeluaran bulan ini','wallet'],['Rp25 jt','Selisih kas','chart']]"/>
@elseif($screen==='daftar-ulang')<x-notice>Petugas menyelesaikan pemeriksaan dokumen asli, pembayaran, dan konfirmasi daftar ulang di sekolah.</x-notice>
@elseif($screen==='alat')<x-notice>Fitur berikut berasal dari brainstorming sebelumnya. Penundaan atau penggabungannya belum disetujui.</x-notice>
@endif
@if($group==='website')@include('website.toolbar')@endif
@if($screen==='nilai')<div class="tabs">@foreach(['nilai'=>'Nilai','rapor'=>'Rapor semester','perkembangan'=>'Perkembangan','ujian'=>'Ujian'] as $key=>$label)<a class="{{ request('tab','nilai')===$key?'active':'' }}" href="?tab={{ $key }}">{{ $label }}</a>@endforeach</div>@endif
@if(!in_array($screen,['alat','aktivitas']))
<form class="filters" data-filter-form method="get"><label class="search-field"><x-icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="{{ in_array($screen,['pendaftar','daftar-ulang'])?'Cari anak, wali, nomor daftar...':($screen==='guru'?'Cari nama atau ID pegawai...':'Cari nama atau nomor induk...') }}" aria-label="Cari data"></label>
@php
$options=$screen==='siswa'?['Semua kelas','1A','1B','2A','5A']:($screen==='guru'?['Semua jabatan','Guru kelas','Tata Usaha']:['Semua status','Aktif','Pemeriksaan','Lolos administrasi','Menunggu daftar ulang','Perlu perbaikan','Terbit','Draf','Hadir','Izin']);
@endphp
<select name="filter" data-auto-submit aria-label="Filter data">@foreach($options as $option)<option value="{{ $loop->first?'':$option }}" @selected(request('filter')===$option)>{{ $option }}</option>@endforeach</select><select aria-label="Tahun ajaran" name="year" data-auto-submit><option>{{ in_array($screen,['pendaftar','daftar-ulang'])?'2027/2028':'2026/2027' }}</option></select><a href="/admin/export/{{ $kind }}?{{ http_build_query(request()->only('q','filter')) }}" class="btn white">{{ __('Ekspor') }}</a>
@if($create)<a class="btn" href="{{ $screen==='kelas'?'/admin/kenaikan':'/admin/'.$screen.'/baru' }}">{{ __($create) }}</a>@endif</form>@endif
@php
$rows=$records->map(function($r)use($kind,$screen){$data=$kind==='invoices'?\App\Support\School::invoice($r):$r->data; return [...$data,'_url'=>in_array($kind,['tools','activities'])?null:'/admin/'.$screen.'/'.$r->id];});
if($screen==='nilai' && request('tab')==='perkembangan') $columns=['name'=>'Siswa','class'=>'Kelas','notes'=>'Perkembangan & catatan wali kelas','author'=>'Wali kelas'];
if($screen==='nilai' && request('tab')==='ujian') $columns=['name'=>'Siswa','subject'=>'Mata pelajaran','semester'=>'Semester','score'=>'Nilai','status'=>'Status'];
@endphp
<x-table :columns="$columns" :rows="$rows"/>
@if($screen==='siswa')<x-notice>Klik nama siswa untuk melihat profil, keluarga, kehadiran, tagihan, nilai dan dokumen.</x-notice>@endif
@if($screen==='pendaftar')<div class="actions"><a class="btn secondary" href="/admin/daftar-ulang">Daftar ulang offline</a><a class="btn secondary" href="/admin/periode">Atur periode & kuota</a></div>@endif
@if($screen==='alat')<div class="actions"><a class="btn secondary" href="/admin/audit">Lihat audit fitur</a><a class="btn white" href="/admin">Kembali ke beranda</a></div>@endif
@if($screen==='nilai')<x-notice>Rapor diperiksa sebelum dibagikan kepada wali.</x-notice>@endif
@endsection
