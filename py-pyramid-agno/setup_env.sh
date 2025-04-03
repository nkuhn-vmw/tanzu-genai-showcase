#!/bin/bash
# Script to set up the development environment for the Airbnb Assistant

echo "Setting up environment for Airbnb Assistant..."

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "Python 3 is not installed. Please install Python 3 and try again."
    exit 1
fi

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
fi

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
echo "Upgrading pip..."
pip install --upgrade pip

# Install application and dependencies
echo "Installing application and dependencies..."
pip install -e .

# Install specific dependencies directly
echo "Ensuring all required packages are installed..."
pip install requests pyramid agno sqlalchemy alembic pyramid_mako waitress python-dotenv cfenv openai

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating example .env file (please update with your own values)..."
    cp .env.example .env 2>/dev/null || cat << EOF > .env
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
EOF
fi

# Initialize database
echo "Initializing database..."
python -m airbnb_assistant.scripts.initialize_db development.ini

echo "Environment setup complete!"
echo "To activate the environment in the future, run: source venv/bin/activate"
echo "To start the application, run: pserve development.ini --reload"
