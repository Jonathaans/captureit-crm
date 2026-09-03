param()

$ErrorActionPreference = 'Stop'

$toolsDir =
    Split-Path `
        -Parent `
        $MyInvocation.MyCommand.Path

$runner =
    Join-Path `
        $toolsDir `
        'run_captureit_laravel_scheduler_hidden.ps1'

$taskName =
    'CaptureIT Laravel Scheduler'

if (-not (Test-Path $runner)) {
    throw "Hidden runner tidak ditemukan: $runner"
}

$task =
    Get-ScheduledTask `
        -TaskName $taskName `
        -ErrorAction Stop

$action =
    New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $runner + '"')

Set-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    | Out-Null

Write-Host "UPDATED: $taskName"
Write-Host "Action sekarang PowerShell hidden."
