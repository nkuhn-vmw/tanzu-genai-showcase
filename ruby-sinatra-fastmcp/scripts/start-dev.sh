#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting Flight Tracking Chatbot in development mode${NC}"
echo "-----------------------------------------------------"

# Check if .env file exists
if [ ! -f .env ]; then
  echo -e "${RED}Error: .env file not found!${NC}"
  echo "Please create a .env file based on .env.example"
  exit 1
fi

# Check if bundler is installed
if ! command -v bundle &> /dev/null; then
  echo -e "${RED}Error: bundler not found!${NC}"
  echo "Please install bundler with: gem install bundler"
  exit 1
fi

# Install dependencies if needed
if [ ! -d "vendor/bundle" ]; then
  echo -e "${YELLOW}Installing dependencies...${NC}"
  bundle install --path vendor/bundle
fi

# Start the server with rerun for auto-reloading
echo -e "${GREEN}Starting server on http://localhost:4567${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
bundle exec rerun -- rackup -p 4567 config.ru
