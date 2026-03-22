@echo off
REM Docker Setup Script for HAUCredit
REM This script automates the Docker setup process for Windows

cls
echo ============================================
echo     HAUCredit Docker Setup
echo ============================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker is not installed or not in PATH
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

REM Check if docker-compose is available
docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    docker compose version >nul 2>&1
    if %errorlevel% neq 0 (
        echo ERROR: Docker Compose is not available
        pause
        exit /b 1
    )
    set DOCKER_COMPOSE_CMD=docker compose
) else (
    set DOCKER_COMPOSE_CMD=docker-compose
)

echo Docker is installed!
echo.

REM Check if .env file exists
if not exist .env (
    echo Creating .env file from template...
    if exist .env.example (
        copy .env.example .env
        echo .env file created successfully!
    ) else (
        echo ERROR: .env.example not found
        pause
        exit /b 1
    )
) else (
    echo .env file already exists
)

echo.
echo ============================================
echo     Building and Starting Services
echo ============================================
echo.

REM Build and start containers
%DOCKER_COMPOSE_CMD% up -d

if %errorlevel% neq 0 (
    echo ERROR: Failed to start Docker services
    pause
    exit /b 1
)

echo.
echo ============================================
echo     Setup Complete!
echo ============================================
echo.
echo Application URL: http://localhost
echo.
echo Services started:
%DOCKER_COMPOSE_CMD% ps
echo.
echo Default Login:
echo   Username: admin
echo   Password: 203
echo.
echo MySQL Connection Details:
echo   Host: 127.0.0.1
echo   Port: 3306
echo   Username: dbuser
echo   Password: dbpassword
echo.
echo To view logs: %DOCKER_COMPOSE_CMD% logs -f
echo To stop services: %DOCKER_COMPOSE_CMD% down
echo.
pause
