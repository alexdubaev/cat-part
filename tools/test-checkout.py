"""Critical cart/forms/native manager ACL scenarios on a dedicated isolated test HTTP process."""
import html, http.cookiejar, json, os, re, socket, subprocess, time, unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import parse_qs, urlencode, urlsplit
from urllib.request import build_opener, HTTPCookieProcessor, Request
ROOT=Path(__file__).resolve().parents[1]
RUNTIME=Path(os.environ.get('CATPART_ADMIN_RUNTIME',ROOT/'local-dev/admin-runtime')).resolve()
STATE=json.loads((RUNTIME/'state.json').read_text())
assert re.fullmatch(r'catpart_admin_[a-f0-9]{12}',STATE['database']) and STATE['origin'].startswith('http://127.0.0.1:')
FIXTURE='http_request_'+os.urandom(6).hex()
BASE=''
PRODUCT=0

def php(code):
    cfg=json.dumps(str(RUNTIME/'core/admin/config.php').replace('\\','/'))
    pre='require '+cfg+'; Catpart\\Factory::assertLocal(["host"=>DB_HOSTNAME,"port"=>DB_PORT,"name"=>DB_DATABASE],CATPART_TEST_DATABASE);$pdo=Catpart\\Factory::pdo();'
    result=subprocess.run([STATE['php'],'-c',str(RUNTIME/'php.ini'),'-r',pre+code],capture_output=True,text=True,encoding='utf-8',cwd=ROOT)
    if result.returncode:raise RuntimeError('Local request test database operation failed; inspect private PHP error log')
    return result.stdout.strip()

def request(opener,path,data=None,base=None,headers=None):
    target=(base or BASE)+path
    payload=urlencode(data,doseq=True).encode() if data is not None else None
    try:
        with opener.open(Request(target,payload,headers or {}),timeout=20) as r:return r.status,r.read().decode('utf-8','replace')
    except HTTPError as e:return e.code,e.read().decode('utf-8','replace')

def field(page,name):
    m=re.search(r'name="'+re.escape(name)+r'"[^>]*value="([^"]*)"',page)
    return html.unescape(m[1]) if m else ''

def native(role):
    credentials=json.loads((RUNTIME/'private/accounts.json').read_text())[role]
    opener=build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
    code,body=request(opener,'/admin/index.php?route=common/login',base=STATE['origin'])
    action=html.unescape(re.search(r'<form id="form-login" action="([^"]+)"',body)[1]);parts=urlsplit(action)
    code,body=request(opener,parts.path+'?'+parts.query,credentials,base=STATE['origin'])
    redirect=html.unescape(json.loads(body).get('redirect',''));token=parse_qs(urlsplit(redirect).query).get('user_token',[''])[0]
    if not token:raise AssertionError('Native test login failed for '+role)
    return opener,token

class Checkout(unittest.TestCase):
    maxDiff=100
    def setUp(self):
        self.http=build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
        code,self.page=request(self.http,'/poisk?q=000-HTTP');self.csrf=field(self.page,'_csrf')
        self.assertEqual(code,200);self.assertTrue(self.csrf)
    def contains(self,needle,page):self.assertTrue(needle in page,'Expected public or admin result missing: '+needle[:90])
    def post(self,path,**data):
        data.setdefault('_csrf',self.csrf);return request(self.http,path,data)
    def add(self,qty=2):return self.post('/korzina/add',product_id=PRODUCT,qty=qty)[1]
    def submit(self,page,**extra):
        data=dict(name='Synthetic HTTP fixture',phone='70000000000',email='http@example.test',comment=FIXTURE,consent='1',checkout_token=field(page,'checkout_token'));data.update(extra)
        return self.post('/korzina/checkout',**data)
    def test_browser_lead_field_errors(self):
        script=r"""
const assert=require('node:assert/strict');const {chromium}=require('playwright');
(async()=>{const browser=await chromium.launch({headless:true});try{
const page=await browser.newPage();const base=process.env.CATPART_TEST_URL;
await page.route('**/*',route=>route.request().url().startsWith(base+'/')?route.continue():route.abort());
await page.goto(base+'/kontakty',{waitUntil:'domcontentloaded'});
if(await page.locator('#cpCookieOk').isVisible())await page.locator('#cpCookieOk').click();
const form=page.locator('form[data-lead]');const original=await form.locator('[name=lead_token]').inputValue();
await form.locator('[name=name]').fill('Synthetic browser fixture');await form.locator('[name=phone]').fill('123');
await form.locator('[name=comment]').fill(process.env.CATPART_FIXTURE);await form.locator('[name=consent]').check();
await form.locator('button[type=submit]').click();await page.locator('[data-field-error=phone]').waitFor();
assert.equal(await form.locator('[name=phone]').inputValue(),'123');assert.equal(await form.locator('[name=phone]').getAttribute('aria-invalid'),'true');
assert.match(await form.locator('[data-field-error=phone]').innerText(),/10/);
await form.locator('[name=phone]').fill('70000000000');await form.locator('button[type=submit]').click();
await page.waitForFunction(()=>document.querySelector('[data-lead-msg]').textContent.includes('CF-'));
assert.equal(await form.locator('[data-field-error]').count(),0);assert.equal(await form.locator('[name=name]').inputValue(),'');
assert.notEqual(await form.locator('[name=lead_token]').inputValue(),original);console.log('PASS browser field error, preserved input, saved lead and fresh idempotency token');
}finally{await browser.close();}})().catch(error=>{console.error(error.message);process.exit(1)});
"""
        result=subprocess.run(['node','-'],input=script,text=True,capture_output=True,encoding='utf-8',cwd=ROOT,env=os.environ|{'CATPART_TEST_URL':BASE,'CATPART_FIXTURE':FIXTURE},timeout=60)
        self.assertEqual(result.returncode,0,'Critical browser scenario failed: '+result.stderr[-700:])
    def test_worker_capture_mime_and_failure(self):
        result=json.loads(php('$q=$pdo->query("SELECT o.order_id,b.id,b.message_id FROM cp_order o JOIN cp_outbox b ON b.kind=\'order\' AND b.entity_id=o.order_id WHERE JSON_UNQUOTE(JSON_EXTRACT(o.customer,\'$.comment\'))=\''+FIXTURE+'\' LIMIT 1");echo json_encode($q->fetch(),JSON_THROW_ON_ERROR);'))
        self.assertIsInstance(result,dict);job=int(result['id']);oid=int(result['order_id'])
        php('$box=new Catpart\\Outbox($pdo);$box->run($box->captureTransport('+json.dumps(str(ROOT/'local-dev/site').replace('\\','/'))+','+json.dumps(str(ROOT/'local-dev/site').replace('\\','/'))+'),1,['+str(job)+']);')
        state=json.loads(php('echo json_encode($pdo->query("SELECT state,error_code FROM cp_outbox WHERE id='+str(job)+'")->fetch(),JSON_THROW_ON_ERROR);'))
        self.assertEqual(state,{'state':'error','error_code':'capture_directory'})
        self.assertEqual(int(php('echo $pdo->query("SELECT COUNT(*) FROM cp_order WHERE order_id='+str(oid)+'")->fetchColumn();')),1)
        directory=RUNTIME/'private'/FIXTURE
        php('$box=new Catpart\\Outbox($pdo);$box->retry('+str(job)+',1);$box->run($box->captureTransport('+json.dumps(str(directory).replace('\\','/'))+','+json.dumps(str(ROOT/'local-dev/site').replace('\\','/'))+'),1,['+str(job)+']);')
        from email.parser import BytesParser
        from email import policy
        filename=__import__('hashlib').sha256(result['message_id'].encode()).hexdigest()+'.eml'
        capture=directory/filename;message=BytesParser(policy=policy.default).parsebytes(capture.read_bytes())
        self.assertEqual(message['Message-ID'],result['message_id']);self.assertTrue(message['To']);self.assertTrue(message['From'])
        self.assertTrue(FIXTURE in message.get_content() and '000-HTTP' in message.get_content())
        self.assertEqual(php('echo $pdo->query("SELECT state FROM cp_outbox WHERE id='+str(job)+'")->fetchColumn();'),'captured')
        capture.unlink();directory.rmdir()
    def test_cart_quantity_and_inputs(self):
        self.add();code,page=self.post('/korzina/update',product_id=PRODUCT,qty=150);self.assertEqual(field(page,'qty'),'150')
        _,bad=self.post('/korzina/update',product_id=PRODUCT,qty='1.5');self.contains('целым числом',bad);self.assertEqual(field(bad,'qty'),'150')
        _,page=self.submit(page,phone='bad');self.contains('не меньше 10 цифр',page);self.contains('value="bad"',page);self.contains('Synthetic HTTP fixture',page)
        _,page=self.post('/korzina/remove',product_id=PRODUCT);self.contains('Корзина пуста',page)
    def test_checkout_replay_preserves_later_cart(self):
        page=self.add();code,done=self.submit(page,price='0.01');self.assertEqual(code,200);self.contains('Запрос принят. Номер CP-',done);self.contains('Корзина пуста',done)
        new=self.add(3);_,again=self.submit(page);self.contains('Запрос принят. Номер CP-',again);self.contains('cp-cart__badge">3<',again)
        old_number=re.search(r'Номер (CP-\d+)',done)[1];self.contains(old_number,again)
        self.assertEqual(int(php('$q=$pdo->query("SELECT COUNT(*) FROM cp_order WHERE JSON_UNQUOTE(JSON_EXTRACT(customer,\'$.comment\'))=\''+FIXTURE+'\'");echo $q->fetchColumn();')),1)
    def test_csrf_honeypot_unavailable(self):
        page=self.add();code,_=self.post('/korzina/checkout',_csrf='bad');self.assertEqual(code,419)
        _,result=self.submit(page,website='spam.test');self.assertNotIn('Запрос принят.',result)
        php(' $pdo->exec("UPDATE products SET is_active=0 WHERE id='+str(PRODUCT)+'");')
        try:
            _,result=self.submit(page);self.contains('больше недоступна',result);self.contains('Позиция недоступна',result);self.contains('cp-cart__badge">2<',result)
        finally:php('$pdo->exec("UPDATE products SET is_active=1 WHERE id='+str(PRODUCT)+'");')
    def test_lead_persistence_replay_and_validation(self):
        _,page=request(self.http,'/kontakty');token=field(page,'lead_token');self.assertTrue(token)
        payload=dict(name='Synthetic lead fixture',phone='70000000000',email='lead-http@example.test',comment=FIXTURE,source='contacts',consent='1',lead_token=token,_csrf=self.csrf)
        code,body=request(self.http,'/zayavka',payload,headers={'Accept':'application/json','Referer':BASE+'/kontakty'});self.assertEqual(code,200);result=json.loads(body);self.assertTrue(result['ok']);self.assertRegex(result['message'],r'CF-\d+')
        code,again=request(self.http,'/zayavka',payload,headers={'Accept':'application/json'});self.assertEqual(json.loads(again)['message'],result['message'])
        payload['lead_token']=os.urandom(32).hex();payload['email']='invalid';code,body=request(self.http,'/zayavka',payload,headers={'Accept':'application/json'});self.assertEqual(code,422);self.assertFalse(json.loads(body)['ok'])
    def test_native_actual_acl_and_escaped_links(self):
        oid=int(php('$q=$pdo->query("SELECT order_id FROM cp_order WHERE JSON_UNQUOTE(JSON_EXTRACT(customer,\'$.comment\'))=\''+FIXTURE+'\' LIMIT 1");echo (int)$q->fetchColumn();'))
        if not oid:
            page=self.add();_,done=self.submit(page);self.contains('Запрос принят.',done)
            oid=int(php('$q=$pdo->query("SELECT order_id FROM cp_order WHERE JSON_UNQUOTE(JSON_EXTRACT(customer,\'$.comment\'))=\''+FIXTURE+'\' LIMIT 1");echo (int)$q->fetchColumn();'))
        for role in ['administrator','manager','editor','reader']:
            opener,token=native(role)
            path='/admin/index.php?'+urlencode({'route':'extension/catpart/module/orders','user_token':token,'id':oid})
            code,page=request(opener,path,base=STATE['origin']);allowed=role in ['administrator','manager'];self.assertTrue(code==200 if allowed else code in (401,403),role+' order access')
            save='/admin/index.php?'+urlencode({'route':'extension/catpart/module/orders.save','user_token':token})
            if allowed:
                form=re.search(r'<form method="post" action="([^"]*orders\.save[^"]*)"',page);self.assertIsNotNone(form)
                action=html.unescape(form[1]);query=parse_qs(urlsplit(action).query);self.assertEqual(query.get('user_token'),[token]);self.assertNotIn('amp;user_token',query)
                p=urlsplit(action);code,result=request(opener,p.path+'?'+p.query,{'id':oid,'status_id':102,'manager_id':0,'note':'HTTP role "fixture" & unit'},base=STATE['origin']);self.assertEqual(code,200);self.contains('Изменения сохранены',result)
                code,_=request(opener,save,base=STATE['origin']);self.assertEqual(code,405,'GET cannot mutate')
            else:
                code,_=request(opener,save,{'id':oid,'status_id':106},base=STATE['origin']);self.assertTrue(code in (401,403),role+' direct modify blocked')
            reports='/admin/index.php?'+urlencode({'route':'extension/catpart/module/reports','user_token':token});code,page=request(opener,reports,base=STATE['origin']);self.assertTrue(code==200 if allowed else code in (401,403),role+' reports access')
            if allowed:self.contains('не являются оплаченными продажами',page)
        status=int(php('echo $pdo->query("SELECT status_id FROM cp_order WHERE order_id='+str(oid)+'")->fetchColumn();'));self.assertEqual(status,102,'denied role did not change order')
        note=php('echo $pdo->query("SELECT note FROM cp_request_history WHERE kind=\'order\' AND entity_id='+str(oid)+' ORDER BY id DESC LIMIT 1")->fetchColumn();');self.assertEqual(note,'HTTP role \"fixture\" & unit','native text decoded once at boundary')

if __name__=='__main__':
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    BASE=f'http://127.0.0.1:{port}'
    env=os.environ|{'CATPART_TEST_DATABASE':STATE['database'],'CATPART_TEST_HTTP_PORT':str(port),'CATPART_ADMIN_RUNTIME':str(RUNTIME),'CATPART_OC_ROOT':str(RUNTIME/'core'),'CATPART_SOURCE_MEDIA':str(Path(STATE['source'])/'local-dev/site/upload')}
    PRODUCT=int(php('$q=$pdo->prepare("INSERT INTO products(slug,sku,sku_norm,name,price,currency,in_stock,is_active) VALUES(?,?,?,\'Synthetic HTTP product\',17.25,\'RUB\',1,1)");$q->execute(["'+FIXTURE+'","000-HTTP","000HTTP"]);echo $pdo->lastInsertId();'))
    rate=ROOT/'local-dev/private/rate-limits'/('checkout-'+__import__('hashlib').sha256(b'192.0.2.250').hexdigest()+'.json')
    rate.unlink(missing_ok=True)
    server=subprocess.Popen([STATE['php'],'-c',str(RUNTIME/'php.ini'),'-S','127.0.0.1:'+str(port),'-t',str(ROOT/'local-dev/site'),str(ROOT/'tools/checkout-test-router.php')],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        for _ in range(30):
            if server.poll() is not None:raise RuntimeError('Dedicated request fixture server failed to start')
            try:request(build_opener(),'/korzina');break
            except OSError:time.sleep(.1)
        unittest.main(verbosity=2)
    finally:
        server.terminate();server.wait(timeout=5);rate.unlink(missing_ok=True)
        php('$ids=$pdo->query("SELECT order_id FROM cp_order WHERE JSON_UNQUOTE(JSON_EXTRACT(customer,\'$.comment\'))=\''+FIXTURE+'\'")->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id){foreach(["cp_order"=>"order_id","oc_order"=>"order_id","oc_order_product"=>"order_id","oc_order_history"=>"order_id"] as $t=>$pk)$pdo->exec("DELETE FROM $t WHERE $pk=".(int)$id);foreach(["cp_request_key","cp_request_history","cp_outbox"] as $t)$pdo->exec("DELETE FROM $t WHERE kind=\'order\' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_audit WHERE entity=\'order\' AND entity_id=\'".(int)$id."\'");}$ids=$pdo->query("SELECT id FROM cp_lead WHERE message=\''+FIXTURE+'\'")->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id){$pdo->exec("DELETE FROM cp_lead WHERE id=".(int)$id);foreach(["cp_request_key","cp_request_history","cp_outbox"] as $t)$pdo->exec("DELETE FROM $t WHERE kind=\'lead\' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_audit WHERE entity=\'lead\' AND entity_id=\'".(int)$id."\'");}$pdo->exec("DELETE FROM products WHERE id='+str(PRODUCT)+'");')
