# Start SmartForum ML service (kills any existing instance on port 5001 first)
$port = 5001
$root = Split-Path -Parent $MyInvocation.MyCommand.Path

Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 1

Set-Location $root
& "$root\.venv\Scripts\uvicorn.exe" app.main:app --host 127.0.0.1 --port $port
