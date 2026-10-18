@php($review = app(\App\Services\WebsiteContent::class)->review())
<div class="cms cms-publication">
    <div class="cms-topline"><div><span class="cms-eyebrow">TINJAU SEBELUM TERBIT</span><h2>Perubahan website</h2><p>Bandingkan konten yang sedang tayang dengan draf tersimpan Anda.</p></div><a class="btn secondary" href="/admin/website">Lanjutkan mengedit</a></div>
    @if(!$review['pages'])
    <div class="cms-empty"><span class="cms-empty-icon" aria-hidden="true">✓</span><h3>Belum ada perubahan untuk diterbitkan</h3><p>Pilih halaman, ubah isinya, lalu klik <strong>Simpan & tinjau</strong>. Semua perubahan tersimpan akan muncul di sini.</p><a class="btn" href="/admin/website">Pilih halaman untuk diedit</a></div>
    @else
    <div class="cms-review-summary"><div><strong>{{ count($review['pages']) }}</strong><span>halaman dalam draf</span></div><div><strong>{{ $review['change_count'] }}</strong><span>isian berubah</span></div><p>{{ $review['has_conflicts'] ? 'Ada perubahan yang perlu diperiksa kembali sebelum terbit.' : 'Website publik masih menampilkan versi terbit hingga Anda menyelesaikan publikasi.' }}</p></div>
    <div class="cms-publish-layout"><div class="cms-reviews">
        @foreach($review['pages'] as $page)
        <article class="cms-review-card" data-review-section="{{ $page['section'] }}">
            <header><div><h3>{{ $page['name'] }}</h3><p>{{ count($page['changes']) }} isian berubah · {{ $page['author'] }}@if($page['updated_at']) · {{ \Carbon\Carbon::parse($page['updated_at'])->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB @endif</p></div><span class="cms-state">{{ $page['conflict'] ? 'Perlu diperiksa' : 'Draf' }}</span></header>
            @if($page['conflict'])<p class="cms-conflict" role="alert">{{ $page['conflict'] }}</p>@endif
            <div class="cms-review-actions"><a href="/admin/website/{{ $page['section'] }}/edit">Edit halaman</a><a href="/admin/website/preview?page={{ $page['section']==='settings'?'home':$page['section'] }}" target="_blank" rel="noopener">Lihat pratinjau draf ↗</a></div>
            @forelse($page['changes'] as $change)
            <details class="cms-change" @if($loop->first) open @endif><summary>{{ $change['label'] }}</summary><div class="cms-change-values">
                @foreach(['before'=>'Sedang tayang','after'=>'Setelah diterbitkan'] as $side=>$label)
                <div class="cms-change-{{ $side }}"><span>{{ $label }}</span>
                    @if($change['image'] && \App\Services\WebsiteContent::image((string)$change[$side]))<img src="{{ \App\Services\LandingPreview::asset($change[$side]) }}" alt="{{ $label }}: {{ $change['label'] }}" loading="lazy">
                    @else<p>{{ is_bool($change[$side]) ? ($change[$side] ? 'Ya' : 'Tidak') : (($change[$side] === null || $change[$side] === '') ? '— Kosong —' : $change[$side]) }}</p>@endif
                </div>
                @endforeach
            </div></details>
            @empty<p class="cms-help">Isi draf sudah sama dengan versi terbit. Draf ini dapat dibatalkan.</p>@endforelse
            <form method="post" action="/admin/website/{{ $page['section'] }}/discard" data-confirm="Batalkan seluruh perubahan draf {{ $page['name'] }}? Konten yang sedang tayang tetap digunakan.">@csrf<input type="hidden" name="version" value="{{ $page['version'] }}"><button class="cms-discard" type="submit">Batalkan draf halaman ini</button></form>
        </article>
        @endforeach
    </div>
    <form class="cms-publish-panel" method="post" action="/admin/website/publish" data-confirm="Terbitkan perubahan pada {{ count($review['pages']) }} halaman ke website sekolah?">
        @csrf<input type="hidden" name="review_version" value="{{ $review['version'] }}">
        <h3>Terbitkan perubahan</h3><p>{{ count($review['pages']) }} halaman akan diperbarui bersama. Periksa perbandingan isi di halaman ini sebelum melanjutkan.</p>
        <label class="field">Catatan <span class="muted">(opsional)</span><textarea name="notes" rows="3" maxlength="3000" placeholder="Contoh: Memperbarui informasi pendaftaran">{{ old('notes') }}</textarea></label>
        <label class="cms-publish-check"><input type="checkbox" name="confirmed" value="1" required><span>Saya sudah memeriksa perubahan dan siap menampilkannya di website.</span></label>
        @if($review['has_conflicts'])<p class="cms-conflict">Periksa halaman yang ditandai sebelum menerbitkan.</p>@endif
        <button class="btn" type="submit" @disabled($review['has_conflicts'] || $review['change_count']===0)>Terbitkan {{ count($review['pages']) }} halaman</button>
    </form></div>
    @endif
</div>
