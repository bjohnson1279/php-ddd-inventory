# Windows PowerShell restore script
if ($args.Count -ne 1) {
    Write-Host "Usage: .\restore.ps1 <path_to_backup_file.sql>" -ForegroundColor Yellow
    exit 1
}

$BackupFile = $args[0]

if (!(Test-Path $BackupFile)) {
    Write-Host "Error: Backup file '$BackupFile' not found." -ForegroundColor Red
    exit 1
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $ScriptDir)

# Load .env file
$EnvFile = Join-Path $ProjectRoot ".env"
$DbUser = "ddd_user"
$DbName = "ddd_inventory"
if (Test-Path $EnvFile) {
    Get-Content $EnvFile | Where-Object { $_ -match '=' -and $_ -notmatch '^#' } | ForEach-Object {
        $var = $_.Split('=', 2)
        $key = $var[0].Trim()
        $val = $var[1].Trim()
        if ($key -eq "DB_USERNAME") { $DbUser = $val }
        if ($key -eq "DB_DATABASE") { $DbName = $val }
    }
}

Write-Host "Starting PostgreSQL database restore from $BackupFile..."
Get-Content $BackupFile | docker-compose -f (Join-Path $ProjectRoot "docker-compose.yml") exec -T db psql -U $DbUser -d $DbName

if ($LASTEXITCODE -eq 0) {
    Write-Host "Database successfully restored from $BackupFile" -ForegroundColor Green
} else {
    Write-Host "Error: Database restore failed." -ForegroundColor Red
    exit 1
}
