# Flight Tracking Chatbot with Ruby Sinatra and FastMCP

![Status](https://img.shields.io/badge/status-ready-darkgreen) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/ruby-sinatra-fastmcp.yml/badge.svg)

This project demonstrates a flight tracking chatbot built with Ruby, Sinatra, and FastMCP. The chatbot integrates with the AviationStack API to provide real-time flight information and status updates through Model Context Protocol (MCP) for AI-powered interactions.

**New to MCP?** Check out our [Frequently Asked Questions](FAQ.md) to learn more about MCP servers, clients, and how they work together.

## Features

- Search for flights based on various criteria
- Get detailed flight status information
- Look up airport information
- Look up airline information
- Get current flight schedules
- Get future flight schedules for specific dates
- Modern web UI with dark/light theme toggle
- MCP integration for AI-powered interactions
- Ready to deploy to Tanzu Platform for Cloud Foundry

## Architecture

This application uses the following technologies:

- **Ruby**: Core programming language
- **Sinatra**: Lightweight web framework
- **FastMCP**: Ruby implementation of the Model Context Protocol (MCP)
- **AviationStack API**: Source for flight data
- **Rack/Puma**: Application server

The application exposes MCP tools that can be called by AI models like Claude to provide flight information to users. These tools interface with the AviationStack API to fetch real-time flight data.

## Prerequisites

- Ruby 3.0+
- Bundler
- AviationStack API Key (Get one at [AviationStack](https://aviationstack.com/signup/free))
- For Claude Desktop integration: Claude Desktop app
- For Cloud Foundry deployment: CF CLI and access to a Tanzu Platform for Cloud Foundry environment

## Project Structure

```
.
├── app/                  # Application code
│   ├── tools/            # MCP tools
│   ├── resources/        # MCP resources
│   └── aviation_stack_client.rb  # API client
├── config/               # Configuration files
├── public/               # Static assets
│   └── css/              # CSS files
├── scripts/              # Utility scripts
├── test/                 # Test files
├── views/                # ERB templates
├── .env.example          # Example environment variables
├── app.rb                # Main application entry point
├── config.ru             # Rack configuration
├── mcp_server.rb         # Standalone MCP server
├── Gemfile               # Ruby dependencies
└── manifest.yml          # Cloud Foundry deployment configuration
```

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/ruby-sinatra-fastmcp
   ```

2. Install dependencies:

   ```bash
   bundle config set --local path 'vendor/bundle'
   bundle install
   ```

3. Create a `.env` file based on the provided `.env.example`:

   ```bash
   cp .env.example .env
   ```

4. Edit the `.env` file and add your AviationStack API key

## Running Locally

Start the server with our development script:

```bash
./scripts/start-dev.sh
```

Or manually with:

```bash
bundle exec rackup -p 4567
```

Visit `http://localhost:4567` to access the web UI or `http://localhost:4567/api` to access the API endpoints.

## Web UI Features

The web UI provides a user-friendly interface to interact with flight data:

- **Dark/Light Theme Toggle**: Switch between dark and light themes based on your preference
- **Flight Search**: Search for flights by flight number, route, or airport
- **Schedules View**: View current and future flight schedules
- **MCP Tools Overview**: See all available MCP tools for AI integration

## API Endpoints

The following API endpoints are available:

- `GET /api`: API information
- `GET /api/search`: Search for flights
- `GET /api/airports`: Search for airports
- `GET /api/schedules`: Get current flight schedules
- `GET /api/future-schedules`: Get future flight schedules

## Using with Claude Desktop

To use this MCP server with [Claude Desktop](https://claude.ai/download), you can run our setup script which will configure Claude Desktop to recognize the MCP server:

```bash
./scripts/setup-claude-desktop.sh
```

Alternatively, you can manually add the server configuration to Claude Desktop's config file:

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
**Linux**: `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "flight-tracking-bot": {
      "command": "ruby",
      "args": [
        "/full/path/to/scripts/mcp_server_wrapper.rb"
      ]
    }
  }
}
```

> [!IMPORTANT]
> Make sure to use the full path to the `mcp_server_wrapper.rb` script, not the `mcp_server.rb` file. The wrapper script ensures that all dependencies are properly loaded.

Restart Claude Desktop to apply the changes.

> [!NOTE]
> For more details on the wrapper script, troubleshooting, design, and function of the MCP server, consult [this guide](CLAUDE.md).

## Example Chat Interactions

Once the MCP server is connected to Claude, you can ask questions like:

- "What's the status of flight BA142?"
- "Find flights from JFK to LAX today"
- "Tell me about London Heathrow airport"
- "Which airlines fly from Singapore to Tokyo?"
- "Show me flight schedules for next week"
- "What are the future flight schedules for JFK on April 10th?"

## Running Tests

Run the test suite with:

```bash
bundle exec rake test
```

## Deploying to Tanzu Platform for Cloud Foundry

### Requirements

- Cloud Foundry CLI
- Access to a Tanzu Platform for Cloud Foundry environment

### Deployment Steps

You can use our deployment script to automate the process:

```bash
set -a; source .env; set +a
envsubst '${APP_NAME} ${AVIATIONSTACK_API_KEY} ${GENAI_SERVICE_TYPE} ${GENAI_SERVICE_PLAN} ${GENAI_SERVICE_NAME}' < scripts/deploy-to-tanzu.sh > scripts/deploy.sh
chmod +x deploy.sh
./scripts/deploy.sh
```

### Important Note About Cloud Foundry Deployment

When deployed to Cloud Foundry, this application functions as an MCP server only. Users will need an MCP client to interact with it conversationally. See the [FAQ](FAQ.md) for more information about MCP servers and clients.

## Resources

### Core Technologies

- [Ruby](https://www.ruby-lang.org/en/) - Dynamic, open source programming language with a focus on simplicity and productivity
- [Sinatra](https://sinatrarb.com/) - DSL for quickly creating web applications in Ruby with minimal effort
- [Fast-MCP](https://github.com/yjacquin/fast-mcp) - Ruby implementation of the Model Context Protocol
- [Puma](https://puma.io/) - A Ruby/Rack web server built for parallelism
- [Rack](https://github.com/rack/rack) - A modular Ruby webserver interface

### Dependencies

- [Bundler](https://bundler.io/) - Dependency management for Ruby applications
- [Faraday](https://lostisland.github.io/faraday/) - HTTP client library for making external API requests
- [JSON](https://rubygems.org/gems/json) - JSON implementation for Ruby
- [Dotenv](https://github.com/bkeepers/dotenv) - Loads environment variables from .env files
- [Dry-Schema](https://dry-rb.org/gems/dry-schema/) - Schema validation library

### Development Dependencies

- [Rackup](https://github.com/rack/rackup) - Command-line tool for running Rack applications
- [Rerun](https://github.com/alexch/rerun) - Tool for restarting applications when files change

### Testing Dependencies

- [Minitest](https://github.com/minitest/minitest) - Complete suite of testing facilities
- [Rack-Test](https://github.com/rack/rack-test) - Testing API for Rack applications
- [WebMock](https://github.com/bblimke/webmock) - Library for stubbing HTTP requests
- [Mocha](https://github.com/freerange/mocha) - Mocking and stubbing library

### APIs & External Services

- [AviationStack API](https://aviationstack.com/) - Real-time flight information provider
- [Claude AI](https://claude.ai/) - AI assistant with MCP client capabilities
- [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) - Protocol specification for AI model interactions

### Deployment Platforms

- [Cloud Foundry](https://www.cloudfoundry.org/) - Open-source cloud application platform
- [Tanzu Platform](https://www.vmware.com/products/app-platform/tanzu) - VMware's enterprise PaaS built on Cloud Foundry