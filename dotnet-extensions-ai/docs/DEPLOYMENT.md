# Tanzu Platform for Cloud Foundry Deployment Guide

This guide provides detailed instructions for deploying the Transportation Recommendation Bot to VMware Tanzu Platform for Cloud Foundry (TAS).

## Prerequisites

Before you begin, ensure you have:

1. **Cloud Foundry CLI** installed ([CF CLI Installation Guide](https://docs.cloudfoundry.org/cf-cli/install-go-cli.html))
2. **Access credentials** for your Tanzu Application Service environment
3. **Sufficient rights** to create service instances and push applications
4. **.NET 9 SDK** installed for local builds

## Step 1: Build the Application

First, build and publish the application for deployment:

```bash
# Navigate to the project directory
cd tanzu-genai-showcase/dotnet-extensions-ai

# Restore dependencies
dotnet restore

# Publish the application
dotnet publish src/TravelAdvisor.Web -c Release -o publish
```

## Step 2: Prepare Environment Variables

The application requires certain environment variables to function properly. You have two options:

### Option A: Set in manifest.yml (Recommended for non-sensitive values)

Edit the `manifest.yml` file to include environment variables:

```yaml
---
applications:
- name: travel-advisor
  path: publish
  memory: 512M
  buildpacks:
    - dotnet_core_buildpack
  env:
    ASPNETCORE_ENVIRONMENT: Production
    DOTNET_CLI_TELEMETRY_OPTOUT: 1
    DOTNET_SKIP_FIRST_TIME_EXPERIENCE: true
    DOTNET_NOLOGO: true
    # Add other non-sensitive environment variables here
  services:
    - travel-advisor-llm
```

### Option B: Use cf set-env (Recommended for sensitive values)

After deployment, use the CLI to set sensitive variables:

```bash
cf set-env travel-advisor GOOGLEMAPS__APIKEY your_google_maps_api_key
```

## Step 3: Create Required Services

The application uses several services that need to be created:

### GenAI LLM Service

```bash
# Create a GenAI service instance
cf create-service genai standard travel-advisor-llm

# Alternatively, if you need to provide configuration
cf create-user-provided-service travel-advisor-llm -p '{
  "api_key":"your_api_key",
  "api_url":"https://api.openai.com/v1",
  "model":"gpt-4o-mini"
}'
```

## Step 4: Deploy the Application

Now deploy the application to your Tanzu environment:

```bash
# Log in to your CF environment
cf login -a API_ENDPOINT -u YOUR_USERNAME -p YOUR_PASSWORD -o YOUR_ORG -s YOUR_SPACE

# Push the application using the manifest
cf push -f manifest.yml
```

## Step 5: Bind Services

If your services weren't specified in the manifest, bind them now:

```bash
cf bind-service travel-advisor travel-advisor-llm
cf restart travel-advisor
```

## Step 6: Verify Deployment

Verify your application is running correctly:

```bash
# Check the application status
cf app travel-advisor

# View logs
cf logs travel-advisor --recent
```

Visit the application URL to test its functionality.

## Troubleshooting

### Application Crashes on Startup

Check the logs for error messages:

```bash
cf logs travel-advisor --recent
```

Common issues include:

1. **Missing environment variables**
   - Solution: Set required environment variables using `cf set-env`

2. **Service binding issues**
   - Solution: Verify service instances exist and are bound correctly:

     ```bash
     cf services
     ```

3. **Memory limitations**
   - Solution: Increase memory allocation in manifest.yml

     ```yaml
     memory: 1G
     ```

4. **Missing permissions**
   - Solution: Ensure your CF user has sufficient permissions

### API Key Issues

If the application cannot connect to external APIs:

1. Verify the API keys are set correctly
2. Check if your Tanzu environment allows outbound connections to these APIs
3. Set up network policies if required

## Advanced Configuration

### Scaling

To handle increased load, you can scale the application:

```bash
# Vertical scaling (increase memory)
cf scale travel-advisor -m 1G

# Horizontal scaling (increase instances)
cf scale travel-advisor -i 3
```

### Route Configuration

To use a custom domain:

```bash
# Map a custom route
cf map-route travel-advisor your-domain.com --hostname travel-advisor
```

### Health Monitoring

The application includes health endpoints via Steeltoe. You can monitor:

```bash
# Get the application route
cf app travel-advisor

# Access health endpoint
curl https://travel-advisor.your-domain.com/actuator/health
```

## Multiple Environment Deployment

For organizations with development, test, and production environments:

1. **Create environment-specific manifests**:
   - `manifest-dev.yml`
   - `manifest-test.yml`
   - `manifest-prod.yml`

2. **Deploy to specific environment**:

   ```bash
   cf push -f manifest-dev.yml
   ```

3. **Use different service instances**:
   - Configure different service bindings per environment
   - Create environment-specific service instances

## Continuous Integration/Deployment

For CI/CD pipelines:

1. **Build**:

   ```bash
   dotnet restore
   dotnet build
   dotnet test
   dotnet publish -c Release -o publish
   ```

2. **Deploy**:

   ```bash
   cf login -a $CF_API -u $CF_USERNAME -p $CF_PASSWORD -o $CF_ORG -s $CF_SPACE
   cf push -f manifest.yml
   ```

## Conclusion

This deployment guide provides the steps necessary to deploy the Transportation Recommendation Bot to Tanzu Platform for Cloud Foundry. For more information on Tanzu Application Service features and capabilities, refer to the [official documentation](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform/).

For application-specific questions or issues, refer to the project's README.md file or submit an issue in the project repository.
