# PowerShell script to set up the development environment for the Movie Chatbot

Write-Host "Setting up environment for Movie Chatbot..." -ForegroundColor Cyan

# Check if Python 3 is installed
try {
    $pythonVersion = python --version
    if (-not $pythonVersion.Contains("Python 3")) {
        Write-Host "Python 3 is not installed. Please install Python 3 and try again." -ForegroundColor Red
        exit 1
    }
}
catch {
    Write-Host "Python 3 is not installed or not in PATH. Please install Python 3 and try again." -ForegroundColor Red
    exit 1
}

# Create virtual environment if it doesn't exist
if (-not (Test-Path -Path "venv")) {
    Write-Host "Creating virtual environment..." -ForegroundColor Yellow
    python -m venv venv
}

# Activate virtual environment
Write-Host "Activating virtual environment..." -ForegroundColor Yellow
& .\venv\Scripts\Activate.ps1

# Upgrade pip
Write-Host "Upgrading pip..." -ForegroundColor Yellow
python -m pip install --upgrade pip

# Install application and dependencies
Write-Host "Installing application and dependencies..." -ForegroundColor Yellow
pip install -r requirements.txt

# Create .env file if it doesn't exist
if (-not (Test-Path -Path ".env")) {
    Write-Host "Creating example .env file (please update with your own values)..." -ForegroundColor Yellow
    if (Test-Path -Path ".env.example") {
        Copy-Item -Path ".env.example" -Destination ".env"
    }
    else {
        Write-Host ".env.example not found. You'll need to create .env file manually." -ForegroundColor Red
    }
}

Write-Host "Environment setup complete!" -ForegroundColor Green
Write-Host "To activate the environment in the future, run: .\venv\Scripts\Activate.ps1" -ForegroundColor Cyan
