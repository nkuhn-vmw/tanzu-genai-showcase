# Congress.gov API Chatbot with Go + Fiber + LangChainGo

![Status](https://img.shields.io/badge/status-under%20development-darkred)

This application demonstrates how to build a chatbot that interacts with the Congress.gov API using Go, Fiber, and LangChainGo, deployable on Tanzu Platform for Cloud Foundry.

## What is it?

A web-based chatbot application that allows users to interact with the Congress.gov API to fetch information about bills, amendments, summaries, and members in natural language. The application uses LangChainGo to integrate with GenAI's LLM service for natural language processing.

## Prerequisites

- Go 1.18+ installed locally for development
- Access to Congress.gov API (get your API key at https://api.congress.gov/sign-up/)
- For Tanzu deployment:
  - Tanzu Platform for Cloud Foundry environment
  - Access to GenAI tile and LLM service instances
  - Cloud Foundry CLI installed

## How to Build

Build a binary:

```bash
go build -o congress-chatbot cmd/server/main.go
```

## How to Run Locally

### 1. Clone the repository

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
cd tanzu-genai-showcase/go-fiber-langchaingo
```

### 2. Create a .env file for local development

```bash
cp .env.example .env
```

Edit the `.env` file to include your API keys:

```
CONGRESS_API_KEY=your_congress_api_key
LLM_API_KEY=your_llm_api_key
LLM_API_URL=your_llm_api_url
LLM=gpt-4o-mini
```

> **Note:** The `LLM` environment variable allows you to specify which language model to use. If not set, it defaults to `gpt-4o-mini`.

### 3. Install dependencies

```bash
go mod tidy
```

### 4. Run the application locally

```bash
go run cmd/server/main.go
```

The application will be available at http://localhost:8080

## How to Run on Cloud Foundry

### 1. Build the application

```bash
go build -o congress-chatbot cmd/server/main.go
```

### 2. Push to Cloud Foundry

```bash
source .env
cf push --no-start
cf set-env congress-chatbot CONGRESS_API_KEY ${CONGRESS_API_KEY}
```

### 3. Bind to LLM Service

```bash
# Create an LLM service instance (if not already created)
cf create-service genai PLAN_NAME congress-llm
```

> [!IMPORTANT]
> Replace `PLAN_NAME` above with the name of an available plan of the GenAI tile service offering

```bash
# Bind the service to your application
cf bind-service congress-chatbot congress-llm

# Restart your application to apply the binding
cf start congress-chatbot
```

### 4. Alternative: Manual Service Configuration

If you prefer to manually configure the LLM service:

```bash
# Create a service key
cf create-service-key congress-llm congress-llm-service-key

# Get the service key details
cf service-key congress-llm congress-llm-service-key
```

Update your application's environment variables with the service key details:

```bash
cf set-env congress-chatbot LLM_API_KEY "the-api-key-from-service-key"
cf set-env congress-chatbot LLM_API_URL "the-url-from-service-key"

# Restart your application to apply changes
cf restart congress-chatbot
```

## Tech Stack

- **Go**: Programming language
- **Fiber**: Web framework for building the API and serving the web interface
- **LangChainGo**: Framework for building applications with large language models
- **Congress.gov API**: External API for fetching legislative data
- **GenAI LLM Service**: Large language model service provided by Tanzu Platform for Cloud Foundry

## Project Structure

```bash
├── api/            # API clients (Congress.gov)
├── cmd/            # Application entry points
├── config/         # Configuration handling
├── internal/       # Private application code
├── pkg/            # Public libraries
├── .env.example    # Example environment variables
├── go.mod          # Go module definition
├── manifest.yml    # Cloud Foundry manifest
└── README.md       # This file
```
