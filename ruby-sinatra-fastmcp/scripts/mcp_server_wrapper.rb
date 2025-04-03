#!/usr/bin/env ruby

# Get the directory where this script is located
script_dir = File.expand_path(File.dirname(__FILE__))
# Get the project root directory (one level up from scripts)
project_dir = File.expand_path('..', script_dir)

# Change to the project directory
Dir.chdir(project_dir)

# Use Bundler to set up the environment
require 'bundler/setup'

# Execute the actual MCP server script
load File.join(project_dir, 'mcp_server.rb')
