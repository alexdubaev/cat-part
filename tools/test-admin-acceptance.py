"""Narrow native HTTP/DB/browser acceptance, using only own isolated fixtures."""
import sys,secrets,subprocess,os,html,http.cookiejar,json,re,urllib.parse,urllib.request,urllib.error
from html.parser import HTMLParser
from pathlib import Path
sys.stdout.reconfigure(encoding='utf-8')
ROOT=Path(__file__).resolve().parents[1]
runtime=Path(os.environ.get('CATPART_ADMIN_RUNTIME',ROOT/'local-dev/admin-runtime'))
state=json.loads((runtime/'state.json').read_text());accounts=json.loads((runtime/'private/accounts.json').read_text());base=state['origin']
class Forms(HTMLParser):
 def __init__(self):super().__init__(convert_charrefs=True);self.forms=[];self.form=None;self.textarea=None;self.select=None;self.option=None
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='form':self.form={'action':a.get('action',''),'values':[]};self.forms.append(self.form)
  if self.form is None:return
  if tag=='input' and a.get('name') and a.get('type') not in ('submit','button','file') and (a.get('type') not in ('checkbox','radio') or 'checked' in a):self.form['values'].append((a['name'],a.get('value','')))
  if tag=='textarea':self.textarea=[a.get('name'),[]]
  if tag=='select':self.select={'name':a.get('name'),'multiple':'multiple'in a,'options':[]}
  if tag=='option' and self.select is not None:self.option=[a.get('value',''),'selected'in a]
 def handle_endtag(self,tag):
  if tag=='textarea' and self.textarea is not None:
   if self.textarea[0]:self.form['values'].append((self.textarea[0],''.join(self.textarea[1])))
   self.textarea=None
  if tag=='option' and self.select is not None and self.option is not None:self.select['options'].append(self.option);self.option=None
  if tag=='select' and self.select is not None:
   selected=[v for v,chosen in self.select['options'] if chosen]
   if not selected and not self.select['multiple'] and self.select['options']:selected=[self.select['options'][0][0]]
   self.form['values'] += [(self.select['name'],v)for v in selected if self.select['name']];self.select=None
  if tag=='form':self.form=None
 def handle_data(self,data):
  if self.textarea is not None:self.textarea[1].append(data)
def req(opener,path,data=None):
 try:
  url=path if path.startswith('http') else base+path
  with opener.open(url,data,timeout=40) as response:return response.status,response.read().decode('utf-8','replace')
 except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8','replace')
def login(role):
 opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));_,body=req(opener,'/admin/index.php?route=common/login');action=html.unescape(re.search('<form id="form-login" action="([^"]+)"',body)[1]);_,body=req(opener,action,urllib.parse.urlencode(accounts[role]).encode());token=urllib.parse.parse_qs(urllib.parse.urlsplit(html.unescape(json.loads(body)['redirect'])).query)['user_token'][0];return opener,token
def route(token,name,**query):return '/admin/index.php?'+urllib.parse.urlencode({'route':'extension/catpart/module/'+name,'user_token':token,**query})
def form(opener,path,action):
 code,body=req(opener,path);assert code==200 and 'Fatal error'not in body,(code,action)
 p=Forms();p.feed(body)
 return next(f for f in p.forms if action in html.unescape(f['action']))
def values(f):return list(f['values'])
def put(items,key,value):return [(k,v)for k,v in items if k!=key]+[(key,value)]
def post(opener,f,items):
 code,body=req(opener,html.unescape(f['action']),urllib.parse.urlencode(items).encode());assert code==200 and 'alert-danger'not in body and 'Fatal error'not in body,(code,'save failed');return body
assert re.fullmatch(r'catpart_admin_[a-f0-9]{12}',state['database']) and re.fullmatch(r'http://127\.0\.0\.1:\d+',base)
fixture=runtime/'private'/('acceptance-'+secrets.token_hex(8)+'.json');env=os.environ|{'CATPART_ADMIN_RUNTIME':str(runtime),'CATPART_ACCEPTANCE_FIXTURE':str(fixture)}
checks=0;failures=[]
def check(value,label):
 global checks
 checks+=1;print(('PASS 'if value else 'FAIL ')+label,flush=True)
 if not value:failures.append(label)
def php(mode):
 r=subprocess.run([state['php'],'-c',str(runtime/'php.ini'),str(ROOT/'tests/admin-acceptance.php'),mode],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8');print(r.stdout[-2000:]);return r.returncode==0
try:
 assert php('prepare');f=json.loads(fixture.read_text());admin,token=login('administrator');public=urllib.request.build_opener()
 check(php('cross'),'DB normalized cross search/import parity')
 for path in [route(token,'catalog',q='00 cross '+f['tag']),'/poisk?'+urllib.parse.urlencode({'q':'00 cross '+f['tag']})]:
  _,body=req(admin,path);check(all(f['tag']+' '+str(i)in body for i in [1,2]),'HTTP canonical cross candidates '+('admin'if '/admin/'in path else 'public'))
 for native,label in [('user/user','Сотрудники'),('user/user.form','Добавить сотрудника'),('user/user_permission','Роли и права'),('user/user_permission.form','Добавить роль')]:
  _,body=req(admin,'/admin/index.php?'+urllib.parse.urlencode({'route':native,'user_token':token}));check(label in body,'Russian native '+native)
 for key,path in [('catalog','/catalog'),('search','/poisk')]:
  original=form(admin,route(token,'content',section='pages',page_key=key),'content.saveSection');items=values(original)
  check(any(k=='seo['+path+'][title]' for k,v in items),'ordinary SEO editor '+path)
  if not any(k=='seo['+path+'][title]'for k,v in items):continue
  for field,value in [('title',f['tag']+' title'),('description',f['tag']+' description'),('seo_h1',f['tag']+' h1'),('canonical',path),('og_image','/assets/img/contacts.webp'),('noindex','1')]:items=put(items,'seo['+path+']['+field+']',value)
  post(admin,original,items);_,body=req(public,path+'?q=%3Cunsafe%3E' if key=='search' else path)
  check('<title>'+f['tag']+' title</title>'in body and f['tag']+' description'in body and re.search(r'<h1[^>]*>'+f['tag']+' h1</h1>',body) and 'property="og:image"'in body,'actual SEO/H1 '+path)
  check('<unsafe>'not in body and ('&lt;unsafe&gt;'in body if key=='search' else True),'escaped search query '+path)
 sf=form(admin,route(token,'content',section='pages',page_key='search'),'content.saveSection');post(admin,sf,put(values(sf),'seo[/poisk][noindex]','0'));_,body=req(public,'/poisk?q=00');check('name="robots" content="noindex, follow"'in body,'search mandatory noindex preserved after ordinary SEO edit')
 product=f['records'][1][1];category=f['records'][0][1]
 _,body=req(public,'/catalog/'+f['tag']+'-0');check('src="/assets/img/contacts.webp"'in body,'category image actual consumer')
 code,body=req(admin,route(token,'content',section='product'));parser=Forms();parser.feed(body);cf=next((x for x in parser.forms if 'content.saveSection' in html.unescape(x['action'])),{'values':[]});items=values(cf);check(any(k=='fields[product.price_hint]'for k,v in items),'ordinary product business copy editor')
 if any(k=='fields[product.price_hint]'for k,v in items):
  for field in ['price_hint','usp_1','usp_2','usp_3','delivery_copy']:items=put(items,'fields[product.'+field+']',f['tag']+' '+field)
  post(admin,cf,items);_,body=req(public,'/tovar/'+f['tag']+'-1');check(all(f['tag']+' '+x in body for x in ['price_hint','usp_1','usp_2','usp_3','delivery_copy']),'all product copy consumers')
 pf=form(admin,route(token,'catalog.edit',type='product',id=product),'catalog.save');items=put(put(values(pf),'name',f['tag']+' retained'),'price_mode','fixed');items=put(items,'price','bad')
 code,body=req(admin,html.unescape(pf['action']),urllib.parse.urlencode(items).encode());check('id="cp-price-error"'in body and 'aria-describedby="cp-price-error"'in body and f['tag']+' retained'in body,'field-local price error retains input')
 for field,value in [('name',''),('sku','x'*121),('slug','admin'),('currency','123'),('category_id','999999999'),('image_url','/private/unsafe.jpg'),('og_image','/private/unsafe.jpg')]:
  bad=put(values(pf),field,value);code,body=req(admin,html.unescape(pf['action']),urllib.parse.urlencode(bad).encode());check('id="cp-'+field+'-error"'in body and 'aria-invalid="true"'in body,'field-local validation '+field)
 categoryform=form(admin,route(token,'catalog.edit',type='category',id=category),'catalog.save');code,body=req(admin,html.unescape(categoryform['action']),urllib.parse.urlencode(put(values(categoryform),'parent_id',str(category))).encode());check('id="cp-parent_id-error"'in body,'field-local parent cycle validation')
 post(admin,categoryform,put(values(categoryform),'image_url',''));_,body=req(public,'/catalog/'+f['tag']+'-0');check('cp-category-image'not in body,'cleared category image leaves no block')
 check(f['tag']+' h1'not in body,'global catalog metadata does not replace category metadata')
 # Write synthetic markers only to actual own PHP log; preserve its exact prior bytes.
 log=runtime/'private/php-errors.log';before=log.read_bytes() if log.exists() else None
 try:
  with log.open('ab')as out:out.write(('\n'+f['tag']+' <script>bad</script> user_token=E1SECRET password="E1PASSWORD" Authorization: Bearer E1AUTH\nCookie: session_cookie=E1COOKIE; other_cookie=E1COOKIE2\n{"Authorization":"Bearer E1JSON","password":"E1JSONPASS"}\n').encode())
  _,body=req(admin,route(token,'maintenance'));check(f['tag']in body and '&lt;script&gt;bad&lt;/script&gt;'in body and all(x not in body for x in ['E1SECRET','E1PASSWORD','E1AUTH','E1COOKIE','E1COOKIE2','E1JSON','E1JSONPASS']),'actual log escaped and credentials redacted')
  with log.open('ab')as out:out.write(('\nEARLY-'+f['tag']+'\n'+''.join(f['tag']+' line '+str(i)+' '+('x'*300)+'\n'for i in range(300))).encode())
  _,tail=req(admin,route(token,'maintenance'));pres=re.findall(r'<pre[^>]*>(.*?)</pre>',tail,re.S);check('EARLY-'+f['tag']not in tail and len(pres[-1].splitlines())<=200 and f['tag']+' line 299'in tail,'actual log bounded tail')
  native_log=runtime/'private/storage/logs/error.log';native_before=native_log.read_bytes()if native_log.exists()else None
  try:
   with native_log.open('ab')as out:out.write(('\nOpenCart-'+f['tag']+' password=E1OCSECRET\n').encode())
   _,body=req(admin,route(token,'maintenance',file=str(fixture)));check('OpenCart-'+f['tag']in body and 'E1OCSECRET'not in body and '"records"'not in body,'actual OpenCart log and arbitrary path ignored')
  finally:
   if native_before is None:native_log.unlink(missing_ok=True)
   else:native_log.write_bytes(native_before)
  for role in ['editor','manager','reader']:
   client,t=login(role);code,_=req(client,route(t,'maintenance'));check(code==403,'direct maintenance denied '+role)
 finally:
  if before is None:log.unlink(missing_ok=True)
  else:log.write_bytes(before)
 r=subprocess.run(['node',str(ROOT/'tools/test-admin-acceptance.cjs')],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8');print(r.stdout[-2500:]);print(r.stderr[-800:]);check(r.returncode==0,'real browser searchable pickers and cookie clearance')
finally:
 if fixture.exists():assert php('cleanup')
print(str(checks)+' acceptance HTTP/DB checks; '+str(len(failures))+' failed')
if failures:raise SystemExit(1)
