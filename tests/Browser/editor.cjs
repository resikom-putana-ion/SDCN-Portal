const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'../..');
const output = path.join(root,'storage/app/editor-browser-check');
const php = process.env.PHP_PATH || 'C:/xampp/php/php.exe';
execFileSync(php,[path.join(__dirname,'render-editor-fixtures.php')],{cwd:root,stdio:'inherit'});

(async () => {
    const browser = await chromium.launch({headless:true,executablePath:process.env.BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        const context = await browser.newContext({viewport:{width:1600,height:1100}});
        const missing = [], errors = [];
        await context.route('**/*',async route => {
            const url = new URL(route.request().url());
            let file;
            if (/^\/(assets|css|js|landing-preview)\//.test(url.pathname)) file = path.resolve(root,'public'+decodeURIComponent(url.pathname));
            else if (url.pathname === '/admin/website/preview/frame') file = path.join(output,'frame-'+url.searchParams.get('page')+'.html');
            else if (url.pathname === '/admin/website/home/preview') {
                assert.ok(route.request().postData().includes('JUDUL UJI PRATINJAU'));
                file = path.join(output,'frame-updated.html');
            } else if (url.pathname === '/admin/website') file = path.join(output,'manage.html');
            else if (url.pathname === '/admin/website/home/edit') file = path.join(output,'editor-home.html');
            else if (url.pathname === '/admin/website/teachers/edit') file = path.join(output,'editor-teachers.html');
            if (file && file.startsWith(root+path.sep) && fs.existsSync(file)) await route.fulfill({path:file});
            else {missing.push(url.pathname);await route.fulfill({status:404})}
        });
        const page = await context.newPage();
        page.on('pageerror',error=>errors.push(error.message));
        await page.goto('http://editor.test/admin/website');
        assert.equal(await page.locator('.cms-page-card').count(),8);
        await page.screenshot({path:path.join(output,'manage-desktop.png'),fullPage:true});
        await page.goto('http://editor.test/admin/website/home/edit');
        const frame = page.frameLocator('[data-cms-frame]');
        await frame.getByRole('button',{name:'Edit Pembuka beranda',exact:true}).waitFor();
        await page.evaluate(()=>document.fonts.ready);
        await page.screenshot({path:path.join(output,'editor-home-desktop.png'),fullPage:true});
        await page.locator('[data-cms-select=profile]').click();
        await page.locator('[data-cms-panel=profile]').waitFor({state:'visible'});
        await frame.getByRole('button',{name:'Edit Pembuka beranda',exact:true}).click();
        await page.locator('[data-cms-panel=hero]').waitFor({state:'visible'});
        await page.locator('[name="values[title]"]').fill('JUDUL UJI PRATINJAU');
        await page.locator('[data-cms-preview]').click();
        await frame.locator('h1').filter({hasText:'JUDUL UJI PRATINJAU'}).waitFor();
        await page.getByRole('button',{name:'Tablet',exact:true}).click();
        assert.equal(await page.locator('[data-cms-frame]').evaluate(el=>el.contentWindow.innerWidth),768);
        await page.getByRole('button',{name:'Ponsel',exact:true}).click();
        assert.equal(await page.locator('[data-cms-frame]').evaluate(el=>el.contentWindow.innerWidth),390);
        await page.screenshot({path:path.join(output,'editor-mobile-preview.png'),fullPage:true});
        page.on('dialog',dialog=>dialog.accept());
        await page.goto('http://editor.test/admin/website/teachers/edit');
        await page.locator('[data-cms-select=people]').click();
        await page.frameLocator('[data-cms-frame]').getByRole('button',{name:'Edit profil Ratna Puspitasari, M.Pd.',exact:true}).click();
        assert.equal(await page.locator('[data-cms-person-index="0"] > details').getAttribute('open'),'');
        await page.locator('[data-cms-team-filter]').selectOption('team-0-1');
        assert.ok(await page.locator('[data-cms-person]:visible').count()>0);
        await page.locator('[data-cms-person-search]').fill('Maria');
        assert.equal(await page.locator('[data-cms-person]:visible').count(),1);
        await page.locator('[data-cms-person]:visible > .cms-item > summary').click();
        await page.locator('[data-cms-person]:visible .cms-remove').click();
        await page.locator('[data-cms-confirm]').waitFor({state:'visible'});
        await page.locator('[data-cms-cancel]').click();
        await page.locator('[data-cms-person-search]').fill('');
        await page.locator('[data-cms-team-filter]').selectOption('');
        await page.screenshot({path:path.join(output,'editor-teachers-desktop.png'),fullPage:true});
        assert.deepEqual(errors,[],'Browser JavaScript errors');
        assert.deepEqual([...new Set(missing)],[],'All preview assets must load locally');
        console.log('Editor browser checks passed: section selection, preview, responsive sizes, team search, deletion confirmation, and assets.');
    } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
