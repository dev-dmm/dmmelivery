@echo off
echo Starting EShop Tracker Development Environment...
echo.
echo Starting Laravel server on http://127.0.0.1:8000
echo Starting Vite dev server for React assets...
echo.
echo Press Ctrl+C to stop both servers
echo.

start "Laravel Server" cmd /k "php artisan serve"
start "Vite Dev Server" cmd /k "npm run dev"

echo Both servers are starting in separate windows.
echo You can now access the application at: http://127.0.0.1:8000
echo.
echo Demo login credentials:
echo - electroshop@demo.com / password
echo - fashionboutique@demo.com / password  
echo - bookstoreplus@demo.com / password
echo.
pause 