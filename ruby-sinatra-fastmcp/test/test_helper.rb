ENV['RACK_ENV'] = 'test'

require 'minitest/autorun'
require 'rack/test'
require 'webmock/minitest'
require 'mocha/minitest'
require 'json'

# Load the application
require_relative '../app'

# Disable external HTTP requests in tests
WebMock.disable_net_connect!(allow_localhost: true)
