#!/bin/bash

# Color codes for terminal output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting dependency fix process...${NC}"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}npm not found. Please install Node.js and npm.${NC}"
    exit 1
fi

# Run npm audit fix
echo -e "${GREEN}Running npm audit fix...${NC}"
npm audit fix

# Install dependencies with overrides
echo -e "${GREEN}Reinstalling dependencies with overrides...${NC}"
npm install

echo -e "${GREEN}Dependency fix process completed.${NC}"
echo -e "${YELLOW}Note: Some warnings may persist but should not affect functionality.${NC}"
