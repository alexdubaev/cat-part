"""Exercise the isolated CatPart OpenCart foundation through native HTTP sessions."""
import argparse, html, http.cookiejar, json, re, sys, urllib.parse, urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def run():
    runtime=ROOT/'local-dev/admin-runtime'
    state=json.loads((runtime/'state.json').read_text())
    credentials=json.loads((runtime/'private/accounts.json').read_text())
    base=state['origin']
    total=0
    def check(value,label):
        nonlocal total
        if not value: raise AssertionError(label)
        total+=1; print('PASS',label)
    def request(opener,path,data=None):
        try:
            with opener.open(base+path,data,timeout=15) as r:return r.status,r.read().decode('utf-8','replace')
        except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8','replace')
    for role in ['administrator','editor','manager','reader']:
        opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        code,body=request(opener,'/admin/index.php?route=common/login')
        check(code==200 and 'form-login' in body,'native login page '+role)
        form=re.search(r'<form id="form-login" action="([^"]+)"',body)
        action=html.unescape(form[1]); path=urllib.parse.urlsplit(action)
        payload=urllib.parse.urlencode(credentials[role]).encode()
        code,body=request(opener,path.path+'?'+path.query,payload)
        result=json.loads(body); redirect=html.unescape(result.get('redirect',''))
        token=urllib.parse.parse_qs(urllib.parse.urlsplit(redirect).query).get('user_token',[''])[0]
        check(bool(token),'native login '+role)
        query=urllib.parse.urlencode({'route':'extension/catpart/module/foundation','user_token':token})
        code,body=request(opener,'/admin/index.php?'+query)
        check(code==200 and 'CatPart' in body,'native protected shell '+role)
        code,body=request(opener,'/admin/index.php?'+urllib.parse.urlencode({'route':'catalog/product','user_token':token}))
        check(code==403,'unconnected native catalog denied '+role)
        code,body=request(opener,'/admin/index.php?'+urllib.parse.urlencode({'route':'extension/catpart/module/foundation.probe','user_token':token,'area':'settings'}),b'')
        check(code==(200 if role=='administrator' else 403),'separate server modify permission '+role)
        code,body=request(opener,'/admin/index.php?'+urllib.parse.urlencode({'route':'extension/catpart/module/foundation.probe','user_token':token,'area':'content'}),b'')
        check(code==(200 if role in ('administrator','editor') else 403),'role content modify '+role)
    opener=urllib.request.build_opener()
    code,body=request(opener,'/')
    check(code==200 and 'CatPart' in body,'existing storefront isolated DB')
    code,body=request(opener,'/app/Config/config.local.php')
    check(code==404,'private storefront config denied')
    code,body=request(opener,'/admin/config.php')
    check(code==404,'private native config denied')
    print(f'{total} passed, 0 failed')
if __name__=='__main__':run()
