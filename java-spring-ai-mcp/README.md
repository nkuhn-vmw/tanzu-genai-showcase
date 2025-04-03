# Flight Tracker Chatbot

![Status](https://img.shields.io/badge/status-under%20development-darkred)

A flight tracking chatbot built with Spring Boot, Spring AI, Model Context Protocol (MCP), and Vaadin. This application integrates with the AviationStack API to provide real-time flight information through a natural language interface.

## Project Structure

This is a multi-module Maven project with the following components:

- **api**: Contains the AviationStack API client and models
- **server**: Provides the MCP server implementation with tools for flight data
- **client**: Implements the Vaadin-based web interface for the chatbot

## Prerequisites

- Java 21
- Maven 3.9+
- AviationStack API key (sign up at [aviationstack.com](https://aviationstack.com/))
- OpenAI API key (for the chatbot functionality)

## Building the Project

To build the project, run:

```bash
mvn clean install
```

## Running Locally

### API Module

The API module is a library and doesn't run standalone.

### Server Module

To run the MCP server:

```bash
cd server
export AVIATION_STACK_ACCESS_KEY=your-aviationstack-api-key
mvn spring-boot:run
```

The server will start on port 8080 with the MCP endpoint available at `/mcp/v1/sse`.

### Client Module

To run the Vaadin client:

```bash
cd client
export OPENAI_API_KEY=your-openai-api-key
mvn spring-boot:run
```

The client will start on port 8090. Open your browser and navigate to `http://localhost:8090` to access the chatbot interface.

## Deploying to Tanzu Platform for Cloud Foundry

### Requirements

- Cloud Foundry CLI installed and configured
- Access to a Tanzu Platform for Cloud Foundry instance
- GenAI tile installed with access to LLM services

### Server Deployment

1. Create a `manifest.yml` file in the server directory:

```yaml
---
applications:
- name: flight-tracker-mcp-server
  path: target/flight-tracker-server-0.0.1-SNAPSHOT.jar
  memory: 1G
  env:
    JAVA_OPTS: -XX:ReservedCodeCacheSize=32M -Xss512k
    JBP_CONFIG_OPEN_JDK_JRE: '{ jre: { version: 21.+ } }'
  services:
  - aviation-stack-service-credentials
```

2. Create a user-provided service for the AviationStack API:

```bash
cf create-user-provided-service aviation-stack-service-credentials -p '{"access_key":"your-aviationstack-api-key"}'
```

3. Deploy the server:

```bash
cd server
mvn package
cf push
```

### Client Deployment

1. Create a `manifest.yml` file in the client directory:

```yaml
---
applications:
- name: flight-tracker-client
  path: target/flight-tracker-client-0.0.1-SNAPSHOT.jar
  memory: 1G
  env:
    JAVA_OPTS: -XX:ReservedCodeCacheSize=32M -Xss512k
    JBP_CONFIG_OPEN_JDK_JRE: '{ jre: { version: 21.+ } }'
    SPRING_AI_MCP_CLIENT_SSE_CONNECTIONS_FLIGHT-TRACKER_URL: https://flight-tracker-mcp-server.apps.your-cf-domain.com/mcp/v1/sse
  services:
  - tracker-llm-service
```

2. Bind the client to the GenAI LLM service:

```bash
cf create-service genai PLAN_NAME tracker-llm-service
```

3. Deploy the client:

```bash
cd client
mvn package
cf push
```

4. Access the chatbot at the deployed URL.

## Usage Examples

Here are some example questions you can ask the chatbot:

- "What's the status of flight BA123?"
- "Show me flights from LAX to JFK today"
- "What airlines fly from Paris to London?"
- "Give me information about Heathrow Airport"
- "What are the routes operated by United Airlines?"

## Development

### Adding New Features

To add new tools to the MCP server, extend the `FlightService` class in the server module with additional methods annotated with `@Tool`.

### Configuration Options

Both the server and client modules have configuration properties that can be customized in their respective `application.properties` files or via environment variables.

## License

This project is licensed under the Apache License 2.0.
