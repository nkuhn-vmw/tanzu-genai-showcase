#!/bin/bash

# Script to deploy the application to Tanzu Platform for Cloud Foundry

set -e  # Exit on error

# Configuration
APP_NAME="event-recommendation-chatbot"
GENAI_SERVICE_NAME="genai-llm-service"
JAR_PATH="backend/target/event-recommendation-chatbot-backend-0.1.0-SNAPSHOT.jar"

# Check if cf CLI is installed
if ! command -v cf &> /dev/null; then
    echo "Error: Cloud Foundry CLI (cf) is not installed."
    echo "Please install it from https://github.com/cloudfoundry/cli/releases"
    exit 1
fi

# Check if the user is logged in
if ! cf target &> /dev/null; then
    echo "Error: You are not logged in to Cloud Foundry."
    echo "Please log in using 'cf login' or 'cf login --sso'"
    exit 1
fi

# Build the application
echo "Building the application..."
./mvnw clean package -DskipTests

# Check if the JAR was created
if [ ! -f "$JAR_PATH" ]; then
    echo "Error: JAR file not found at $JAR_PATH"
    echo "Build may have failed. Check the logs above."
    exit 1
fi

# Check if the GenAI service exists
if ! cf service "$GENAI_SERVICE_NAME" &> /dev/null; then
    echo "Creating GenAI LLM service..."
    cf create-service genai standard "$GENAI_SERVICE_NAME"
fi

# Check if the app exists
if cf app "$APP_NAME" &> /dev/null; then
    echo "Updating existing application: $APP_NAME..."
    cf push "$APP_NAME"
else
    echo "Deploying new application: $APP_NAME..."
    cf push "$APP_NAME" -p "$JAR_PATH" \
        --random-route \
        -b java_buildpack
fi

# Ensure the app is bound to the GenAI service
if ! cf services | grep "$APP_NAME" | grep "$GENAI_SERVICE_NAME" &> /dev/null; then
    echo "Binding application to GenAI service..."
    cf bind-service "$APP_NAME" "$GENAI_SERVICE_NAME"
    cf restage "$APP_NAME"
fi

echo "Deployment complete!"
echo "You can access your application at: $(cf app "$APP_NAME" | grep routes | awk '{print $2}')"
