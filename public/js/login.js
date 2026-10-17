const passwordToggle = document.querySelector('[data-password-toggle]');
passwordToggle?.addEventListener('click', () => {
    const password = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    const visible = password.type === 'password';
    password.type = visible ? 'text' : 'password';
    passwordToggle.textContent = visible ? 'Sembunyikan' : 'Lihat';
    passwordToggle.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Lihat kata sandi');
    passwordToggle.setAttribute('aria-pressed', String(visible));
});
