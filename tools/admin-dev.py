"""Prepare and run a strictly isolated local CatPart + OpenCart 4.1.0.4 stand."""
import argparse, hashlib, json, os, secrets, shutil, signal, subprocess, sys, time, urllib.request, zipfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
RUNTIME=ROOT/'local-dev/admin-runtime'
CORE_HASH='cdef537f5a0a7e1b4ddbe16f589dd2e80b10150a09bfbe93ce76ef2d5c3218c0'

def php_quote(value):return "'"+str(value).replace('\\','/').replace("'","\\'")+"'"

def local_environment(state):
    return os.environ|{'CATPART_TEST_DATABASE':state['database'],'CATPART_OC_ROOT':str(RUNTIME/'core'),'CATPART_SOURCE_MEDIA':str(Path(state['source'])/'local-dev/site/upload'),'CATPART_ADMIN_RUNTIME':str(RUNTIME)}

def php_command(state):
    return [state['php'],'-c',str(RUNTIME/'php.ini')]

def php_script(state,path,data=None):
    result=subprocess.run([*php_command(state),str(path)],input=json.dumps(data) if data is not None else None,capture_output=True,text=True,encoding='utf-8',env=local_environment(state),cwd=ROOT)
    if result.returncode:
        # The bootstrap receives secrets, so do not echo subprocess arguments or stdin.
        print(result.stderr[-3000:],file=sys.stderr)
        raise RuntimeError('Local PHP operation failed; see private runtime logs.')
    return result.stdout

def setup(args):
    source=args.source.resolve()
    if ROOT==source or source in ROOT.parents:raise RuntimeError('The stand must be separate from the read-only source checkout.')
    php=source/'local-dev/bin/php/php.exe'
    if not php.is_file():raise RuntimeError('The supplied source checkout has no ready PHP binary.')
    archive=args.upstream.resolve()
    if hashlib.sha256(archive.read_bytes()).hexdigest()!=CORE_HASH:raise RuntimeError('OpenCart upstream checksum mismatch.')
    RUNTIME.mkdir(parents=True,exist_ok=True)
    private=RUNTIME/'private';private.mkdir(exist_ok=True)
    state_file=RUNTIME/'state.json'
    if state_file.is_file():
        state=json.loads(state_file.read_text(encoding='utf-8'))
        if state['source']!=str(source) or state['database']!='catpart_admin_'+hashlib.sha256(str(ROOT).lower().encode()).hexdigest()[:12]:raise RuntimeError('Existing runtime identity mismatch.')
    else:
        state={'source':str(source),'php':str(php),'database':'catpart_admin_'+hashlib.sha256(str(ROOT).lower().encode()).hexdigest()[:12],'origin':f'http://127.0.0.1:{args.port}','port':args.port,'upstream_sha256':CORE_HASH}
    if not 1024<=int(state['port'])<=65535:raise RuntimeError('Unprivileged local port required.')
    core=RUNTIME/'core'
    if not (core/'admin/index.php').is_file():
        with zipfile.ZipFile(archive) as z:
            for name in z.namelist():
                if not name.startswith('upload/') or name.endswith('/'):continue
                relative=Path(name.removeprefix('upload/'))
                if '..' in relative.parts or relative.is_absolute():raise RuntimeError('Unsafe upstream path.')
                dest=core/relative;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(z.read(name))
    # Copy the ready PHP ini, enabling GD only for this isolated process.
    original_ini=php.parent/'php.ini'
    ini=original_ini.read_text(encoding='utf-8') if original_ini.is_file() else ''
    ini+='\nextension_dir='+str(php.parent/'ext').replace('\\','/')+'\nextension=gd\nextension=mysqli\n'
    ini+='\ndisplay_errors=Off\nlog_errors=On\nerror_log='+str(private/'php-errors.log').replace('\\','/')+'\n'
    # Isolated importer: bounded streaming files, never change the shared PHP ini.
    ini+='\nupload_max_filesize=16M\npost_max_size=20M\n'
    (RUNTIME/'php.ini').write_text(ini,encoding='utf-8')
    account_file=private/'accounts.json'
    if account_file.is_file():accounts=json.loads(account_file.read_text(encoding='utf-8'))
    else:
        accounts={role:{'username':'catpart-'+role,'password':secrets.token_urlsafe(24)} for role in ('administrator','editor','manager','reader')}
        account_file.write_text(json.dumps(accounts,ensure_ascii=False,indent=2),encoding='utf-8')
    usernames={'administrator':'cp-admin','editor':'cp-editor','manager':'cp-manager','reader':'cp-reader'}
    for role,username in usernames.items():accounts[role]['username']=username
    account_file.write_text(json.dumps(accounts,ensure_ascii=False,indent=2),encoding='utf-8')
    db_password_file=private/'database-password'
    if not db_password_file.is_file():db_password_file.write_text(secrets.token_urlsafe(32),encoding='ascii')
    db_password=db_password_file.read_text(encoding='ascii')
    state_file.write_text(json.dumps(state,indent=2),encoding='utf-8')
    payload={'database':state['database'],'core':str(core),'extension':str(ROOT/'opencart/extension/catpart'),'origin':state['origin'],'accounts':accounts,'db_password':db_password}
    report=json.loads(php_script(state,ROOT/'tools/admin-bootstrap.php',payload))
    db_user='cp_'+state['database'][-12:]
    storage=private/'storage'
    for name in ('cache','download','logs','modification','session','upload','vendor'):(storage/name).mkdir(parents=True,exist_ok=True)
    # Core's vendor code is immutable and remains outside the source checkout.
    if (core/'system/storage/vendor').is_dir():
        if not (storage/'vendor/autoload.php').is_file():shutil.copytree(core/'system/storage/vendor',storage/'vendor',dirs_exist_ok=True)
    config={'APPLICATION':'Admin','HTTP_SERVER':state['origin']+'/admin/','HTTP_CATALOG':state['origin']+'/','DIR_OPENCART':str(core)+'/', 'DIR_APPLICATION':str(core/'admin')+'/', 'DIR_EXTENSION':str(ROOT/'opencart/extension')+'/', 'DIR_SYSTEM':str(core/'system')+'/', 'DIR_IMAGE':str(core/'image')+'/', 'DIR_STORAGE':str(storage)+'/', 'DIR_LANGUAGE':str(core/'admin/language')+'/', 'DIR_TEMPLATE':str(core/'admin/view/template')+'/', 'DIR_CONFIG':str(core/'system/config')+'/', 'DIR_CACHE':str(storage/'cache')+'/', 'DIR_DOWNLOAD':str(storage/'download')+'/', 'DIR_LOGS':str(storage/'logs')+'/', 'DIR_SESSION':str(storage/'session')+'/', 'DIR_UPLOAD':str(storage/'upload')+'/', 'DB_DRIVER':'mysqli','DB_HOSTNAME':'127.0.0.1','DB_USERNAME':db_user,'DB_PASSWORD':db_password,'DB_DATABASE':state['database'],'DB_PREFIX':'oc_','DB_PORT':'3307','CATPART_TEST_DATABASE':state['database']}
    content='<?php\n'+''.join(f'define({php_quote(k)}, {php_quote(v)});\n' for k,v in config.items())+'require_once '+php_quote(ROOT/'opencart/extension/catpart/system/autoload.php')+';\n'
    (core/'admin/config.php').write_text(content,encoding='utf-8')
    public=ROOT/'local-dev/site';cfg=public/'app/Config';cfg.mkdir(parents=True,exist_ok=True)
    base={'app':{'base_url':state['origin'],'debug':False,'timezone':'Europe/Moscow'},'site':{'name':'CatPart','domain':'cat-part.ru','tagline':'Запчасти Caterpillar','email':'info@cmteh.ru','phone':'+7 (812) 436-82-99','company':'СМ ТЕХНО'},'db':{'host':'127.0.0.1','port':3307,'name':state['database'],'user':db_user,'pass':db_password,'charset':'utf8mb4'},'mail':{'delivery':'capture','capture_dir':str(private/'mail-captures'),'from_email':'info@cmteh.ru','notify':['info@cmteh.ru']},'catalog':{'per_page':24,'search_per_page':24}}
    def php_value(value):
        if isinstance(value,dict):return '['+','.join(php_quote(k)+'=>'+php_value(v) for k,v in value.items())+']'
        if isinstance(value,list):return '['+','.join(php_value(v) for v in value)+']'
        if isinstance(value,bool):return 'true' if value else 'false'
        if isinstance(value,int):return str(value)
        return php_quote(value)
    # Only this worktree's ignored files. Never use a production config as input.
    for name in ('config.php','config.local.php'):(cfg/name).write_text('<?php\nreturn '+php_value(base)+';\n',encoding='utf-8')
    print(json.dumps(report,ensure_ascii=False))
    print('Credentials file:',account_file)
    print(php_script(state,ROOT/'tools/admin-migrate.php').strip())
    start(state)

def start(state):
    ready=False
    try:
        with urllib.request.urlopen(state['origin']+'/admin/index.php?route=common/login',timeout=2) as r:
            ready=r.status==200 and b'form-login' in r.read()
    except Exception:pass
    if ready:
        pid_file=RUNTIME/'server.pid'
        if not pid_file.is_file():raise RuntimeError('Port is occupied without an owned task PID; refusing reuse.')
        verify_process(state,int(pid_file.read_text()))
        print('Ready:',state['origin']);return
    out=(RUNTIME/'private/server.log').open('ab')
    command=[*php_command(state),'-S','127.0.0.1:'+str(state['port']),'-t',str(ROOT/'local-dev/site'),str(ROOT/'local-dev/router.php')]
    options={'creationflags':subprocess.CREATE_NO_WINDOW} if os.name=='nt' else {'start_new_session':True}
    process=subprocess.Popen(command,cwd=ROOT/'local-dev',env=local_environment(state),stdout=out,stderr=out,**options)
    (RUNTIME/'server.pid').write_text(str(process.pid))
    for _ in range(30):
        time.sleep(.25)
        if process.poll() is not None:raise RuntimeError('Local server exited; inspect private/server.log.')
        try:
            with urllib.request.urlopen(state['origin']+'/admin/index.php?route=common/login',timeout=2) as r:
                if r.status==200:print('Ready:',state['origin']);return
        except Exception:pass
    raise RuntimeError('Local server did not become ready; inspect private logs.')

def verify_process(state,pid):
    if os.name=='nt':
        command=f'Get-CimInstance Win32_Process -Filter "ProcessId={pid}" | Select-Object ExecutablePath,CommandLine | ConvertTo-Json -Compress'
        result=subprocess.run([shutil.which('pwsh') or 'powershell','-NoProfile','-Command',command],capture_output=True,text=True,creationflags=subprocess.CREATE_NO_WINDOW)
        if result.returncode:raise RuntimeError('Cannot verify the owned server process.')
        proc=json.loads(result.stdout or 'null')
        if proc is None:raise RuntimeError('Owned task PID is no longer running.')
        actual=Path(proc['ExecutablePath']).resolve()
        expected=Path(state['php']).resolve()
        if actual!=expected or str(ROOT/'local-dev/router.php') not in proc['CommandLine']:
            raise RuntimeError('PID is no longer the owned task server; refusing to stop it.')
    else:
        args=Path(f'/proc/{pid}/cmdline').read_bytes().replace(b'\0',b' ').decode()
        if str(ROOT/'local-dev/router.php') not in args:raise RuntimeError('PID does not belong to this task.')
def stop(state):
    pid_file=RUNTIME/'server.pid'
    if not pid_file.is_file():return
    pid=int(pid_file.read_text())
    try:verify_process(state,pid)
    except RuntimeError as error:
        if str(error)=='Owned task PID is no longer running.':pid_file.unlink(missing_ok=True);return
        raise
    os.kill(pid,signal.SIGTERM)
    pid_file.unlink(missing_ok=True)

def main():
    global RUNTIME
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('command',choices=('setup','start','stop','status','refresh'))
    p.add_argument('--runtime',type=Path,default=Path(os.environ.get('CATPART_ADMIN_RUNTIME',str(RUNTIME))))
    p.add_argument('--source',type=Path,default=ROOT.parent/'cat-part');p.add_argument('--upstream',type=Path,default=Path('D:/codex/temp/cmteh-opencart-cache/opencart-4.1.0.4.zip'));p.add_argument('--port',type=int,default=8095);a=p.parse_args()
    RUNTIME=a.runtime.resolve()
    if a.command=='setup':setup(a)
    else:
        state=json.loads((RUNTIME/'state.json').read_text(encoding='utf-8'))
        if a.command=='refresh':
            print(php_script(state,ROOT/'tools/admin-migrate.php').strip())
            start(state)
        elif a.command=='start':start(state)
        elif a.command=='stop':stop(state)
        else:print(json.dumps({k:state[k] for k in ('database','origin','upstream_sha256')},indent=2))
if __name__=='__main__':main()
