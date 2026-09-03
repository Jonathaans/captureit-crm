$ErrorActionPreference = 'Stop'

$task =
    Get-ScheduledTask `
        -TaskName 'CaptureIT Laravel Scheduler' `
        -ErrorAction Stop

$info =
    Get-ScheduledTaskInfo `
        -TaskName 'CaptureIT Laravel Scheduler'

$action =
    $task.Actions `
        | Select-Object -First 1

Write-Output ('STATE|' + $task.State)
Write-Output ('EXECUTE|' + $action.Execute)
Write-Output ('ARGUMENTS|' + $action.Arguments)
Write-Output ('LAST_RUN|' + $info.LastRunTime.ToString('yyyy-MM-dd HH:mm:ss'))
Write-Output ('LAST_RESULT|' + $info.LastTaskResult)
Write-Output ('NEXT_RUN|' + $info.NextRunTime.ToString('yyyy-MM-dd HH:mm:ss'))