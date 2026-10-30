"""Package all 26 approved illustrations beside the standalone brandbook."""
from pathlib import Path
import re
import shutil
from PIL import Image, ImageDraw, ImageFont

root = Path(__file__).resolve().parents[1]
out = root/'deliverables/brandbook'
site = root/'local-dev/site/assets/img'
groups = [('catalog-icons', 'Каталог'), ('benefits', 'Преимущества'), ('articles', 'Статьи')]
assets = []
for folder, label in groups:
    for path in sorted((site/folder).glob('*.webp')):
        if path.stem.endswith('-thumb') or path.stem == 'article-default': continue
        target = out/'assets'/folder/path.name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(path, target)
        assets.append((folder, path, label))
assert len(assets) == 26, f'Expected 26 approved images, got {len(assets)}'
html = (out/'index.html').read_text(encoding='utf-8')
html = html.replace('../../local-dev/site/assets/img/', 'assets/')
html = html.replace('benefits/in-stock.webp', 'benefits/stock.webp').replace('articles/filters-and-fluids', 'articles/filtry-i-rabochie-zhidkosti-poddelka')
html = html.replace('Имена файлов — английские semantic slugs', 'Имена файлов — semantic slugs из согласованного реестра')
contacts = re.search(r'<h2>Контакты</h2>(<p>.*?</p>)', html, re.S)
if contacts:
    html = html.replace(contacts.group(0), '')
    html = html.replace('Внутреннее руководство</div>', 'Внутреннее руководство' + contacts.group(1).replace('<p>', '<p class="brand-contacts">') + '</div>')
if '<h2>Название и знак</h2>' not in html:
    html = html.replace('<h2>Цвета</h2>', '<h2>Название и знак</h2><p>Пишем CatPart слитно, с прописными C и P; рядом при необходимости поясняем «сайт компании-поставщика СМ ТЕХНО». Знак CP сохраняет пропорции: не растягивать, не наклонять, не добавлять эффекты. Охранное поле — не менее половины высоты знака; графит на белом или жёлтом фоне. Знаки Caterpillar в фирменную систему не включаем.</p><h2>Цвета</h2>')
gallery = '<section class="asset-library"><h2>Полная библиотека · 26 иллюстраций</h2>'
for folder, label in groups:
    gallery += f'<h3>{label}</h3><div class="sample library-grid">'
    for group, path, _ in assets:
        if group != folder: continue
        name = path.stem
        gallery += f'<figure><img src="assets/{group}/{path.name}" alt="{name}" width="384" height="384"><figcaption>{group}/{path.name}</figcaption></figure>'
    gallery += '</div>'
gallery += '</section>'
html = re.sub(r'<section class="asset-library">.*?</section>', '', html, flags=re.S)
html = html.replace('</main>', gallery + '</main>')
style = '''
<style id="print-polish">
h2,h3{break-after:avoid}figure,.rule,.swatch{break-inside:avoid}.brand-contacts{font-size:11px;line-height:1.6}.mono{display:block;margin:8px 0;overflow-wrap:anywhere}.library-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.library-grid figcaption{overflow-wrap:anywhere;font-size:10px}.library-grid img{height:140px;aspect-ratio:auto;background:#fff}.asset-library{break-before:page}
@page{size:A4;margin:14mm} @media print{body{background:white;font-size:11px;line-height:1.45}main{padding:0}header{padding-bottom:18px}.mark{font-size:54px}h1{font-size:32px}h2{font-size:19px;margin-top:20px}h3{margin-top:14px}.lead{font-size:15px}p{break-inside:avoid}.sample:not(.library-grid){grid-template-columns:repeat(4,minmax(0,1fr))}.sample:not(.library-grid) img{height:100px;aspect-ratio:auto}.sample img{max-height:140px}.rule{gap:14px}.rule div{padding:12px}.mono{font-size:10px}.library-grid{gap:8px}.library-grid figure{padding:5px}.library-grid img{height:130px}}
</style>'''
html = re.sub(r'<style id="print-polish">.*?</style>', '', html, flags=re.S)
html = html.replace('</head>', style + '</head>')
(out/'index.html').write_text(html, encoding='utf-8')
sheet = Image.new('RGB', (1440, 5*260), 'white')
draw = ImageDraw.Draw(sheet)
font = ImageFont.truetype('C:/Windows/Fonts/arial.ttf', 13)
for idx, (group, path, _) in enumerate(assets):
    x,y = (idx%6)*240, (idx//6)*260
    image = Image.open(path).convert('RGB'); image.thumbnail((220,215))
    sheet.paste(image, (x+(240-image.width)//2,y+(220-image.height)//2))
    name = path.stem
    if len(name)>29: name = name[:29]+'…'
    draw.text((x+8,y+225),name,fill='#20252b',font=font)
    draw.text((x+8,y+244),group,fill='#66717b',font=font)
sheet.save(out/'asset-contact-sheet.png')
print('Brandbook packaged: 26 illustrations; standalone paths; contact sheet created')
