@extends('layouts.app')
@section('content')
@php
$active=request('tab','verifikasi');
$invoices=\App\Models\SchoolRecord::ofKind('invoices')->get();
if(request('student'))$invoices=$invoices->filter(fn($r)=>(string)$r->value('student_id')===request('student'));
$invoice=$invoices->firstWhere('id',(int)request('invoice'))??$invoices->first();
$d=$invoice?\App\Support\School::invoice($invoice):null;
$payments=$invoice?\App\Support\CloudData::table('school_payments')->where('invoice_id',$invoice->id)->orderByDesc('id')->get():collect();
@endphp
<div class="tabs">@foreach(['verifikasi'=>['Verifikasi','users'],'tunai'=>['Tunai di sekolah','calendar'],'riwayat'=>['Riwayat','wallet'],'kuitansi'=>['Kuitansi','book']] as $key=>[$label,$icon])<a class="{{ $active===$key?'active':'' }}" href="?tab={{ $key }}&invoice={{ $invoice?->id }}"><x-icon :name="$icon"/>{{ $label }}</a>@endforeach</div>
<x-card title="Cari tagihan siswa"><form method="get" class="form-grid no-margin"><input type="hidden" name="tab" value="{{ $active }}"><label class="field">Nama / nomor induk<select name="invoice" data-auto-submit>@foreach($invoices as $item)<option value="{{ $item->id }}" @selected($invoice?->id===$item->id)>{{ $item->value('name') }}</option>@endforeach</select></label><label class="field">Tahun / periode<input value="2026/2027 · {{ $d['period']??'—' }}" readonly></label></form></x-card>
@if($invoice)@php
$student=\App\Models\SchoolRecord::find($invoice->value('student_id'));
@endphp<x-card :title="($student?->value('name')??'').' · Kelas '.($d['class']??'')"><p class="muted" style="margin-bottom:16px">{{ $d['name'] }}</p><x-stats :items="[[\App\Support\School::money($d['amount']),'Tagihan','wallet'],[\App\Support\School::money($d['paid']),'Dibayar','check'],[\App\Support\School::money($d['balance']),'Sisa','file']]"/></x-card>
@if($active==='tunai')<x-card title="Catat Pembayaran Tunai"><form action="/admin/pembayaran/catat" method="post" data-confirm="Simpan pembayaran tunai ini? Nominal akan langsung mengurangi sisa tagihan.">@csrf<input type="hidden" name="invoice_id" value="{{ $invoice->id }}"><input type="hidden" name="method" value="Tunai di sekolah"><div class="form-grid"><label class="field">Nominal diterima<input type="number" name="amount" min="1" max="{{ $d['balance'] }}" value="{{ old('amount',$d['balance']) }}" required></label><label class="field">Tanggal penerimaan<input type="date" name="received_at" value="{{ old('received_at',now()->toDateString()) }}" required></label><label class="field">Referensi / nomor kuitansi<input name="reference" value="{{ old('reference','KW-SPP-'.now()->format('YmdHis')) }}" required></label><label class="field">Petugas penerima<input value="{{ auth()->user()->name }}" readonly></label></div><div class="actions"><button class="btn" @disabled($d['balance']===0)>Simpan perubahan</button><a href="/admin/pembayaran" class="btn secondary">Batal</a></div></form></x-card>
@else
@php
$rows=$payments->filter(fn($p)=>$active!=='kuitansi'||$p->status==='Terverifikasi')->map(fn($p)=>['reference'=>$p->reference,'method'=>$p->method,'amount'=>$p->amount,'date'=>$p->received_at,'status'=>$p->status,'_url'=>$p->status==='Menunggu'?'/admin/periksa-pembayaran/'.$p->id:'/admin/kuitansi/'.$p->id]);
$pending=$payments->firstWhere('status','Menunggu');
@endphp
<x-table :columns="['reference'=>'Referensi','method'=>'Metode','amount'=>'Nominal','date'=>'Tanggal','status'=>'Status']" :rows="$rows"/>
<x-notice>SPP: virtual account / QR dapat dikonfirmasi otomatis; transfer manual diperiksa petugas; tunai dicatat di sekolah.</x-notice><div class="actions">@if($pending)<a href="/admin/periksa-pembayaran/{{ $pending->id }}" class="btn">Periksa pembayaran</a>@endif<a href="?tab=tunai&invoice={{ $invoice->id }}" class="btn secondary">Catat pembayaran tunai</a><a href="?tab=kuitansi&invoice={{ $invoice->id }}" class="btn secondary">Lihat kuitansi</a></div>@endif
@else<div class="empty">Belum ada tagihan siswa.</div>@endif
@endsection
