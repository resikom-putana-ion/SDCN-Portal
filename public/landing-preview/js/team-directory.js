(() => {
    const cards = [...document.querySelectorAll('[data-team-group]')];
    const filters = [...document.querySelectorAll('[data-team-filter]')];
    const search = document.querySelector('[data-team-search]');
    let selected = 'semua';
    function update() {
        const query = (search?.value ?? '').toLocaleLowerCase('id').trim();
        let count = 0;
        cards.forEach(card => {
            card.hidden = !(selected === 'semua' || card.dataset.teamGroup === selected)
                || !card.dataset.teamText.includes(query);
            if (!card.hidden) count++;
        });
        document.querySelector('[data-team-count]').textContent = `Menampilkan ${count} anggota tim`;
        document.querySelector('[data-team-empty]').hidden = count > 0;
    }
    filters.forEach(button => button.addEventListener('click', () => {
        selected = button.dataset.teamFilter;
        filters.forEach(filter => {
            const active = filter === button;
            filter.classList.toggle('is-active', active);
            filter.setAttribute('aria-pressed', String(active));
        });
        update();
    }));
    search?.addEventListener('input', update);

    const organizationCards = [...document.querySelectorAll('[data-organization-person]')];
    const setPhotoVisible = (card, visible) => {
        const name = card.dataset.organizationName;
        const role = card.dataset.organizationRole;
        card.classList.toggle('is-photo-visible', visible);
        card.setAttribute('aria-pressed', String(visible));
        card.setAttribute('aria-label', `${visible ? 'Sembunyikan' : 'Tampilkan'} foto ${name}, ${role}`);
    };
    const closeOrganizationPhotos = except => {
        organizationCards.forEach(card => {
            if (card !== except) setPhotoVisible(card, false);
        });
    };

    organizationCards.forEach(card => {
        card.addEventListener('click', () => {
            const willShow = card.getAttribute('aria-pressed') !== 'true';
            closeOrganizationPhotos(card);
            setPhotoVisible(card, willShow);
        });
        card.addEventListener('blur', () => {
            if (!card.matches(':hover')) setPhotoVisible(card, false);
        });
    });

    document.addEventListener('pointerdown', event => {
        if (!event.target.closest('[data-organization-person]')) closeOrganizationPhotos();
    }, { passive: true });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const activeCard = document.activeElement?.closest?.('[data-organization-person]');
        closeOrganizationPhotos();
        activeCard?.blur();
    });
})();
