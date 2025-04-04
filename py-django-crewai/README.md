# CrewAI + Django Movie Booking Chatbot

![Status](https://img.shields.io/badge/status-under%20development-darkred) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/py-django-crewai.yml/badge.svg)

This example demonstrates a movie booking chatbot built with Django and CrewAI that can be deployed to Tanzu Platform for Cloud Foundry and integrate with LLM services through the GenAI tile.

## Features

- Conversational interface to find movies based on interests or topics
- Recommends top 3 movie choices based on user preferences
- Shows where movies are playing nearby and available show times
- Uses CrewAI to coordinate multiple AI agents working together
- Responsive Django web interface

## Architecture

The application consists of:

1. **Django Web Framework**: Handles HTTP requests, user sessions, and renders the UI
2. **CrewAI Integration**: Orchestrates specialized AI agents for different tasks
3. **Multiple Agent System**:
   - **Movie Finder Agent**: Searches for movies matching user criteria
   - **Recommendation Agent**: Ranks and selects the top 3 choices
   - **Location Agent**: Finds nearby theaters and show times
4. **Service Binding**: Connects to LLM services provided by the GenAI tile

## Prerequisites

- Python 3.9+ and pip
- Cloud Foundry CLI
- Access to Tanzu Platform for Cloud Foundry with GenAI tile installed

## Local Development

1. Clone the repository:
   ```
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/py-django-crewai
   ```

2. Create and activate a virtual environment:
   ```
   python -m venv venv
   source venv/bin/activate  # On Windows, use: venv\Scripts\activate
   ```

3. Install dependencies:
   ```
   pip install -r requirements.txt
   ```

4. Create a `.env` file with your API keys (for local development only):
   ```
   LLM_API_KEY=your_llm_api_key_here
   LLM_BASE_URL=optional_custom_endpoint
   LLM_MODEL=gpt-3.5-turbo
   TMDB_API_KEY=your_movie_db_api_key_here
   ```

5. Run migrations:
   ```
   python manage.py migrate
   ```

6. Start the development server:
   ```
   python manage.py runserver
   ```

7. Open your browser to `http://localhost:8000`

## Building for Production

1. Create a production-ready build:
   ```
   python manage.py collectstatic --noinput
   ```

## Deploying to Tanzu Platform for Cloud Foundry

1. Login to your Cloud Foundry instance:
   ```
   cf login -a API_ENDPOINT
   ```

2. Deploy the application:
   ```
   cf push
   ```

3. Bind to a GenAI service instance:
   ```
   cf create-service genai-service standard my-llm-service
   cf bind-service movie-chatbot my-llm-service
   cf restage movie-chatbot
   ```

## Service Binding

The application uses the following approach to consume service credentials:

1. When deployed to Cloud Foundry, it automatically detects VCAP_SERVICES environment variables
2. It extracts LLM service credentials from the bound service instance
3. CrewAI agents are configured to use these credentials for LLM interactions

## Resources

- [CrewAI Documentation](https://docs.crewai.com/)
- [Django Documentation](https://docs.djangoproject.com/)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
