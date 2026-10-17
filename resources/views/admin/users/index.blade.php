@extends('layouts.app')
@section('content')
@php($canManage = auth()->user()->hasFullSchoolAccess())
<x-notice>Owner dan Admin menambah akun serta mengubah role dan status. Profil pribadi diubah oleh pemilik akun masing-masing. Owner dan Admin dapat menghapus akun pengelola lain; hanya Owner yang dapat menghapus Owner lain. Akun sendiri dan Owner aktif terakhir tidak dapat dihapus.</x-notice>
<div class="filters">
    <p class="muted">{{ $users->count() }} akun pengguna</p>
    @if($canManage)<a class="btn" href="/admin/pengguna/baru">Tambah akun</a>@endif
</div>
<form id="delete-selected-accounts" method="post" action="/admin/pengguna/hapus/konfirmasi">@csrf</form>
<div class="table-wrap" data-table-page>
    <table>
        <thead><tr>
            <th scope="col">Pilih</th>
            <th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Role</th>
            <th scope="col">Akses</th><th scope="col">Diperbarui</th><th scope="col">Status</th>
            <th scope="col">Tindakan</th>
        </tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>@if(auth()->user()->canDeleteAccount($user))<input type="checkbox" name="accounts[]" value="{{ $user->id }}" form="delete-selected-accounts" aria-label="Pilih akun {{ $user->name }} untuk dihapus">@endif</td>
                <td>{{ $user->name }}@if((string) $user->id === (string) auth()->id()) <small class="muted">(Anda)</small>@endif</td>
                <td>{{ $user->email }}</td>
                <td>{{ $roles[$user->role] ?? ucfirst($user->role) }}</td>
                <td>{{ match($user->role) {'owner'=>'Seluruh fitur & akun','admin'=>'Seluruh operasional sekolah','keuangan'=>'Tagihan, pembayaran & kas','akademik'=>'Siswa, kelas, nilai & rapor',default=>'Akses terbatas'} }}</td>
                <td>{{ $user->updated_at ? \App\Support\School::date($user->updated_at) : '—' }}</td>
                <td>@if($user->deletion_pending)<span class="error-text">Penghapusan tertunda</span>@else<x-badge :value="$user->active ? 'Aktif' : 'Nonaktif'"/>@endif</td>
                <td><div class="actions">
                    @if($canManage && !$user->deletion_pending)<a href="/admin/pengguna/{{ $user->id }}/edit" aria-label="Kelola akun {{ $user->name }}">Kelola</a>@endif
                    @if(auth()->user()->canDeleteAccount($user))<a class="error-text" href="/admin/pengguna/{{ $user->id }}/hapus" aria-label="Hapus akun {{ $user->name }}">{{ $user->deletion_pending ? 'Lanjutkan hapus' : 'Hapus' }}</a>@endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty">Belum ada akun pengguna.</div></td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="table-footer">
        <span>Menampilkan {{ $users->count() }} akun</span>
        <div class="pagination">
            <button type="button" class="prev" data-prev aria-label="Halaman sebelumnya"><x-icon name="chevron"/></button>
            <span data-page-number>1</span>
            <button type="button" data-next aria-label="Halaman berikutnya"><x-icon name="chevron"/></button>
        </div>
    </div>
</div>
<div class="actions"><button class="btn danger" type="submit" form="delete-selected-accounts">Hapus akun yang dipilih</button><span class="muted">Pilih maksimal 20 akun. Daftar akun akan ditampilkan sebelum konfirmasi.</span></div>
@endsection
