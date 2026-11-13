"""Bounded SEO regressions on a real isolated MariaDB + HTTP stand."""
import argparse, json, os, re, secrets, subprocess, urllib.error, urllib.parse, urllib.request, xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--case',choices=['aliases','sitemap','redirect','http','all'],default='all');args=parser.parse_args()
    runtime=Path(os.environ.get('CATPART_ADMIN_RUNTIME',ROOT/'local-dev/admin-runtime')).resolve();state=json.loads((runtime/'state.json').read_text())
    if not re.fullmatch(r'catpart_admin_[a-f0-9]{12}',state['database']) or not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+',state['origin']):raise RuntimeError('Isolated loopback runtime required')
    fixture=runtime/'private'/('seo-fixture-'+secrets.token_hex(8)+'.json');env=os.environ|{'CATPART_ADMIN_RUNTIME':str(runtime),'CATPART_SEO_FIXTURE':str(fixture)}
    def php(mode):
        result=subprocess.run([state['php'],'-c',str(runtime/'php.ini'),str(ROOT/'tests/admin-seo.php'),mode],cwd=ROOT,env=env,text=True,encoding='utf-8',stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
        print(result.stdout,flush=True);return result.returncode==0
    opener=urllib.request.build_opener(NoRedirect());checks=0;failures=[]
    def check(ok,label):
        nonlocal checks
        checks+=1;print(('PASS 'if ok else 'FAIL ')+label,flush=True)
        if not ok:failures.append(label)
    def get(path,method='GET'):
        try:r=opener.open(urllib.request.Request(state['origin']+path,method=method),timeout=45)
        except urllib.error.HTTPError as e:r=e
        with r:return r.status,r.headers,r.read().decode('utf-8','replace')
    if args.case in ('aliases','all'):check(php('aliases'),'real DB historical aliases and import validation')
    if args.case in ('sitemap','redirect','http','all'):
        try:
            if not php('prepare'):raise RuntimeError('SEO fixture preparation failed')
            data=json.loads(fixture.read_text(encoding='utf-8'));ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
            if args.case in ('sitemap','http','all'):
                status,_,body=get('/sitemap.xml');check(status==200,'sitemap index HTTP 200');chunks=[x.text for x in ET.fromstring(body).findall('s:sitemap/s:loc',ns) if '/sitemap-products-'in x.text]
                check(len(chunks)==data['chunks'],'sitemap chunk count excludes JSON true noindex')
                urls=[]
                for number in range(1,data['chunks']+1):
                    status,_,body=get('/sitemap-products-'+str(number)+'.xml');check(status==200,'nonempty product sitemap chunk '+str(number));urls += [x.text for x in ET.fromstring(body).findall('s:url/s:loc',ns)]
                check(any(x.endswith('/tovar/'+data['public'])for x in urls),'indexable product remains in sitemap')
                check(not any(data['hidden']in x or data['tag']+'-bulk-'in x for x in urls),'JSON true noindex products excluded from sitemap')
                status,_,body=get('/sitemap-pages.xml');check(status==200 and '/catalog/'+data['public']in body and '/catalog/'+data['hidden']not in body,'JSON true noindex category excluded; indexable category retained')
                status,_,_=get('/sitemap-products-'+str(data['chunks']+1)+'.xml');check(status==404,'chunk beyond indexable count is 404')
            if args.case in ('redirect','http','all'):
                for path in data['current_paths']:
                    status,_,_=get(path);check(status==200,'accepted rename-back URL opens '+path.split('/')[1])
                for old,target in data['old_paths']:
                    status,headers,_=get(old);check(status==301 and urllib.parse.urlsplit(headers.get('Location','')).path==target,'historical alias flattened after rename-back '+old.split('/')[1])
                old='/tovar/'+urllib.parse.quote(data['old']);target='/tovar/'+data['current']
                for method in ('GET','HEAD'):
                    status,headers,_=get(old+'?source=seo',method);check(status==301 and urllib.parse.urlsplit(headers.get('Location','')).path==target,'encoded Cyrillic historical URL redirects directly '+method)
                status,_,_=get(target);check(status==200,'accepted current slug opens publicly')
                status,headers,_=get(old+'/');check(status==301 and urllib.parse.urlsplit(headers.get('Location','')).path==target,'redirect trim matches Router trailing slash normalization')
                for path in ['/tovar%2f'+urllib.parse.quote(data['old']),'/tovar/'+urllib.parse.quote(data['old'])+'%00','/tovar/%2e%2e/'+urllib.parse.quote(data['old']),'/tovar%5c'+urllib.parse.quote(data['old']),'/tovar/'+urllib.parse.quote(data['old'])+'%252f']:
                    status,_,_=get(path);check(status!=301,'unsafe encoded path has no historical redirect '+path.split('%')[0])
        finally:
            if fixture.is_file():check(php('cleanup'),'exact persisted SEO fixture cleanup')
    if failures:raise RuntimeError(str(len(failures))+' SEO regressions failed: '+'; '.join(failures))
    print(str(checks)+' SEO HTTP/orchestration checks passed')
if __name__=='__main__':main()
