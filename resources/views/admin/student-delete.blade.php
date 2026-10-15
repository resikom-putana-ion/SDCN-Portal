@extends('layouts.app')
@section('content')
@php
$inProgress=is_array($record->value('deletion_plan'));
$inactive=$record->value('status')==='Nonaktif';
@endphp
<x-notice>Untuk siswa yang pindah, lulus, atau berhenti, status <strong>Nonaktif</strong> adalah pilihan yang disarankan karena seluruh riwayat tetap utuh. Hapus permanen hanya untuk data duplikat, salah input, atau permintaan penghapusan yang sudah disetujui sekolah.</x-notice>

<div class="grid-2">
    <x-card title="Data yang akan dihapus">
        <dl class="definition">
            <dt>Siswa</dt><dd>{{ $record->value('name') }}</dd>
            <dt>NIS</dt><dd>{{ $record->code }}</dd>
            <dt>Status</dt><dd>{{ $record->value('status') }}</dd>
            <dt>Profil & kontak</dt><dd>Dihapus permanen</dd>
            <dt>Foto & dokumen</dt><dd>{{ $summary['documents'] ?? 0 }} dokumen dan foto profil dihapus</dd>
            <dt>Data operasional</dt><dd>{{ $summary['operational'] ?? 0 }} catatan, termasuk kehadiran, dihapus</dd>
            <dt>Data PPDB</dt><dd>{{ ($summary['application'] ?? 0) ? 'Dihapus bersama berkas pendaftaran' : 'Tidak terhubung' }}</dd>
        </dl>
    </x-card>
    <x-card title="Arsip yang tetap disimpan">
        <dl class="definition">
            <dt>Tagihan</dt><dd>{{ $summary['invoices'] ?? 0 }} arsip</dd>
            <dt>Pembayaran</dt><dd>{{ $summary['payments'] ?? 0 }} transaksi</dd>
            <dt>Nilai / rapor</dt><dd>{{ $summary['grades'] ?? 0 }} catatan akademik</dd>
            <dt>Identitas arsip</dt><dd>Nama, NIS, kelas, tahun ajaran, dan nama wali seperlunya</dd>
        </dl>
        <p class="muted">Arsip tersebut dilepas dari profil siswa aktif agar kuitansi, audit pembayaran, dan riwayat rapor tetap dapat dibuktikan.</p>
    </x-card>
</div>

<x-card title="Konfirmasi penghapusan permanen">
    @if($inProgress)
        <div class="flash danger" role="status">Penghapusan sudah dimulai dan dapat dilanjutkan dengan aman dari tahap terakhir yang berhasil.</div>
    @elseif(!$inactive)
        <div class="flash danger" role="alert">Siswa harus dinonaktifkan terlebih dahulu. Langkah ini mencegah profil aktif terhapus karena salah klik.</div>
        <form method="post" action="/admin/siswa/{{ $record->id }}/status" data-confirm="Nonaktifkan siswa ini? Data dan riwayatnya tetap tersimpan.">
            @csrf
            <input type="hidden" name="status" value="Nonaktif">
            <input type="hidden" name="confirmed" value="1">
            <div class="actions"><button class="btn secondary">Nonaktifkan siswa</button><a class="btn white" href="/admin/siswa/{{ $record->id }}">Kembali ke profil</a></div>
        </form>
    @endif

    @if($inactive || $inProgress)
        <form method="post" action="/admin/siswa/{{ $record->id }}" data-confirm="Hapus siswa ini secara permanen? Profil operasional tidak dapat dipulihkan.">
            @csrf
            @method('DELETE')
            <label class="field" style="max-width:520px;margin-bottom:20px">
                Ketik NIS <strong>{{ $record->code }}</strong> untuk mengonfirmasi
                <input name="nis" value="{{ old('nis') }}" autocomplete="off" required>
                @error('nis')<span class="error-text">{{ $message }}</span>@enderror
            </label>
            <label class="field" style="max-width:720px;margin-bottom:20px">
                Alasan penghapusan
                <textarea name="reason" rows="3" maxlength="500" required placeholder="Contoh: Data siswa tercatat dua kali dan profil ini adalah duplikat.">{{ old('reason', $record->value('deletion_plan.reason')) }}</textarea>
                @error('reason')<span class="error-text">{{ $message }}</span>@enderror
            </label>
            <label class="check-row"><input type="checkbox" name="confirmed" value="1" required>Saya memahami profil, kontak, foto, dokumen, dan data operasional siswa tidak dapat dipulihkan.</label>
            <div class="actions"><button class="btn danger">{{ $inProgress ? 'Lanjutkan penghapusan' : 'Hapus siswa permanen' }}</button><a class="btn white" href="/admin/siswa/{{ $record->id }}">Batal</a></div>
        </form>
    @endif
</x-card>
@endsection
