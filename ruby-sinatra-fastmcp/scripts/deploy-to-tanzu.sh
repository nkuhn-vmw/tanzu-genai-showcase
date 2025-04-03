#!/bin/bash
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="flight-tracking-bot"
GENAI_SERVICE_NAME="genai-llm-service"
GENAI_SERVICE_PLAN="basic"
GENAI_SERVICE_TYPE="genai"

# Print header
echo -e "${YELLOW}Deploying Flight Tracking Chatbot to Tanzu Platform for Cloud Foundry${NC}"
echo "-------------------------------------------------------------------------"

# Check if logged in to CF
CF_API=$(cf target | grep "api endpoint" || echo "")
if [[ -z "$CF_API" ]]; then
  echo -e "${RED}Not logged in to Cloud Foundry.${NC}"
  echo "Please log in using 'cf login' command before running this script."
  exit 1
fi

echo -e "${GREEN}Targeting:${NC} $(cf target | grep org -A 1)"
echo

# Check if service exists
echo -e "${YELLOW}Checking for GenAI service...${NC}"
SERVICE_EXISTS=$(cf services | grep $GENAI_SERVICE_NAME || echo "")

if [[ -z "$SERVICE_EXISTS" ]]; then
  echo -e "${YELLOW}Creating GenAI LLM service instance...${NC}"
  cf create-service $GENAI_SERVICE_TYPE $GENAI_SERVICE_PLAN $GENAI_SERVICE_NAME
  echo -e "${GREEN}Service created.${NC}"
else
  echo -e "${GREEN}GenAI service already exists.${NC}"
fi

# Deploy application
echo -e "${YELLOW}Deploying application...${NC}"
cf push

# Bind service if not already bound
APP_SERVICES=$(cf services | grep $APP_NAME || echo "")
if [[ -z "$APP_SERVICES" ]]; then
  echo -e "${YELLOW}Binding GenAI service to application...${NC}"
  cf bind-service $APP_NAME $GENAI_SERVICE_NAME
  
  echo -e "${YELLOW}Restarting application to apply bindings...${NC}"
  cf restart $APP_NAME
else
  echo -e "${GREEN}Service already bound to application.${NC}"
fi

# Display application information
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo -e "${YELLOW}Application details:${NC}"
cf app $APP_NAME

echo
echo -e "${GREEN}Your Flight Tracking Chatbot is now running on Tanzu Platform for Cloud Foundry.${NC}"
