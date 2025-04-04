# Cloud Foundry Deployment Guide

This document provides detailed instructions for deploying the News Aggregator application to Cloud Foundry and troubleshooting common issues.

## Deployment Architecture

The News Aggregator is deployed as a **single application** that includes both the Express backend and React frontend. The Express server serves the static React build files and also handles API requests.

```
+------------------------+        +------------------------+
|                        |        |                        |
|    Cloud Foundry App   |<------>|   External Services    |
|                        |        |                        |
+------------------------+        +------------------------+
          |                                 |
          v                                 v
+------------------------+        +------------------------+
|                        |        |                        |
|  - Express Backend     |        |  - News API            |
|  - React Frontend      |        |  - GenAI LLM Service   |
|  (served as static)    |        |                        |
|                        |        |                        |
+------------------------+        +------------------------+
```

## Prerequisites

- Cloud Foundry CLI installed and configured
- Access to a Cloud Foundry environment with the GenAI tile
- News API key (register at [newsapi.org](https://newsapi.org))

## Fixed Issues

The following issues have been addressed in this deployment:

1. **Frontend-Backend Communication**:
   - Changed API calls to use relative URLs instead of hardcoded localhost
   - Enables proper communication in both local and CF environments

2. **Memory Optimization**:
   - Increased memory allocation from 256MB to 512MB
   - Added NODE_OPTIONS environment variable to optimize memory usage

3. **Build Process**:
   - The React app is built on the server during application startup
   - This approach eliminates the need for a local build step before deployment
   - Increased memory allocation to accommodate the build process

4. **Environment Variables**:
   - Proper handling of NEWS_API_KEY in the manifest
   - More robust error handling for missing environment variables

5. **Node.js Version**:
   - Fixed semver warning by specifying a concrete Node.js version

## Deployment Options

### Option 1: Using the Deployment Script (Recommended)

We've created a deployment script that automates the entire process:

1. Make the script executable if it's not already:

   ```bash
   chmod +x deploy.sh
   ```

2. Set your News API key as an environment variable:

   ```bash
   export NEWS_API_KEY=your_news_api_key
   ```

3. Run the deployment script:

   ```bash
   ./deploy.sh
   ```

4. Follow the prompts to complete the deployment.

### Option 2: Manual Deployment

If you prefer to deploy manually:

1. Generate the manifest.yml file:

   ```bash
   export NEWS_API_KEY=your_news_api_key
   envsubst '${NEWS_API_KEY}' < manifest.template > manifest.yml
   ```

2. Push the application without starting it:

   ```bash
   cf push --no-start
   ```

3. Check if you need to create and bind the GenAI service:

   ```bash
   # Create service if needed
   cf create-service genai YOUR_PLAN_NAME news-aggregator-llm

   # Bind to application
   cf bind-service news-aggregator news-aggregator-llm
   ```

4. Start the application:

   ```bash
   cf start news-aggregator
   ```

## Troubleshooting

### Search Functionality Not Working

If the search functionality doesn't work:

1. **Check the NEWS_API_KEY**:
   - Verify that the NEWS_API_KEY is correctly set in your manifest.yml
   - Check if the API key is valid and not expired

2. **Examine the logs**:

   ```bash
   cf logs news-aggregator --recent
   ```

3. **Verify service binding**:
   - Ensure the GenAI service is properly bound

   ```bash
   cf services
   ```

### Memory Issues

If you're experiencing memory-related crashes:

1. Increase the memory allocation in manifest.yml:

   ```yaml
   memory: 768M  # Try a higher value
   ```

2. Adjust the NODE_OPTIONS value:

   ```yaml
   NODE_OPTIONS: --max_old_space_size=700  # About 90% of your memory setting
   ```

3. Consider optimizing your code to reduce memory usage.

### Application Crashing

If the application crashes during startup:

1. Check for buildpack-related issues:
   - Ensure you're using the right buildpack version
   - Consider pinning the buildpack version if needed

2. Verify your Node.js version compatibility:
   - The app requires Node.js 18.0.0 or higher
   - You can specify a compatible version in manifest.yml:

   ```yaml
   env:
     NODE_VERSION: 18.18.0
   ```

## Best Practices

1. **Always Use Relative URLs** in frontend code for API calls to ensure they work in all environments

2. **Use Environment Variables** for configuration instead of hardcoding values

3. **Memory Management**:
   - Monitor application memory usage
   - Adjust allocations as needed
   - Use NODE_OPTIONS to optimize garbage collection

4. **Secure Secrets**:
   - Avoid committing API keys to your repository
   - Use user-provided services or environment variables for secrets

5. **Log Management**:
   - Add proper logging to help diagnose issues
   - Use cf logs to monitor application behavior

6. **Build Optimization**:
   - Consider using the Cloud Foundry Node.js buildpack's built-in support for npm scripts
   - Use NODE_ENV=production to enable optimizations

## Additional Resources

- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [Node.js Buildpack Documentation](https://docs.cloudfoundry.org/buildpacks/node/index.html)
- [Cloud Foundry Node.js Tips](https://docs.cloudfoundry.org/buildpacks/node/node-tips.html)
