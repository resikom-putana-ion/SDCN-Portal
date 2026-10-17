(() => {
    const origin = location.origin === 'null' || location.href === 'about:srcdoc' ? parent.location.origin : location.origin;
    const blocks = JSON.parse(document.querySelector('#preview-blocks')?.textContent || '[]');
    const targets = [];
    blocks.forEach(block => {
        const target = document.querySelector(block.selector);
        if (!target) return;
        target.dataset.previewTarget = block.block;
        if (getComputedStyle(target).position === 'static') target.style.position = 'relative';
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'preview-edit';
        const icon = document.createElement('span'); icon.textContent = '✎'; icon.setAttribute('aria-hidden','true');
        button.append(icon, document.createTextNode('Edit bagian ini'));
        button.setAttribute('aria-label', 'Edit ' + block.label);
        button.addEventListener('click', () => parent.postMessage({type:'cms-edit', section:block.section, block:block.block}, origin));
        target.append(button); targets.push({target,...block});
    });
    if (blocks.some(block => block.section === 'teachers')) {
        document.querySelectorAll('.person-card').forEach((card, personIndex) => {
            card.classList.add('preview-person-editable');
            const button = document.createElement('button');
            button.type='button';button.className='preview-edit-person';button.textContent='Edit profil';
            button.setAttribute('aria-label','Edit profil '+card.querySelector('h3').textContent);
            button.addEventListener('click',()=>parent.postMessage({type:'cms-edit',section:'teachers',block:'people',personIndex},origin));
            card.append(button);
        });
    }
    window.addEventListener('message', event => {
        if (event.source !== parent || event.origin !== origin || event.data?.type !== 'cms-focus') return;
        targets.forEach(item => {
            const active = item.block === event.data.block && item.section === event.data.section;
            item.target.classList.toggle('preview-focused', active);
            if (active) item.target.scrollIntoView({block:'start', behavior:'instant'});
        });
    });
    document.addEventListener('submit', event => event.preventDefault(), true);
    document.querySelectorAll('form button[type=submit],form button:not([type])').forEach(button => button.disabled = true);
    document.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link) return;
        const url = new URL(link.href, location.href);
        if (url.pathname === '/admin/website/preview/frame') {
            event.preventDefault(); parent.postMessage({type:'cms-navigate', section:url.searchParams.get('page')}, origin);
        } else if (url.origin !== origin || link.getAttribute('href') === '#') event.preventDefault();
    });
})();
