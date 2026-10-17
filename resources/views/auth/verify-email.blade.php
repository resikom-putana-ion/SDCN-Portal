<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Verifikasi email · SD Ceria Nusantara</title><link rel="stylesheet" href="/css/app.css"><link rel="icon" href="/assets/logo.png"></head>
<body class="recovery-page"><main class="recovery-card auth-form" style="width:min(520px, calc(100% - 32px));transform:none">
    <h1>Verifikasi email Anda</h1>
    <p class="intro">Konfirmasikan kepemilikan <strong>{{ auth()->user()->email }}</strong> untuk membuka dashboard. Ini juga berlaku untuk akun lama yang belum diverifikasi.</p>
    @if($errors->any())<div class="flash danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
    <form method="post" action="/verify-email">@csrf
        <label class="field">Konfirmasi kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn full">Kirim / kirim ulang email verifikasi</button>
    </form>
    <p class="footnote">Setelah membuka tautan verifikasi di email, kembali ke halaman ini.</p>
    <a href="/verify-email" class="btn secondary full">Saya sudah verifikasi</a>
    <p class="footnote"><a href="/forgot-password">Lupa kata sandi?</a></p>
    <form method="post" action="/logout">@csrf<button class="btn secondary full">Keluar / gunakan akun lain</button></form>
</main></body></html>
