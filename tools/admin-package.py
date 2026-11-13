"""Deterministic offline package and explicit loopback-only installer. Preview is default."""
import argparse, datetime, hashlib, json, os, re, shutil, subprocess, sys, zipfile
from pathlib import Path, PurePosixPath
ROOT=Path(__file__).resolve().parents[1]
CORE_HASH='cdef537f5a0a7e1b4ddbe16f589dd2e80b10150a09bfbe93ce76ef2d5c3218c0'
UPSTREAM=Path('D:/codex/temp/cmteh-opencart-cache/opencart-4.1.0.4.zip')
UPSTREAM_NAME='upstream/opencart-4.1.0.4.zip'
BLOCKED={'private','admin-runtime','upload','media','storage','.git','.autopilot','node_modules','dist','__pycache__'}

def digest(data):return hashlib.sha256(data).hexdigest()
def encoded(value):return (json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode('utf-8')
def safe(name):
    path=PurePosixPath(name)
    return not (path.is_absolute() or '..' in path.parts or '\\' in name or ':' in name or any(x in BLOCKED for x in path.parts) or path.name in ('config.php','config.local.php','accounts.json') or path.suffix in ('.sql','.log') or path.name.startswith('.env'))

def sources():
    # Fixed source roots, never walk the checkout or runtime. These roots contain
    # only application/library files; explicit filters also protect dirty exports.
    paths=[]
    for area in ('opencart/extension/catpart','local-dev/site/app','local-dev/site/assets','docs/adr'):
        paths.extend(p for p in (ROOT/area).rglob('*') if p.is_file())
    for area,pattern in (('tools','admin-*.py'),('tools','admin-*.php'),('tools','test-admin-*'),('tests','admin-*'),('opencart/packaging','*.json'),('opencart/packaging','*.md'),('opencart/packaging/licenses','*')):
        paths.extend(p for p in (ROOT/area).glob(pattern) if p.is_file())
    for name in ('local-dev/router.php','local-dev/site/.htaccess','local-dev/site/app/.htaccess','local-dev/site/index.php','local-dev/site/manifest.webmanifest','local-dev/site/robots.txt','local-dev/site/yandex_130ab6b1c0898944.html','local-dev/site/yandex_5c308862defefe42.html','tools/test-checkout.py','tools/checkout-test-router.php','tools/send-admin-notifications.php','docs/admin-guide.md','docs/admin-architecture.md','docs/admin-migration.md','docs/admin-reuse.md'):
        p=ROOT/name
        if not p.is_file():raise RuntimeError('Required package source missing: '+name)
        paths.append(p)
    tracked=subprocess.run(['git','ls-files','-z'],cwd=ROOT,capture_output=True)
    if tracked.returncode==0:
        permitted=set(tracked.stdout.decode('utf-8').split('\0'))|{'tools/admin-package.py','tools/admin-data.php','tools/test-admin-integration.py','tests/admin-package.php','docs/admin-guide.md','docs/admin-architecture.md','docs/admin-migration.md','docs/admin-reuse.md','opencart/packaging/open-cart.lock.json','opencart/packaging/README.md','opencart/packaging/licenses/CMTEH-NOTICE.md'}
    else:
        permitted={row['path'] for row in json.loads((ROOT/'package-manifest.json').read_text())['files']}
    return {p.relative_to(ROOT).as_posix():p.read_bytes() for p in sorted(set(paths)) if safe(p.relative_to(ROOT).as_posix()) and p.relative_to(ROOT).as_posix() in permitted}

def build(args):
    upstream=args.upstream.resolve();core=upstream.read_bytes()
    if digest(core)!=CORE_HASH:raise RuntimeError('Pinned upstream checksum mismatch.')
    payload=sources();payload[UPSTREAM_NAME]=core
    with zipfile.ZipFile(upstream) as z:
        for name,dest in (('LICENSE.md','OpenCart-LICENSE.md'),('upload/admin/view/javascript/ckeditor/LICENSE.md','CKEditor-LICENSE.md')):
            payload['opencart/packaging/licenses/'+dest]=z.read(name)
    revision=subprocess.run(['git','rev-parse','HEAD'],cwd=ROOT,capture_output=True,text=True)
    source_ref=revision.stdout.strip() if revision.returncode==0 else json.loads((ROOT/'package-manifest.json').read_text())['source_revision']
    rows=[{'path':name,'size':len(data),'sha256':digest(data)} for name,data in sorted(payload.items())]
    manifest={'format':1,'source_revision':source_ref,'source_tree_sha256':digest(encoded(rows)),'upstream':{'version':'4.1.0.4','path':UPSTREAM_NAME,'sha256':CORE_HASH},'installed_extensions':[{'code':'catpart','version':'1.0.0'}],'files':rows,'scope':'loopback stand only; no hosting or real mail verification'}
    payload['package-manifest.json']=encoded(manifest)
    output=args.output.resolve();output.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(output,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for name,data in sorted(payload.items()):
            item=zipfile.ZipInfo(name,date_time=(2026,1,1,0,0,0));item.compress_type=zipfile.ZIP_DEFLATED;item.external_attr=0o100644<<16;item.create_system=3
            z.writestr(item,data,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    checksum=digest(output.read_bytes());output.with_suffix(output.suffix+'.sha256').write_text(checksum+'  '+output.name+'\n',encoding='ascii')
    print(json.dumps({'package':str(output),'sha256':checksum,'files':len(rows),'source_revision':source_ref,'source_tree_sha256':manifest['source_tree_sha256']}))

def verify(path):
    with zipfile.ZipFile(path) as z:
        names=z.namelist()
        if len(names)!=len(set(names)) or any(not safe(n) for n in names):raise RuntimeError('Unsafe or duplicate package member.')
        manifest=json.loads(z.read('package-manifest.json'));rows=manifest['files']
        if manifest['format']!=1 or manifest['upstream']['sha256']!=CORE_HASH:raise RuntimeError('Unknown package or upstream lock.')
        if set(names)!={r['path'] for r in rows}|{'package-manifest.json'}:raise RuntimeError('Unexpected or missing package members.')
        if digest(encoded(rows))!=manifest['source_tree_sha256']:raise RuntimeError('Manifest tree checksum mismatch.')
        for row in rows:
            content=z.read(row['path'])
            if len(content)!=row['size'] or digest(content)!=row['sha256']:raise RuntimeError('Payload checksum mismatch: '+row['path'])
        if digest(z.read(UPSTREAM_NAME))!=CORE_HASH:raise RuntimeError('Upstream checksum mismatch.')
        return manifest

def target_state(target):
    runtime=target/'local-dev/admin-runtime';state=json.loads((runtime/'state.json').read_text())
    expected='catpart_admin_'+digest(str(target).lower().encode())[:12]
    if state['database']!=expected or not re.fullmatch('catpart_admin_[a-f0-9]{12}',state['database']) or state['origin']!='http://127.0.0.1:'+str(state['port']):raise RuntimeError('Target is not its own loopback task runtime.')
    return runtime,state

def local(command,target,runtime):
    subprocess.run([sys.executable,str(target/'tools/admin-dev.py'),command,'--runtime',str(runtime)],cwd=target,check=True)

def apply_package(args):
    target=args.target.resolve();manifest=verify(args.package)
    source=args.source.resolve()
    if target==source or source in target.parents or target in source.parents:raise RuntimeError('Target overlaps the read-only source checkout.')
    if (args.command=='install' and target==ROOT) or target==ROOT.parent or target.name.lower() in ('cat-part','cmteh'):raise RuntimeError('Install target must be a separate isolated directory.')
    if args.command=='install':
        if target.exists() and any(target.iterdir()):raise RuntimeError('Fresh install target must be empty.')
    else:runtime,state=target_state(target)
    print(json.dumps({'operation':args.command,'target':str(target),'source_revision':manifest['source_revision'],'payload_files':len(manifest['files']),'apply':args.apply,'database_policy':'fresh own schema' if args.command=='install' else 'additive migration; preserve database/config/uploads'}))
    if not args.apply:return
    if args.command=='update':
        local('stop',target,runtime)
        backup=runtime/'private/code-rollback'
        if not backup.exists():
            backup.mkdir(parents=True)
            old=json.loads((target/'package-manifest.json').read_text())
            for row in old['files']:
                source=target/row['path']
                if source.is_file():dest=backup/row['path'];dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,dest)
            shutil.copyfile(target/'package-manifest.json',backup/'package-manifest.json')
    with zipfile.ZipFile(args.package) as z:
        for name in z.namelist():
            dest=target/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(z.read(name))
    if args.command=='install':
        subprocess.run([sys.executable,str(target/'tools/admin-dev.py'),'setup','--source',str(args.source.resolve()),'--upstream',str(target/UPSTREAM_NAME),'--port',str(args.port)],cwd=target,check=True)
    else:local('refresh',target,runtime)
    print('PASS '+args.command+' completed in isolated stand')

def rollback(args):
    target=args.target.resolve();runtime,state=target_state(target);backup=runtime/'private/code-rollback';manifest=json.loads((backup/'package-manifest.json').read_text())
    print('Code-only rollback; database and new requests preserved. Apply:',args.apply)
    if not args.apply:return
    local('stop',target,runtime)
    for row in manifest['files']:
        source=backup/row['path']
        if source.is_file():dest=target/row['path'];dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,dest)
    shutil.copyfile(backup/'package-manifest.json',target/'package-manifest.json');local('start',target,runtime)
    print('PASS code rollback; no database restore performed')

def main():
    p=argparse.ArgumentParser(description=__doc__);sub=p.add_subparsers(dest='command',required=True)
    b=sub.add_parser('build');b.add_argument('--output',type=Path,default=ROOT/'opencart/packaging/dist/catpart-admin.zip');b.add_argument('--upstream',type=Path,default=UPSTREAM)
    v=sub.add_parser('verify');v.add_argument('--package',type=Path,required=True)
    for name in ('install','update'):
        i=sub.add_parser(name);i.add_argument('--package',type=Path,required=True);i.add_argument('--target',type=Path,required=True);i.add_argument('--apply',action='store_true');i.add_argument('--source',type=Path,default=ROOT.parent/'cat-part');i.add_argument('--port',type=int,default=8095)
    r=sub.add_parser('rollback');r.add_argument('--target',type=Path,required=True);r.add_argument('--apply',action='store_true')
    a=p.parse_args()
    if a.command=='build':build(a)
    elif a.command=='verify':print(json.dumps({'verified':True,'source_revision':verify(a.package)['source_revision'],'sha256':digest(a.package.read_bytes())}))
    elif a.command=='rollback':rollback(a)
    else:apply_package(a)
if __name__=='__main__':
    try:main()
    except Exception as error:print(type(error).__name__+': '+str(error),file=sys.stderr);sys.exit(1)
