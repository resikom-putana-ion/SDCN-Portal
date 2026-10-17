<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pemulihan akun · SD Ceria Nusantara</title>
    <link rel="stylesheet" href="/css/app.css">
    <meta name="referrer" content="no-referrer">
</head>
<body class="recovery-page">
<main class="recovery-card">
    <a href="/login" class="brand"><img src="/assets/logo.png" alt="Logo sekolah"><span><strong>SD Ceria Nusantara</strong><small>Belajar ceria, tumbuh bersama.</small></span></a>
    @if($mode === 'forgot' && session('sent'))
        <h1>Periksa email Anda</h1>
        <p>Jika email terdaftar dan akun aktif, tautan pemulihan dikirim ke email akun. Periksa juga folder Spam.</p>
        <p>
            @if(!session('firebase_recovery'))
                Tautan berlaku {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire') }} menit.
            @endif
            Tautan hanya bisa digunakan sekali. Jika baru saja meminta tautan, gunakan email terakhir atau tunggu satu menit sebelum meminta lagi.
        </p>
        @if(session('firebase_recovery'))<p>Buat kata sandi baru melalui halaman yang dibuka dari email, lalu kembali ke website sekolah untuk masuk.</p>@endif
        <a href="/login" class="btn full">Kembali masuk</a>
        <a href="/forgot-password" class="back-link">Belum menerima email? Kirim ulang</a>
    @else
        <h1>{{ $mode === 'reset' ? 'Buat kata sandi baru' : 'Lupa kata sandi' }}</h1>
        <p>{{ $mode === 'reset' ? 'Buat kata sandi baru, lalu gunakan untuk masuk ke akun sekolah Anda.' : 'Masukkan email akun sekolah Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.' }}</p>
        @if($errors->any())<div class="flash danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ $mode === 'reset' ? '/reset-password' : '/forgot-password' }}">
            @csrf
            <label class="field">Email<input type="email" name="email" value="{{ old('email', request('email')) }}" required autocomplete="email"></label>
            @if($mode === 'reset')
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="field">Kata sandi baru<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
                <label class="field">Konfirmasi kata sandi<input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></label>
            @endif
            <button class="btn full">{{ $mode === 'reset' ? 'Simpan kata sandi' : 'Kirim tautan pemulihan' }}</button>
        </form>
    @endif
    <a href="/login" class="back-link">Kembali ke halaman masuk</a>
</main>
</body>
</html>
