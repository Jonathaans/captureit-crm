$ErrorActionPreference = 'Stop'

$taskName = 'CaptureIT Laravel Scheduler'
$vbs = 'C:\Users\Administrator\Documents\laravel-crm-2.2/tools/run_captureit_laravel_scheduler_hidden.vbs'
$wscript = Join-Path $env:SystemRoot 'System32\wscript.exe'

$action =
    New-ScheduledTaskAction `
        -Execute $wscript `
        -Argument ('"' + $vbs + '"')

$trigger =
    New-ScheduledTaskTrigger `
        -Once `
        -At (Get-Date).AddMinutes(1) `
        -RepetitionInterval (New-TimeSpan -Minutes 1)

$settings =
    New-ScheduledTaskSettingsSet `
        -MultipleInstances IgnoreNew `
        -StartWhenAvailable `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'Runs Laravel schedule:run invisibly every minute. Personal My Email sync is due every 5 minutes.' `
    -Force `
    | Out-Null

Write-Host "INSTALLED: $taskName"