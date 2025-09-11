@echo off
echo Starting DMM Delivery Development Environment...
echo.

echo Starting Laravel server on http://127.0.0.1:8000...
start "Laravel Server" cmd /k "php artisan serve"

echo.
echo Starting Vite development server on http://localhost:5173...
start "Vite Dev Server" cmd /k "npm run dev"

echo.
echo Both servers are starting...
echo Laravel: http://127.0.0.1:8000
echo Vite: http://localhost:5173
echo.
echo Press any key to exit this window...
pause > nul