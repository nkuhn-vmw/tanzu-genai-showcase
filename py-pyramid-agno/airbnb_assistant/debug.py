"""
Debug middleware for Pyramid
"""

import os
import traceback
from pyramid.httpexceptions import HTTPInternalServerError


class DebugMiddleware:
    """
    Middleware for debugging Pyramid applications
    """

    def __init__(self, app, global_config=None):
        self.app = app
        self.global_config = global_config

    def __call__(self, environ, start_response):
        try:
            return self.app(environ, start_response)
        except Exception as e:
            # Print the full traceback to the console
            traceback.print_exc()

            # Create an error response
            error_message = f"""
            <html>
                <head>
                    <title>Application Error</title>
                    <style>
                        body {{ font-family: Arial, sans-serif; margin: 20px; }}
                        h1 {{ color: #d32f2f; }}
                        pre {{ background-color: #f5f5f5; padding: 10px; overflow: auto; }}
                        .info {{ color: #888; }}
                    </style>
                </head>
                <body>
                    <h1>Application Error</h1>
                    <p>An error occurred while processing your request:</p>
                    <pre>{str(e)}</pre>
                    <h2>Traceback:</h2>
                    <pre>{traceback.format_exc()}</pre>
                    <h2>Environment Information:</h2>
                    <pre class="info">
                        PATH_INFO: {environ.get('PATH_INFO')}
                        REQUEST_METHOD: {environ.get('REQUEST_METHOD')}
                        SCRIPT_NAME: {environ.get('SCRIPT_NAME')}
                    </pre>
                </body>
            </html>
            """

            # Create a basic error response
            error = HTTPInternalServerError(body=error_message, content_type='text/html')
            return error(environ, start_response)


def make_debug_middleware(app, global_config, **settings):
    """
    Make the debug middleware
    """
    return DebugMiddleware(app, global_config)
