# Implementation Details

## Architecture

The Congress.gov Chatbot is built using a clean architecture approach with the following components:

1. **API Layer** - Handles communication with the Congress.gov API
2. **LLM Integration** - Uses LangChainGo to integrate with the GenAI LLM service
3. **Service Layer** - Orchestrates the interaction between the user, LLM, and Congress.gov API
4. **Handler Layer** - Manages HTTP requests/responses using Fiber
5. **Web Interface** - A simple HTML/CSS/JS frontend for the chatbot

## Components

### Congress.gov API Client (`api/congress_client.go`)

The API client provides a clean interface to the Congress.gov API endpoints:

- Search Bills
- Get Bill Details
- Get Bill Summaries
- Search Members
- Get Member Details
- Search Amendments

### LLM Client (`pkg/llm/llm.go`)

The LLM client wraps LangChainGo to provide a simplified interface for interacting with the GenAI LLM service:

- Managing conversation history
- Generating responses from the LLM
- Adding system, user, and assistant messages

### Chatbot Service (`internal/service/chatbot.go`)

The service layer implements the core logic of the chatbot:

1. User sends a query
2. LLM analyzes the query to determine which Congress.gov API to call
3. API call is made to fetch relevant information
4. LLM interprets the API response to generate a helpful answer
5. Response is returned to the user

### HTTP Handler (`internal/handler/handler.go`)

The handler layer manages HTTP requests and responses:

- `/api/chat` - Process user messages
- `/api/history` - Get conversation history
- `/api/clear` - Clear conversation history
- `/api/health` - Health check endpoint

### Web Interface

A simple single-page application using HTML, CSS, and JavaScript provides the user interface for the chatbot.

## Deployment

### Cloud Foundry Configuration

The application is configured to be deployed to Tanzu Platform for Cloud Foundry:

- `manifest.yml` defines the application configuration
- The application can be pushed using `cf push`
- It can be bound to a GenAI LLM service instance

### Service Binding

The application demonstrates two approaches to service binding:

1. **Automatic Service Binding**:
   - The application is bound to a service instance using `cf bind-service`
   - Service credentials are automatically injected via `VCAP_SERVICES`

2. **Manual Configuration**:
   - Service keys are created using `cf create-service-key`
   - The application is configured with environment variables

## Design Decisions

### Why Go + Fiber + LangChainGo?

- **Go**: Lightweight, efficient, and perfect for cloud-native applications
- **Fiber**: Fast, Express-like web framework with good middleware support
- **LangChainGo**: Provides a clean interface to various LLM providers

### Conversation Management

The application maintains conversation history to provide context to the LLM, enabling more coherent multi-turn conversations.

### Error Handling

Comprehensive error handling ensures that the application can recover from failures and provide helpful error messages to the user.

### Security

API keys and service credentials are securely handled through environment variables and service bindings.

## Future Improvements

- Add authentication for the API endpoints
- Implement streaming responses for a more interactive experience
- Add more advanced conversation capabilities
- Expand the range of Congress.gov API endpoints used
- Add better error handling and retry logic
- Implement unit and integration tests
