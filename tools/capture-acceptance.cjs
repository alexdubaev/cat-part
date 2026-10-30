// Run with bundled node modules available through NODE_PATH; local browser only.
const {chromium} = require('playwright');
const fs = require('fs');
const path = require('path');
const {pathToFileURL} = require('url');
const root = path.resolve(__dirname,'..');
const out = path.join(root,'.autopilot/2026-10-30-home-polish--wip/run');
async function loaded(page) {
  await page.evaluate(async()=>{
    document.documentElement.style.scrollBehavior='auto';
    for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo({top:y,behavior:'instant'});await new Promise(r=>setTimeout(r,50));}
    await Promise.all([...document.images].map(img=>img.decode().catch(()=>{})));
    window.scrollTo({top:0,behavior:'instant'});
    await new Promise(requestAnimationFrame);
  });
}
(async()=>{
  const cache=path.join(process.env.LOCALAPPDATA,'ms-playwright');
  const installed=fs.readdirSync(cache).filter(x=>/^chromium-\d+$/.test(x)).sort().reverse().map(x=>path.join(cache,x,'chrome-win64/chrome.exe')).find(x=>fs.existsSync(x));
  const browser=await chromium.launch({headless:true,...(installed?{executablePath:installed}:{})});
  const context=await browser.newContext();
  await context.route('**/*',r=>/^https?:\/\/(?:127\.0\.0\.1|localhost)(?::|\/)/.test(r.request().url()) || r.request().url().startsWith('file:') ? r.continue() : r.abort());
  const page=await context.newPage();
  const metrics=[];
  for(const width of [1440,1280,390,375]){
    await page.setViewportSize({width,height:900});
    await page.goto('http://127.0.0.1:8080/',{waitUntil:'networkidle'});
    const cookie=page.locator('#cpCookieOk');
    if(await cookie.isVisible()){await cookie.click();await page.waitForTimeout(400);}
    await loaded(page);
    const data=await page.evaluate(()=>({width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,articles:document.querySelectorAll('.cp-post').length,broken:[...document.images].filter(i=>!i.complete||!i.naturalWidth).length,articleRows:[...document.querySelectorAll('.cp-post')].map(i=>Math.round(i.getBoundingClientRect().top))}));
    if(data.overflow||data.broken||data.articles!==3 || (width>=1280&&new Set(data.articleRows).size!==1))throw Error(JSON.stringify(data));
    metrics.push(data);
    await page.screenshot({path:path.join(out,width===1440?'desktop-after.png':width===390?'mobile-after.png':`home-${width}-after.png`),fullPage:true});
  }
  for(const route of ['/kontakty','/stati']){
    await page.setViewportSize({width:1440,height:900});
    await page.goto('http://127.0.0.1:8080'+route,{waitUntil:'networkidle'});await loaded(page);
    await page.screenshot({path:path.join(out,route.slice(1)+'-after.png'),fullPage:true});
  }
  await page.goto(pathToFileURL(path.join(root,'deliverables/brandbook/index.html')).href,{waitUntil:'load'});
  await loaded(page);
  const broken=await page.evaluate(()=>[...document.images].filter(i=>!i.naturalWidth).length);
  if(broken)throw Error('Brandbook broken images: '+broken);
  await page.pdf({path:path.join(root,'deliverables/brandbook/brandbook.pdf'),format:'A4',printBackground:true,displayHeaderFooter:false,preferCSSPageSize:true});
  await browser.close();
  fs.writeFileSync(path.join(out,'visual-acceptance.json'),JSON.stringify(metrics,null,2));
  const canvas=require('@napi-rs/canvas');
  global.DOMMatrix=canvas.DOMMatrix;global.ImageData=canvas.ImageData;global.Path2D=canvas.Path2D;
  const pdfjs=await import(pathToFileURL(require.resolve('pdfjs-dist/legacy/build/pdf.mjs')).href);
  const pdf=await pdfjs.getDocument({data:new Uint8Array(fs.readFileSync(path.join(root,'deliverables/brandbook/brandbook.pdf'))),useSystemFonts:true}).promise;
  const previews=path.join(root,'deliverables/brandbook/previews');fs.mkdirSync(previews,{recursive:true});
  for(const name of fs.readdirSync(previews)){if(/^page-\d+\.png$/.test(name)||name==='overview.png')fs.unlinkSync(path.join(previews,name));}
  for(const name of fs.readdirSync(out)){const match=/^brandbook-page-(\d+)\.png$/.exec(name);if(match&&Number(match[1])>pdf.numPages)fs.unlinkSync(path.join(out,name));}
  for(let i=1;i<=pdf.numPages;i++){
    const p=await pdf.getPage(i),v=p.getViewport({scale:1.5}),c=canvas.createCanvas(Math.ceil(v.width),Math.ceil(v.height));
    await p.render({canvasContext:c.getContext('2d'),viewport:v}).promise;
    fs.writeFileSync(path.join(previews,`page-${i}.png`),c.toBuffer('image/png'));
    fs.writeFileSync(path.join(out,`brandbook-page-${i}.png`),c.toBuffer('image/png'));
  }
  console.log(JSON.stringify({screenshots:6,homeWidths:metrics.map(x=>x.width),overflow:false,brokenImages:0,brandbookPages:pdf.numPages}));
})().catch(e=>{console.error(e.message);process.exitCode=1});
