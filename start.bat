@echo off
echo Starting Transaction-War Local Server...
echo The website will open in your default browser shortly.
echo.
echo Please DO NOT close this black window while you are using the website!
echo If you close this window, the website will stop working.
echo.
start http://localhost:8080/
C:\xampp\php\php.exe -S localhost:8080
