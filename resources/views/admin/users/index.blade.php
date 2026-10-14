@extends('layouts.app')
@section('content')
@php($canManage = auth()->user()->isOwner())
<x-notice>Owner memiliki akses penuh ke sekolah, website, dan pengelolaan akun. Admin dapat mengelola sekolah dan website. Penambahan akun serta perubahan role dan status dikelola oleh Owner.</x-notice>
<div class="filters">
    <p class="muted">{{ $users->count() }} akun pengguna</p>
    @if($canManage)<a class="btn" href="/admin/pengguna/baru">Tambah akun</a>@endif
</div>
<div class="table-wrap" data-table-page>
    <table>
        <thead><tr>
            <th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Role</th>
            <th scope="col">Akses</th><th scope="col">Diperbarui</th><th scope="col">Status</th>
            @if($canManage)<th scope="col">Tindakan</th>@endif
        </tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}@if((string) $user->id === (string) auth()->id()) <small class="muted">(Anda)</small>@endif</td>
                <td>{{ $user->email }}</td>
                <td>{{ $roles[$user->role] ?? ucfirst($user->role) }}</td>
                <td>{{ $user->isOwner() ? 'Sekolah, website & akun' : ($user->role === 'admin' ? 'Sekolah & website' : 'Akses terbatas') }}</td>
                <td>{{ $user->updated_at ? \App\Support\School::date($user->updated_at) : '—' }}</td>
                <td><x-badge :value="$user->active ? 'Aktif' : 'Nonaktif'"/></td>
                @if($canManage)<td><a href="/admin/pengguna/{{ $user->id }}/edit" aria-label="Kelola akun {{ $user->name }}">Kelola</a></td>@endif
            </tr>
        @empty
            <tr><td colspan="{{ $canManage ? 7 : 6 }}"><div class="empty">Belum ada akun pengguna.</div></td></tr>
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
@endsection
