const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'storage/app/crop-browser-check');
const php = process.env.PHP_PATH || (fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
execFileSync(php, [path.join(__dirname, 'render-crop-fixtures.php')], { cwd: root, stdio: 'inherit' });

(async () => {
    const brave = 'C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.BROWSER_PATH || (fs.existsSync(brave) ? brave : undefined) });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', async route => {
            const url = new URL(route.request().url());
            const asset = /^\/(assets|css|js)\//.test(url.pathname);
            const filename = asset ? path.resolve(root, 'public' + decodeURIComponent(url.pathname)) : path.join(output, url.pathname.slice(1) + '.html');
            if (fs.existsSync(filename)) await route.fulfill({ path: filename });
            else await route.fulfill({ status: 404 });
        });
        const page = await context.newPage(), errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.setDefaultTimeout(10000);
        await page.goto('http://crop.test/admin');
        const buffer = Buffer.from(await page.evaluate(() => {
            const canvas = document.createElement('canvas'); canvas.width = 800; canvas.height = 600;
            const ctx = canvas.getContext('2d');
            for (const [color, x, y] of [['#ed263a', 0, 0], ['#12bb55', 400, 0], ['#2371ef', 0, 300], ['#ffcb18', 400, 300]]) {
                ctx.fillStyle = color; ctx.fillRect(x, y, 400, 300);
            }
            return canvas.toDataURL('image/png').split(',')[1];
        }), 'base64');
        const upload = { name: 'quadrants.png', mimeType: 'image/png', buffer };
        const open = async (input, file = upload) => {
            await input.setInputFiles(file);
            await page.locator('[data-photo-dialog]').waitFor({ state: 'visible' });
        };
        const apply = async () => {
            await page.getByRole('button', { name: 'Gunakan potongan', exact: true }).click();
            try {
                await page.locator('[data-photo-dialog]').waitFor({ state: 'hidden', timeout: 30000 });
            } catch (error) {
                await page.screenshot({ path: path.join(output, 'crop-failure.png'), fullPage: true });
                throw new Error('Crop failed: ' + await page.locator('[data-crop-dialog-error]').textContent(), { cause: error });
            }
        };
        const box = () => page.locator('[data-crop-frame]').boundingBox();
        const drag = async (from, to) => {
            await page.mouse.move(from.x, from.y); await page.mouse.down();
            await page.mouse.move(to.x, to.y, { steps: 8 }); await page.mouse.up();
        };
        const inspectFile = input => input.evaluate(async element => {
            const file = element.files[0], bitmap = await createImageBitmap(file);
            const result = { type: file.type, size: file.size, width: bitmap.width, height: bitmap.height, name: file.name };
            bitmap.close(); return result;
        });
        const profile = page.locator('[data-crop-input]');
        await open(profile);
        assert.equal(await page.locator('input[type=range]').count(), 0);
        assert.equal(await profile.evaluate(el => el.checkValidity()), false);
        assert.equal(await page.locator('[data-crop-ratio]').isDisabled(), true);
        let before = await box();
        const handle = await page.locator('[data-crop-handle=se]').boundingBox();
        await drag({ x: handle.x + handle.width / 2, y: handle.y + handle.height / 2 }, { x: handle.x - 100, y: handle.y - 100 });
        let after = await box();
        assert.ok(after.width < before.width - 50, 'Corner handle must resize the crop');
        assert.ok(Math.abs(after.width - after.height) < 2, 'Profile stays square');
        before = after;
        await drag({ x: before.x + before.width / 2, y: before.y + before.height / 2 }, { x: before.x + before.width / 2 + 40, y: before.y + before.height / 2 + 30 });
        after = await box(); assert.ok(after.x > before.x + 20, 'Crop frame moves');
        await page.locator('[data-crop-frame]').focus(); await page.keyboard.press('ArrowLeft');
        assert.ok((await box()).x < after.x, 'Keyboard can move the frame');
        const zoomBefore = await page.locator('[data-crop-zoom]').innerText();
        await page.getByRole('button', { name: 'Perbesar foto', exact: true }).click();
        assert.notEqual(await page.locator('[data-crop-zoom]').innerText(), zoomBefore);
        await page.locator('[data-crop-stage]').hover(); await page.mouse.wheel(0, -150);
        await page.screenshot({ path: path.join(output, 'grid-transform-desktop.png'), fullPage: true });
        await apply();
        const result = await page.locator('[data-photo-result]').inputValue();
        assert.match(result, /^data:image\/jpeg;base64,/);
        assert.equal(await profile.evaluate(el => el.checkValidity()), true);
        const dimensions = await page.evaluate(async data => { const image = new Image(); image.src = data; await image.decode(); return [image.width, image.height]; }, result);
        assert.deepEqual(dimensions, [512, 512]);
        await open(profile); await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-photo-result]').inputValue(), result, 'Cancel preserves accepted profile');
        await profile.setInputFiles({ name: 'broken.png', mimeType: 'image/png', buffer: Buffer.from('broken') });
        await page.locator('[data-photo-error]').filter({ hasText: 'tidak dapat dibaca' }).waitFor();
        assert.equal(await page.locator('[data-photo-result]').inputValue(), result);
        console.log('PASS profile: resize, move, keyboard, zoom, JPEG 512px, cancel and invalid image.');

        for (const route of ['student', 'staff']) {
            await page.goto('http://crop.test/' + route); await open(page.locator('[data-crop-input]')); await apply();
            assert.match(await page.locator('[data-photo-result]').inputValue(), /^data:image\/jpeg;base64,/);
        }
        await page.goto('http://crop.test/media');
        const media = page.locator('[data-crop-input]'); await open(media);
        await page.getByLabel('Rasio potongan').selectOption('1.7777777778'); await apply();
        const cropped = await inspectFile(media);
        assert.ok(Math.abs(cropped.width / cropped.height - 16 / 9) < .01);
        assert.equal(cropped.type, 'image/png'); assert.ok(cropped.size <= 1024 * 1024);
        await open(media); await page.getByRole('button', { name: 'Batal crop', exact: true }).click();
        assert.deepEqual(await inspectFile(media), cropped, 'Cancel restores the previously accepted upload');
        await media.setInputFiles({ name: 'too-big.png', mimeType: 'image/png', buffer: Buffer.alloc(5 * 1024 * 1024 + 1) });
        assert.deepEqual(await inspectFile(media), cropped, 'Oversize input must not replace accepted upload');
        await open(media);
        const full = await box(), corner = await page.locator('[data-crop-handle=se]').boundingBox();
        await drag({ x: corner.x + 9, y: corner.y + 9 }, { x: full.x + full.width / 2, y: full.y + full.height / 2 });
        await apply();
        const redPixels = await media.evaluate(async element => {
            const image = await createImageBitmap(element.files[0]), canvas = document.createElement('canvas');
            canvas.width = image.width; canvas.height = image.height;
            const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0); image.close();
            return [.2, 1 / 3, .5, 2 / 3, .8].map(fraction => Array.from(ctx.getImageData(Math.floor(canvas.width * fraction), Math.floor(canvas.height * fraction), 1, 1).data));
        });
        assert.ok(redPixels.every(pixel => pixel[3] === 255 && [237, 38, 58].every((value, index) => Math.abs(pixel[index] - value) <= 2)),
            'Export must contain the selected red quadrant without the grid overlay: ' + JSON.stringify(redPixels));
        const transparent = Buffer.from(await page.evaluate(() => {
            const canvas = document.createElement('canvas'); canvas.width = 400; canvas.height = 300;
            const ctx = canvas.getContext('2d'); ctx.fillStyle = '#f00'; ctx.fillRect(100, 100, 100, 100);
            return canvas.toDataURL('image/png').split(',')[1];
        }), 'base64');
        await open(media, { name: 'transparent.png', mimeType: 'image/png', buffer: transparent }); await apply();
        assert.equal(await media.evaluate(async element => {
            const image = await createImageBitmap(element.files[0]), canvas = document.createElement('canvas');
            canvas.width = image.width; canvas.height = image.height;
            const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0); image.close();
            return ctx.getImageData(0, 0, 1, 1).data[3];
        }), 0, 'PNG transparency is preserved');
        const noise = Buffer.from(await page.evaluate(() => {
            const canvas = document.createElement('canvas'); canvas.width = 1100; canvas.height = 900;
            const ctx = canvas.getContext('2d'), image = ctx.createImageData(canvas.width, canvas.height);
            let seed = 42;
            for (let i = 0; i < image.data.length; i++) { seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0; image.data[i] = seed >>> 24; }
            ctx.putImageData(image, 0, 0); return canvas.toDataURL('image/png').split(',')[1];
        }), 'base64');
        assert.ok(noise.length > 1024 * 1024 && noise.length < 5 * 1024 * 1024);
        await open(media, { name: 'large.png', mimeType: 'image/png', buffer: noise }); await apply();
        assert.ok((await inspectFile(media)).size <= 1024 * 1024, 'Detailed photos are reduced to the server upload limit');
        console.log('PASS student/staff/media: crop pixels, PNG transparency, ratios, size compression, cancel and oversize file.');

        await page.goto('http://crop.test/website');
        const websiteInputs = page.locator('[data-crop-input]'); assert.ok(await websiteInputs.count() > 1);
        await open(websiteInputs.nth(0)); await apply();
        const first = await inspectFile(websiteInputs.nth(0));
        await open(websiteInputs.nth(1)); await page.keyboard.press('Escape');
        assert.deepEqual(await inspectFile(websiteInputs.nth(0)), first);
        assert.equal(await websiteInputs.nth(1).evaluate(el => el.files.length), 0);
        await open(websiteInputs.nth(1)); await page.getByLabel('Rasio potongan').selectOption('free');
        const freeBefore = await box(), freeHandle = await page.locator('[data-crop-handle=se]').boundingBox();
        await drag({ x: freeHandle.x + 9, y: freeHandle.y + 9 }, { x: freeHandle.x - 120, y: freeHandle.y + 9 });
        assert.ok((await box()).width < freeBefore.width - 70); await apply();
        assert.deepEqual(await inspectFile(websiteInputs.nth(0)), first, 'Multiple CMS fields remain independent');
        console.log('PASS CMS: multiple photo inputs, independent results, free crop.');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('http://crop.test/ppdb');
        const passport = page.locator('[name=photo]');
        assert.equal(await page.locator('[data-crop-input]').count(), 1, 'Document scans stay outside the photo cropper');
        await open(passport);
        assert.equal(await page.locator('[data-crop-ratio]').inputValue(), '0.75');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        await page.screenshot({ path: path.join(output, 'grid-transform-mobile.png'), fullPage: true });
        const cdp = await context.newCDPSession(page), stage = await page.locator('[data-crop-stage]').boundingBox();
        const cx = stage.x + stage.width / 2, cy = stage.y + stage.height / 2;
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: cx - 30, y: cy, id: 1 }, { x: cx + 30, y: cy, id: 2 }] });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: cx - 60, y: cy, id: 1 }, { x: cx + 60, y: cy, id: 2 }] });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        assert.ok(parseInt(await page.locator('[data-crop-zoom]').innerText()) > 100, 'Pinch zoom must work');
        await apply();
        const passportFile = await inspectFile(passport);
        assert.equal(passportFile.type, 'image/jpeg'); assert.ok(passportFile.size <= 1024 * 1024);
        assert.ok(Math.abs(passportFile.width / passportFile.height - .75) < .01);
        assert.deepEqual(errors, []);
        console.log('PASS PPDB mobile: pinch zoom, 3:4 JPEG, no overflow or JavaScript errors.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
