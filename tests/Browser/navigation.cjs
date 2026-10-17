const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'storage/app/navigation-browser-check');
execFileSync(process.env.PHP_PATH || 'C:/xampp/php/php.exe', [path.join(__dirname, 'render-navigation-fixtures.php')], {cwd:root, stdio:'inherit'});
const manifest = JSON.parse(fs.readFileSync(path.join(output, 'manifest.json')));

(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000}});
        await context.route('**/*', async route => {
            const url = new URL(route.request().url());
            let file;
            if (/^\/(assets|css|js)\//.test(url.pathname)) file = path.join(root, 'public', url.pathname);
            else if (url.pathname === '/admin/siswa') file = path.join(output, 'list.html');
            else if (url.pathname === manifest.profile) file = path.join(output, 'profile.html');
            else if (url.pathname === manifest.profile+'/dokumen') file = path.join(output, 'documents.html');
            else if (url.pathname === manifest.profile+'/edit') file = path.join(output, 'edit.html');
            if (file && fs.existsSync(file)) await route.fulfill({path:file});
            else await route.fulfill({status:404,body:'Fixture not found'});
        });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('http://navigation.test'+manifest.profile+'/edit');
        assert.equal(await page.locator('[data-dashboard-back]').getAttribute('href'), manifest.profile);
        await page.getByRole('link', {name:'Batal', exact:true}).click();
        assert.equal(new URL(page.url()).pathname, manifest.profile);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({path:path.join(output, 'profile-desktop.png'),fullPage:true});
        await page.locator('[data-dashboard-back]').focus();
        assert.ok(await page.locator('[data-dashboard-back]').evaluate(el=>el===document.activeElement));
        await Promise.all([page.waitForURL('http://navigation.test'+manifest.list), page.keyboard.press('Enter')]);
        assert.equal(new URL(page.url()).pathname+new URL(page.url()).search, manifest.list);
        await page.goto('http://navigation.test'+manifest.profile+'/dokumen');
        await page.locator('[data-dashboard-back]').click();
        assert.equal(new URL(page.url()).pathname, manifest.profile);
        await page.setViewportSize({width:390,height:844});
        await page.goto('http://navigation.test'+manifest.profile);
        // The existing dashboard intentionally has a 1060px desktop minimum.
        // Collapsing its sidebar must still make the new return control reachable.
        await page.locator('[data-sidebar]').click();
        const box = await page.locator('[data-dashboard-back]').boundingBox();
        assert.ok(box && box.height >= 44 && box.x >= 0 && box.x+box.width <= 390, 'Back link is reachable on mobile');
        await page.screenshot({path:path.join(output,'profile-mobile.png'),fullPage:true});
        await page.locator('[data-dashboard-back]').click();
        assert.equal(new URL(page.url()).pathname+new URL(page.url()).search, manifest.list);
        assert.deepEqual(errors, []);
        console.log('Navigation browser checks passed: cancel, parent links, retained filters, keyboard, and return target with sidebar collapsed on a narrow viewport. Full mobile layout is outside this fix.');
    } finally {await browser.close()}
})().catch(error => {console.error(error); process.exitCode=1});
