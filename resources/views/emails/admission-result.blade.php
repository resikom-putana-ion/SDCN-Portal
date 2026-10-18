<!DOCTYPE html>
<html lang="id"><body>
<p>Yth. {{ $record->value('guardian') }},</p>
<p>Hasil pemeriksaan administrasi pendaftaran {{ $record->code }} atas nama <strong>{{ $record->value('name') }}</strong>: <strong>{{ $record->value('status') }}</strong>.</p>
<p>{{ $record->value('notes') }}</p>
@if($record->value('status') === 'Lolos administrasi')
<p>Silakan mengikuti proses daftar ulang dan pemeriksaan dokumen di sekolah. Kelulusan administrasi belum merupakan aktivasi sebagai siswa.</p>
@endif
<p>Email ini dikirim otomatis. Mohon tidak membalas email ini; hubungi sekolah untuk informasi lebih lanjut.</p>
</body></html>
