(() => {
    const root = document.querySelector('[data-website-editor],[data-website-preview]');
    if (!root) return;
    const frame = root.querySelector('[data-cms-frame]'), wrap = root.querySelector('[data-cms-frame-wrap]');
    const form = root.querySelector('[data-cms-form]'), status = root.querySelector('[data-cms-status]');
    const previewStatus = root.querySelector('[data-cms-preview-status]');
    const pages = ['home','about','program','facilities','teachers','gallery','contact','settings','privacy','terms'];
    let previewPage = root.dataset.previewPage, width = 1280, dirty = false, busy = false;
    let selected = root.dataset.initialBlock, timer, previewRequest, sequence = 0;
    const resize = () => {
        if (!wrap.clientWidth) return;
        const scale = Math.min(1, wrap.clientWidth / width);
        frame.style.width = width + 'px';
        frame.style.height = Math.ceil(wrap.clientHeight / scale) + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        frame.style.left = Math.max(0, (wrap.clientWidth - width * scale) / 2) + 'px';
    };
    new ResizeObserver(resize).observe(wrap);
    root.querySelectorAll('[data-cms-width]').forEach(button => button.addEventListener('click', () => {
        width = Number(button.dataset.cmsWidth);
        root.querySelectorAll('[data-cms-width]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        resize();
    }));
    const focusBlock = () => frame.contentWindow?.postMessage({type:'cms-focus', block:selected, section:root.dataset.section}, location.origin);
    frame.addEventListener('load', () => {resize(); if (selected) focusBlock()});
    if (!form) {
        window.addEventListener('message', event => {
            if (event.origin !== location.origin || event.source !== frame.contentWindow || event.data?.type !== 'cms-navigate' || !pages.includes(event.data.section)) return;
            const url = new URL(location.href); url.searchParams.set('page', event.data.section); location.assign(url.href);
        });
        return;
    }
    const errors = root.querySelector('[data-cms-errors]'), badge = root.querySelector('[data-cms-draft-badge]');
    const fields = form.querySelector('fieldset');
    const setDirty = () => {
        dirty = true; status.textContent = 'Ada perubahan belum disimpan';
        badge.textContent = 'Belum disimpan'; badge.dataset.unsaved = 'true';
    };
    const showError = message => {
        errors.textContent = message; errors.hidden = false;
        status.textContent = 'Belum tersimpan. Periksa pesan kesalahan.';
        errors.scrollIntoView({block:'center', behavior:'smooth'});
    };
    const updatePreview = async () => {
        clearTimeout(timer);
        if (busy || root.classList.contains('cms-preview-hidden') || !form.checkValidity()) return;
        previewRequest?.abort(); previewRequest = new AbortController();
        const current = ++sequence;
        previewStatus.textContent = 'Memperbarui pratinjau…';
        try {
            const response = await fetch(form.dataset.previewUrl, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, signal:previewRequest.signal});
            if (!response.ok || response.redirected) {
                const error = await response.json().catch(() => ({}));
                throw new Error(Object.values(error.errors || {}).flat()[0] || 'Pratinjau belum dapat dimuat. Isian Anda tetap ada.');
            }
            const html = await response.text();
            if (current !== sequence) return;
            frame.srcdoc = html;
            previewStatus.textContent = dirty ? 'Pratinjau isian terbaru · belum disimpan' : 'Pratinjau draf tersimpan';
        } catch (error) {if (error.name !== 'AbortError') previewStatus.textContent = error.message}
    };
    const toggle = root.querySelector('[data-cms-toggle-preview]');
    const setPreview = visible => {
        root.classList.toggle('cms-preview-hidden', !visible);
        toggle.setAttribute('aria-expanded', String(visible));
        toggle.textContent = visible ? 'Sembunyikan pratinjau' : 'Tampilkan pratinjau';
        if (visible) {resize(); if (dirty) updatePreview()}
    };
    setPreview(window.innerWidth >= 1350);
    toggle.addEventListener('click', () => setPreview(root.classList.contains('cms-preview-hidden')));
    root.querySelector('[data-cms-preview]').addEventListener('click', () => {
        if (root.classList.contains('cms-preview-hidden')) setPreview(true);
        updatePreview();
    });
    const select = (id, focus = true) => {
        if (!root.querySelector('[data-cms-panel="' + CSS.escape(id) + '"]')) return;
        selected = id; form.elements.block.value = id;
        root.querySelectorAll('[data-cms-panel]').forEach(panel => panel.hidden = panel.dataset.cmsPanel !== id);
        root.querySelectorAll('[data-cms-select]').forEach(link => link.setAttribute('aria-current', String(link.dataset.cmsSelect === id)));
        const url = new URL(location.href); url.searchParams.set('block', id); history.replaceState(null, '', url);
        const nextPage = root.dataset.section === 'settings' ? (id === 'legal' ? 'privacy' : 'home') : (root.dataset.section === 'teachers' && id === 'team' ? 'home' : root.dataset.section);
        if (nextPage !== previewPage) {
            previewPage = nextPage;
            form.dataset.previewUrl = '/admin/website/' + root.dataset.section + '/preview?page=' + previewPage;
            if (dirty) updatePreview();
            else {frame.removeAttribute('srcdoc');frame.src='/admin/website/preview/frame?page='+previewPage+'&edit=1'}
        } else if (focus) focusBlock();
    };
    root.querySelectorAll('[data-cms-select]').forEach(link => link.addEventListener('click', event => {event.preventDefault();select(link.dataset.cmsSelect)}));
    form.addEventListener('invalid', event => {
        const panel = event.target.closest('[data-cms-panel]');
        if (panel) select(panel.dataset.cmsPanel, false);
        for (const details of event.target.closest('[data-cms-panel]')?.querySelectorAll('details') || []) {
            if (details.contains(event.target)) details.open = true;
        }
    }, true);
    const changed = event => {
        if (!/^(values|uploads)\[/.test(event.target.name)) return;
        setDirty(); clearTimeout(timer); timer = setTimeout(updatePreview, 900);
        const summary = event.target.closest('[data-cms-item]')?.querySelector('summary strong');
        if (summary && /\[(?:.*\.)?(name|title|question)\]$/.test(event.target.name)) summary.textContent = event.target.value || 'Item tanpa judul';
    };
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    const save = async destination => {
        if (busy || !form.reportValidity()) return false;
        clearTimeout(timer); previewRequest?.abort(); ++sequence;
        const body = new FormData(form); body.set('intent', 'save');
        const submitButtons = [...root.querySelectorAll('button[type=submit]')];
        busy = true; fields.disabled = true; submitButtons.forEach(button => button.disabled = true);
        root.setAttribute('aria-busy','true'); errors.hidden = true; status.textContent = 'Menyimpan draf…';
        try {
            const response = await fetch(form.action, {method:'POST', body, headers:{Accept:'application/json'}});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.version) {
                const messages = Object.values(data.errors || {}).flat();
                throw new Error(messages.join(' ') || (response.status === 419 || response.redirected ? 'Sesi berakhir. Buka login di tab lain, lalu coba simpan kembali.' : 'Draf belum tersimpan. Periksa koneksi dan coba lagi; isian Anda tetap ada.'));
            }
            form.elements.version.value = data.version;
            dirty = false; badge.dataset.unsaved = 'false';
            badge.textContent = data.has_draft ? 'Draf belum diterbitkan' : 'Sesuai versi terbit';
            status.textContent = data.message + ' · ' + data.saved_at;
            if (destination || data.reload_url) {busy = false; location.assign(destination || data.reload_url)}
            return true;
        } catch (error) {showError(error.message); return false}
        finally {
            busy = false; fields.disabled = false; submitButtons.forEach(button => button.disabled = false);
            root.removeAttribute('aria-busy');
        }
    };
    const navigate = url => {
        if (busy) return;
        if (dirty) save(url); else location.assign(url);
    };
    root.querySelectorAll('.cms-pages a,.cms-back').forEach(link => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); navigate(link.href);
    }));
    const search = root.querySelector('[data-cms-person-search]'), filter = root.querySelector('[data-cms-team-filter]');
    const filterPeople = () => {
        let count = 0;
        root.querySelectorAll('[data-cms-team]').forEach(team => {
            let visible = 0;
            team.querySelectorAll('[data-cms-person]').forEach(person => {
                person.hidden = (!!filter.value && team.dataset.cmsTeam !== filter.value) || !person.textContent.toLocaleLowerCase('id-ID').includes(search.value.trim().toLocaleLowerCase('id-ID'));
                if (!person.hidden) visible++;
            });
            team.hidden = !visible; count += visible;
        });
        root.querySelector('[data-cms-people-empty]').hidden = count > 0;
    };
    search?.addEventListener('input', filterPeople); filter?.addEventListener('change', filterPeople);
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.source !== frame.contentWindow) return;
        const data = event.data;
        if (!data || !pages.includes(data.section)) return;
        if (data.type === 'cms-edit') {
            if (data.section === root.dataset.section) {
                select(data.block, false);
                if (Number.isInteger(data.personIndex) && data.personIndex >= 0 && data.personIndex < 100) {
                    if (search && filter) {search.value='';filter.value='';filterPeople()}
                    const person = root.querySelector('[data-cms-person-index="' + data.personIndex + '"]');
                    const details = person?.querySelector('.cms-item');
                    if (details) {details.open=true;details.scrollIntoView({block:'center'})}
                }
            } else navigate('/admin/website/' + data.section + '/edit?block=' + encodeURIComponent(data.block));
        }
        if (data.type === 'cms-navigate') navigate('/admin/website/' + (['privacy','terms'].includes(data.section) ? 'settings/edit?block=legal' : data.section+'/edit'));
    });
    const dialog = document.querySelector('[data-cms-confirm]');
    let pending, confirmed = false;
    form.addEventListener('submit', event => {
        if (busy) {event.preventDefault();return}
        const operation = event.submitter?.name === 'operation' ? event.submitter.value : '';
        if (operation.startsWith('remove:') && !confirmed) {
            event.preventDefault(); pending = event.submitter; dialog.showModal(); return;
        }
        if (operation) {
            dirty = false; clearTimeout(timer); previewRequest?.abort();
            return;
        }
        event.preventDefault();
        save(event.submitter?.value === 'review' ? '/admin/publikasi' : null);
    });
    document.querySelector('[data-cms-cancel]').addEventListener('click', () => dialog.close());
    document.querySelector('[data-cms-accept]').addEventListener('click', () => {confirmed = true;dialog.close();form.requestSubmit(pending);confirmed=false});
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {event.preventDefault();save(null)}
    });
    window.addEventListener('beforeunload', event => {if (dirty || busy) {event.preventDefault();event.returnValue = ''}});
})();
