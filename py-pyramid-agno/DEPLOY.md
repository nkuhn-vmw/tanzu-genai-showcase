# Detailed Setup and Deployment Guide

This guide provides comprehensive instructions for setting up, running, and deploying the Airbnb Assistant application built with Python Pyramid and Agno AI. It includes detailed steps, troubleshooting tips, and advanced configuration options not covered in the basic setup in the [README.md](README.md).

## Contents

- [Local Development Setup](#local-development-setup)
- [Running with a MCP Server](#running-with-a-mcp-server)
- [Deployment to Tanzu Platform for Cloud Foundry](#deployment-to-tanzu-platform-for-cloud-foundry)
- [Troubleshooting](#troubleshooting)
- [Advanced Configuration](#advanced-configuration)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)
- [Monitoring and Logging](#monitoring-and-logging)
- [References](#references)

## Local Development Setup

> **Note:** For a quicker setup, use the convenience scripts provided in the project root: `setup_env.sh` (Linux/macOS) or `setup_env.ps1` (Windows).

### Prerequisites

- Python 3.8+ (recommended Python 3.10 or higher)
- pip (latest version)
- Git
- A text editor or IDE
- OpenAI API key or access to another GenAI provider

### Step 1: Clone the Repository (if you haven't already)

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
cd tanzu-genai-showcase/py-pyramid-agno
```

### Step 2: Set Up a Virtual Environment

```bash
# Create a virtual environment
python3 -m venv venv

# Activate the virtual environment
# On Linux/macOS:
source venv/bin/activate
# On Windows:
venv\Scripts\activate.bat
```

### Step 3: Install Dependencies

```bash
# Upgrade pip
pip3 install --upgrade pip

# Install the application in development mode
pip3 install -e .
```

### Step 4: Configure Environment Variables

Create a `.env` file in the project root with the following variables:

```
# LLM API Configuration
OPENAI_API_KEY=your_api_key_here
GENAI_API_KEY=your_api_key_here
GENAI_MODEL=gpt-4o
GENAI_API_URL=https://api.openai.com/v1

# MCP Configuration - HTTP Transport
MCP_AIRBNB_URL=http://localhost:3000

# MCP Configuration - Stdio Transport
MCP_USE_STDIO=false
MCP_SERVER_PATH=/path/to/mcp-server-airbnb/dist/index.js

# Development Settings
USE_MOCK_DATA=true
DEBUG=true
```

### Step 5: Initialize the Database

For first-time setup, initialize the SQLite database:

```bash
# Initialize the database
initialize_db development.ini
```

This creates the required tables and adds some initial data.

### Step 6: Run the Development Server

```bash
# Using Pyramid's development server with auto-reload
pserve development.ini --reload

# Or using the waitress production server
python -m waitress --port=8080 airbnb_assistant:run_app
```

The application will be available at http://localhost:8080

### Step 7: Test the Application

1. Open your browser and navigate to http://localhost:8080
2. Try the chatbot with a query like "Find me a place to stay in San Francisco"
3. Test the theme toggle button to switch between light and dark mode
4. Click on listing cards to view details

## Running with a MCP Server

The MCP server repository should be located underneath the `py-pyramid-agno` project directory.

```
tanzu-genai-showcase/
└── py-pyramid-agno/
    └── mcp-server-airbnb/
```

> [!NOTE]
> The MCP server does not reside in the `tanzu-genai-showcase` repository.

You will have to clone it:

```bash
# Clone the MCP server repository so that it is a child directory of py-pyramid-agno
git clone https://github.com/openbnb-org/mcp-server-airbnb.git
```

If you want to use a MCP Server instead of mock data, you have two options:

### Option 1: Using HTTP Transport

This approach requires running the MCP server as a separate process that listens on a network port.

Update your `.env` file to use the HTTP transport:

```
# MCP Configuration - HTTP Transport
MCP_AIRBNB_URL=http://localhost:3000
MCP_USE_STDIO=false
USE_MOCK_DATA=false
```

> [!IMPORTANT]
> You will need to update the value of `MCP_AIRBNB_URL` for your purposes.

### Option 2: Using Stdio Transport (Recommended)

This approach launches the MCP server as a subprocess and communicates through stdin/stdout pipes, eliminating the need for a network port.

Update your `.env` file to use the stdio transport:

```
# MCP Configuration - Stdio Transport
MCP_USE_STDIO=true
MCP_SERVER_PATH=/path/to/tanzu-genai-showcase/py-pyramid-agno/mcp-server-airbnb/dist/index.js
USE_MOCK_DATA=false
```

> [!IMPORTANT]
> Replace `/path/to/tanzu-genai-showcase/py-pyramid-agno` above with the actual path

With this configuration, the application will launch it automatically as a subprocess when needed.

## Deployment to Tanzu Platform for Cloud Foundry

### Requirements

- Cloud Foundry CLI installed
- Access to a Tanzu Platform for Cloud Foundry environment
- Access to the GenAI tile in the CF marketplace

### Step 1: Login to Cloud Foundry

```bash
cf login -a API_ENDPOINT -u YOUR_USERNAME -p YOUR_PASSWORD -o YOUR_ORG -s YOUR_SPACE
```

### Step 2: Create a GenAI Service Instance

```bash
# List available service plans
cf marketplace -s genai

# Create a service instance
cf create-service genai PLAN_NAME airbnb-assistant-llm
```

> [!IMPORTANT]
> Replace `PLAN_NAME` above with an available service offering plan name

### Step 3: Review and Update the Manifest

Check that your `manifest.yml` file includes:

```yaml
---
applications:
- name: py-pyramid-agno
  memory: 512M
  instances: 1
  buildpacks:
    - python_buildpack
  command: python -m waitress --port=$PORT airbnb_assistant:run_app
  env:
    PYTHONUNBUFFERED: true
    GENAI_SERVICE_NAME: airbnb-assistant-llm
    USE_MOCK_DATA: false
    MCP_USE_STDIO: true
    MCP_SERVER_PATH: ./mcp-server-airbnb/dist/index.js
```

### Step 4: Deploy the Application

```bash
# Deploy the application
cf push -f manifest.yml

# If services were not specified in manifest.yml, bind them manually
cf bind-service py-pyramid-agno airbnb-assistant-llm

# Restart the application after binding
cf restart py-pyramid-agno
```

### Step 5: Check the Application Status

```bash
# View application status
cf app py-pyramid-agno

# View recent logs
cf logs py-pyramid-agno --recent
```

### Step 6: Access the Application

Navigate to the URL provided by Cloud Foundry after deployment.

## Setting Up MCP Integration in Production

For production environments where you want to use a real MCP server:

### Option 1: Deploy Your Own MCP Server

1. Deploy the MCP server as a separate CF application
2. Bind the applications or set environment variables to configure the integration
3. Update the `MCP_AIRBNB_URL` environment variable in your application to point to the deployed MCP server

### Option 2: Use a Managed MCP Service

If a managed MCP service is available:

1. Create an instance of the MCP service
2. Bind it to your application
3. Update your application to use the service binding credentials

## Troubleshooting

### Common Local Development Issues

#### Missing Dependencies

If you encounter missing dependency errors:

```bash
pip install -e .
```

#### Database Errors

If you encounter database errors:

```bash
# Remove existing database
rm airbnb_assistant.db

# Recreate the database
initialize_db development.ini
```

#### Python Version Issues

The application requires Python 3.8+. If you encounter compatibility issues:

```bash
# Check your Python version
python --version
```

#### LLM API Key Issues

If you encounter errors connecting to the LLM provider:

1. Check that your API key is correct in the `.env` file
2. Verify that the API is accessible from your environment
3. Try using a different model
4. Check your LLM provider's quota limits

### Cloud Foundry Deployment Issues

#### Buildpack Detection Failures

If the Python buildpack is not detecting your application:

1. Make sure you have a `requirements.txt` file at the root of your application
2. Verify that your `setup.py` is correctly configured

#### Service Binding Issues

If the application can't connect to the GenAI service:

1. Check that the service is properly created and bound
2. Verify that the service name matches what's specified in the application
3. Check the logs for binding errors

```bash
cf services
cf service airbnb-assistant-llm
```

#### Environment Variable Issues

If environment variables aren't being properly set:

```bash
# Set them manually
cf set-env py-pyramid-agno GENAI_SERVICE_NAME airbnb-assistant-llm

# After setting environment variables, restart the application
cf restart py-pyramid-agno
```

## Advanced Configuration

### Configuring for Different LLM Providers

The application supports different OpenAI-compatible LLM providers by changing the API URL and model name:

#### OpenAI Configuration

```
GENAI_API_KEY=your_openai_api_key
GENAI_MODEL=gpt-4o
GENAI_API_URL=https://api.openai.com/v1
```

#### Azure OpenAI Configuration

```
GENAI_API_KEY=your_azure_api_key
GENAI_MODEL=your_deployed_model_name
GENAI_API_URL=https://your-resource.openai.azure.com/openai/deployments/your_deployment
```

#### OpenRouter Configuration

```
GENAI_API_KEY=your_openrouter_api_key
GENAI_MODEL=openai/gpt-4o-mini
GENAI_API_URL=https://openrouter.ai/api/v1
```

### Multi-Environment Configuration

For supporting multiple environments (dev, test, prod):

1. Create separate configuration files:
   - `development.ini` (already exists)
   - `testing.ini`
   - `production.ini`

2. Customize each file for its environment

3. Use the appropriate file when starting the server:

```bash
# Development
pserve development.ini --reload

# Testing
pserve testing.ini

# Production
pserve production.ini
```

## Security Considerations

### API Key Management

- Never commit API keys to your source code repository
- Use environment variables or service bindings for API keys
- For local development, use `.env` files and add them to `.gitignore`
- In Cloud Foundry, use service bindings or environment variables

### Input Validation

The application includes input validation, but you should be aware of:

- Potential for prompt injection attacks
- XSS vulnerabilities in the frontend
- SQL injection (mitigated by using SQLAlchemy with parameterized queries)

### Content Filtering

The application currently doesn't include content filtering. Consider:

- Adding content filters for user inputs
- Configuring content filtering on the LLM API side
- Implementing post-processing of LLM responses

## Performance Optimization

### Caching

Consider implementing caching to improve performance:

- Cache frequent LLM queries
- Cache MCP server responses
- Use Redis or Memcached for distributed caching in production

### Database Optimization

For production deployments:

- Use a proper database service instead of SQLite
- Set up connection pooling
- Consider adding indexes for frequently queried fields

## Monitoring and Logging

### Cloud Foundry Logging

Cloud Foundry provides built-in logging:

```bash
# Stream logs
cf logs py-pyramid-agno

# Get recent logs
cf logs py-pyramid-agno --recent
```

### Custom Logging

The application uses Python's logging framework. Log levels can be configured in the INI files.

## References

- [Agno Documentation](https://docs.agno.com)
- [Pyramid Documentation](https://docs.pylonsproject.org/projects/pyramid/en/latest/)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [Model Context Protocol Documentation](https://github.com/openbnb-org/mcp-server-airbnb)
- [Tanzu Platform Documentation](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform)
