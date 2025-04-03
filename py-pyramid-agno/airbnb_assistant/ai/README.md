# Airbnb Assistant AI Implementation

This directory contains the AI implementation for the Airbnb Assistant using the Agno framework. The implementation follows the best practices and patterns as described in the Agno documentation.

## Environment Variables

The agent supports various environment variables to configure LLM providers:

- `GENAI_API_KEY`: API key for the GenAI provider (overrides the api_key parameter)
- `GENAI_MODEL`: Model ID to use (overrides the model parameter)
- `GENAI_API_URL`: API URL for the GenAI provider (overrides the api_url parameter)
- `GENAI_PROVIDER`: Provider type (openai, azure, anthropic, etc.)

Additionally, if `GENAI_API_KEY` is not set, the agent will look for a provider-specific API key based on the detected provider (e.g. `AZURE_API_KEY`, `ANTHROPIC_API_KEY`, etc.).

## Architecture

The AI implementation consists of three main components:

1. **Agent**: The main entry point that configures and manages the Agno agent.
2. **Tools**: Custom tools that provide Airbnb-specific functionality to the agent.
3. **MCP Client**: A client for interacting with the Model Context Protocol (MCP) server for external data.

## Key Components

### `agent.py`

The `AirbnbAssistantAgent` class provides a wrapper around the Agno Agent with Airbnb-specific configuration:

- Creates and configures an Agno agent with appropriate model and tools
- Processes user queries and returns structured responses
- Provides fallback mechanisms for when the agent is not available
- Supports multiple GenAI providers through OpenAI-compatible APIs using environment variables
- Handles different response types from Agno (string or RunResponse objects)
- Correctly handles MCP integration through the mcp_url parameter
- Uses proper Agno patterns with the `run()` method to get responses

### `tools.py`

The `AirbnbToolkit` class extends Agno's `Toolkit` class to provide custom Airbnb-specific tools:

- `search_listings`: Search for Airbnb listings based on location and dates
- `get_listing_details`: Get detailed information about a specific listing

The toolkit accepts an optional MCPAirbnbClient in its constructor and properly registers tool functions using the `register` method as recommended in the Agno documentation.

### `mcp/client.py`

The `MCPAirbnbClient` class provides a client for interacting with the MCP Airbnb server:

- Communicates with the MCP server via REST API
- Provides mock data for development when the MCP server is not available
- Implements proper error handling and fallback mechanisms

## Usage Example

```python
from airbnb_assistant.ai.agent import AirbnbAssistantAgent

# Create an agent with your API key
agent = AirbnbAssistantAgent(api_key="your_api_key_here")

# Process a user query
response = agent.process_query("I'm looking for a place to stay in San Francisco for 2 nights")

# Get the response text
print(response["response"])

# Using environment variables for different providers
# Example 1: Using OpenAI
# export GENAI_API_KEY="sk-..." 
# export GENAI_PROVIDER="openai"

# Example 2: Using Azure OpenAI
# export GENAI_API_KEY="..." 
# export GENAI_PROVIDER="azure"
# export GENAI_API_URL="https://your-resource.openai.azure.com/"
```

## Implementation Notes

This implementation follows the Agno best practices:

1. **Toolkit-based Approach**: Instead of directly using functions as tools, we use a proper Toolkit class that registers functions.

2. **Structured Agent Configuration**: The agent configuration uses a clear description and instructions list.

3. **Modern Response Handling**: Uses the `run()` method to get responses directly instead of capturing printed output.

4. **Error Handling**: Robust error handling at all levels of the implementation.

5. **Mock Data Support**: Provides mock data for development and testing when external services are not available.

6. **Multi-provider Support**: Supports multiple GenAI providers through OpenAI-compatible APIs with flexible configuration options.

## MCP Integration

The MCP (Model Context Protocol) integration allows the agent to access external data and functionality:

### HTTP Transport (Traditional Approach)

- The MCP client communicates with an MCP server over HTTP
- Requires the MCP server to be running and listening on a network port
- Configured via the `MCP_AIRBNB_URL` environment variable

### Stdio Transport (New Feature)

- Launches the MCP server as a subprocess and communicates through stdin/stdout
- No need for the MCP server to listen on a network port
- More secure as it doesn't expose the MCP server to the network
- Configured via:
  - `MCP_USE_STDIO=true` - Enables stdio transport
  - `MCP_SERVER_PATH` - Path to the MCP server script/executable

### Common Features

- Provides search and listing details functionality
- Includes fallback mechanisms to mock data when server communication fails
- Handles "Method not found" errors gracefully by falling back to mock data
- Provides informative error messages for debugging configuration issues

## Front-end Integration

The agent's responses are designed to work with the front-end implementation in `static/js/app.js`, which provides:

- JSON response formatting
- Listing card rendering
- Detailed listing view
- Interactive chat interface

## Troubleshooting

### Common Issues

1. **'RunResponse' object has no attribute 'lower'**
   - This error occurs when the Agno agent returns a RunResponse object instead of a string.
   - A fix has been implemented in `agent.py` to handle both string and RunResponse object returns.

2. **"Method not found" error from MCP server**
   - This error occurs when the MCP server doesn't support a method being called.
   - The code now gracefully handles this error and falls back to mock data.
   - Ensure you're using the correct MCP server implementation for your needs.

3. **API Key Issues**
   - If you're seeing authentication errors, check that you've set the correct API key for your provider.
   - For different providers, ensure you're using the correct environment variable (e.g., `GENAI_API_KEY` or provider-specific like `AZURE_API_KEY`).
   - The agent will provide informative error messages when API key validation fails.

4. **Provider Configuration**
   - When using non-OpenAI providers, make sure to set both `GENAI_PROVIDER` and the appropriate API URL if necessary.
   - The agent will attempt to detect the provider based on the API URL if `GENAI_PROVIDER` is not set.
