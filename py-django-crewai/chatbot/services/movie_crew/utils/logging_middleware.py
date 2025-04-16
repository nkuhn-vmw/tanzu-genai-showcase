"""
Logging middleware for the movie crew.
"""
import logging

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class LoggingMiddleware:
    """Middleware for logging method calls."""

    @staticmethod
    def log_method_call(func):
        """
        Decorator for logging method calls.

        Args:
            func: The function to decorate

        Returns:
            Wrapped function with logging
        """
        def wrapper(*args, **kwargs):
            logger.debug(f"Entering {func.__name__}")
            try:
                result = func(*args, **kwargs)
                logger.debug(f"Exiting {func.__name__} successfully")
                return result
            except Exception as e:
                logger.error(f"Error in {func.__name__}: {str(e)}")
                logger.exception(e)
                raise
        return wrapper
