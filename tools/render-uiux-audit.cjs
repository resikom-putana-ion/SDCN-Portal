const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({headless:true,executablePath:process.env.BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        const page = await browser.newPage({viewport:{width:1512,height:1100},deviceScaleFactor:1});
        const errors = [];
        page.on('pageerror', e=>errors.push(e.message));
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            const file = path.resolve(root, '.'+decodeURIComponent(url.pathname));
            if (url.host === 'audit.test' && file.startsWith(root+path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) await route.fulfill({path:file});
            else await route.fulfill({status:404,body:'Local audit asset not found'});
        });
        await page.goto('http://audit.test/docs/audit-uiux/header-proposal.html');
        await page.evaluate(()=>document.fonts.ready);
        assert.ok(await page.evaluate(()=>document.fonts.check('500 14px Poppins')));
        assert.equal(await page.locator('img').evaluateAll(imgs=>imgs.every(img=>img.complete && img.naturalWidth>0)),true);
        await page.locator('#desktop-proposal').screenshot({path:path.join(root,'docs/audit-uiux/header-desktop.png')});
        await page.locator('#mobile-proposal').screenshot({path:path.join(root,'docs/audit-uiux/header-mobile.png')});
        await page.locator('.profile summary').click();
        assert.ok(await page.locator('.dropdown').isVisible());
        await page.locator('.profile summary').click();
        await page.setViewportSize({width:390,height:844});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth),'Proposal fits mobile width');
        assert.deepEqual(errors,[]);
        console.log('Captured and checked standalone desktop/mobile header proposals.');
    } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
