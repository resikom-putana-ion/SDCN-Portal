const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseURL = process.env.APP_TEST_URL || 'http://127.0.0.1:8017';
const output = path.resolve('storage/app/browser-check');
const brave = 'C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe';
const executablePath = process.env.BROWSER_PATH || (fs.existsSync(brave) ? brave : undefined);
const routes = [
    '', 'siswa', 'siswa/1', 'siswa/1/kehadiran', 'siswa/1/tagihan', 'siswa/1/nilai',
    'siswa/1/dokumen', 'siswa/1/edit', 'guru', 'guru/5', 'guru/8', 'pendaftar',
    'pendaftar/9', 'daftar-ulang', 'daftar-ulang/10', 'periode', 'tagihan', 'pembayaran',
    'pembayaran?tab=tunai', 'kas', 'kelas', 'kenaikan', 'absensi', 'nilai', 'pengumuman',
    'agenda', 'laporan', 'pengaturan', 'pengguna', 'aktivitas', 'alat', 'audit', 'website',
    'halaman', 'website/hero/edit', 'website/profil/edit', 'website/program/edit',
    'website/fasilitas/edit', 'website/guru/edit', 'website/kontak/edit', 'website/ppdb/edit',
    'berita', 'media', 'publikasi', 'website/preview', 'bahasa', 'notifikasi',
    'kuitansi/1', 'periksa-pembayaran/2',
];

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ executablePath, headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
    page.setDefaultNavigationTimeout(120000);
    page.setDefaultTimeout(30000);
    const errors = [];
    const checked = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(`${baseURL}/login`);
        await page.fill('[name=email]', process.env.ADMIN_EMAIL || 'admin@sdcerianusantara.sch.id');
        await page.fill('[name=password]', process.env.ADMIN_PASSWORD || 'Ceria2026!');
        await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Masuk', exact: true }).click()]);
        for (const route of routes) {
            const url = `/admin${route ? '/' + route : ''}`;
            const response = await page.goto(baseURL + url);
            assert.equal(response.status(), 200, url);
            assert.ok(!page.url().endsWith('/login'), 'Session must remain authenticated');
            await page.locator('img').evaluateAll(images => Promise.all(images.map(img => img.decode().catch(() => {}))));
            const broken = await page.locator('img').evaluateAll(images => images.filter(img => !img.naturalWidth).map(img => img.src));
            assert.deepEqual(broken, [], `Broken images: ${url}`);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            assert.equal(overflow, false, `Desktop horizontal overflow: ${url}`);
            checked.push(url);
            console.log('OK', url);
            if (['', 'siswa', 'siswa/1', 'guru/5', 'website/hero/edit', 'pembayaran', 'audit'].includes(route)) {
                await page.screenshot({ path: path.join(output, (route || 'dashboard').replaceAll('/', '-') + '.png'), fullPage: true });
            }
        }

        await page.goto(`${baseURL}/admin/siswa`);
        await Promise.all([page.waitForURL('**/siswa?**q=tidak-ada-siswa**'), page.locator('[data-filter-form] [name=q]').fill('tidak-ada-siswa')]);
        assert.equal(await page.getByText('Tidak ada data yang sesuai.', { exact: true }).count(), 1);
        await page.goto(`${baseURL}/admin/tagihan`);
        await Promise.all([page.waitForURL('**filter=Lunas**'), page.selectOption('[name=filter]', 'Lunas')]);
        const statuses = await page.locator('tbody .badge').allTextContents();
        assert.ok(statuses.length > 0 && statuses.every(status => status === 'Lunas'));

        await page.goto(`${baseURL}/admin/bahasa`);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'English' }).click()]);
        await page.goto(`${baseURL}/admin`);
        assert.equal(await page.locator('h1').innerText(), 'School Overview');
        await page.goto(`${baseURL}/admin/bahasa`);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Indonesia' }).click()]);

        await page.goto(`${baseURL}/admin/website/hero/edit`);
        assert.equal(await page.locator('.subnav a.selected').innerText(), 'Halaman & bagian');
        await page.fill('[name=title]', 'Uji pratinjau tanpa menyimpan');
        assert.equal(await page.locator('[data-preview-target=title]').innerText(), 'Uji pratinjau tanpa menyimpan');
        await page.click('[data-open-media]');
        assert.equal(await page.locator('#media-picker').isVisible(), true);
        await page.locator('[data-select-media]').first().click();
        assert.equal(await page.locator('#media-picker').isVisible(), false);

        await page.goto(`${baseURL}/admin/daftar-ulang/10`);
        for (const box of await page.locator('input[name="checks[]"], input[name=paid]').all()) await box.uncheck();
        assert.equal(await page.locator('[value=activate]').isDisabled(), true);
        for (const box of await page.locator('input[name="checks[]"], input[name=paid]').all()) await box.check();
        assert.equal(await page.locator('[value=activate]').isDisabled(), false);
        await page.locator('[value=activate]').click();
        assert.equal(await page.locator('#confirm-dialog').isVisible(), true);
        await page.click('[data-confirm-cancel]');
        assert.equal(await page.locator('#confirm-dialog').isVisible(), false);

        for (const [route, label] of [['kuitansi/1', 'Unduh PDF'], ['siswa/1/nilai', 'Unduh rapor']]) {
            await page.goto(`${baseURL}/admin/${route}`);
            const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('link', { name: label, exact: true }).click()]);
            const filename = path.join(output, download.suggestedFilename());
            await download.saveAs(filename);
            assert.equal(fs.readFileSync(filename).subarray(0, 5).toString(), '%PDF-');
        }

        for (const route of ['website', 'ppdb']) {
            const response = await page.goto(`${baseURL}/${route}`);
            assert.equal(response.status(), 200);
            checked.push('/' + route);
        }
        await page.setViewportSize({ width: 390, height: 844 });
        for (const route of ['website', 'ppdb']) {
            await page.goto(`${baseURL}/${route}`);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, `Mobile overflow: ${route}`);
            await page.screenshot({ path: path.join(output, route + '-mobile.png'), fullPage: true });
        }
        assert.deepEqual(errors, [], 'Browser JavaScript errors');
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ checked, errors, interactions: 'passed', time: new Date().toISOString() }, null, 2));
        console.log(`PASS: ${checked.length} pages, filters, language, media, activation confirmation, PDF downloads, mobile public forms.`);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
