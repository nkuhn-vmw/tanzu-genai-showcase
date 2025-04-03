# PowerShell script to set up the development environment for the Airbnb Assistant

Write-Host "Setting up environment for Airbnb Assistant..." -ForegroundColor Green

# Check if Python 3 is installed
try {
    $pythonVersion = python --version
    if (-not $pythonVersion.Contains("Python 3")) {
        Write-Host "Python 3 is not the default Python. Please install Python 3 and try again." -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "Python 3 is not installed. Please install Python 3 and try again." -ForegroundColor Red
    exit 1
}

# Create virtual environment if it doesn't exist
if (-not (Test-Path -Path "venv")) {
    Write-Host "Creating virtual environment..." -ForegroundColor Cyan
    python -m venv venv
}

# Activate virtual environment
Write-Host "Activating virtual environment..." -ForegroundColor Cyan
& .\venv\Scripts\Activate.ps1

# Upgrade pip
Write-Host "Upgrading pip..." -ForegroundColor Cyan
python -m pip install --upgrade pip

# Install application and dependencies
Write-Host "Installing application and dependencies..." -ForegroundColor Cyan
pip install -e .

# Install specific dependencies directly
Write-Host "Ensuring all required packages are installed..." -ForegroundColor Cyan
pip install requests pyramid agno sqlalchemy alembic pyramid_mako waitress python-dotenv cfenv

# Create .env file if it doesn't exist
if (-not (Test-Path -Path ".env")) {
    Write-Host "Creating example .env file (please update with your own values)..." -ForegroundColor Cyan
    if (Test-Path -Path ".env.example") {
        Copy-Item -Path ".env.example" -Destination ".env"
    } else {
        @"
# LLM API Configuration
OPENAI_API_KEY=your_api_key_here
GENAI_API_KEY=your_api_key_here
GENAI_MODEL=gpt-4o
GENAI_API_URL=https://api.openai.com/v1

# MCP Configuration
MCP_AIRBNB_URL=http://localhost:3000

# Development Settings
USE_MOCK_DATA=true
DEBUG=true

# Database Settings
DB_REINITIALIZE=false
"@ | Out-File -FilePath ".env" -Encoding utf8
    }
}

# Initialize database
Write-Host "Initializing database..." -ForegroundColor Cyan
python -m airbnb_assistant.scripts.initialize_db development.ini

Write-Host "Environment setup complete!" -ForegroundColor Green
Write-Host "To activate the environment in the future, run: .\venv\Scripts\Activate.ps1" -ForegroundColor Yellow
Write-Host "To start the application, run: pserve development.ini --reload" -ForegroundColor Yellow
