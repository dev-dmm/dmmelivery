Write-Host "Starting DMM Delivery Development Environment..." -ForegroundColor Green
Write-Host ""

Write-Host "Starting Laravel server on http://127.0.0.1:8000..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan serve" -WindowStyle Normal

Write-Host ""
Write-Host "Starting Vite development server on http://localhost:5173..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "npm run dev" -WindowStyle Normal

Write-Host ""
Write-Host "Both servers are starting..." -ForegroundColor Green
Write-Host "Laravel: http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Vite: http://localhost:5173" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press any key to exit this window..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
