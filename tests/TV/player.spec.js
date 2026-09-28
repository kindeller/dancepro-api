import fs from 'node:fs';
import { createHash } from 'node:crypto';
import { test, expect } from '@playwright/test';
const id = 'slide-00000000-0000-4000-8000-000000000001';
const photo = 'photo-00000000-0000-4000-8000-000000000002';
const text = {id,kind:'text',title:'Live event information',body:'Welcome to DancePro',seconds:3,version:'a'.repeat(64)};
function manifest(slides) {return {paired:true,image_variant:'unwatermarked-v1',enabled:true,event:{uuid:'event',name:'Demo event'},revision:'b'.repeat(64),offline_until:new Date(Date.now()+86400000).toISOString(),slides};}
async function setup(page, getManifest) {
    await page.route('**/api/publicity/screens/pair', route => route.fulfill({json:{success:true,data:{token:'t'.repeat(64),code:'123456',expires_in:600}}}));
    await page.route('**/api/publicity/screens/manifest?*',route=>route.fulfill({json:{success:true,data:getManifest()}}));
    await page.goto('/tv');
    await page.getByRole('button',{name:'Pair screen',exact:true}).click();
}
test('cached playback survives offline reload, then removes old slides after reconnecting',async({page,context})=>{
    let data=manifest([text]); await setup(page,()=>data);
    await expect(page.locator('#status')).toContainText('Ready offline');
    await expect(page.locator('.frame.visible')).toContainText('Live event information');
    await page.evaluate(async()=>{await navigator.serviceWorker.ready;});
    await context.setOffline(true); await page.reload();
    await expect(page.locator('.frame.visible')).toContainText('Live event information');
    data=manifest([{...text,title:'Updated event information',version:'c'.repeat(64)}]);
    await context.setOffline(false); await page.keyboard.press('m');
    await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('.frame.visible')).toContainText('Updated event information');
    data={...manifest([]),enabled:false}; await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('#message')).toContainText('TV playback is disabled');
    await expect(page.locator('.frame.visible')).toHaveCount(0);
});
test('failed image downloads keep ready slides playing; removed slides disappear on update',async({page})=>{
    let data=manifest([text,{id:photo,kind:'image',title:'Photo',seconds:3,version:'a'.repeat(64)}]);
    await page.route('**/api/publicity/screens/images/*',route=>route.fulfill({status:503,body:'Not ready'}));
    await setup(page,()=>data);
    await expect(page.locator('#status')).toContainText('1/2 slides');
    await expect(page.locator('.frame.visible')).toContainText('Live event information');
    data=manifest([]); await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('.frame.visible')).toHaveCount(0);
    await expect(page.locator('#message')).toContainText('No slides yet');
});
test('revoked device clears stored media and expired offline lease stops playback',async({page})=>{
    let data=manifest([text]);await setup(page,()=>data);
    await expect(page.locator('#status')).toContainText('Ready offline');
    data={...manifest([text]),offline_until:new Date(Date.now()-1000).toISOString()};
    await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('.frame.visible')).toHaveCount(0);
    await page.route('**/api/publicity/screens/manifest?*',route=>route.fulfill({status:401,json:{success:false}}));
    await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('#message')).toContainText('Access has ended');
    const stored=await page.evaluate(()=>new Promise(resolve=>{const req=indexedDB.open('dancepro-tv-v1');req.onsuccess=()=>{const read=req.result.transaction('data').objectStore('data').get('token');read.onsuccess=()=>resolve(read.result??null);};}));
    expect(stored).toBeNull();
});
test('images are downloaded once and survive an offline restart',async({page,context})=>{
    let requests=0;
    await page.route('**/api/publicity/screens/images/*',route=>{requests++;return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="80" height="60"><rect width="80" height="60" fill="blue"/></svg>'});});
    await setup(page,()=>manifest([{id:photo,kind:'image',title:'Cached photo',body:null,seconds:3,version:'a'.repeat(64)}]));
    await expect(page.locator('#status')).toContainText('Ready offline');
    await expect(page.locator('.frame.visible img')).toBeVisible();
    await page.keyboard.press('m'); await page.getByRole('button',{name:'Check for updates'}).click();
    await expect(page.locator('#status')).toContainText('Ready offline');
    expect(requests).toBe(1);
    await page.evaluate(async()=>{await navigator.serviceWorker.ready;});
    await context.setOffline(true); await page.reload();
    await expect(page.locator('.frame.visible img')).toBeVisible();
    expect(await page.locator('.frame.visible img').evaluate(img=>img.naturalWidth)).toBe(80);
});

for (const [orientation, width, height] of [['landscape', 120, 80], ['portrait', 80, 120]]) {
    test(`${orientation} photos fill the display and preserve proportions`, async ({page}) => {
        await page.setViewportSize({width:1600,height:900});
        await page.route('**/api/publicity/screens/images/*', route => route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%" fill="blue"/></svg>`}));
        await setup(page,()=>manifest([{id:photo,kind:'image',title:'',body:null,seconds:60,version:'a'.repeat(64)}]));
        const image = page.locator('.frame.visible img');
        await expect(image).toBeVisible();
        const display = await image.evaluate(img => {
            const box = img.getBoundingClientRect();
            return {x:box.x,y:box.y,width:box.width,height:box.height,fit:getComputedStyle(img).objectFit,naturalWidth:img.naturalWidth,naturalHeight:img.naturalHeight};
        });
        expect(display).toEqual({x:0,y:0,width:1600,height:900,fit:'cover',naturalWidth:width,naturalHeight:height});
    });
}

for (const body of [null, 'Available now']) {
    test(`advert artwork fills the screen with ${body ? 'an overlay caption' : 'no title strip'}`, async ({page}) => {
        await page.setViewportSize({width:1920,height:1080});
        await page.route('**/api/publicity/screens/images/*', route => route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><rect width="100%" height="100%" fill="black"/></svg>'}));
        await setup(page,()=>manifest([{id,kind:'image',title:'DancePro',body,seconds:60,version:'a'.repeat(64)}]));
        const frame = page.locator('.frame.visible');
        await expect(frame.locator('img')).toBeVisible();
        expect(await frame.locator('img').boundingBox()).toEqual({x:0,y:0,width:1920,height:1080});
        await expect(frame.locator('.caption')).toHaveCount(body ? 1 : 0);
        if (body) await expect(frame.locator('.caption')).toHaveCSS('position','absolute');
    });
}

const clip = fs.readFileSync(new URL('../Fixtures/publicity/advert.mp4',import.meta.url));
const videoSlide = {id,kind:'video',title:'Video advert',body:null,seconds:3,version:'e'.repeat(64),sha256:createHash('sha256').update(clip).digest('hex'),bytes:clip.length,play_sound:false};
test('video adverts play silently to completion, then resume slides and survive offline restart',async({page,context})=>{
    let requests = 0;
    await page.route('**/api/publicity/screens/videos/*',route=>{requests++; return route.fulfill({contentType:'video/mp4',body:clip});});
    await setup(page,()=>manifest([videoSlide,{...text,id:'auto-00000000-0000-4000-8000-000000000003',seconds:2}]));
    const video = page.locator('.frame.visible video');
    await expect(video).toBeVisible();
    await expect.poll(()=>video.evaluate(v=>v.currentTime)).toBeGreaterThan(0.1);
    expect(await video.evaluate(v=>v.muted)).toBe(true);
    await expect(page.locator('.frame.visible')).toContainText('Live event information',{timeout:8000});
    expect(requests).toBe(1);
    await page.evaluate(async()=>{await navigator.serviceWorker.ready;});
    await context.setOffline(true); await page.reload();
    await expect(page.locator('.frame.visible video')).toBeVisible();
    expect(requests).toBe(1);
});
test('video pause resumes the same clip and Next stops it',async({page})=>{
    await page.route('**/api/publicity/screens/videos/*',route=>route.fulfill({contentType:'video/mp4',body:clip}));
    await setup(page,()=>manifest([videoSlide,{...text,id:'auto-00000000-0000-4000-8000-000000000003',seconds:2}]));
    const video=page.locator('.frame.visible video'); await expect(video).toBeVisible();
    await page.keyboard.press('m');
    await page.getByRole('button',{name:'Pause',exact:true}).click();
    const position=await video.evaluate(v=>v.currentTime); await page.waitForTimeout(400);
    expect(await video.evaluate(v=>v.currentTime)).toBe(position);
    await page.getByRole('button',{name:'Play',exact:true}).click();
    await expect.poll(()=>video.evaluate(v=>v.currentTime)).toBeGreaterThan(position);
    await page.getByRole('button',{name:'Next slide',exact:true}).click();
    await expect(page.locator('.frame.visible')).toContainText('Live event information');
    expect(await page.locator('video').evaluateAll(videos=>videos.every(v=>v.paused))).toBe(true);
});
test('incomplete videos never enter playback',async({page})=>{
    await page.route('**/api/publicity/screens/videos/*',route=>route.fulfill({contentType:'video/mp4',body:clip.subarray(0,100)}));
    await setup(page,()=>manifest([videoSlide,{...text,id:'auto-00000000-0000-4000-8000-000000000003',seconds:2}]));
    await expect(page.locator('#status')).toContainText('1/2 slides');
    await expect(page.locator('.frame.visible')).toContainText('Live event information');
    await expect(page.locator('video')).toHaveCount(0);
});

test('slideshow controls remain hidden during playback and pointer movement',async({page})=>{
    await page.route('**/api/publicity/screens/videos/*',route=>route.fulfill({contentType:'video/mp4',body:clip}));
    await setup(page,()=>manifest([videoSlide]));
    const video = page.locator('.frame.visible video'); await expect(video).toBeVisible();
    await expect(page.locator('#controls')).toHaveClass(/hidden/);
    await page.mouse.move(200,200); await expect(page.locator('#controls')).toHaveClass(/hidden/);
    expect(await video.evaluate(v=>v.controls)).toBe(false);
    await page.keyboard.press('m'); await expect(page.locator('#controls')).not.toHaveClass(/hidden/);
    await page.getByRole('button',{name:'Hide controls',exact:true}).click();
    await expect(page.locator('#controls')).toHaveClass(/hidden/);
});

for (const kind of ['image', 'video']) {
    test(`general ${kind} adverts persist across events, settings changes and disabled playback`, async ({page}) => {
        let downloads = 0;
        const ad = kind === 'video' ? {...videoSlide, asset_version:videoSlide.sha256} : {id,kind:'image',title:'General',seconds:5,version:'a'.repeat(64),asset_version:'c'.repeat(64)};
        let data = {...manifest([ad]), standard_slides:[ad]};
        await page.route(`**/api/publicity/screens/${kind === 'video' ? 'videos' : 'images'}/*`, route => {
            downloads++;
            return route.fulfill(kind === 'video' ? {contentType:'video/mp4',body:clip} : {contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="80" height="60"><rect width="80" height="60" fill="blue"/></svg>'});
        });
        await setup(page, () => data);
        await expect(page.locator('#status')).toContainText('Ready offline');
        const refresh = async () => {
            await page.keyboard.press('m');
            await page.getByRole('button',{name:'Check for updates'}).click();
        };
        const keys = () => page.evaluate(async () => {
            const {SlideCache} = await import('/tv-assets/cache.js');
            const cache = new SlideCache(); await cache.open(); return (await cache.keys()).filter(k => k.startsWith('image:'));
        });
        const originalKeys = await keys();
        data = {...manifest([]),enabled:false,standard_slides:[ad]};
        await refresh(); await expect(page.locator('#status')).toContainText('Playback disabled');
        expect(await keys()).toEqual(originalKeys);
        const changed = {...ad,title:'Updated label',seconds:kind === 'video' ? 3 : 8,play_sound:false,version:'d'.repeat(64)};
        data = {...manifest([changed]),event:{uuid:'second-event',name:'Second event'},standard_slides:[changed]};
        await refresh(); await expect(page.locator('#status')).toContainText('Ready offline');
        await page.reload(); await page.waitForLoadState('networkidle');
        await expect(page.locator('#status')).toContainText('Ready offline');
        expect(downloads).toBe(1);
        expect(await keys()).toEqual(originalKeys);
        const replaced = {...changed,asset_version:'f'.repeat(64),version:'f'.repeat(64)};
        data = {...manifest([replaced]),standard_slides:[replaced]};
        await refresh(); await expect.poll(() => downloads).toBe(2);
        await expect(page.locator('#status')).toContainText('Ready offline');
        expect(await keys()).not.toEqual(originalKeys);
        data = {...manifest([]),enabled:false,standard_slides:[]};
        await refresh(); await expect(page.locator('#status')).toContainText('Playback disabled');
        expect(await keys()).toEqual([]);
    });
}
