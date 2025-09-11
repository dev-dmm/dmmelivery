# Development Commands

This document lists all available commands for running the DMM Delivery application in development mode.

## Quick Start

### Option 1: Single Command (Recommended)
```bash
npm run start
```
This will start both Laravel server and Vite development server simultaneously using `concurrently`.

### Option 2: Windows Batch File
```bash
start-dev.bat
```
Double-click the file or run from command prompt. Opens both servers in separate windows.

### Option 3: PowerShell Script
```powershell
.\start-dev.ps1
```
Run from PowerShell. Opens both servers in separate windows.

## Individual Commands

### Laravel Server Only
```bash
php artisan serve
# or
npm run serve
```
Runs Laravel server on http://127.0.0.1:8000

### Vite Development Server Only
```bash
npm run dev
```
Runs Vite development server on http://localhost:5173

## Build Commands

### Production Build
```bash
npm run build
```
Builds assets for production deployment.

## Server URLs

- **Laravel Backend**: http://127.0.0.1:8000
- **Vite Dev Server**: http://localhost:5173
- **Application**: http://127.0.0.1:8000 (Laravel serves the main app)

## Notes

- The `npm run start` command is the most convenient as it runs both servers in a single terminal with colored output
- Laravel server handles the main application and API routes
- Vite server handles hot module replacement for frontend assets
- Both servers need to be running for full development functionality
