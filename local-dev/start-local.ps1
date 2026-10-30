$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$bin = Join-Path $root 'bin'
$php = Join-Path $bin 'php\php.exe'
$dbBin = Join-Path $bin 'mariadb\bin'
$server = Join-Path $dbBin 'mariadbd.exe'
$client = Join-Path $dbBin 'mariadb.exe'
$install = Join-Path $dbBin 'mariadb-install-db.exe'
$data = Join-Path $root 'data\mariadb'
$logs = Join-Path $root 'logs'
$dbPidFile = Join-Path $logs 'mariadb.pid'
$phpPidFile = Join-Path $logs 'php.pid'
$lockPath = Join-Path $logs 'startup.lock'
try {
    $startupLock = [System.IO.File]::Open($lockPath, [System.IO.FileMode]::OpenOrCreate, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
} catch {
    throw 'Another local startup is already running; wait for it to finish.'
}
$dump = Join-Path (Split-Path $root -Parent) 'u2797422_catpart.sql'
$importMarker = Join-Path $data '.local_catpart_imported'
$dbStartedHere = $false
$webStartedHere = $false

function Get-ManagedProcess {
    param(
        [string] $PidFile,
        [string] $ExpectedPath
    )

    if (!(Test-Path $PidFile)) { return $null }
    try {
        $processId = [int](Get-Content $PidFile -Raw).Trim()
        $process = Get-Process -Id $processId -ErrorAction Stop
        $actualPath = [System.IO.Path]::GetFullPath($process.Path)
        $expectedFullPath = [System.IO.Path]::GetFullPath($ExpectedPath)
    } catch {
        Remove-Item -LiteralPath $PidFile -Force -ErrorAction SilentlyContinue
        return $null
    }
    if (-not [string]::Equals($actualPath, $expectedFullPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        Write-Warning "Ignoring stale PID file ${PidFile}: PID $processId belongs to $actualPath."
        return $null
    }
    return $process
}

if (!(Test-Path $php)) { throw "PHP is not installed. Extract PHP into $bin\php." }
if (!(Test-Path $server)) { throw "MariaDB is not installed. Extract MariaDB into $bin\mariadb." }
New-Item -ItemType Directory -Force -Path $data,$logs | Out-Null

if (!(Test-Path (Join-Path $data 'mysql'))) {
    & $install --datadir=$data --port=3307 --password= | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'MariaDB data directory initialization failed.' }
}

try {
    $dbProcess = Get-ManagedProcess $dbPidFile $server
    if (!$dbProcess) {
        $portInUse = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
        if ($portInUse) { throw 'Port 3307 is already in use by another process; stop it or choose another local port.' }
        $dbProcess = Start-Process -FilePath $server -ArgumentList @("--datadir=$data",'--port=3307','--bind-address=127.0.0.1','--skip-name-resolve','--console') -WorkingDirectory $root -RedirectStandardOutput (Join-Path $logs 'mariadb.out.log') -RedirectStandardError (Join-Path $logs 'mariadb.err.log') -WindowStyle Hidden -PassThru
        $dbStartedHere = $true
        Set-Content $dbPidFile $dbProcess.Id
        Start-Sleep -Seconds 3
        if ($dbProcess.HasExited) { throw 'MariaDB stopped during startup; inspect logs/mariadb.err.log.' }
    }

# The process can exist before MariaDB is ready to accept TCP connections.
$dbReady = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    & $client --protocol=tcp --host=127.0.0.1 --port=3307 -uroot -e 'SELECT 1' *> $null
    if ($LASTEXITCODE -eq 0) { $dbReady = $true; break }
    Start-Sleep -Seconds 1
}
if (!$dbReady) { throw 'MariaDB did not become ready on port 3307; inspect logs/mariadb.err.log.' }

& $client --protocol=tcp --host=127.0.0.1 --port=3307 -uroot -e "CREATE DATABASE IF NOT EXISTS local_catpart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw 'Could not create or access local_catpart; refusing to start the preview.' }
    $count = & $client --protocol=tcp --host=127.0.0.1 --port=3307 -uroot local_catpart -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='local_catpart';"
    if ($LASTEXITCODE -ne 0 -or $count -notmatch '^\s*\d+\s*$') { throw 'Could not inspect local_catpart; refusing to start the preview.' }
    if (!(Test-Path $dump)) { throw "Database dump not found: $dump" }
    $dumpHash = (Get-FileHash -LiteralPath $dump -Algorithm SHA256).Hash
    if ([int]$count -eq 0) {
        cmd /c "\"$client\" --protocol=tcp --host=127.0.0.1 --port=3307 -uroot local_catpart < \"$dump\"" | Out-Host
        if ($LASTEXITCODE -ne 0) { throw 'Database import failed; inspect the MariaDB output before retrying.' }
        Set-Content -LiteralPath $importMarker -Value $dumpHash -NoNewline
    } else {
        if (!(Test-Path $importMarker) -or (Get-Content -LiteralPath $importMarker -Raw).Trim() -ne $dumpHash) {
            throw 'local_catpart is non-empty but has no completion marker for this dump; refusing to assume a partial import is complete.'
        }
    }

$webProcess = $null
$webProcess = Get-ManagedProcess $phpPidFile $php
if (!$webProcess) {
    $webPort = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
    if ($webPort) { throw 'Port 8080 is already in use by another process; stop it or choose another local port.' }
    $webProcess = Start-Process -FilePath $php -ArgumentList @('-S','127.0.0.1:8080','-t','site','router.php') -WorkingDirectory $root -RedirectStandardOutput (Join-Path $logs 'php.out.log') -RedirectStandardError (Join-Path $logs 'php.err.log') -WindowStyle Hidden -PassThru
    $webStartedHere = $true
    Set-Content $phpPidFile $webProcess.Id
    Start-Sleep -Milliseconds 500
    if ($webProcess.HasExited) { throw 'PHP development server stopped during startup; inspect logs/php.err.log.' }
}
Write-Host 'Local site: http://127.0.0.1:8080/'
} catch {
    if ($dbStartedHere -and $dbProcess -and !$dbProcess.HasExited) {
        Stop-Process -Id $dbProcess.Id -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath $dbPidFile -Force -ErrorAction SilentlyContinue
    }
    if ($webStartedHere -and $webProcess) {
        if (!$webProcess.HasExited) { Stop-Process -Id $webProcess.Id -Force -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $phpPidFile -Force -ErrorAction SilentlyContinue
    }
    throw
}
