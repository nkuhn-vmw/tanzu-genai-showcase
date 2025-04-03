# Frequently Asked Questions

## Model Context Protocol (MCP)

### What is the Model Context Protocol (MCP)?

MCP is a standardized way for AI models to interact with external tools and resources. It defines a structured communication protocol that allows AI models like Claude to call functions in your application, access data, and maintain context during interactions.

### What's the difference between an MCP server and an MCP client?

- **MCP Server**: This is what our application implements. It exposes tools and resources that AI models can use. Our server defines tools like `FlightSearchTool`, `FlightStatusTool`, etc., which Claude can call when a user asks flight-related questions.

- **MCP Client**: This is the component that connects to an MCP server and manages the conversation with it. Claude Desktop is an example of an MCP client. It sends user messages to MCP servers and displays the responses.

### How does our application work with Claude locally?

When running locally, our application starts an MCP server that Claude can connect to. Users interact with Claude directly (through Claude Desktop or another interface), and Claude calls our application's tools and APIs as needed to answer flight-related questions.

## Deployment Questions

### Will our application work the same way on Cloud Foundry as it does locally?

There are key differences depending on your setup:

1. **MCP Server Functionality**: The MCP server part of our application will work on Cloud Foundry just as it does locally. It exposes the same endpoints and tools.

2. **Access to Claude**: If users have Claude Desktop or another MCP-compatible client, they can potentially connect to your deployed application's MCP server (assuming network accessibility and proper configuration).

3. **Direct API Access**: Any RESTful API endpoints you've defined (`/api/search`, `/api/airports`, etc.) will be accessible directly as normal web endpoints.

### Can users interact with our application conversationally when deployed to Cloud Foundry?

This depends on whether:

1. Users have access to an MCP client like Claude Desktop that can connect to your deployed application
2. Your Cloud Foundry deployment is configured to allow external connections to the MCP endpoint
3. Any necessary network routing and security measures are in place

Without an MCP client, users would need to interact with your application's direct API endpoints.

### Do we need to implement our own chat interface in the application?

If you want users to have a conversational experience directly in your web UI (without requiring Claude Desktop):

- **Yes**, you would need to implement a chat interface in your web application
- You would also need to implement an MCP client in your frontend that can communicate with your MCP server
- This would allow users to type questions and get responses directly in your application's UI, similar to how they interact with Claude Desktop

### What's involved in implementing an MCP client in our web UI?

To add a chat interface that connects to your MCP server, you would need to:

1. Create a chat UI component (input box, message display area, etc.)
2. Implement client-side code that can:
   - Connect to your MCP server endpoint (typically via Server-Sent Events for real-time communication)
   - Send user messages in the MCP format
   - Receive and process responses, including tool calls
   - Display results to the user in a conversational format

This would create a seamless experience where users can ask natural language questions about flights directly in your application.

## Architecture Overview

### What's the overall architecture of our application?

Our application has several components:

1. **Sinatra Web Application**: Provides the web server and handles routing
2. **RESTful APIs**: Direct endpoints for flight data (`/api/search`, `/api/airports`, etc.)
3. **MCP Server**: Exposes tools and resources that AI models can use
4. **MCP Tools**: Custom implementations (like `FlightSearchTool`) that define functionality AI models can access

### How do the MCP and API components interact?

The MCP tools typically call the same underlying services as your API endpoints. The difference is in how they're exposed:

- **API Endpoints**: Directly accessible via HTTP requests with specific parameters
- **MCP Tools**: Accessible to AI models through the MCP protocol, allowing natural language interactions

Both ultimately access the same flight data sources, just through different interfaces.
