# .NET with Microsoft.Extensions.AI and Semantic Kernel

![Status](https://img.shields.io/badge/status-stable-green)

This directory contains an implementation of a GenAI application using .NET with Microsoft.Extensions.AI and Semantic Kernel. The application demonstrates how to build and deploy AI-powered applications on Tanzu Platform for Cloud Foundry.

For detailed instructions on using environment variables, see [Environment Variables Documentation](docs/ENVVARS.md).

## Use Case: Transportation Mode Recommendation Bot

This application showcases a bot that answers questions about transportation options (walk, bike, bus, car, train, or plane) based on origin and destination. It leverages the Google Maps API and considers user-supplied criteria like efficiency and speed to provide recommendations.

## Features

- Natural language input processing 
- Intelligent transportation mode recommendations
- Integration with Google Maps for accurate distance and duration calculations
- Score-based recommendations based on user preferences
- Detailed explanations of recommendations
- Follow-up question answering capability

## Prerequisites

- .NET 9 SDK
- Cloud Foundry CLI
- Access to a Tanzu Platform for Cloud Foundry environment with GenAI tile installed
- Google Maps API key
- OpenAI API Key (or Azure OpenAI)

## Technologies Used

- Microsoft.Extensions.AI - Provides abstractions for AI services
- Semantic Kernel - AI orchestration and prompt management
- Blazor - Web UI framework
- Google Maps API - Transportation and mapping data
- Steeltoe - Cloud Foundry integration

## Directory Structure

```bash
dotnet-extensions-ai/
├── src/                             # Application code
│   ├── TravelAdvisor.Web/           # Blazor Web UI
│   ├── TravelAdvisor.Core/          # Core business logic
│   └── TravelAdvisor.Infrastructure/ # External services integration
├── tests/                           # Test cases
├── .config/                         # Configuration files
├── manifest.yml                     # Cloud Foundry manifest
└── README.md                        # Documentation
```

## Local Development

### Setup

1. Clone the repository

   ```bash
   git clone http://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/dotnet-extensions-ai
   ```

2. Install dependencies:

   ```bash
   dotnet restore
   ```

3. Set up environment variables:

   You can configure the application in one of the following ways:

   **Option 1: Using .env file (Recommended)**

   Copy the example environment file and modify it with your API keys:

   ```bash
   cp src/.env.example src/.env
   ```

   Edit the `src/.env` file and replace placeholder values with your actual API keys.

   **Option 2: Using user secrets**

   ```bash
   dotnet user-secrets init --project src/TravelAdvisor.Web
   dotnet user-secrets set "GoogleMaps:ApiKey" "your_api_key" --project src/TravelAdvisor.Web
   dotnet user-secrets set "GenAI:ApiKey" "your_api_key" --project src/TravelAdvisor.Web
   dotnet user-secrets set "GenAI:ApiUrl" "your_api_url" --project src/TravelAdvisor.Web
   dotnet user-secrets set "GenAI:Model" "your_model" --project src/TravelAdvisor.Web
   ```

   **Option 3: Using system environment variables**

   You can also set system environment variables directly:

   On Linux/macOS:

   ```bash
   export GENAI__APIKEY=your_genai_api_key_here
   export GENAI__APIURL=your_api_url_here
   export GENAI__MODEL=your_model_here
   export GOOGLEMAPS__APIKEY=your_googlemaps_api_key_here
   ```

   On Windows (Command Prompt):

   ```bash
   set GENAI__APIKEY=your_genai_api_key_here
   set GENAI__APIURL=your_api_url_here
   set GENAI__MODEL=your_model_here
   set GOOGLEMAPS__APIKEY=your_googlemaps_api_key_here
   ```

   On Windows (PowerShell):

   ```bash
   $env:GENAI__APIKEY="your_genai_api_key_here"
   $env:GENAI__APIURL="your_api_url_here"
   $env:GENAI__MODEL="your_model_here"
   $env:GOOGLEMAPS__APIKEY="your_googlemaps_api_key_here"
   ```

### Build and Run Locally

```bash
dotnet build
dotnet run --project src/TravelAdvisor.Web
```

The application will be available at https://localhost:5001

## Deployment to Tanzu Platform for Cloud Foundry

### Preparation

1. Log in to your Cloud Foundry environment:

   ```bash
   cf login -a API_ENDPOINT
   ```

2. Create a GenAI service instance:

   ```bash
   cf create-service genai standard travel-advisor-llm
   ```

### Deployment

1. Publish the application:

   ```bash
   dotnet publish src/TravelAdvisor.Web -c Release -o publish
   ```

2. Push the application:

   ```bash
   cf push -f manifest.yml
   ```

3. Bind the service to the application:

   ```bash
   cf bind-service travel-advisor travel-advisor-llm
   ```

4. Restart the application to apply the binding:

   ```bash
   cf restart travel-advisor
   ```

## Implementation Details

This application demonstrates:

1. Integration with Microsoft.Extensions.AI for AI capabilities
2. Use of Semantic Kernel for natural language processing
3. Blazor for the web interface
4. Integration with Google Maps API
5. Service binding for GenAI on Tanzu Platform for Cloud Foundry

## Key Components

### TravelAdvisorService

The core service that processes natural language queries, generates recommendations based on transportation mode, and provides explanations for the recommendations.

### GoogleMapsService

Integrates with the Google Maps API to calculate distances, durations, and provide travel steps for different transportation modes.

### PromptFactory

Manages prompts for the LLM, allowing for templated prompts with parameter injection.

## References

* [Install .NET on Windows, Linux, and macOS](https://learn.microsoft.com/en-us/dotnet/core/install/)
* [Microsoft.Extensions.AI Documentation](https://devblogs.microsoft.com/dotnet/introducing-microsoft-extensions-ai-preview/)
* [Semantic Kernel Documentation](https://learn.microsoft.com/en-us/semantic-kernel/)
* [Blazor Documentation](https://dotnet.microsoft.com/apps/aspnet/web-apps/blazor)
* [Google Maps API Documentation](https://developers.google.com/maps/documentation)
* [Tanzu Platform Documentation](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform)
