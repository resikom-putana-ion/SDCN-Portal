const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');

(async()=>{
    const brave='C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe';
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_PATH || (fs.existsSync(brave)?brave:undefined)});
    try {
        const page = await browser.newPage();
        const errors=[];
        page.on('pageerror',error=>errors.push(error.message));
        let requests=0;
        let items=[
            {id:'invoice-1',category:'tagihan',title:'Tagihan terlambat',description:'Sisa Rp300.000',href:'/admin/pembayaran',time:'19 Okt'},
            {id:'applicant-1',category:'pendaftaran',title:'Pendaftar baru',description:'PPDB',href:null,time:'19 Okt'},
            {id:'invoice-2',category:'tagihan',title:'Tagihan segera jatuh tempo',description:'SPP',href:'/admin/pembayaran',time:'20 Okt'},
        ];
        await page.route('**/*',route=>{
            if(route.request().url().endsWith('/feed')){
                requests++;
                return route.fulfill({json:{notifications:items,count:items.length}});
            }
            return route.fulfill({body:`<html><body><a data-notification-feed="/feed"><span data-notification-count hidden></span></a><div class="tabs notification-tabs">${['semua','pendaftaran','tagihan','kas'].map(category=>`<button data-notification-filter="${category}">${category} <span data-notification-tab-count></span></button>`).join('')}</div><div data-notification-list data-notification-categories='{"pendaftaran":"Pendaftaran","tagihan":"Tagihan"}'><div class="notification-item">Initial item</div></div><p class="empty" data-notification-category-empty hidden>Belum ada notifikasi dalam kategori ini.</p><p data-notification-checked></p></body></html>`});
        });
        await page.goto('http://notifications.test');
        await page.evaluate(()=>document.body.classList.add('dashboard-shell'));
        await page.addScriptTag({content:fs.readFileSync(path.resolve(__dirname,'../../public/js/app.js'),'utf8')});
        await page.addStyleTag({content:fs.readFileSync(path.resolve(__dirname,'../../public/css/app.css'),'utf8')});
        assert.equal(requests,0,'Server-rendered feed must not fetch twice on load');
        assert.equal(await page.locator('[data-notification-count]').textContent(),'1');
        await page.evaluate(()=>refreshNotifications());
        assert.deepEqual(await page.locator('.notification-group h3').allTextContents(),['Pendaftaran 1','Tagihan 2']);
        assert.deepEqual(await page.locator('.notification-group').nth(1).locator('strong').allTextContents(),['Tagihan terlambat','Tagihan segera jatuh tempo']);
        assert.equal(await page.locator('.notification-group').first().locator('a').count(),0);
        const beforeSelection=requests;
        await page.locator('[data-notification-filter="tagihan"]').click();
        assert.equal(await page.locator('.notification-group:visible').count(),1);
        assert.equal(await page.locator('.notification-item:visible').count(),2);
        assert.equal(await page.locator('[data-notification-filter="tagihan"]').getAttribute('aria-pressed'),'true');
        assert.equal(requests,beforeSelection,'Switching category does not fetch again');
        items[0].description='Updated balance';
        await page.evaluate(()=>refreshNotifications());
        assert.equal(await page.locator('.notification-group:visible').getAttribute('data-notification-category'),'tagihan','Selection survives refresh');
        await page.locator('[data-notification-filter="kas"]').click();
        assert.equal(await page.locator('.notification-item:visible').count(),0);
        assert.equal(await page.locator('[data-notification-category-empty]').isVisible(),true);
        await page.locator('[data-notification-filter="semua"]').focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('.notification-item:visible').count(),3);
        await page.setViewportSize({width:390,height:844});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        items=[];
        await page.evaluate(()=>refreshNotifications());
        assert.equal(await page.locator('.notification-group').count(),0);
        assert.match(await page.locator('.empty:visible').textContent(),/Belum ada notifikasi/);
        assert.equal(await page.locator('[data-notification-count]').isVisible(),false);
        assert.deepEqual(errors,[]);
        console.log('Notification grouping, refresh, empty state, and initial request checks passed.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1});
