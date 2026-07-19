# Windows PowerShell backup script
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $ScriptDir)
$BackupDir = Join-Path $ScriptDir "backups"
if (!(Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupFile = Join-Path $BackupDir "inventory_db_backup_$Timestamp.sql"

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

Write-Host "Starting PostgreSQL database backup..."
docker-compose -f (Join-Path $ProjectRoot "docker-compose.yml") exec -T db pg_dump -U $DbUser -d $DbName > $BackupFile

if ($LASTEXITCODE -eq 0) {
    Write-Host "Backup successfully created: $BackupFile" -ForegroundColor Green
} else {
    Write-Host "Error: Database backup failed." -ForegroundColor Red
    exit 1
}
