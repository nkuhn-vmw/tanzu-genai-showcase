# Deployment Guide

This document outlines deployment scenarios for the Neuron AI + Symfony Company Research Application, focusing on Cloud Foundry deployment options with the Tanzu Platform.

## Table of Contents

- [Deployment Prerequisites](#deployment-prerequisites)
- [Basic Deployment](#basic-deployment)
- [GenAI Service Integration](#genai-service-integration)
- [Database Service Integration](#database-service-integration)
- [High Availability Configuration](#high-availability-configuration)
- [Custom Domain Configuration](#custom-domain-configuration)
- [Scaling Considerations](#scaling-considerations)
- [Common Issues & Troubleshooting](#common-issues--troubleshooting)
- [Deployment Checklist](#deployment-checklist)

## Deployment Prerequisites

Before deploying the application, ensure you have:

- Cloud Foundry CLI installed (`cf` command available)
- Access to a Tanzu Platform for Cloud Foundry environment
- Access to the GenAI tile in your foundation
- Proper permissions to create and bind services
- The application code from the repository

Additional requirements:

- API keys for external services if not using bound services
- Understanding of your organization's network policies
- Knowledge of resource constraints in your environment

## Basic Deployment

### Preparing the Application

1. Clone the repository:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/php-symfony-neuron
   ```

2. Review and modify the `manifest.yml` file:

   ```yaml
   applications:
   - name: company-research
     memory: 512M
     disk_quota: 1G
     instances: 1
     buildpacks:
       - php_buildpack
     env:
       APP_ENV: prod
       APP_DEBUG: false
       LOG_LEVEL: info
   ```

3. Build for production:

   ```bash
   composer install --optimize-autoloader --no-dev
   APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
   APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
   ```

### Deploying to Cloud Foundry

1. Login to your Cloud Foundry instance:

   ```bash
   cf login -a API_ENDPOINT -o YOUR_ORG -s YOUR_SPACE
   ```

2. Push the application:

   ```bash
   cf push
   ```

3. Check the application status:

   ```bash
   cf app company-research
   ```

Example output:

```
Showing health and status for app company-research in org my-org / space dev-space as user@example.com...

name:              company-research
requested state:   started
routes:            company-research.apps.cf.example.com
last uploaded:     Thu 18 Apr 12:15:34 PDT 2025
stack:             cflinuxfs3
buildpacks:        php_buildpack

type:           web
instances:      1/1
memory usage:   512M
     state     since                  cpu    memory        disk       details
#0   running   2025-04-18T19:16:02Z   0.2%   112.6M of 512M   291.8M of 1G
```

### Configuring Environment Variables

For basic operation without service binding, you'll need to set API keys:

```bash
cf set-env company-research GENAI_API_KEY your_genai_api_key
cf set-env company-research FINANCIAL_API_KEY your_financial_api_key
cf restage company-research
```

## GenAI Service Integration

The application is designed to work with the GenAI tile, which provides LLM capabilities.

### Creating a GenAI Service Instance

1. Check available service plans:

   ```bash
   cf marketplace -e genai
   ```

2. Create a service instance:

   ```bash
   cf create-service genai PLAN_NAME company-research-llm
   ```

   Where `PLAN_NAME` is one of the available service plans.

3. Check the service creation status:

   ```bash
   cf service company-research-llm
   ```

### Binding the GenAI Service

1. Bind the service to your application:

   ```bash
   cf bind-service company-research company-research-llm
   ```

2. Restage the application to apply the binding:

   ```bash
   cf restage company-research
   ```

3. Verify the binding:

   ```bash
   cf env company-research
   ```

   Look for the `VCAP_SERVICES` section in the output to confirm the service binding.

### Understanding Service Binding

When bound to a GenAI service, the application:

1. Automatically detects the service credentials from `VCAP_SERVICES` environment variable
2. Extracts API key, base URL, and model name
3. Configures the NeuronAiService to use these credentials
4. No manual API key configuration is needed

Example `VCAP_SERVICES` structure:

```json
{
  "genai": [
    {
      "binding_name": null,
      "instance_name": "company-research-llm",
      "name": "company-research-llm",
      "label": "genai",
      "tags": ["ai", "llm"],
      "plan": "standard",
      "credentials": {
        "api_key": "sk-xxxxxxxxxxxxxxxxxxxx",
        "url": "https://api.genai.example.com/v1",
        "model": "gpt-4o-mini"
      }
    }
  ]
}
```

## Database Service Integration

By default, the application uses SQLite for local development. For production deployments, a managed database service is recommended.

### Creating a Database Service

1. Check available database services:

   ```bash
   cf marketplace -e mysql
   ```

2. Create a database service instance:

   ```bash
   cf create-service mysql PLAN_NAME company-research-db
   ```

3. Bind the database service:

   ```bash
   cf bind-service company-research company-research-db
   ```

4. Configure the application to use the database:

   ```bash
   cf set-env company-research DATABASE_URL_ENV_NAME VCAP_SERVICES_MYSQL_0_CREDENTIALS_URI
   cf restage company-research
   ```

### Database Migration

When switching to a new database service, you need to run migrations:

1. Open an SSH connection to the application:

   ```bash
   cf ssh company-research
   ```

2. Navigate to the application directory:

   ```bash
   cd app
   ```

3. Run migrations:

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

## High Availability Configuration

For production deployments, configure high availability to ensure application resilience.

### Increasing Instance Count

1. Update the manifest with multiple instances:

   ```yaml
   applications:
   - name: company-research
     memory: 512M
     instances: 3
     # other settings...
   ```

   Or use the CF CLI:

   ```bash
   cf scale company-research -i 3
   ```

### Session Management

When running multiple instances, ensure session persistence:

1. Configure Redis service for session storage:

   ```bash
   cf create-service redis PLAN_NAME company-research-sessions
   cf bind-service company-research company-research-sessions
   ```

2. Set environment variables for session configuration:

   ```bash
   cf set-env company-research APP_SESSION_HANDLER redis
   cf set-env company-research APP_SESSION_REDIS_SERVICE_NAME company-research-sessions
   cf restage company-research
   ```

### Health Checks

Configure custom health checks for better monitoring:

```bash
cf set-health-check company-research http --endpoint /health
cf set-health-check-timeout company-research 30
```

Create a health endpoint in your application by adding a controller route:

```php
#[Route('/health', name: 'app_health', methods: ['GET'])]
public function health(): Response
{
    return $this->json(['status' => 'healthy']);
}
```

## Custom Domain Configuration

To use a custom domain for your application:

1. Register the domain with your Cloud Foundry instance:

   ```bash
   cf create-domain ORG_NAME example.com
   ```

2. Map the route to your application:

   ```bash
   cf map-route company-research example.com --hostname research
   ```

   This would make your app available at `research.example.com`.

## Scaling Considerations

### Memory Sizing

The application memory requirements depend on several factors:

- **Base Symfony application**: ~128MB
- **Neuron AI processing**: +256MB
- **Number of concurrent users**: +64MB per 10 users

Recommended configurations:

- **Development/Testing**: 512MB
- **Production (low traffic)**: 1GB
- **Production (high traffic)**: 2GB+ with multiple instances

### Disk Space

Disk requirements:

- **Application code**: ~50MB
- **Dependencies**: ~150MB
- **Logs & temporary files**: ~200MB
- **SQLite database (if used)**: Variable (use managed DB service instead)

Recommended disk quota: 1GB minimum

### PHP Configuration

Optimize PHP configuration for production:

1. Increase memory limit:

   ```bash
   cf set-env company-research PHP_MEMORY_LIMIT 512M
   ```

2. Configure OPcache:

   ```bash
   cf set-env company-research PHP_INI_SCAN_DIR .bp-config/php/conf.d
   ```

   Create a file `.bp-config/php/conf.d/opcache.ini`:

   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.interned_strings_buffer=16
   opcache.max_accelerated_files=20000
   opcache.validate_timestamps=0
   ```

## Common Issues & Troubleshooting

### Application Crashes on Startup

**Symptoms**: Application crashes immediately after deployment

**Possible causes and solutions**:

1. **Missing environment variables**:

   Check logs for missing configuration:

   ```bash
   cf logs company-research --recent
   ```

   Solution: Add missing environment variables:

   ```bash
   cf set-env company-research MISSING_VARIABLE value
   cf restage company-research
   ```

2. **Database migration issues**:

   Check logs for migration errors:

   ```bash
   cf logs company-research --recent | grep -i migration
   ```

   Solution: Connect via SSH and run migrations manually:

   ```bash
   cf ssh company-research -c "cd app && php bin/console doctrine:migrations:migrate --no-interaction"
   ```

3. **Memory limits exceeded**:

   Check if the application is hitting memory limits:

   ```bash
   cf app company-research
   ```

   Solution: Increase memory allocation:

   ```bash
   cf scale company-research -m 1G
   ```

### GenAI Service Integration Issues

**Symptoms**: "Technical difficulties with the language model" error message

**Possible causes and solutions**:

1. **Incorrect service binding**:

   Verify service binding:

   ```bash
   cf services
   cf env company-research
   ```

   Solution: Rebind the service:

   ```bash
   cf unbind-service company-research company-research-llm
   cf bind-service company-research company-research-llm
   cf restage company-research
   ```

2. **Incompatible API format**:

   Check if the GenAI service provides an OpenAI-compatible API.

   Solution: Set a custom base URL if needed:

   ```bash
   cf set-env company-research GENAI_BASE_URL custom_endpoint_url
   cf restage company-research
   ```

### Network Connectivity Issues

**Symptoms**: Application unable to reach external APIs

**Possible causes and solutions**:

1. **Outbound network restrictions**:

   Check if outbound network requests are blocked by network policies.

   Solution: Configure network policies to allow outbound connections:

   ```bash
   cf add-network-policy company-research --destination-app internet --protocol tcp --port 443
   ```

2. **API rate limiting**:

   Check logs for rate limit errors from financial APIs.

   Solution: Implement request throttling or upgrade API plans.

## Deployment Checklist

Use this checklist before deployment to production:

### Pre-Deployment Checklist

- [ ] Configure all required API keys
- [ ] Set `APP_ENV=prod` in environment
- [ ] Set `APP_DEBUG=false` in environment
- [ ] Set appropriate logging configuration
- [ ] Run cache warming: `APP_ENV=prod php bin/console cache:warmup`
- [ ] Install dependencies without dev packages: `composer install --optimize-autoloader --no-dev`
- [ ] Review memory and disk allocations in manifest.yml
- [ ] Ensure proper buildpack configuration

### Post-Deployment Verification

- [ ] Verify application is running (`cf app company-research`)
- [ ] Check logs for startup errors (`cf logs company-research --recent`)
- [ ] Test basic application functionality via browser
- [ ] Verify service bindings are working properly
- [ ] Test company search functionality
- [ ] Test AI-enhanced features
- [ ] Monitor resource usage during testing

### Security Checklist

- [ ] Ensure no API keys are hardcoded in source code
- [ ] Verify service binding credentials are properly secured
- [ ] Check that DEBUG mode is disabled in production
- [ ] Verify CSRF protection is enabled
- [ ] Ensure sensitive environment variables are properly set
- [ ] Check that database connections are secured (if applicable)
