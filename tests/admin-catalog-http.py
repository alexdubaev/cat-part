"""Short native admin → shared catalog → public catalog scenario, with role denial."""
import html, http.cookiejar, json, os, re, secrets, subprocess, sys, urllib.error, urllib.parse, urllib.request
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
runtime=Path(os.environ.get('CATPART_ADMIN_RUNTIME', ROOT/'local-dev/admin-runtime'))
state=json.loads((runtime/'state.json').read_text())
accounts=json.loads((runtime/'private/accounts.json').read_text())
base=state['origin'];media=[];tag='cp-http-'+secrets.token_hex(5);passed=0

def check(ok,label):
    global passed
    if not ok:raise AssertionError(label)
    passed+=1;print('PASS',label)

def request(opener,path,data=None):
    try:
        with opener.open(base+path,urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None,timeout=25) as r:return r.status,r.read().decode('utf-8','replace'),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8','replace'),e.geturl()

def multipart(opener,path,fields,files):
    boundary='----Catpart'+secrets.token_hex(12);parts=[]
    for name,value in fields.items():
        parts.extend([('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+str(value)+'\r\n').encode()])
    for name,filename,content,mime in files:
        parts.extend([('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"; filename="'+filename+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode(),content,b'\r\n'])
    parts.append(('--'+boundary+'--\r\n').encode())
    req=urllib.request.Request(base+path,b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary})
    try:
        with opener.open(req,timeout=25) as r:return r.status,r.read().decode('utf-8','replace'),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8','replace'),e.geturl()

def login(role):
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    code,body,_=request(opener,'/admin/index.php?route=common/login')
    form=re.search(r'<form id="form-login" action="([^"]+)"',body)
    assert form,'native login form'
    u=urllib.parse.urlsplit(html.unescape(form[1]));code,body,_=request(opener,u.path+'?'+u.query,accounts[role]);result=json.loads(body)
    token=urllib.parse.parse_qs(urllib.parse.urlsplit(html.unescape(result['redirect'])).query)['user_token'][0]
    return opener,token

def action(token,area='catalog',method='',**query):
    return '/admin/index.php?'+urllib.parse.urlencode({'route':'extension/catpart/module/'+area+('.'+method if method else ''),'user_token':token,**query})


def cleanup():
    result=subprocess.run([state['php'],'-c',str(runtime/'php.ini'),str(ROOT/'tests/admin-catalog-http-cleanup.php')],input=json.dumps({'tag':tag,'media':list(set(media))}),text=True,capture_output=True,env=os.environ|{'CATPART_ADMIN_RUNTIME':str(runtime)},cwd=ROOT)
    if result.returncode:raise RuntimeError('Own HTTP fixture cleanup failed; inspect private runtime log.')

if '--currency-only' in sys.argv:
    try:
        admin,token=login('administrator')
        values={'name':'Currency part '+tag,'slug':tag+'-currency','sku':'000-'+tag,'status':'published','price_mode':'fixed','price':'456.78','currency':'USD'}
        code,body,url=request(admin,action(token,method='save',type='product'),values)
        product=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('id',[''])[0]
        if not product.isdigit():raise RuntimeError('Currency fixture could not be created.')
        visitor=urllib.request.build_opener()
        code,body,_=request(visitor,'/tovar/'+tag+'-currency')
        check(code==200 and '456,78 $' in body,'saved currency and exact cents appear on public detail')
        code,body,_=request(visitor,'/poisk?'+urllib.parse.urlencode({'q':'000-'+tag}))
        check(code==200 and '456,78 $' in body,'SKU search uses same currency as canonical product')
        print(f'{passed} passed, 0 failed')
    finally:cleanup()
    raise SystemExit(0)

try:
    admin,token=login('administrator')
    code,body,_=request(admin,action(token,type='product'))
    check(code==200 and 'Создать запись' in body and 'Импорт CSV' in body,'native CatPart catalog list opens')
    code,body,_=request(admin,action(token,method='edit',type='category'))
    check(code==200 and 'Родительская категория' in body,'category editor uses normal fields')
    code,body,url=request(admin,action(token,method='save',type='category'),{'name':'HTTP category '+tag,'slug':tag+'-category','status':'published'})
    category=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('id',[''])[0]
    check(code==200 and category.isdigit() and 'Сохранено.' in body,'native admin creates real category')
    code,body,url=request(admin,action(token,method='save',type='brand'),{'name':'HTTP brand '+tag,'slug':tag+'-brand','status':'published','intro_html':'<p>HTTP brand description</p>','logo_url':'/assets/img/logo.webp','seo_title':'HTTP brand SEO '+tag})
    brand=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('id',[''])[0]
    check(code==200 and brand.isdigit(),'brand editor saves logo description and SEO')
    values={'brand_id':brand,'name':'HTTP part '+tag,'slug':tag+'-part','sku':'000-'+tag,'category_id':category,'status':'published','price_mode':'fixed','price':'456.78','currency':'RUB','in_stock':'1','short_description':'HTTP shared summary','delivery_terms':'HTTP agreed delivery','body_html':'<h2>HTTP details</h2><p>Safe body<script>alert(1)</script></p>','image_url':'/assets/img/logo.webp','seo_title':'HTTP product SEO '+tag,'seo_description':'HTTP product description','seo_h1':'HTTP product H1','canonical':'/tovar/'+tag+'-part'}
    code,body,url=request(admin,action(token,method='save',type='product'),values)
    product=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('id',[''])[0]
    check(code==200 and product.isdigit() and 'Сохранено.' in body,'native admin creates canonical product')
    visitor=urllib.request.build_opener();code,body,_=request(visitor,'/tovar/'+tag+'-part')
    check(code==200 and 'HTTP product H1' in body and 'HTTP shared summary' in body and 'HTTP agreed delivery' in body and '456,78' in body,'public detail shows saved title summary delivery and price')
    check('HTTP product SEO '+tag in body and 'HTTP product description' in body and 'alert(1)' not in body,'public SEO and sanitized description reflect admin save')
    values['id']=product;values['currency']='USD'
    request(admin,action(token,method='save',type='product'),values)
    code,body,_=request(visitor,'/tovar/'+tag+'-part')
    check(code==200 and '456,78 $' in body,'saved currency and exact cents appear on public detail')
    code,body,_=request(visitor,'/poisk?'+urllib.parse.urlencode({'q':'000-'+tag}))
    check(code==200 and '456,78 $' in body,'SKU search uses same currency as canonical product')
    values['currency']='RUB';request(admin,action(token,method='save',type='product'),values)
    code,body,_=request(visitor,'/catalog/'+tag+'-category')
    check(code==200 and '000-'+tag in body,'public category lists new shared product')
    code,body,_=request(visitor,'/poisk?'+urllib.parse.urlencode({'q':'000-'+tag}))
    check(code==200 and 'HTTP part '+tag in body,'public search finds new canonical SKU')
    code,body,_=request(visitor,'/brand/'+tag+'-brand')
    check(code==200 and 'HTTP brand SEO '+tag in body and 'HTTP brand description' in body and '000-'+tag in body,'public brand page consumes SEO description logo and real products')
    image=(ROOT/'local-dev/site/assets/img/logo.webp').read_bytes()
    values['id']=product
    code,body,_=multipart(admin,action(token,method='save',type='product'),values,[('main_upload','photo.webp',image,'image/webp'),('gallery_upload[0]','',b'','application/octet-stream')])
    check(code==200 and 'Сохранено.' in body,'multipart form accepts verified image and ignores empty upload controls')
    code,body,_=request(visitor,'/tovar/'+tag+'-part')
    found=re.findall(r'/media/catalog/[a-f0-9]{32}\.(?:webp|png|jpg|gif)',body);media.extend(found)
    check(bool(found),'uploaded image is the public primary photo')
    code,_,_=request(visitor,found[0]);check(code==200,'uploaded own media is served from stand')
    values['slug']=tag+'-changed'
    code,body,_=request(admin,action(token,method='save',type='product'),values)
    check(code==200 and 'подтвердите' in body and 'HTTP part '+tag in body,'slug error retains entered fields')
    values['confirm_slug']='1';request(admin,action(token,method='save',type='product'),values)
    code,body,url=request(visitor,'/tovar/'+tag+'-part')
    check(code==200 and urllib.parse.urlsplit(url).path=='/tovar/'+tag+'-changed','old public address redirects to changed product')
    for role in ['manager','reader']:
        client,t=login(role);code,body,_=request(client,action(t,method='save',type='product'),{'id':product,'name':'Unauthorized'})
        check(code==403,'direct catalog modify denied '+role)
    csv=('sku,name,slug,status\n000-'+tag+'-CSV,HTTP imported,'+tag+'-import,published\n').encode()
    code,body,url=multipart(admin,action(token,area='imports',method='upload'),{},[('catalog_file',tag+'.csv',csv,'text/csv')])
    job=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('id',[''])[0]
    check(code==200 and len(job)==32 and 'Сопоставление колонок' in body,'native multipart CSV opens column mapping')
    code,body,_=request(admin,action(token,area='imports',method='preview'),{'id':job,'mapping[0]':'sku','mapping[1]':'name','mapping[2]':'slug','mapping[3]':'status'})
    check(code==200 and 'Готово к применению: 1;' in body and 'Создать' in body,'native import preview shows proposed row without applying')
    code,body,_=request(admin,action(token,area='imports',method='apply'),{'id':job,'confirmed':'1'})
    check(code==200 and 'применено: 1;' in body,'native import explicit apply produces report')
    code,body,_=request(visitor,'/tovar/'+tag+'-import');check(code==200 and 'HTTP imported' in body,'native imported product appears on shared storefront')
    editor,t=login('editor');code,body,_=request(editor,action(t,area='imports'))
    check(code==200 and 'Сопоставление' not in body and 'Файл каталога' in body,'editor can open CSV XLSX import without raw JSON')
    code,body,_=request(visitor,'/tovar/'+tag+'-changed')
    check(code==200 and 'Unauthorized' not in body,'denied saves leave published product unchanged')
    print(f'{passed} passed, 0 failed')
finally:
    cleanup()
