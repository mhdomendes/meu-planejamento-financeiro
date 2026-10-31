$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$backupDirectory = Join-Path $repositoryRoot 'backups'
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = Join-Path $backupDirectory "financeiro-$timestamp.dump"

$dumpInfo = New-Object System.Diagnostics.ProcessStartInfo
$dumpInfo.FileName = 'wsl.exe'
$dumpInfo.Arguments = '-e docker exec financeiro-postgres pg_dump -U postgres -d financeiro -Fc'
$dumpInfo.UseShellExecute = $false
$dumpInfo.RedirectStandardOutput = $true
$dumpInfo.RedirectStandardError = $true

$dumpProcess = New-Object System.Diagnostics.Process
$dumpProcess.StartInfo = $dumpInfo

try {
    if (-not $dumpProcess.Start()) {
        throw 'Nao foi possivel iniciar o backup do PostgreSQL no Docker.'
    }

    $dumpErrorTask = $dumpProcess.StandardError.ReadToEndAsync()
    $backupStream = [System.IO.File]::Open(
        $backupPath,
        [System.IO.FileMode]::CreateNew,
        [System.IO.FileAccess]::Write,
        [System.IO.FileShare]::None
    )

    try {
        $dumpProcess.StandardOutput.BaseStream.CopyTo($backupStream)
    }
    finally {
        $backupStream.Dispose()
    }

    $dumpProcess.WaitForExit()
    $dumpError = $dumpErrorTask.GetAwaiter().GetResult()

    if ($dumpProcess.ExitCode -ne 0) {
        throw "pg_dump falhou (codigo $($dumpProcess.ExitCode)): $dumpError"
    }

    if ((Get-Item $backupPath).Length -eq 0) {
        throw 'O arquivo de backup foi criado vazio.'
    }

    $verifyInfo = New-Object System.Diagnostics.ProcessStartInfo
    $verifyInfo.FileName = 'wsl.exe'
    $verifyInfo.Arguments = '-e docker exec -i financeiro-postgres pg_restore --list'
    $verifyInfo.UseShellExecute = $false
    $verifyInfo.RedirectStandardInput = $true
    $verifyInfo.RedirectStandardOutput = $true
    $verifyInfo.RedirectStandardError = $true

    $verifyProcess = New-Object System.Diagnostics.Process
    $verifyProcess.StartInfo = $verifyInfo

    if (-not $verifyProcess.Start()) {
        throw 'Nao foi possivel verificar o arquivo de backup.'
    }

    $verifyOutputTask = $verifyProcess.StandardOutput.ReadToEndAsync()
    $verifyErrorTask = $verifyProcess.StandardError.ReadToEndAsync()
    $backupStream = [System.IO.File]::OpenRead($backupPath)

    try {
        $backupStream.CopyTo($verifyProcess.StandardInput.BaseStream)
    }
    finally {
        $backupStream.Dispose()
        $verifyProcess.StandardInput.Close()
    }

    $verifyProcess.WaitForExit()
    $verifyOutput = $verifyOutputTask.GetAwaiter().GetResult()
    $verifyError = $verifyErrorTask.GetAwaiter().GetResult()

    if ($verifyProcess.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($verifyOutput)) {
        throw "A verificacao do backup falhou: $verifyError"
    }

    Write-Output "Backup criado e verificado: $backupPath"
    Write-Output ('Tamanho: {0:N2} MB' -f ((Get-Item $backupPath).Length / 1MB))
}
catch {
    if (Test-Path $backupPath) {
        Remove-Item -LiteralPath $backupPath
    }

    throw
}
finally {
    $dumpProcess.Dispose()
    if ($verifyProcess) {
        $verifyProcess.Dispose()
    }
}
