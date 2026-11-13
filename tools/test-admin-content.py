"""Real native-admin saves against the isolated stand, restoring all changed settings."""
import os,html,http.cookiejar,json,re,urllib.parse,urllib.request,urllib.error
from html.parser import HTMLParser
from pathlib import Path
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
checks=0
def ok(value,label):
 global checks
 assert value,label
 checks+=1;print('PASS',label)
admin,token=login('administrator');public=urllib.request.build_opener()
contact=form(admin,route(token,'settings',tab='contacts'),'settings.save');original=values(contact)
try:
 changed=put(original,'phones','+7 900 111-22-33\n+7 900 444-55-66');changed=put(changed,'primary_phone','0');post(admin,contact,changed)
 for path in ['/','/catalog','/kontakty']:
  _,body=req(public,path);ok('+7 900 111-22-33'in body and 'tel:+79001112233'in body,'shared phone in '+path)
 _,home=req(public,'/');ok('"telephone":"+7 900 111-22-33"'in home,'shared phone in structured data')
 mapurl='https://yandex.ru/map-widget/v1/?um=constructor%3Atest';post(admin,contact,put(changed,'map_widget','<iframe src="'+mapurl+'"></iframe>'));_,body=req(public,'/kontakty');ok('title="Карта офиса"'in body and 'constructor%3Atest'in body,'map visible after admin save')
 post(admin,contact,put(changed,'map_widget',''));_,body=req(public,'/kontakty');ok('title="Карта офиса"'not in body,'map clear removes its block')
finally:post(admin,contact,original)
analytics=form(admin,route(token,'settings',tab='analytics'),'settings.save');original=values(analytics)
try:
 changed=put(original,'ga4_id','G-1234567890');changed=put(changed,'yandex_verification','catpart-local-test');changed=put(changed,'google_verification','catpart-google-test');post(admin,analytics,changed);_,body=req(public,'/');ok('mc.yandex.ru/metrika/tag.js'not in body and 'googletagmanager.com/gtag/js'not in body,'local analytics never loaded');ok('name="yandex-verification" content="catpart-local-test"'in body and 'name="google-site-verification" content="catpart-google-test"'in body,'managed verification tags')
finally:post(admin,analytics,original)
homeform=form(admin,route(token,'content',section='home'),'content.saveSection');original=values(homeform)
try:
 changed=put(original,'fields[home.text.001]','Проверяемый заголовок CatPart');changed=put(changed,'fields[home.hero_image]','/assets/img/contacts.webp');changed=put(changed,'fields[home.link.001]','/kontakty?source=managed-home');changed=put(changed,'home[blocks][hero][sort_order]','900');post(admin,homeform,changed);_,body=req(public,'/');ok('Проверяемый заголовок CatPart'in body and 'src="/assets/img/contacts.webp"'in body and '/kontakty?source=managed-home'in body,'home text image and link saved');ok(body.index('cp-section--catalog')<body.index('cp-hero--home'),'home block order saved')
finally:post(admin,homeform,original)
for role in ['editor','manager','reader']:
 opener,t=login(role);landing,landing_body=req(opener,'/admin/index.php?'+urllib.parse.urlencode({'route':'common/dashboard','user_token':t}));ok(landing==200 and 'id="column-left"'in landing_body,'native post-login landing '+role);code,_=req(opener,route(t,'settings.save'),urllib.parse.urlencode({'tab':'analytics','metrika_id':'1'}).encode());ok(code==403,'settings server ACL '+role)
 code,_=req(opener,route(t,'content.save'),urllib.parse.urlencode({'title':'Forbidden'}).encode());ok(code==(200 if role=='editor' else 403),'content modify ACL '+role)
for verification in ['yandex_5c308862defefe42.html','yandex_130ab6b1c0898944.html']:
 _,body=req(public,'/'+verification);ok('Verification:'in body,'existing verification file preserved '+verification)
print(f'{checks} content HTTP checks passed')
