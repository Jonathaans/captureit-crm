$ErrorActionPreference = 'Stop'

$taskName = 'CaptureIT Laravel Scheduler'
$runner = 'C:\Users\Administrator\Documents\laravel-crm-2.2/tools/run_captureit_laravel_scheduler.cmd'

$action = New-ScheduledTaskAction `
    -Execute 'cmd.exe' `
    -Argument ('/c "' + $runner + '"')

$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1)

$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'Runs Laravel schedule:run every minute. My Email inbound sync is due every 5 minutes.' `
    -Force | Out-Null

Write-Host "WINDOWS TASK INSTALLED: $taskName"