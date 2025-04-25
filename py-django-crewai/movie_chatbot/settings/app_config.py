# movie_chatbot/settings/app_config.py

import os

# --- Movie Recommendation App Configuration ---

# Number of movie results to return from search/discover APIs
MOVIE_RESULTS_LIMIT = int(os.getenv('MOVIE_RESULTS_LIMIT', '5'))
# Maximum number of recommended movies to return to the user
MAX_RECOMMENDATIONS = int(os.getenv('MAX_RECOMMENDATIONS', '3'))
# Radius in miles to search for theaters
THEATER_SEARCH_RADIUS_MILES = int(os.getenv('THEATER_SEARCH_RADIUS_MILES', '15'))
# Maximum showtimes per theater to limit data size
MAX_SHOWTIMES_PER_THEATER = int(os.getenv('MAX_SHOWTIMES_PER_THEATER', '10'))
# Maximum theaters to return in total
MAX_THEATERS = int(os.getenv('MAX_THEATERS', '5'))
# Default starting year for historical movie searches ("before X" queries)
DEFAULT_SEARCH_START_YEAR = int(os.getenv('DEFAULT_SEARCH_START_YEAR', '1900'))


# --- API Request Configuration ---

# Maximum seconds to wait for API responses
API_REQUEST_TIMEOUT = int(os.getenv('API_REQUEST_TIMEOUT_SECONDS', '180'))  # Increased from 120 to 180
# Maximum number of retry attempts for failed API requests
API_MAX_RETRIES = int(os.getenv('API_MAX_RETRIES', '10'))  # Reduced from 15 to 10
# Exponential backoff factor between retries (in seconds)
API_RETRY_BACKOFF_FACTOR = float(os.getenv('API_RETRY_BACKOFF_FACTOR', '1.3'))  # Reduced from 1.5 to 1.3


# --- SerpAPI Request Configuration ---

# Base delay between theater requests for different movies (seconds)
SERPAPI_REQUEST_BASE_DELAY = float(os.getenv('SERPAPI_REQUEST_BASE_DELAY', '5.0'))  # Reduced from 10.0 to 5.0
# Additional delay per movie processed (seconds)
SERPAPI_PER_MOVIE_DELAY = float(os.getenv('SERPAPI_PER_MOVIE_DELAY', '2.0'))  # Reduced from 3.0 to 2.0
# Maximum retries for SerpAPI requests
SERPAPI_MAX_RETRIES = int(os.getenv('SERPAPI_MAX_RETRIES', '2'))  # Reduced from 3 to 2
# Base delay for exponential backoff during retries (seconds)
SERPAPI_BASE_RETRY_DELAY = float(os.getenv('SERPAPI_BASE_RETRY_DELAY', '3.0'))  # Reduced from 5.0 to 3.0
# Multiplier for exponential backoff during retries
SERPAPI_RETRY_MULTIPLIER = float(os.getenv('SERPAPI_RETRY_MULTIPLIER', '1.5'))  # Reduced from 2.0 to 1.5
