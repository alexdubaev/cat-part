$ErrorActionPreference = 'SilentlyContinue'
$root = $PSScriptRoot
$expected = @{
    'php.pid' = Join-Path $root 'bin\php\php.exe'
    'mariadb.pid' = Join-Path $root 'bin\mariadb\bin\mariadbd.exe'
}
foreach ($name in @('php.pid','mariadb.pid')) {
    $file = Join-Path (Join-Path $PSScriptRoot 'logs') $name
    if (Test-Path $file) {
        $id = 0
        $process = $null
        try {
            $id = [int](Get-Content $file -Raw).Trim()
            $process = Get-Process -Id $id -ErrorAction Stop
            $actualPath = [System.IO.Path]::GetFullPath($process.Path)
            $expectedPath = [System.IO.Path]::GetFullPath($expected[$name])
            if ([string]::Equals($actualPath, $expectedPath, [System.StringComparison]::OrdinalIgnoreCase)) {
                Stop-Process -Id $id -Force
            } else {
                Write-Warning "Ignoring stale PID file ${file}: PID $id belongs to $actualPath."
            }
        } catch {
            # The process is already gone, the PID file is malformed, or its
            # executable cannot be identified. In all cases it is safe to
            # remove the stale marker, but never to kill an unknown process.
        }
        Remove-Item -LiteralPath $file -Force -ErrorAction SilentlyContinue
    }
}
Write-Host 'Local PHP and MariaDB processes stopped.'
