<link rel="stylesheet" href="/css/photo-crop.css">
<script src="/js/photo-crop.js" defer></script>
<dialog class="photo-crop-dialog" data-photo-dialog aria-labelledby="photo-crop-title" aria-describedby="photo-crop-help">
    <header class="photo-crop-heading">
        <div><h2 id="photo-crop-title">Atur potongan foto</h2><p id="photo-crop-help">Geser bingkai atau tarik sudutnya. Geser foto di luar bingkai; scroll atau pinch untuk zoom.</p></div>
        <button type="button" class="photo-crop-close" data-crop-cancel aria-label="Tutup crop foto">×</button>
    </header>
    <div class="photo-crop-toolbar">
        <label class="photo-crop-ratio">Rasio <select data-crop-ratio aria-label="Rasio potongan"><option value="original">Asli</option><option value="free">Bebas</option><option value="1">1:1</option><option value="1.3333333333">4:3</option><option value="1.7777777778">16:9</option><option value="0.75">3:4</option></select></label>
        <div class="photo-crop-zoom"><button type="button" data-crop-zoom-out aria-label="Perkecil foto">−</button><output data-crop-zoom aria-live="polite">100%</output><button type="button" data-crop-zoom-in aria-label="Perbesar foto">+</button></div>
        <button type="button" class="btn secondary small" data-crop-reset>Atur ulang</button>
    </div>
    <div class="photo-crop-stage" data-crop-stage>
        <canvas width="960" height="640" data-photo-canvas aria-label="Foto yang sedang dipotong"></canvas>
        <div class="photo-crop-frame" data-crop-frame tabindex="0" role="group" aria-label="Bingkai crop. Gunakan tombol panah untuk menggeser, tambah dan kurang untuk zoom.">
            <span class="photo-crop-grid" aria-hidden="true"></span>
            @foreach(['nw'=>'kiri atas','ne'=>'kanan atas','sw'=>'kiri bawah','se'=>'kanan bawah'] as $corner=>$label)
                <button type="button" class="photo-crop-handle {{ $corner }}" data-crop-handle="{{ $corner }}" aria-label="Ubah ukuran dari sudut {{ $label }}"></button>
            @endforeach
        </div>
    </div>
    <div class="photo-crop-details"><span data-crop-dimensions></span><span>Panah: geser · Shift: lebih cepat</span></div>
    <p class="error-text" data-crop-dialog-error role="alert"></p>
    <footer class="photo-crop-actions"><button type="button" class="btn secondary" data-crop-cancel>Batal crop</button><button type="button" class="btn" data-photo-apply>Gunakan potongan</button></footer>
</dialog>
