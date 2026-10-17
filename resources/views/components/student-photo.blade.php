@props(['record'=>null, 'staff'=>false, 'admin'=>false])
<section data-student-photo data-crop-field class="student-photo-editor">
    <h3>Foto {{ $admin ? 'admin' : ($staff ? 'guru / staf' : 'siswa') }} (opsional)</h3>
    <p class="muted">Pilih JPG, PNG, atau WebP maksimal 5 MB. Geser bingkai dan tarik sudut grid untuk mengatur potongan persegi.</p>
    <img data-photo-preview class="student-photo-preview" src="{{ $admin ? ((auth()->user()->photo_path ?? null) ? '/admin/foto-admin' : '/assets/staff.png') : ($record?->value('photo_path') ? '/admin/'.($staff ? 'foto-guru' : 'foto-siswa').'/'.$record->id : '/assets/'.($staff ? 'teacher.png' : 'student.png')) }}" alt="Pratinjau foto {{ $admin ? 'admin' : ($staff ? 'guru / staf' : 'siswa') }}">
    <label class="field">Pilih foto<input type="file" accept="image/jpeg,image/png,image/webp" data-photo-file data-crop-input data-crop-mode="profile" data-crop-aspect="1" data-crop-size="512"></label>
    <input type="hidden" name="photo_crop" value="{{ old('photo_crop') }}" data-photo-result>
    <small class="error-text" data-photo-error aria-live="polite"></small>
    @error('photo_crop')<small class="error-text">{{ $message }}</small>@enderror
</section>
