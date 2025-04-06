# Neuron AI + Symfony Company Research Application

![Status](https://img.shields.io/badge/status-under%20development-darkred) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/php-symfony-neuron.yml/badge.svg)

This example demonstrates a company research application built with Symfony and Neuron AI that can be deployed to Tanzu Platform for Cloud Foundry and integrate with LLM services through the GenAI tile.

## Features

- Research companies by name, industry, or keyword
- Gather financial data (quarterly reports, 10K)
- Collect investor relations reports
- Identify company leadership
- Generate competitive profiles
- Provide industry segment analysis
- Export reports to PDF or Excel

## Architecture

The application consists of:

1. **Symfony Framework**: Handles HTTP requests, routing, and rendering
2. **Neuron AI Integration**: PHP library for LLM interactions
3. **Service Components**:
   - Financial Data Retriever: Gets financial information from public sources
   - Leadership Analysis: Identifies and profiles key executives
   - Competitive Intelligence: Maps competitor landscape
   - Report Generator: Creates structured research reports
4. **Cloud Foundry Deployment**: Configuration for Tanzu Platform deployment

## Prerequisites

- PHP 8.1+ and Composer
- Cloud Foundry CLI
- Access to Tanzu Platform for Cloud Foundry with GenAI tile installed

## Local Development

1. Clone the repository:
   ```
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/php-symfony-neuron
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Create a `.env.local` file with your API keys (for local development only):
   ```
   APP_ENV=dev
   APP_SECRET=your_symfony_app_secret
   DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db
   GENAI_API_KEY=your_GENAI_API_KEY_here
   GENAI_BASE_URL=optional_custom_endpoint
   GENAI_MODEL=gpt-4o-mini
   ```

4. Set up the database:
   ```
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:create
   ```

5. Start the development server:
   ```
   symfony server:start
   ```

6. Open your browser to `http://localhost:8000`

## Building for Production

```
composer install --optimize-autoloader --no-dev
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
```

## Deploying to Tanzu Platform for Cloud Foundry

1. Login to your Cloud Foundry instance:
   ```
   cf login -a API_ENDPOINT
   ```

2. Deploy the application:
   ```
   cf push
   ```

3. Bind to a GenAI service instance:
   ```
   cf create-service genai-service standard my-llm-service
   cf bind-service company-research my-llm-service
   cf restage company-research
   ```

## Service Binding

The application uses the following approach to consume service credentials:

1. When deployed to Cloud Foundry, it automatically detects VCAP_SERVICES environment variables
2. It extracts LLM service credentials from the bound service instance
3. The Neuron AI client is configured to use these credentials for LLM interactions

## Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [PHP Buildpack Documentation](https://docs.cloudfoundry.org/buildpacks/php/index.html)
