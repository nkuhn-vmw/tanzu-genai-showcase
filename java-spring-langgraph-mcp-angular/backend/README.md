# Event Recommendation Chatbot Backend

This is the Spring Boot backend for the Event Recommendation Chatbot application.

## Overview

This backend provides the API and AI intelligence for the event recommendation chatbot. It integrates with:

- Ticketmaster MCP server for event data
- API Ninjas Cities API for city information
- LangGraph4j for agent orchestration
- Spring AI for MCP client support

## Tech Stack

- Java 17
- Spring Boot 3.2
- Spring AI
- LangGraph4j
- Model Context Protocol (MCP)
- Spring WebFlux for reactive APIs

## Setup and Running

### Prerequisites

- Java 17 or higher
- Maven 3.8 or higher
- Ticketmaster API key
- API Ninjas API key
- (Optional) OpenAI API key (for direct LLM integration)

### Environment Variables

Set the following environment variables before running:

```
TICKETMASTER_API_KEY=your-ticketmaster-api-key
CITIES_API_KEY=your-api-ninjas-key
OPENAI_API_KEY=your-openai-api-key (optional)
```

### Running Locally

```bash
mvn spring-boot:run
```

The application will be available at http://localhost:8080

### Building

```bash
mvn clean package
```

This will create a JAR file in the `target` directory that includes both the backend and frontend.

## API Endpoints

### Chat Sessions

- `POST /api/chat/sessions` - Create a new chat session
- `GET /api/chat/sessions/{sessionId}` - Get an existing chat session

### Chat Messages

- `POST /api/chat/messages` - Send a message to the chatbot

## Architecture

### LangGraph Implementation

The chatbot uses LangGraph4j to orchestrate a stateful conversation flow:

1. `understand_user_intent` - Analyzes user messages to determine intent and extract city names
2. `check_for_city` - Looks up city information using the Cities API
3. `search_events` - Searches for events in the specified city using the Ticketmaster MCP server
4. `generate_response` - Generates a natural language response based on the conversation context

### MCP Integration

The application uses Spring AI's MCP client to communicate with the Ticketmaster MCP server. This integration allows the chatbot to query for events based on location, date, type, and other criteria.

## Deployment to Tanzu Platform for Cloud Foundry

Refer to the main README and the deployment script in the `scripts` directory for instructions on deploying to Tanzu Platform for Cloud Foundry.
