@props(['record'=>null])
<section data-student-photo class="student-photo-editor">
    <h3>Foto siswa (opsional)</h3>
    <p class="muted">Pilih JPG, PNG, atau WebP maksimal 5 MB. Atur potongan persegi agar wajah terlihat rapi.</p>
    <img data-photo-preview class="student-photo-preview" src="{{ $record?->value('photo_path') ? '/admin/foto-siswa/'.$record->id : '/assets/student.png' }}" alt="Pratinjau foto siswa">
    <label class="field">Pilih foto<input type="file" accept="image/jpeg,image/png,image/webp" data-photo-file></label>
    <input type="hidden" name="photo_crop" value="{{ old('photo_crop') }}" data-photo-result>
    <small class="error-text" data-photo-error aria-live="polite"></small>
    @error('photo_crop')<small class="error-text">{{ $message }}</small>@enderror
    <div data-photo-crop hidden>
        <canvas width="512" height="512" data-photo-canvas aria-label="Pratinjau potongan foto"></canvas>
        <label class="field">Zoom<input type="range" min="1" max="3" step="0.01" value="1" data-photo-zoom></label>
        <label class="field">Posisi horizontal<input type="range" min="0" max="100" value="50" data-photo-x></label>
        <label class="field">Posisi vertikal<input type="range" min="0" max="100" value="50" data-photo-y></label>
        <div class="actions"><button type="button" class="btn secondary" data-photo-apply>Gunakan potongan</button><button type="button" class="btn white" data-photo-cancel>Batal crop</button></div>
    </div>
</section>
