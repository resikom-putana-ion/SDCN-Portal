@extends('layouts.app')
@section('content')
<x-notice>Owner dan Admin dapat menambah akun serta mengubah role dan status setiap akun. Identitas, nomor HP, dan foto profil hanya diubah oleh pemilik akun melalui Pengaturan. Keuangan menangani keuangan sekolah; Akademik menangani siswa, guru, kelas, absensi, nilai, dan rapor.</x-notice>
<x-card :title="$account ? 'Informasi & akses akun' : 'Akun pengguna baru'">
    <form method="post" action="{{ $account ? '/admin/pengguna/'.$account->id : '/admin/pengguna' }}" @if($account) data-confirm="Simpan perubahan role dan status akun ini?" @endif>
        @csrf
        @if($account) @method('PUT') @endif
        <div class="form-grid">
            @if($account)<div class="field">
                <label for="account-name">Nama lengkap</label>
                <input id="account-name" value="{{ $account->name }}" readonly>
                <small class="muted">Nama diisi sendiri oleh pemilik akun di profil pribadi.</small>
            </div>@endif
            <div class="field">
                <label for="account-email">Email login</label>
                @if($account)
                    <input id="account-email" type="email" value="{{ $account->email }}" aria-describedby="account-email-help" readonly>
                    <small id="account-email-help" class="muted">Email login tetap. Gunakan akun baru untuk alamat email berbeda.</small>
                @else
                    <input id="account-email" type="email" name="email" value="{{ old('email') }}" maxlength="190" autocomplete="off" required>
                    <small class="muted">Gunakan email Google / Google Workspace yang dapat dibuka oleh pemiliknya. Verifikasi melalui tautan email diperlukan sebelum membuka dashboard.</small>
                @endif
            </div>
            <div class="field">
                <label for="account-role">Role</label>
                <select id="account-role" name="role" required>
                    @foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $account?->role ?? 'admin') === $value)>{{ $label }}</option>@endforeach
                </select>
                <small class="muted">Menu dan URL dibatasi otomatis sesuai role.</small>
            </div>
            <div class="field">
                <label for="account-active">Status akun</label>
                <select id="account-active" name="active" aria-describedby="account-active-help" required>
                    <option value="1" @selected((string) old('active', $account ? (int) $account->active : 1) === '1')>Aktif</option>
                    <option value="0" @selected((string) old('active', $account ? (int) $account->active : 1) === '0')>Nonaktif</option>
                </select>
                <small id="account-active-help" class="muted">Akun nonaktif tidak dapat masuk atau mengakses dashboard.</small>
            </div>
            @unless($account)
                <div class="field">
                    <label for="account-password">Kata sandi</label>
                    <input id="account-password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="account-password-help" required>
                    <small id="account-password-help" class="muted">Minimal 8 karakter. Berikan email dan kata sandi kepada pemilik akun.</small>
                </div>
                <div class="field">
                    <label for="account-password-confirmation">Konfirmasi kata sandi</label>
                    <input id="account-password-confirmation" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
            @endunless
        </div>
        @if($account?->isOwner())<p class="muted">Sekolah harus memiliki minimal satu Owner aktif. Siapkan Owner lain sebelum menonaktifkan atau mengganti role Owner terakhir.</p>@endif
        <div class="actions">
            <button class="btn">{{ $account ? 'Simpan perubahan akun' : 'Buat akun' }}</button>
            <a class="btn secondary" href="/admin/pengguna">Batal</a>
        </div>
    </form>
</x-card>
@endsection
