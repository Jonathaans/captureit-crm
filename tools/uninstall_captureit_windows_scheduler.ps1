param()

$ErrorActionPreference = 'Stop'

$taskName = 'CaptureIT Laravel Scheduler'

$task =
    Get-ScheduledTask `
        -TaskName $taskName `
        -ErrorAction SilentlyContinue

if ($null -eq $task) {
    Write-Host "Task tidak ditemukan: $taskName"
    exit 0
}

Unregister-ScheduledTask `
    -TaskName $taskName `
    -Confirm:$false

Write-Host "Task dihapus: $taskName"
