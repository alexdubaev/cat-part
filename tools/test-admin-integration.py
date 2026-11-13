"""Run affected real integration groups; unavailable dependencies always fail."""
import argparse, hashlib, html, http.cookiejar, json, os, re, subprocess, sys, tempfile, urllib.parse, urllib.request, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def run(command,env):
    result=subprocess.run(command,cwd=ROOT,env=env,text=True,encoding='utf-8',stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
    print('\n'.join(result.stdout.splitlines()[-32:]),flush=True)
    if result.returncode:raise RuntimeError('Integration command failed: '+str(command[0])+' '+str(command[-1]))

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--group',action='append',choices=('package','shipping','foundation','catalog','content','requests','browser'));p.add_argument('--runtime',type=Path,default=Path(os.environ.get('CATPART_ADMIN_RUNTIME',ROOT/'local-dev/admin-runtime')));p.add_argument('--package',type=Path);a=p.parse_args()
    runtime=a.runtime.resolve();state=json.loads((runtime/'state.json').read_text())
    if not re.fullmatch(r'catpart_admin_[a-f0-9]{12}',state['database']) or not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+',state['origin']):raise RuntimeError('Only an isolated loopback task runtime may be tested.')
    env=os.environ|{'CATPART_ADMIN_RUNTIME':str(runtime),'CATPART_SITE_ROOT':str(ROOT/'local-dev/site'),'CATPART_TEST_DATABASE':state['database'],'CATPART_OC_ROOT':str(runtime/'core')}
    php=[state['php'],'-c',str(runtime/'php.ini')];groups=a.group or ['package','shipping','foundation','catalog','content','requests','browser']
    for group in groups:
        print('GROUP '+group,flush=True)
        if group=='package':
            with tempfile.TemporaryDirectory(prefix='catpart-package-') as td:
                first=Path(td)/'first.zip';second=Path(td)/'second.zip'
                for dest in (first,second):run([sys.executable,str(ROOT/'tools/admin-package.py'),'build','--output',str(dest)],env)
                assert first.read_bytes()==second.read_bytes(),'package must be byte deterministic'
                with zipfile.ZipFile(first) as z:
                    names=z.namelist();manifest=json.loads(z.read('package-manifest.json'))
                    for name in names:
                        parts=Path(name).parts
                        assert not any(x in parts for x in ('private','admin-runtime','upload','media','.git','.autopilot')),'private path in package'
                        assert Path(name).name not in ('config.php','config.local.php','accounts.json'),'private configuration in package'
                        assert not name.endswith('.sql'),'database dump in package'
                    for row in manifest['files']:
                        assert hashlib.sha256(z.read(row['path'])).hexdigest()==row['sha256'],'payload checksum mismatch'
                    assert manifest['upstream']['sha256']=='cdef537f5a0a7e1b4ddbe16f589dd2e80b10150a09bfbe93ce76ef2d5c3218c0'
                    assert 'tools/admin-package.py' in names and 'docs/admin-guide.md' in names
                run([sys.executable,str(ROOT/'tools/admin-package.py'),'verify','--package',str(first)],env)
                broken=Path(td)/'broken.zip';broken.write_bytes(first.read_bytes())
                with zipfile.ZipFile(broken,'a') as z:z.writestr('local-dev/site/index.php',b'corrupt')
                denied=subprocess.run([sys.executable,str(ROOT/'tools/admin-package.py'),'verify','--package',str(broken)],cwd=ROOT,env=env,capture_output=True)
                assert denied.returncode!=0,'tampered package accepted'
                print('PASS deterministic archive, allowlist, pinned upstream, every payload hash, tamper rejection')
        elif group=='shipping':
            if not (ROOT/'package-manifest.json').is_file() or not a.package:raise RuntimeError('Shipping requires a clean installed package and --package ZIP (no source-worktree update).')
            package=a.package.resolve();run([sys.executable,str(ROOT/'tools/admin-package.py'),'verify','--package',str(package)],env)
            run([*php,str(ROOT/'tests/admin-package.php'),'prepare'],env)
            try:
                fixture=json.loads((runtime/'private/package-fixture.json').read_text());base=state['origin'];opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
                with opener.open(base+'/tovar/'+urllib.parse.quote(fixture['legacy_products'][0]['slug'])) as r:body=r.read().decode()
                csrf=html.unescape(re.search(r'name="_csrf"[^>]*value="([^"]*)"',body)[1])
                for i,product in enumerate(fixture['legacy_products']):
                    with opener.open(base+'/korzina/add',urllib.parse.urlencode({'_csrf':csrf,'product_id':product['id'],'qty':(2,150)[i]}).encode()) as r:body=r.read().decode()
                before=body
                for _ in range(2):run([sys.executable,str(ROOT/'tools/admin-package.py'),'update','--package',str(package),'--target',str(ROOT),'--apply'],env)
                run([*php,str(ROOT/'tests/admin-package.php'),'updated'],env)
                run([sys.executable,str(ROOT/'tools/admin-package.py'),'rollback','--target',str(ROOT),'--apply'],env)
                with opener.open(base+'/korzina') as r:body=r.read().decode()
                for i,product in enumerate(fixture['legacy_products']):
                    assert html.escape(product['sku']) in before and html.escape(product['sku']) in body,'legacy cart SKU lost'
                    assert re.search(r'name="product_id"[^>]*value="'+str(product['id'])+'"',body),'legacy cart product ID lost'
                assert 'value="150"' in body and 'value="2"' in body,'legacy cart quantities lost'
                print('PASS same pre-update cart cookie retains original catalog IDs/SKUs and exact quantities 2/150 after repeated update/code rollback')
                run([*php,str(ROOT/'tests/admin-package.php'),'restore'],env)
            finally:
                if (runtime/'private/package-fixture.json').is_file():run([*php,str(ROOT/'tests/admin-package.php'),'cleanup'],env)
            run([*php,str(ROOT/'tools/admin-data.php'),'inventory'],env)
        elif group=='foundation':
            run([*php,str(ROOT/'tests/admin-foundation.php')],env);run([sys.executable,str(ROOT/'tools/test-admin-foundation.py')],env)
        elif group=='catalog':
            run([*php,str(ROOT/'tests/admin-catalog.php')],env);run([sys.executable,str(ROOT/'tests/admin-catalog-http.py')],env)
        elif group=='content':
            run([*php,str(ROOT/'tests/admin-content.php')],env);run([sys.executable,str(ROOT/'tools/test-admin-content.py')],env)
        elif group=='requests':
            run([*php,str(ROOT/'tests/admin-requests.php')],env);run([sys.executable,str(ROOT/'tools/test-checkout.py')],env)
        elif group=='browser':run(['node',str(ROOT/'tools/test-admin-content.cjs')],env)
    print('PASS all selected real integration groups')
if __name__=='__main__':main()
