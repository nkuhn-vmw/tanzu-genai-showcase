"""
Main application configuration for Airbnb Assistant
"""
import os
import logging
from pyramid.config import Configurator
from pyramid.session import SignedCookieSessionFactory
from sqlalchemy import engine_from_config
from cfenv import AppEnv
from dotenv import load_dotenv
import pyramid_mako

# Load environment variables
load_dotenv()

# Configure logging
log = logging.getLogger(__name__)

# Cloud Foundry environment detection
app_env = AppEnv()
is_cf = app_env.name is not None


def get_genai_config(settings):
    """
    Get the GenAI configuration from Cloud Foundry service bindings or environment variables
    """
    if is_cf:
        # Get service by name
        genai_service_name = os.environ.get('GENAI_SERVICE_NAME', 'airbnb-assistant-llm')
        genai_service = app_env.get_service(name=genai_service_name)

        if genai_service:
            credentials = genai_service.credentials
            return {
                'api_key': credentials.get('api_key'),
                'model': credentials.get('model', 'gpt-4'),
                'api_url': credentials.get('api_url', 'https://api.openai.com/v1')
            }
        else:
            log.warning(f"GenAI service '{genai_service_name}' not found. Using environment variables.")

    # Fallback to environment variables or configuration settings
    return {
        'api_key': os.environ.get('GENAI_API_KEY', settings.get('genai.api_key')),
        'model': os.environ.get('GENAI_MODEL', settings.get('genai.model', 'gpt-4')),
        'api_url': os.environ.get('GENAI_API_URL', 'https://api.openai.com/v1')
    }


def main(global_config, **settings):
    """
    This function returns a Pyramid WSGI application.
    """
    # Configure the database
    engine = engine_from_config(settings, 'sqlalchemy.')

    # Create session factory
    session_factory = SignedCookieSessionFactory(
        settings['session.secret'],
        cookie_name=settings['session.cookie_name']
    )

    # Create and configure the Pyramid application
    config = Configurator(settings=settings)

    # Configure Mako templates
    config.include('pyramid_mako')

    # Set up the template directory
    current_dir = os.path.abspath(os.path.dirname(__file__))
    mako_settings = {
        'mako.directories': os.path.join(current_dir, 'templates')
    }
    config.add_settings(mako_settings)

    # Set up session factory
    config.set_session_factory(session_factory)

    # Add GenAI config to settings
    genai_config = get_genai_config(settings)
    config.registry.settings.update({'genai': genai_config})

    # Add MCP URL to settings
    mcp_url = os.environ.get('MCP_AIRBNB_URL', settings.get('mcp.airbnb_url', 'http://localhost:3000'))
    config.registry.settings.update({'mcp.airbnb_url': mcp_url})

    # Set up database connection
    from .models import DBSession, Base
    DBSession.configure(bind=engine)
    Base.metadata.bind = engine

    # Add routes
    config.add_static_view('static', 'airbnb_assistant:static', cache_max_age=3600)
    config.add_route('home', '/')
    config.add_route('chat', '/chat')
    config.add_route('search', '/search')
    config.add_route('toggle_theme', '/toggle_theme')

    # Scan for views - specify the package to avoid scanning views.py in root
    config.scan('.views')

    return config.make_wsgi_app()


# Stand-alone application server (optional)
def run_app():
    """
    Run the application using Waitress when executed directly
    """
    port = int(os.environ.get('PORT', 8080))
    from waitress import serve

    # Create a minimal configuration for standalone execution
    settings = {
        'sqlalchemy.url': os.environ.get('DATABASE_URL', 'sqlite:///airbnb_assistant.db'),
        'session.secret': os.environ.get('SESSION_SECRET', 'somesecret'),
        'session.cookie_name': 'airbnb_assistant_session',
        'mcp.airbnb_url': os.environ.get('MCP_AIRBNB_URL', 'http://localhost:3000'),
        'genai.api_key': os.environ.get('GENAI_API_KEY', 'your_api_key_here'),
        'model': os.environ.get('GENAI_MODEL', 'gpt-4'),
    }

    app = main({}, **settings)
    serve(app, host='0.0.0.0', port=port)


if __name__ == '__main__':
    run_app()
