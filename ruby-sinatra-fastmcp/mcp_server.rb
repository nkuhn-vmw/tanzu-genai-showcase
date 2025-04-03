#!/usr/bin/env ruby
require 'fast_mcp'
require 'dotenv/load'

# Load tools and resources
Dir.glob('./app/tools/*.rb').sort.each { |file| require file }
Dir.glob('./app/resources/*.rb').sort.each { |file| require file }

# Create an MCP server
server = FastMcp::Server.new(
  name: 'flight-tracking-bot',
  version: '1.0.0'
)

# Register all tools
[
  FlightSearchTool,
  FlightStatusTool,
  AirportInfoTool,
  AirlineInfoTool,
  FlightSchedulesTool,
  FutureFlightSchedulesTool
].each do |tool_class|
  server.register_tool(tool_class)
end

# Run the server with stdio transport
server.start
