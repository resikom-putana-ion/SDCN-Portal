@extends('layouts.app')
@section('content')
<x-notice>Penghapusan akun bersifat permanen. Akses login, profil akun, dan foto profil akan dihapus. Riwayat aktivitas, pembayaran, serta data sekolah yang pernah dikelola tetap disimpan.</x-notice>
<x-card title="Konfirmasi penghapusan akun">
    <p>Anda akan menghapus {{ count($accounts) }} akun berikut:</p>
    <div class="table-wrap">
        <table><thead><tr><th>Nama</th><th>Email</th><th>Role</th></tr></thead><tbody>
            @foreach($accounts as $account)
                <tr><td>{{ $account->name }}</td><td>{{ $account->email }}</td><td>{{ $roles[$account->role] ?? $account->role }}</td></tr>
            @endforeach
        </tbody></table>
    </div>
    <form method="post" action="/admin/pengguna/hapus">
        @csrf
        @method('DELETE')
        @foreach($accounts as $account)<input type="hidden" name="accounts[]" value="{{ $account->id }}">@endforeach
        <label class="field">Ketik HAPUS untuk melanjutkan<input name="confirmation" required pattern="HAPUS" autocomplete="off" placeholder="HAPUS"></label>
        <div class="actions"><button class="btn danger">Hapus akun permanen</button><a class="btn secondary" href="/admin/pengguna">Batal</a></div>
    </form>
</x-card>
@endsection
