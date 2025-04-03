# Contributing to the Flight Tracking Chatbot

We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## Development Process

We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

### Pull Requests

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

1. Make sure you have Ruby 3.0+ installed
2. Clone the repository
3. Install dependencies:

```bash
bundle install
```

4. Copy `.env.example` to `.env` and add your AviationStack API key:

```bash
cp .env.example .env
```

5. Start the development server:

```bash
./scripts/start-dev.sh
```

### Testing

We use Minitest for testing. Run the tests with:

```bash
bundle exec rake test
```

When adding new features, please add appropriate tests.

## Code Style

We follow the [Ruby Style Guide](https://rubystyle.guide/) for coding standards. Please keep your code clean and consistent with the existing codebase.

Some key guidelines:
- Use 2 spaces for indentation
- Keep lines under 80 characters when possible
- Use snake_case for methods and variables
- Use CamelCase for classes and modules
- Write clear, descriptive method and variable names
- Add comments for complex logic

## MCP Tools and Resources

When adding new MCP tools or resources:

1. Create a new file in `app/tools/` or `app/resources/` directory
2. Follow the existing pattern for creating tools/resources
3. Make sure to include comprehensive docstrings
4. Register the new tool in `app.rb`
5. Add appropriate tests

## Project Structure

- `app/` - Application code
  - `tools/` - MCP tools for interacting with AviationStack API
  - `resources/` - MCP resources (if any)
- `config/` - Configuration files
- `scripts/` - Utility scripts
- `test/` - Test files
- `.env.example` - Example environment variables
- `app.rb` - Main application entry point
- `config.ru` - Rack configuration
- `mcp_server.rb` - Standalone MCP server for use with Claude Desktop
- `Gemfile` - Ruby dependencies
- `manifest.yml` - Cloud Foundry deployment configuration

## License

By contributing, you agree that your contributions will be licensed under the project's license.

## Questions?

Feel free to open an issue if you have questions about contributing.
