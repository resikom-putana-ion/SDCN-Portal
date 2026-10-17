(() => {
    const root = document.querySelector('[data-website-editor],[data-website-preview]');
    if (!root) return;
    const frame = root.querySelector('[data-cms-frame]'), wrap = root.querySelector('[data-cms-frame-wrap]');
    const form = root.querySelector('[data-cms-form]'), status = root.querySelector('[data-cms-status]');
    const pages = ['home','about','program','facilities','teachers','gallery','contact','settings','privacy','terms'];
    let previewPage = root.dataset.previewPage;
    let width = 1280, dirty = false, selected = root.dataset.initialBlock, timer, request, sequence = 0;
    const resize = () => {
        const scale = Math.min(1, wrap.clientWidth / width);
        frame.style.width = width + 'px';
        frame.style.height = Math.ceil(wrap.clientHeight / scale) + 'px';
        frame.style.transform = `scale(${scale})`;
        frame.style.left = Math.max(0, (wrap.clientWidth - width * scale) / 2) + 'px';
    };
    new ResizeObserver(resize).observe(wrap);
    root.querySelectorAll('[data-cms-width]').forEach(button => button.addEventListener('click', () => {
        width = Number(button.dataset.cmsWidth);
        root.querySelectorAll('[data-cms-width]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        resize();
    }));
    const focusBlock = () => frame.contentWindow?.postMessage({type:'cms-focus', block:selected, section:root.dataset.section}, location.origin);
    const select = (id, focus = true) => {
        if (!root.querySelector(`[data-cms-panel="${id}"]`)) return;
        selected = id;
        root.querySelectorAll('[data-cms-panel]').forEach(panel => panel.hidden = panel.dataset.cmsPanel !== id);
        root.querySelectorAll('[data-cms-select]').forEach(link => link.setAttribute('aria-current', String(link.dataset.cmsSelect === id)));
        const url = new URL(location.href); url.searchParams.set('block', id); history.replaceState(null, '', url);
        root.querySelector('.cms-inspector-scroll').scrollTop = 0;
        const nextPage = root.dataset.section === 'settings' ? (id === 'legal' ? 'privacy' : 'home') : (root.dataset.section === 'teachers' && id === 'team' ? 'home' : root.dataset.section);
        if (nextPage !== previewPage) {
            previewPage = nextPage;
            frame.removeAttribute('srcdoc');frame.src='/admin/website/preview/frame?page='+previewPage+'&edit=1';
            form.dataset.previewUrl='/admin/website/'+root.dataset.section+'/preview?page='+previewPage;
        } else if (focus) focusBlock();
    };
    root.querySelectorAll('[data-cms-select]').forEach(link => link.addEventListener('click', event => {event.preventDefault();select(link.dataset.cmsSelect)}));
    frame.addEventListener('load', () => {resize(); if (selected) focusBlock()});
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.source !== frame.contentWindow) return;
        const data = event.data;
        if (!data || !pages.includes(data.section)) return;
        if (data.type === 'cms-edit') {
            if (data.section === root.dataset.section) {
                select(data.block, false);
                if (Number.isInteger(data.personIndex) && data.personIndex >= 0 && data.personIndex < 100) {
                    if (search && filter) {search.value='';filter.value='';filterPeople()}
                    const person = root.querySelector(`[data-cms-person-index="${data.personIndex}"]`);
                    const details = person?.querySelector('.cms-item');
                    if (details) {details.open=true;details.scrollIntoView({block:'nearest'})}
                }
            }
            else location.href = '/admin/website/' + data.section + '/edit?block=' + encodeURIComponent(data.block);
        }
        if (data.type === 'cms-navigate') location.href = form ? '/admin/website/' + (['privacy','terms'].includes(data.section) ? 'settings/edit?block=legal' : data.section+'/edit') : '?page=' + data.section;
    });
    if (!form) return;
    const updatePreview = async () => {
        if (!form.checkValidity()) {status.textContent = 'Selesaikan isian atau potongan foto terlebih dahulu.'; return}
        request?.abort(); request = new AbortController();
        const current = ++sequence;
        status.textContent = 'Memperbarui pratinjau…';
        try {
            const response = await fetch(form.dataset.previewUrl, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, signal:request.signal});
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(Object.values(error.errors || {}).flat()[0] || 'Pratinjau belum dapat diperbarui. Muat ulang halaman.');
            }
            const html = await response.text();
            if (current !== sequence) return;
            frame.srcdoc = html;
            status.textContent = dirty ? 'Pratinjau diperbarui · belum disimpan' : 'Pratinjau diperbarui';
        } catch (error) {if (error.name !== 'AbortError') status.textContent = error.message}
    };
    root.querySelector('[data-cms-preview]').addEventListener('click', updatePreview);
    form.addEventListener('input', event => {
        if (!/^(values|uploads)\[/.test(event.target.name)) return;
        dirty = true; status.textContent = 'Ada perubahan yang belum disimpan';
        clearTimeout(timer); timer = setTimeout(updatePreview, 1100);
    });
    const search = root.querySelector('[data-cms-person-search]'), filter = root.querySelector('[data-cms-team-filter]');
    const filterPeople = () => {
        let count = 0;
        root.querySelectorAll('[data-cms-team]').forEach(team => {
            let visible = 0;
            team.querySelectorAll('[data-cms-person]').forEach(person => {
                person.hidden = (!!filter.value && team.dataset.cmsTeam !== filter.value) || !person.dataset.cmsPerson.includes(search.value.trim().toLocaleLowerCase('id-ID'));
                if (!person.hidden) visible++;
            });
            team.hidden = !visible; count += visible;
        });
        root.querySelector('[data-cms-people-empty]').hidden = count > 0;
    };
    search?.addEventListener('input', filterPeople); filter?.addEventListener('change', filterPeople);
    const dialog = document.querySelector('[data-cms-confirm]');
    let pending, confirmed = false;
    form.addEventListener('submit', event => {
        if (event.submitter?.value.startsWith('remove:') && !confirmed) {
            event.preventDefault(); pending = event.submitter; dialog.showModal(); return;
        }
        dirty = false; clearTimeout(timer); request?.abort();
    });
    document.querySelector('[data-cms-cancel]').addEventListener('click', () => dialog.close());
    document.querySelector('[data-cms-accept]').addEventListener('click', () => {confirmed = true; dialog.close();form.requestSubmit(pending)});
    window.addEventListener('beforeunload', event => {if (dirty) {event.preventDefault();event.returnValue = ''}});
})();
