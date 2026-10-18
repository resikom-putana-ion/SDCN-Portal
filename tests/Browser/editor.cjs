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
    const browser = await chromium.launch({headless:true,executablePath:process.env.BROWSER_PATH || 'C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe'});
    try {
        const context = await browser.newContext({viewport:{width:1600,height:1100}});
        const missing = [], errors = [], saves = [], publications = [];
        let failSave = false;
        await context.route('**/*',async route => {
            const url = new URL(route.request().url());
            let file;
            if (url.pathname === '/admin/website/home/edit' && route.request().method() === 'POST') {
                saves.push(route.request().postData());
                if (failSave) {
                    failSave = false;
                    return route.fulfill({status:422,json:{errors:{content:['Konten berubah. Periksa isian lalu simpan kembali.']}}});
                }
                return route.fulfill({json:{version:'b'.repeat(64),has_draft:true,message:'Draf tersimpan.',saved_at:'14:30',reload_url:null}});
            }
            if (url.pathname === '/admin/website/publish') {
                publications.push(route.request().postData());
                return route.fulfill({status:303,headers:{location:'/admin/terbit'},body:''});
            }
            if (/^\/(assets|css|js|landing-preview)\//.test(url.pathname)) file = path.resolve(root,'public'+decodeURIComponent(url.pathname));
            else if (url.pathname === '/admin/website/preview/frame') file = path.join(output,'frame-'+url.searchParams.get('page')+'.html');
            else if (url.pathname === '/admin/website/home/preview') {
                assert.ok(route.request().postData().includes('JUDUL UJI PRATINJAU'));
                file = path.join(output,'frame-updated.html');
            } else if (url.pathname === '/admin/website') file = path.join(output,'manage.html');
            else if (/^\/admin\/website\/\w+\/edit$/.test(url.pathname)) file = path.join(output,'editor-'+url.pathname.split('/')[3]+'.html');
            else if (url.pathname === '/admin/publikasi') file = path.join(output,url.searchParams.has('empty')?'publication-empty.html':'publication.html');
            else if (url.pathname === '/admin/terbit') file = path.join(output,'published.html');
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
        failSave = true;
        await page.getByRole('button',{name:'Simpan draf',exact:true}).click();
        await page.locator('[data-cms-errors]').waitFor({state:'visible'});
        assert.equal(await page.locator('[name="values[title]"]').inputValue(),'JUDUL UJI PRATINJAU');
        assert.match(await page.locator('[data-cms-status]').textContent(),/Belum tersimpan/);
        await page.getByRole('button',{name:'Simpan & tinjau',exact:true}).click();
        await page.waitForURL('**/admin/publikasi');
        assert.equal(saves.length,2,'Failed save can be retried without losing the form');
        assert.ok(saves[1].includes('JUDUL UJI PRATINJAU'));
        assert.equal(await page.locator('[data-review-section]').count(),8);
        await page.screenshot({path:path.join(output,'publication-desktop.png'),fullPage:true});
        const publishButton=page.getByRole('button',{name:'Terbitkan 8 halaman',exact:true});
        await publishButton.click();
        assert.equal(publications.length,0,'Review confirmation is required');
        await page.locator('[name=confirmed]').check();
        await publishButton.click();
        await page.locator('#confirm-dialog').waitFor({state:'visible'});
        await page.locator('[data-confirm-accept]').click();
        await page.waitForURL('**/admin/terbit');
        assert.ok(publications[0].includes('review_version='));
        assert.match(await page.locator('.cms-empty').textContent(),/Perubahan berhasil diterbitkan/);
        await page.goto('http://editor.test/admin/publikasi?empty=1');
        assert.equal(await page.getByRole('button',{name:/Terbitkan/}).count(),0);
        await page.screenshot({path:path.join(output,'publication-empty.png'),fullPage:true});
        await page.goto('http://editor.test/admin/website/home/edit');
        await page.locator('[name="values[title]"]').fill('JUDUL UJI PRATINJAU');
        await page.locator('.cms-pages a[href="/admin/website/teachers/edit"]').click();
        await page.waitForURL('**/admin/website/teachers/edit');
        assert.equal(saves.length,3,'Changing pages saves pending changes');
        page.on('dialog',dialog=>dialog.accept());
        await page.goto('http://editor.test/admin/website/teachers/edit');
        await page.locator('[data-cms-select=people]').click();
        await page.frameLocator('[data-cms-frame]').getByRole('button',{name:'Edit profil Ratna Puspitasari, M.Pd.',exact:true}).click();
        await page.locator('[data-cms-person-index="0"] > details[open]').waitFor();
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
        for (const section of ['settings','home','about','program','facilities','teachers','gallery','contact']) {
            await page.goto('http://editor.test/admin/website/'+section+'/edit');
            assert.equal(await page.locator('[data-cms-form]').evaluate(form=>form.checkValidity()),true,section+' can be submitted');
        }
        await page.setViewportSize({width:1280,height:900});
        await page.goto('http://editor.test/admin/website/home/edit');
        assert.equal(await page.locator('[data-cms-toggle-preview]').getAttribute('aria-expanded'),'false');
        assert.ok((await page.locator('[data-cms-form]').boundingBox()).width>600);
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        await page.screenshot({path:path.join(output,'editor-laptop.png'),fullPage:true});
        await page.setViewportSize({width:390,height:844});
        await page.goto('http://editor.test/admin/publikasi');
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Publication fits a phone');
        await page.screenshot({path:path.join(output,'publication-mobile.png'),fullPage:true});
        assert.deepEqual(errors,[],'Browser JavaScript errors');
        assert.deepEqual([...new Set(missing)],[],'All preview assets must load locally');
        console.log('Editor browser checks passed: all 8 pages, save failures/retry, save before review/navigation, before/after review, publication confirmation, empty state, responsive layout, preview, and team controls.');
    } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
