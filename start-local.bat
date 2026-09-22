@echo off
echo ========================================================
echo Starting Haven Hotel Management on http://localhost:8000
echo ========================================================
wsl -d Ubuntu-24.04 -e bash -c "sudo service mariadb status >/dev/null 2>&1 || sudo service mariadb start"
echo MariaDB service is ready.
echo Starting PHP web server at http://localhost:8000 ...
echo Press Ctrl+C to stop.
echo ========================================================
wsl -d Ubuntu-24.04 --cd "/mnt/c/Users/lenovo/Desktop/Hotel management" -e php -S 0.0.0.0:8000
