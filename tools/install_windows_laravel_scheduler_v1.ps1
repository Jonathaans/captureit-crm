$ErrorActionPreference = 'Stop'

$TaskName = 'Laravel CRM Scheduler'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$ArtisanPath = Join-Path $ProjectRoot 'artisan'

if (-not (Test-Path $ArtisanPath -PathType Leaf)) {
    throw "artisan tidak ditemukan di $ProjectRoot. Simpan script ini di folder tools."
}

$PhpCommand = Get-Command php -ErrorAction Stop
$PhpPath = $PhpCommand.Source
$TaskCommand = '"{0}" "{1}" schedule:run' -f $PhpPath, $ArtisanPath

Write-Host 'INSTALL WINDOWS LARAVEL SCHEDULER V1'
Write-Host '===================================='
Write-Host "PHP     : $PhpPath"
Write-Host "Project : $ProjectRoot"
Write-Host "Task    : $TaskName"
Write-Host ''

& schtasks.exe /Create `
    /SC MINUTE `
    /MO 1 `
    /TN $TaskName `
    /TR $TaskCommand `
    /F `
    /RL HIGHEST

if ($LASTEXITCODE -ne 0) {
    throw 'Gagal membuat Windows Task Scheduler. Jalankan PowerShell sebagai Administrator.'
}

& schtasks.exe /Run /TN $TaskName

if ($LASTEXITCODE -ne 0) {
    throw 'Task berhasil dibuat tetapi test run gagal.'
}

Write-Host ''
Write-Host '[PASS] Windows akan menjalankan Laravel scheduler setiap menit.'
Write-Host 'Laravel sendiri hanya mengeksekusi crm:backup sekali sehari pukul 02:00.'

