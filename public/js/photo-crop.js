(() => {
    const dialog = document.querySelector('[data-photo-dialog]');
    const inputs = [...document.querySelectorAll('[data-crop-input]')];
    if (!dialog || !inputs.length) return;
    const find = name => dialog.querySelector(`[data-crop-${name}]`);
    const stage = find('stage'), frame = find('frame'), ratioSelect = find('ratio');
    const canvas = dialog.querySelector('[data-photo-canvas]'), context = canvas.getContext('2d');
    const apply = dialog.querySelector('[data-photo-apply]'), message = find('dialog-error');
    const applyLabel = apply.textContent;
    const W = canvas.width, H = canvas.height, saved = new WeakMap(), pointers = new Map();
    let state = null, gesture = null, revision = 0;
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const point = event => {
        const bounds = stage.getBoundingClientRect();
        return { x: (event.clientX - bounds.left) * W / bounds.width, y: (event.clientY - bounds.top) * H / bounds.height };
    };
    const setFile = (input, file) => {
        const transfer = new DataTransfer();
        if (file) transfer.items.add(file);
        input.files = transfer.files;
    };
    const bounds = () => ({ left: Math.max(0, state.x), top: Math.max(0, state.y),
        right: Math.min(W, state.x + state.image.naturalWidth * state.scale),
        bottom: Math.min(H, state.y + state.image.naturalHeight * state.scale) });
    const outputSize = () => {
        const nativeWidth = state.box.w / state.scale, nativeHeight = state.box.h / state.scale;
        const size = Number(state.input.dataset.cropSize) || Math.min(1600, Math.max(nativeWidth, nativeHeight));
        const scale = size / Math.max(nativeWidth, nativeHeight);
        return [Math.max(1, Math.round(nativeWidth * scale)), Math.max(1, Math.round(nativeHeight * scale))];
    };
    const draw = () => {
        if (!state?.image) return;
        context.clearRect(0, 0, W, H);
        context.drawImage(state.image, state.x, state.y, state.image.naturalWidth * state.scale, state.image.naturalHeight * state.scale);
        const box = state.box;
        Object.assign(frame.style, { left: `${box.x / W * 100}%`, top: `${box.y / H * 100}%`, width: `${box.w / W * 100}%`, height: `${box.h / H * 100}%` });
        find('zoom').textContent = `${Math.round(state.scale / state.fit * 100)}%`;
        find('dimensions').textContent = outputSize().join(' × ') + ' px';
    };
    const reset = () => {
        if (!state?.image) return;
        const image = state.image;
        state.fit = Math.min((W - 64) / image.naturalWidth, (H - 64) / image.naturalHeight);
        state.scale = state.fit;
        const width = image.naturalWidth * state.scale, height = image.naturalHeight * state.scale;
        state.x = (W - width) / 2; state.y = (H - height) / 2;
        state.ratio = ratioSelect.value === 'original' ? image.naturalWidth / image.naturalHeight : Number(ratioSelect.value) || 0;
        const w = state.ratio ? Math.min(width, height * state.ratio) : width;
        const h = state.ratio ? w / state.ratio : height;
        state.box = { x: (W - w) / 2, y: (H - h) / 2, w, h };
        pointers.clear(); gesture = null; draw();
    };
    const transformImage = (scale, x, y) => {
        const image = state.image, box = state.box;
        state.scale = scale;
        state.x = clamp(x, box.x + box.w - image.naturalWidth * scale, box.x);
        state.y = clamp(y, box.y + box.h - image.naturalHeight * scale, box.y);
        draw();
    };
    const zoom = (factor, origin = null) => {
        if (!state?.image) return;
        origin ??= { x: state.box.x + state.box.w / 2, y: state.box.y + state.box.h / 2 };
        const min = Math.max(state.box.w / state.image.naturalWidth, state.box.h / state.image.naturalHeight);
        const scale = clamp(state.scale * factor, min, state.fit * 16), change = scale / state.scale;
        transformImage(scale, origin.x - (origin.x - state.x) * change, origin.y - (origin.y - state.y) * change);
    };
    const move = (box, dx, dy) => {
        const limit = bounds();
        state.box = { ...box, x: clamp(box.x + dx, limit.left, limit.right - box.w), y: clamp(box.y + dy, limit.top, limit.bottom - box.h) };
        draw();
    };
    const resize = (box, handle, dx, dy) => {
        const limit = bounds(), west = handle.includes('w'), north = handle.includes('n');
        const ax = west ? box.x + box.w : box.x, ay = north ? box.y + box.h : box.y;
        const maxW = west ? ax - limit.left : limit.right - ax, maxH = north ? ay - limit.top : limit.bottom - ay;
        let w = box.w + (west ? -dx : dx), h = box.h + (north ? -dy : dy);
        if (state.ratio) {
            const ratio = state.ratio, max = Math.min(maxW, maxH * ratio);
            w = clamp((w + h / ratio) / (1 + 1 / (ratio * ratio)), Math.min(48, max), max); h = w / ratio;
        } else {
            w = clamp(w, Math.min(48, maxW), maxW); h = clamp(h, Math.min(48, maxH), maxH);
        }
        state.box = { x: west ? ax - w : ax, y: north ? ay - h : ay, w, h }; draw();
    };
    const close = (restore = true) => {
        if (!state) return;
        const input = state.input;
        if (restore) setFile(input, saved.get(input).file);
        input.setCustomValidity('');
        state = null; revision++; gesture = null; pointers.clear();
        stage.classList.remove('is-dragging'); apply.disabled = false; apply.textContent = applyLabel;
        if (dialog.open) dialog.close();
        input.focus({ preventScroll: true });
    };
    for (const input of inputs) {
        const field = input.closest('[data-crop-field]') || input.parentElement;
        let error = field.querySelector('[data-photo-error]');
        if (!error) { error = document.createElement('small'); error.className = 'error-text'; error.setAttribute('role', 'alert'); input.after(error); }
        const preview = field.querySelector('[data-photo-preview], [data-crop-preview]');
        const result = field.querySelector('[data-photo-result]');
        if (result?.value && preview) preview.src = result.value;
        saved.set(input, { file: null, preview, original: preview?.getAttribute('src'), url: null, error, result });
        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (state) close();
            const record = saved.get(input);
            record.error.textContent = '';
            if (!file) { setFile(input, record.file); return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                setFile(input, record.file); record.error.textContent = 'Pilih JPG, PNG, atau WebP maksimal 5 MB.'; return;
            }
            const token = ++revision;
            state = { input, file, token, image: null };
            input.setCustomValidity('Selesaikan crop foto atau pilih Batal crop sebelum menyimpan.');
            const url = URL.createObjectURL(file), image = new Image();
            try {
                image.src = url; await image.decode();
                if (token !== revision) return;
                if (!image.naturalWidth || image.naturalWidth * image.naturalHeight > 40000000) throw new Error('image');
                state.image = image;
                ratioSelect.disabled = Boolean(input.dataset.cropAspect);
                ratioSelect.value = input.dataset.cropAspect || 'original';
                message.textContent = ''; reset(); dialog.showModal(); frame.focus();
            } catch {
                if (token === revision) { close(); record.error.textContent = 'Foto tidak dapat dibaca atau resolusinya terlalu besar. Pilih gambar lain (maksimal 40 megapiksel).'; }
            } finally { URL.revokeObjectURL(url); }
        });
        input.form?.addEventListener('reset', () => {
            if (state?.input === input) close();
            const record = saved.get(input);
            if (record.url) URL.revokeObjectURL(record.url);
            record.file = null; record.url = null; record.error.textContent = ''; input.setCustomValidity('');
            if (record.preview) {
                if (record.original) record.preview.src = record.original;
                else { record.preview.remove(); record.preview = null; }
            }
        });
    }
    const beginGesture = (event, start) => {
        if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            gesture = { mode: 'pinch', distance: Math.hypot(a.x - b.x, a.y - b.y), center: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, scale: state.scale, x: state.x, y: state.y };
        } else {
            const handle = event.target.closest('[data-crop-handle]')?.dataset.cropHandle;
            gesture = { mode: handle ? 'resize' : frame.contains(event.target) ? 'move' : 'pan', handle, start, box: { ...state.box }, x: state.x, y: state.y };
        }
    };
    stage.addEventListener('pointerdown', event => {
        if (!state?.image || pointers.size >= 2 || (event.pointerType === 'mouse' && event.button !== 0)) return;
        event.preventDefault(); frame.focus({ preventScroll: true });
        const start = point(event); pointers.set(event.pointerId, start);
        stage.setPointerCapture(event.pointerId); beginGesture(event, start); stage.classList.add('is-dragging');
    });
    stage.addEventListener('pointermove', event => {
        if (!state || !pointers.has(event.pointerId) || !gesture) return;
        const current = point(event); pointers.set(event.pointerId, current);
        if (pointers.size >= 2 && gesture.mode === 'pinch') {
            const [a, b] = [...pointers.values()], origin = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
            const min = Math.max(state.box.w / state.image.naturalWidth, state.box.h / state.image.naturalHeight);
            const scale = clamp(gesture.scale * Math.hypot(a.x - b.x, a.y - b.y) / Math.max(1, gesture.distance), min, state.fit * 16);
            transformImage(scale, origin.x - (gesture.center.x - gesture.x) * scale / gesture.scale, origin.y - (gesture.center.y - gesture.y) * scale / gesture.scale);
            return;
        }
        const dx = current.x - gesture.start.x, dy = current.y - gesture.start.y;
        if (gesture.mode === 'resize') resize(gesture.box, gesture.handle, dx, dy);
        else if (gesture.mode === 'move') move(gesture.box, dx, dy);
        else if (gesture.mode === 'pan') transformImage(state.scale, gesture.x + dx, gesture.y + dy);
    });
    const release = event => {
        pointers.delete(event.pointerId);
        if (!state) return;
        if (pointers.size === 1) gesture = { mode: 'pan', start: [...pointers.values()][0], x: state.x, y: state.y };
        else { gesture = null; stage.classList.remove('is-dragging'); }
    };
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(name => stage.addEventListener(name, release));
    stage.addEventListener('wheel', event => {
        if (!state?.image) return;
        event.preventDefault(); zoom(Math.exp(-clamp(event.deltaY * (event.deltaMode === 1 ? 16 : 1), -300, 300) * .002), point(event));
    }, { passive: false });
    frame.addEventListener('keydown', event => {
        if (!state?.image) return;
        const step = event.shiftKey ? 24 : 4, direction = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[event.key];
        if (direction) {
            event.preventDefault();
            const handle = event.target.dataset.cropHandle;
            if (handle) resize(state.box, handle, ...direction); else move(state.box, ...direction);
        } else if (['+', '=', '-'].includes(event.key)) { event.preventDefault(); zoom(event.key === '-' ? 1 / 1.15 : 1.15); }
        else if (event.key === 'Home') { event.preventDefault(); reset(); }
    });
    find('zoom-in').addEventListener('click', () => zoom(1.15));
    find('zoom-out').addEventListener('click', () => zoom(1 / 1.15));
    find('reset').addEventListener('click', reset);
    ratioSelect.addEventListener('change', reset);
    dialog.querySelectorAll('[data-crop-cancel]').forEach(button => button.addEventListener('click', () => close()));
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    apply.addEventListener('click', async () => {
        if (!state?.image || apply.disabled) return;
        const current = { ...state, box: { ...state.box } }, token = revision, record = saved.get(current.input);
        const profile = current.input.dataset.cropMode === 'profile';
        const type = profile || current.input.dataset.cropFormat === 'jpeg' ? 'image/jpeg' : current.file.type;
        const output = document.createElement('canvas');
        let [width, height] = outputSize(), blob;
        apply.disabled = true; message.textContent = '';
        apply.textContent = 'Menyiapkan foto…';
        try {
            for (let attempt = 0; attempt < 10; attempt++) {
                output.width = width; output.height = height;
                const ctx = output.getContext('2d');
                if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, width, height); }
                ctx.imageSmoothingQuality = 'high';
                const box = current.box;
                ctx.drawImage(current.image, (box.x - current.x) / current.scale, (box.y - current.y) / current.scale,
                    box.w / current.scale, box.h / current.scale, 0, 0, width, height);
                blob = await new Promise(resolve => output.toBlob(resolve, type, .9));
                if (token !== revision) return;
                if (!blob) throw new Error('encode');
                if (blob.size <= (profile ? 500000 : 1024 * 1024)) break;
                if (profile) throw new Error('size');
                width = Math.max(1, Math.round(width * .8)); height = Math.max(1, Math.round(height * .8));
            }
            if (!blob || blob.size > (profile ? 500000 : 1024 * 1024)) throw new Error('size');
            if (profile) {
                record.result.value = output.toDataURL('image/jpeg', .9);
                record.preview.src = record.result.value;
                current.input.value = '';
            } else {
                const extension = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[blob.type];
                if (!extension) throw new Error('format');
                const file = new File([blob], current.file.name.replace(/\.[^.]+$/, '') + '-crop.' + extension, { type: blob.type });
                setFile(current.input, file); record.file = file;
                if (!record.preview) {
                    record.preview = document.createElement('img'); record.preview.className = 'crop-upload-preview';
                    record.preview.alt = 'Pratinjau hasil crop'; current.input.after(record.preview);
                }
                if (record.url) URL.revokeObjectURL(record.url);
                record.url = URL.createObjectURL(blob); record.preview.src = record.url;
            }
            current.input.dispatchEvent(new Event('input', { bubbles: true })); close(false);
        } catch {
            if (token === revision) message.textContent = 'Foto belum dapat diproses. Coba potongan lebih kecil atau pilih foto lain.';
        } finally {
            if (token === revision || !state) { apply.disabled = false; apply.textContent = applyLabel; }
        }
    });
    window.addEventListener('pagehide', () => inputs.forEach(input => { const url = saved.get(input).url; if (url) URL.revokeObjectURL(url); }));
})();
