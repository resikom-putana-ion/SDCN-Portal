const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'../..');
const output = path.join(root,'storage/app/navigation-browser-check');
execFileSync(process.env.PHP_PATH || 'C:/xampp/php/php.exe',[path.join(__dirname,'render-navigation-fixtures.php')],{cwd:root,stdio:'inherit'});
const manifest = JSON.parse(fs.readFileSync(path.join(output,'manifest.json')));

(async()=>{
    const browser = await chromium.launch({headless:true,executablePath:process.env.BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
        await context.route('**/*',async route=>{
            const url = new URL(route.request().url());
            let file;
            if(/^\/(assets|css|js)\//.test(url.pathname)) file=path.join(root,'public',url.pathname);
            else if(/^\/header\/(admin|keuangan|akademik|long|en)$/.test(url.pathname)) file=path.join(output,'header-'+url.pathname.split('/')[2]+'.html');
            else if(url.pathname===manifest.profile) file=path.join(output,'profile.html');
            else if(url.pathname==='/admin/siswa') file=path.join(output,'list.html');
            else if(url.pathname==='/admin/cari') return route.fulfill({body:'<p>Hasil pencarian uji</p>'});
            if(file&&fs.existsSync(file)) await route.fulfill({path:file});
            else await route.fulfill({status:404,body:'Local fixture not found'});
        });
        const page=await context.newPage();
        const errors=[];
        page.on('pageerror',error=>errors.push(error.message));
        await page.goto('http://navigation.test'+manifest.profile);
        await page.evaluate(()=>document.fonts.ready);
        const summary=page.locator('[data-account-menu] > summary');
        await summary.focus();
        await page.keyboard.press('Enter');
        await page.locator('.header-account-panel').waitFor({state:'visible'});
        assert.equal(await page.locator('.header-account-panel a[href="/admin/pengaturan"]').count(),1);
        await page.keyboard.press('Escape');
        assert.ok(await summary.evaluate(el=>el===document.activeElement),'Escape restores account trigger focus');
        await summary.click();
        await page.locator('h1').click();
        assert.equal(await page.locator('[data-account-menu]').getAttribute('open'),null,'Outside click closes account panel');
        await page.screenshot({path:path.join(output,'header-desktop.png'),fullPage:true});
        await summary.click();
        await page.locator('[data-theme]').click();
        assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('dark')));
        assert.equal(await page.locator('[data-theme]').getAttribute('aria-pressed'),'true');
        assert.equal(await page.locator('[data-theme-label]').textContent(),'Tema: Gelap');
        await page.keyboard.press('Escape');
        await page.reload();
        assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('dark')),'Theme persists after navigation');
        await summary.click();
        await page.screenshot({path:path.join(output,'header-dark-account.png'),fullPage:false});
        await page.locator('[data-theme]').click();
        await page.keyboard.press('Escape');
        await page.locator('[data-sidebar]').click();
        assert.ok(await page.locator('#dashboard-sidebar').evaluate(el=>el.inert),'Collapsed desktop sidebar is not focusable');
        await page.locator('[data-sidebar]').click();

        for(const width of [1600,1440,1301,1101,1100,850,768,700,640,601,600,390,320]){
            await page.setViewportSize({width,height:900});
            const header=await page.locator('.workspace-header').boundingBox();
            assert.ok(header.x>=0&&header.x+header.width<=width,'Header fits '+width+'px');
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Content overflow stays inside work area at '+width+'px');
            assert.equal(await page.locator('#dashboard-sidebar').evaluate(el=>el.inert),width<=1100,'Sidebar focusability follows its visibility at '+width+'px');
            const search=await page.locator('.header-search input').boundingBox();
            assert.ok(search&&search.x>=0&&search.x+search.width<=width,'Global search is reachable');
            const trigger=await summary.boundingBox();
            assert.ok(trigger&&trigger.x>=0&&trigger.x+trigger.width<=width,'Account trigger fits '+width+'px');
        }
        await page.setViewportSize({width:390,height:844});
        await page.locator('[data-sidebar]').click();
        assert.equal(await page.locator('#dashboard-sidebar').getAttribute('aria-modal'),'true');
        assert.ok(await page.locator('.main').evaluate(el=>el.inert));
        await page.locator('#dashboard-sidebar .brand').focus();
        await page.keyboard.press('Shift+Tab');
        assert.ok(await page.locator('#dashboard-sidebar [action="/logout"] button').evaluate(el=>el===document.activeElement),'Modal focus wraps backwards');
        await page.keyboard.press('Tab');
        assert.ok(await page.locator('#dashboard-sidebar .brand').evaluate(el=>el===document.activeElement),'Modal focus wraps forwards');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-sidebar]').getAttribute('aria-expanded'),'false');
        assert.ok(await page.locator('[data-sidebar]').evaluate(el=>el===document.activeElement));
        await page.locator('[data-sidebar]').click();
        await page.locator('[data-sidebar-backdrop]').click({position:{x:380,y:20}});
        assert.equal(await page.locator('#dashboard-sidebar').getAttribute('aria-modal'),null);
        await summary.click();
        const panel=await page.locator('.header-account-panel').boundingBox();
        assert.ok(panel.x>=0&&panel.x+panel.width<=390,'Account panel fits mobile');
        await page.keyboard.press('Escape');
        await page.screenshot({path:path.join(output,'header-mobile.png'),fullPage:false});
        await page.setViewportSize({width:1440,height:1000});
        for(const [role,placeholder] of [['keuangan','Cari tagihan atau transaksi'],['akademik','Cari siswa atau guru']]){
            await page.goto('http://navigation.test/header/'+role);
            assert.equal(await page.locator('.header-search input').getAttribute('placeholder'),placeholder);
        }
        await page.goto('http://navigation.test/header/long');
        assert.ok(await page.locator('.header-account-identity strong').evaluate(el=>el.scrollWidth>el.clientWidth),'Long account names are truncated');
        await page.goto('http://navigation.test/header/en');
        await summary.click();
        assert.equal(await page.locator('[data-theme-label]').textContent(),'Theme: Light');
        await page.keyboard.press('Escape');
        await page.locator('.header-search input').fill('Alya');
        await Promise.all([page.waitForURL('**/admin/cari?q=Alya'),page.locator('.header-search input').press('Enter')]);
        assert.equal(new URL(page.url()).searchParams.get('q'),'Alya');
        assert.deepEqual(errors,[],'No browser JavaScript errors');
        console.log('Header browser checks passed: account keyboard/outside dismissal, theme persistence, roles, global search, long names, 320–1600px layout, and sidebar focus management.');
    } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
