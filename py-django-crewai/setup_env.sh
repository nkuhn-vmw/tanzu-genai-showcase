#!/bin/bash
# Script to set up the development environment for the Movie Chatbot

echo "Setting up environment for Movie Chatbot..."

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
pip install -r requirements.txt

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating example .env file (please update with your own values)..."
    cp .env.example .env 2>/dev/null
fi

echo "Environment setup complete!"
echo "To activate the environment in the future, run: source venv/bin/activate"
