# Transportation Recommendation Bot: Implementation Guide

This guide explains the architecture, components, and implementation details of the Transportation Recommendation Bot developed using .NET 9 with Microsoft.Extensions.AI, Semantic Kernel, and Google Maps API integration.

## Architecture Overview

The application follows a three-tier architecture pattern:

1. **Presentation Layer** (TravelAdvisor.Web): Blazor-based web interface that allows users to interact with the application.
2. **Business Logic Layer** (TravelAdvisor.Core): Contains core business logic, models, and service interfaces.
3. **Infrastructure Layer** (TravelAdvisor.Infrastructure): Implements service interfaces, handles external API integrations, and provides dependency injection.

The application also follows the dependency inversion principle, with higher layers depending on abstractions (interfaces) rather than concrete implementations.

## Key Components

### Core Components

#### TravelQuery Model

Represents a user's transportation query, including:

- Origin and destination locations
- Travel time preferences (departure/arrival times)
- Transportation preferences (modes, priorities, constraints)

#### TravelRecommendation Model

Represents a recommendation for a specific transportation mode, including:

- Mode (walk, bike, bus, car, train, plane)
- Distance and duration
- Cost estimate
- Environmental and convenience scores
- Pros and cons
- Detailed journey steps

#### Service Interfaces

- `IMapService`: Interface for mapping and distance services
- `ITravelAdvisorService`: Interface for generating travel recommendations
- `IPromptFactory`: Interface for creating AI prompts

### Infrastructure Components

#### GoogleMapsService

Implements the `IMapService` interface using the Google Maps API:

- Calculates distances and durations between locations
- Provides detailed journey steps
- Determines feasibility of transportation modes based on distance

#### TravelAdvisorService

Implements the `ITravelAdvisorService` interface using Microsoft.Extensions.AI and Semantic Kernel:

- Processes natural language queries to extract structured data
- Generates recommendations for different transportation modes
- Calculates scores based on user preferences
- Generates natural language explanations for recommendations
- Answers follow-up questions about recommendations

#### PromptFactory

Implements the `IPromptFactory` interface to create and manage AI prompts:

- Creates prompts with parameter placeholders
- Replaces parameters with actual values
- Submits prompts to the LLM and returns responses

### Web Interface

The web interface is built using Blazor and consists of the following pages:

- `Index.razor`: Landing page
- `Advisor.razor`: Main transportation advisor interface
- `About.razor`: Information about the application

The main advisor page allows users to:

1. Enter a natural language query about transportation options
2. View and compare recommendations for different transportation modes
3. See detailed information about each recommendation
4. Ask follow-up questions about specific recommendations

## AI Integration

### Microsoft.Extensions.AI

The application uses Microsoft.Extensions.AI for AI service integration:

- Provides abstractions for chat completion services
- Simplifies interaction with different AI providers (Azure OpenAI, OpenAI)
- Includes middleware for caching, logging, and other cross-cutting concerns

Implementation steps:

1. Register IChatClient in dependency injection
2. Configure client with API key and endpoint
3. Use client to send prompts and receive responses

### Semantic Kernel

Semantic Kernel is used for AI orchestration and prompt management:

- Manages conversation context
- Provides utilities for prompt engineering
- Supports plugins and function calling

## Data Flow

1. User enters a natural language query about transportation options
2. The query is processed by TravelAdvisorService using the LLM to extract structured data
3. The service uses GoogleMapsService to calculate distances, durations, and steps for each feasible transportation mode
4. Scores are calculated for each mode based on environmental impact, convenience, and user preferences
5. The service generates recommendations and sorts them by overall score
6. The UI displays the recommendations, allowing the user to view details and compare options
7. The user can select a recommendation to see a detailed explanation
8. The user can ask follow-up questions about the recommendation

## Integration with Cloud Foundry

The application is designed to be deployed on Tanzu Platform for Cloud Foundry:

- Uses Steeltoe for service binding and configuration
- Supports binding to GenAI services for LLM access
- Includes health monitoring endpoints for platform integration

Configuration steps:

1. Create a GenAI service instance in Cloud Foundry
2. Bind the service to the application
3. Configure environment variables or service bindings for Google Maps API access

## API Key Management

The application supports multiple methods for API key management:

1. Environment variables
2. .env file
3. User secrets
4. Cloud Foundry service bindings

For production deployments, it's recommended to use service bindings rather than environment variables for better security.

## Extension Points

The application is designed to be extensible in several ways:

1. Support for additional AI providers by implementing IChatClient
2. Support for additional mapping services by implementing IMapService
3. Additional transportation modes by extending the TransportMode enum
4. Enhanced scoring algorithms by modifying the calculation methods

## Testing

The application includes test cases for key components:

- Unit tests for service implementations
- Integration tests for API interactions
- End-to-end tests for the complete workflow

## Best Practices

The implementation follows several best practices:

1. Dependency injection for loose coupling
2. Interface-based design for testability
3. Separation of concerns with clear responsibility boundaries
4. Error handling and fallback mechanisms
5. Logging and telemetry for monitoring
6. Configuration management via environment variables and service bindings

## Usage Guide

### Local Development

1. Set up API keys for OpenAI/Azure OpenAI and Google Maps
2. Configure environment variables or .env file
3. Run the application using `dotnet run`
4. Access the web interface at https://localhost:5001

### Cloud Foundry Deployment

1. Create necessary service instances
2. Update manifest.yml if needed
3. Build and publish the application
4. Push to Cloud Foundry
5. Bind services
6. Access the application at the provided URL

## Troubleshooting

Common issues and solutions:

1. API key authentication failures: Verify keys and endpoints
2. Service binding issues: Check service instance names and types
3. Google Maps API limitations: Be aware of rate limits and billing
4. LLM response parsing errors: Validate prompt formats and expectations

## Conclusion

This implementation demonstrates how to build a comprehensive AI-powered application using .NET 9, Microsoft.Extensions.AI, Semantic Kernel, and Google Maps API. The application showcases best practices for AI integration, external service consumption, and Cloud Foundry deployment.

By following this architecture and implementation approach, you can create similar AI-powered applications that leverage the power of large language models while maintaining a clean, modular, and maintainable codebase.