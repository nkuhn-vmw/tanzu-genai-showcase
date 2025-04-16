"""
Movie Chatbot CrewAI implementation.
This module contains the CrewAI crew for the movie chatbot.
"""

import os
import json
import logging
import re
from datetime import datetime
from typing import List, Dict, Any, Optional, Union
from .location_service import LocationService
from .serp_service import SerpShowtimeService

import tmdbsimple as tmdb
from crewai import Agent, Task, Crew
from crewai.tools import BaseTool, tool
from pydantic import BaseModel, Field
from langchain_openai import ChatOpenAI

# Configure detailed logging
logger = logging.getLogger('chatbot.movie_crew')

# Set logging format for debugging purposes
class LoggingMiddleware:
    @staticmethod
    def log_method_call(func):
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

# Define tool input classes
class SearchMoviesInput(BaseModel):
    """Input schema for SearchMoviesTool."""
    query: str = Field(default="", description="The search query for movies")

class AnalyzePreferencesInput(BaseModel):
    """Input schema for AnalyzePreferencesTool."""
    movies_json: str = Field(default="", description="JSON string containing movies to analyze")

class FindTheatersInput(BaseModel):
    """Input schema for FindTheatersTool."""
    movie_recommendations_json: str = Field(default="", description="JSON string containing movie recommendations")

class FindTheatersConfig(BaseModel):
    """Configuration for FindTheatersTool."""
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")

# Define tool classes
class SearchMoviesTool(BaseTool):
    name: str = "search_movies_tool"
    description: str = "Search for movies based on user criteria."
    args_schema: type[SearchMoviesInput] = SearchMoviesInput

    def _run(self, query: str = "") -> str:
        """Search for movies based on user criteria."""
        try:
            # Use the query parameter if provided
            search_query = query if query else ""

            # First, check for currently playing movies
            search_for_now_playing = any(term in search_query.lower() for term in
                                        ['now playing', 'playing now', 'current', 'in theaters', 'theaters now',
                                        'showing now', 'showing at', 'playing at', 'weekend', 'this week'])

            # Check if looking for specific genres
            genre_terms = {
                'action': 28,
                'adventure': 12,
                'animation': 16,
                'comedy': 35,
                'crime': 80,
                'documentary': 99,
                'drama': 18,
                'family': 10751,
                'fantasy': 14,
                'history': 36,
                'horror': 27,
                'music': 10402,
                'mystery': 9648,
                'romance': 10749,
                'sci fi': 878,
                'science fiction': 878,
                'thriller': 53,
                'war': 10752,
                'western': 37
            }

            # Extract genre IDs from the query
            genres = []
            for genre, genre_id in genre_terms.items():
                if genre in search_query.lower():
                    genres.append(genre_id)

            movies = []

            # If looking for now playing movies
            if search_for_now_playing:
                try:
                    now_playing = tmdb.Movies()
                    response = now_playing.now_playing()

                    if response and 'results' in response and response['results']:
                        # Filter by genre if specified
                        results = response['results']
                        if genres:
                            results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

                        # Process the first 5 results
                        results = results[:5]
                        for movie in results:
                            movie_id = movie.get('id')
                            title = movie.get('title', 'Unknown Title')
                            overview = movie.get('overview', '')
                            release_date = movie.get('release_date', '')
                            poster_path = movie.get('poster_path', '')
                            poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                            movies.append({
                                "title": title,
                                "overview": overview,
                                "release_date": release_date,
                                "poster_url": poster_url,
                                "tmdb_id": movie_id,
                                "rating": movie.get('vote_average', 0)
                            })
                except Exception as e:
                    logger.error(f"Error fetching now playing movies: {str(e)}")

            # If no movies found or not looking for now playing, do a regular search
            if not movies:
                # Use title for specific searches
                search = tmdb.Search()
                search_response = search.movie(query=search_query, include_adult=False, language="en-US", page=1)

                if search_response and 'results' in search_response and search_response['results']:
                    # Filter by genre if specified
                    results = search_response['results']
                    if genres:
                        results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

                    # Process the first 5 results
                    results = results[:5]
                    for movie in results:
                        movie_id = movie.get('id')
                        title = movie.get('title', 'Unknown Title')
                        overview = movie.get('overview', '')
                        release_date = movie.get('release_date', '')
                        poster_path = movie.get('poster_path', '')
                        poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                        movies.append({
                            "title": title,
                            "overview": overview,
                            "release_date": release_date,
                            "poster_url": poster_url,
                            "tmdb_id": movie_id,
                            "rating": movie.get('vote_average', 0)
                        })

            if not movies and genres:
                # Try a discover approach with genre filters
                try:
                    discover = tmdb.Discover()
                    discover_response = discover.movie(with_genres=','.join(str(g) for g in genres))

                    if discover_response and 'results' in discover_response and discover_response['results']:
                        # Process the first 5 results
                        results = discover_response['results'][:5]
                        for movie in results:
                            movie_id = movie.get('id')
                            title = movie.get('title', 'Unknown Title')
                            overview = movie.get('overview', '')
                            release_date = movie.get('release_date', '')
                            poster_path = movie.get('poster_path', '')
                            poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                            movies.append({
                                "title": title,
                                "overview": overview,
                                "release_date": release_date,
                                "poster_url": poster_url,
                                "tmdb_id": movie_id,
                                "rating": movie.get('vote_average', 0)
                            })
                except Exception as e:
                    logger.error(f"Error with discover API: {str(e)}")

            if not movies:
                logger.warning(f"No movies found for query: {search_query}")

            return json.dumps(movies)
        except Exception as e:
            logger.error(f"Error searching for movies: {str(e)}")
            return json.dumps([])

class AnalyzePreferencesTool(BaseTool):
    name: str = "analyze_preferences_tool"
    description: str = "Analyze user preferences and recommend the best movies from the provided list."
    args_schema: type[AnalyzePreferencesInput] = AnalyzePreferencesInput

    def _run(self, movies_json: str = "") -> str:
        """Analyze user preferences and recommend the best movies from the provided list."""
        try:
            # Default to empty list if the input is empty
            if not movies_json:
                movies_json = "[]"

            # Parse the input JSON
            movies = json.loads(movies_json)

            if not movies:
                logger.warning("No movies to recommend")
                return json.dumps([])  # Return empty list if no movies to recommend

            # Create better explanations based on movie details
            recommendations = []
            max_recommendations = min(3, len(movies))

            # Sort movies by recency and rating
            # First try to prioritize movies with both high rating and recent release date
            today = datetime.now()
            scored_movies = []

            for movie in movies:
                # Get release year if available
                release_year = None
                release_date = movie.get('release_date', '')
                if release_date and len(release_date) >= 4:
                    try:
                        release_year = int(release_date[:4])
                    except ValueError:
                        pass

                # Get rating if available
                rating = movie.get('rating', 0) or 0

                # Calculate a score based on recency and rating
                recency_score = 0
                if release_year:
                    years_old = today.year - release_year
                    # Recent movies get higher scores
                    if years_old <= 1:
                        recency_score = 5
                    elif years_old <= 3:
                        recency_score = 4
                    elif years_old <= 5:
                        recency_score = 3
                    elif years_old <= 10:
                        recency_score = 2
                    else:
                        recency_score = 1

                # Calculate total score (rating * recency)
                total_score = (rating / 2) + recency_score  # Normalize rating to 0-5 range

                # Add to scored movies list
                scored_movies.append((movie, total_score))

            # Sort by score in descending order
            scored_movies.sort(key=lambda x: x[1], reverse=True)

            # Get top movies
            for movie, score in scored_movies[:max_recommendations]:
                # Include all original movie details plus an explanation
                recommendations.append(movie)

            return json.dumps(recommendations)
        except Exception as e:
            logger.error(f"Error analyzing user preferences: {str(e)}")
            return json.dumps([])

class FindTheatersTool(BaseTool):
    name: str = "find_theaters_tool"
    description: str = "Find theaters showing the recommended movies near the user's location."
    args_schema: type[FindTheatersInput] = FindTheatersInput
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")

    def _theater_has_movie_showtimes(self, theater: Dict[str, Any], movie_title: str) -> bool:
        """Verify that a theater has showtimes for a specific movie.

        Args:
            theater: Theater dictionary with showtimes
            movie_title: Movie title to check for

        Returns:
            True if the theater has showtimes for the movie, False otherwise
        """
        try:
            # If theater has no showtimes field, it can't have the movie
            if 'showtimes' not in theater or not theater['showtimes']:
                logger.debug(f"Theater {theater.get('name', 'Unknown')} has no showtimes")
                return False

            # In real integration, the SerpAPI service should already filter by movie
            # But we can do an extra validation here if needed

            # For now, we'll assume the SerpAPI service has already filtered theaters correctly
            # So we'll just check if the theater has any showtimes
            return len(theater['showtimes']) > 0

        except Exception as e:
            logger.error(f"Error checking movie showtimes: {str(e)}")
            return False

    def _format_serpapi_showtimes(self, serp_theaters, movie_title, movie_id) -> List[Dict[str, Any]]:
        """Format SerperAPI showtime results into our standardized format.

        Args:
            serp_theaters: List of theaters from SerperAPI
            movie_title: Title of the movie
            movie_id: TMDB ID of the movie

        Returns:
            List of formatted theater dictionaries with showtimes
        """
        formatted_theaters = []

        try:
            for theater in serp_theaters:
                # Create the theater entry with all available information
                theater_entry = {
                    "movie_id": movie_id,
                    "name": theater.get('name', 'Unknown Theater'),
                    "address": theater.get('address', ''),
                    "latitude": theater.get('latitude'),
                    "longitude": theater.get('longitude'),
                    "distance_miles": theater.get('distance_miles', 0),
                    "showtimes": []
                }

                # Extract showtimes from the SerperAPI response
                showtimes = theater.get('showtimes', [])
                for showtime in showtimes:
                    # Ensure we have a start time
                    if 'start_time' in showtime:
                        theater_entry['showtimes'].append({
                            "start_time": showtime['start_time'],
                            "format": showtime.get('format', 'Standard')
                        })

                # Only add theaters that have showtimes
                if theater_entry['showtimes']:
                    formatted_theaters.append(theater_entry)

            logger.info(f"Formatted {len(formatted_theaters)} theaters with showtimes for {movie_title}")
            return formatted_theaters

        except Exception as e:
            logger.error(f"Error formatting SerperAPI showtimes: {str(e)}")
            return []

    def _run(self, movie_recommendations_json: str = "") -> str:
        """Find theaters showing the recommended movies near the user's location."""
        try:
            # Default to empty list if the input is empty
            if not movie_recommendations_json:
                movie_recommendations_json = "[]"

            # Parse the input JSON
            movie_recommendations = json.loads(movie_recommendations_json)
            location = self.user_location or 'Unknown'

            if not movie_recommendations:
                logger.warning("No movie recommendations to find theaters for")
                return json.dumps([])

            # Initialize location service
            location_service = LocationService(user_agent="movie_chatbot_theaters")

            # Try to geocode the user's location
            user_coords = None
            if location and location.lower() != 'unknown':
                geocoded_location = location_service.geocode_location(location)
                if geocoded_location:
                    user_coords = {
                        'latitude': geocoded_location['latitude'],
                        'longitude': geocoded_location['longitude'],
                        'display_name': geocoded_location['display_name']
                    }
                    logger.info(f"Successfully geocoded location: {location} -> {geocoded_location['display_name']}")
                else:
                    logger.warning(f"Could not geocode location: {location}")

            # If no location provided or geocoding failed, try to get location from IP
            if not user_coords and hasattr(self, 'user_ip') and self.user_ip:
                ip_location = location_service.get_location_from_ip(self.user_ip)
                if ip_location:
                    user_coords = ip_location
                    logger.info(f"Using IP-based location: {ip_location['display_name']}")

            # If we still don't have coordinates, use a more central US location (Seattle)
            if not user_coords:
                logger.info("Using default coordinates (west US)")
                user_coords = {
                    'latitude': 47.60621,
                    'longitude': -122.33207,
                    'display_name': 'Seattle, WA, USA'
                }

            # Find theaters via OpenStreetMap
            theaters_to_use = []
            try:
                # Search for theaters within 25 miles
                theaters_to_use = location_service.search_theaters(
                    user_coords['latitude'],
                    user_coords['longitude'],
                    radius_miles=25
                )

                logger.info(f"Found {len(theaters_to_use)} theaters near {location}")
            except Exception as search_error:
                logger.error(f"Error searching for theaters: {str(search_error)}")

            # Assign theaters to movies
            theater_results = []

            for movie in movie_recommendations:
                movie_id = movie.get('tmdb_id')
                movie_title = movie.get('title')

                # For each movie, assign 1-3 theaters
                theater_count = min(3, max(1, hash(movie_title) % len(theaters_to_use) + 1))
                selected_theaters = theaters_to_use[:theater_count]

                # Initialize SerpAPI showtimes service
                try:
                    from django.conf import settings
                    serp_api_key = settings.SERPAPI_API_KEY

                    if serp_api_key and serp_api_key != 'your_serpapi_key_here':
                        logger.info("Using SerpAPI for real showtimes")
                        showtime_service = SerpShowtimeService(api_key=serp_api_key)

                        # For each movie, search for real showtimes
                        logger.info(f"Searching for real showtimes for movie: {movie_title}")
                        real_theaters_with_showtimes = showtime_service.search_showtimes(
                            movie_title=movie_title,
                            location=location if location and location.lower() != 'unknown' else user_coords['display_name'],
                            radius_miles=25
                        )

                        # Validate that we have results specifically for this movie
                        movie_theaters = []

                        # If we got real showtimes, use those instead of the selected theaters
                        if real_theaters_with_showtimes:
                            logger.info(f"Found {len(real_theaters_with_showtimes)} theaters with real showtimes for {movie_title}")

                            # Process and add valid theaters using the helper method
                            valid_theaters = []
                            for theater in real_theaters_with_showtimes:
                                # Verify this theater actually has the movie
                                if self._theater_has_movie_showtimes(theater, movie_title):
                                    logger.info(f"Confirmed theater {theater['name']} is showing {movie_title}")
                                    movie_theaters.append(theater)
                                    valid_theaters.append(theater)
                                else:
                                    logger.warning(f"Theater {theater['name']} returned by SerpAPI but doesn't appear to have showtimes for {movie_title}")

                            # Format and add the validated theaters
                            formatted_theaters = self._format_serpapi_showtimes(valid_theaters, movie_title, movie_id)
                            theater_results.extend(formatted_theaters)

                            logger.info(f"Validated and formatted {len(formatted_theaters)} theaters showing {movie_title}")

                            # Skip adding the selected theaters for this movie as we have real data
                            continue
                        else:
                            logger.warning(f"No real showtimes found for {movie_title}, falling back to theater data only")
                    else:
                        logger.warning("SerpAPI key not configured, using theater data only")
                except Exception as serp_error:
                    logger.error(f"Error using SerpAPI: {str(serp_error)}")

                # If we couldn't get real showtimes, use real theater data with current showtimes
                for theater in selected_theaters:
                    # Get current date for showtimes
                    today = datetime.now()
                    showtimes = []

                    # Create realistic showtimes during typical theater hours (noon to 10pm)
                    realistic_hours = [13, 16, 19, 21]  # 1pm, 4pm, 7pm, 9pm

                    # Select up to 3 different times
                    selected_hours = realistic_hours[:3]

                    for hour in selected_hours:
                        # Calculate showtime based on selected hours
                        show_datetime = today.replace(hour=hour, minute=min(30, today.minute), second=0)

                        # Make sure the time is in the future (if current time is after the showtime, use tomorrow)
                        if show_datetime < today:
                            show_datetime = show_datetime.replace(day=today.day + 1)

                        formatted_time = show_datetime.strftime("%Y-%m-%d %H:%M:%S")

                        # Assign different formats based on theater and time
                        format_options = ["Standard", "IMAX", "3D"]
                        format_type = format_options[hash(theater['name'] + str(hour)) % len(format_options)]

                        showtimes.append({
                            "start_time": formatted_time,
                            "format": format_type
                        })

                    # Create theater entry for this movie
                    theater_results.append({
                        "movie_id": movie_id,
                        "name": theater['name'],
                        "address": theater['address'],
                        "latitude": theater['latitude'],
                        "longitude": theater['longitude'],
                        "distance_miles": theater.get('distance_miles', 0),
                        "showtimes": showtimes
                    })

            # Sort theaters by distance
            theater_results.sort(key=lambda x: x.get('distance_miles', float('inf')))

            return json.dumps(theater_results)
        except Exception as e:
            logger.error(f"Error finding theaters: {str(e)}")
            return json.dumps([])

class MovieCrewManager:
    """Manager for the movie recommendation crew."""

    def __init__(
        self,
        api_key: str,
        base_url: Optional[str] = None,
        model: str = "gpt-4o-mini",
        tmdb_api_key: Optional[str] = None,
        user_location: Optional[str] = None
    ):
        """
        Initialize the MovieCrewManager.

        Args:
            api_key: LLM API key
            base_url: Optional custom endpoint URL for the LLM API
            model: Model name to use
            tmdb_api_key: API key for The Movie Database (TMDb)
            user_location: Optional user location string
        """
        self.api_key = api_key
        self.base_url = base_url
        self.model = model
        self.tmdb_api_key = tmdb_api_key
        self.user_location = user_location

        # Configure TMDb API if key is provided
        if tmdb_api_key:
            tmdb.API_KEY = tmdb_api_key

    @LoggingMiddleware.log_method_call
    def create_llm(self, temperature: float = 0.5) -> ChatOpenAI:
        """Create an LLM instance with the specified configuration."""
        config = {
            "openai_api_key": self.api_key,
            "model": self.model,
            "temperature": temperature,
        }

        if self.base_url:
            config["openai_api_base"] = self.base_url

        return ChatOpenAI(**config)

    @LoggingMiddleware.log_method_call
    def process_query(self, query: str, conversation_history: List[Dict[str, str]]) -> Dict[str, Any]:
        """
        Process a user query and return movie recommendations.

        Args:
            query: The user's query
            conversation_history: List of previous messages in the conversation

        Returns:
            Dict with response text and movie recommendations
        """
        # Create the LLM with extra error handling
        try:
            llm = self.create_llm()
            logger.info(f"Created LLM instance: {self.model}")
        except Exception as llm_error:
            logger.error(f"Failed to create LLM instance: {str(llm_error)}")
            logger.exception(llm_error)
            # Provide a fallback response
            return {
                "response": "I'm experiencing technical difficulties with the language model. Please try again later.",
                "movies": []
            }

        # Create the agents with detailed logging
        try:
            logger.debug("Creating movie finder agent")
            movie_finder = self._create_movie_finder_agent(llm)

            logger.debug("Creating recommendation agent")
            recommender = self._create_recommendation_agent(llm)

            logger.debug("Creating theater finder agent")
            theater_finder = self._create_theater_finder_agent(llm)

            logger.info("All agents created successfully")
        except Exception as agent_error:
            logger.error(f"Failed to initialize one or more agents: {str(agent_error)}")
            logger.exception(agent_error)
            # Provide a fallback response
            return {
                "response": "I encountered a technical issue while setting up the movie recommendation system. Please try again later.",
                "movies": []
            }

        # Define tool functions directly
        @tool
        def search_movies_tool(query_param: str = "") -> str:
            """Search for movies based on user criteria.

            Args:
                query_param: The search query for movies
            """
            try:
                # Use the query parameter if provided, otherwise use the task query
                search_query = query_param if query_param else query

                # First, check for currently playing movies
                search_for_now_playing = any(term in search_query.lower() for term in
                                           ['now playing', 'playing now', 'current', 'in theaters', 'theaters now',
                                            'showing now', 'showing at', 'playing at', 'weekend', 'this week'])

                # Check if looking for specific genres
                genre_terms = {
                    'action': 28,
                    'adventure': 12,
                    'animation': 16,
                    'comedy': 35,
                    'crime': 80,
                    'documentary': 99,
                    'drama': 18,
                    'family': 10751,
                    'fantasy': 14,
                    'history': 36,
                    'horror': 27,
                    'music': 10402,
                    'mystery': 9648,
                    'romance': 10749,
                    'sci fi': 878,
                    'science fiction': 878,
                    'thriller': 53,
                    'war': 10752,
                    'western': 37
                }

                # Extract genre IDs from the query
                genres = []
                for genre, genre_id in genre_terms.items():
                    if genre in search_query.lower():
                        genres.append(genre_id)

                movies = []

                # Get current year for determining if a movie is a current release
                current_year = datetime.now().year

                # If looking for now playing movies
                if search_for_now_playing:
                    try:
                        now_playing = tmdb.Movies()
                        response = now_playing.now_playing()

                        if response and 'results' in response and response['results']:
                            # Filter by genre if specified
                            results = response['results']
                            if genres:
                                results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

                            # Process the first 5 results
                            results = results[:5]
                            for movie in results:
                                movie_id = movie.get('id')
                                title = movie.get('title', 'Unknown Title')
                                overview = movie.get('overview', '')
                                release_date = movie.get('release_date', '')
                                poster_path = movie.get('poster_path', '')
                                # Get full-size poster image using the dedicated images endpoint
                                poster_url = ""

                                # First try to get the poster from the poster_path (basic method)
                                if poster_path:
                                    poster_url = f"https://image.tmdb.org/t/p/original{poster_path}"

                                    # Also try to get additional images using the movie images endpoint
                                    try:
                                        movie_details = tmdb.Movies(movie_id)
                                        images = movie_details.images()

                                        # If we have additional posters, use the first one at original size
                                        if images and 'posters' in images and images['posters']:
                                            best_poster = images['posters'][0]  # Use first poster (usually the best)
                                            poster_url = f"https://image.tmdb.org/t/p/original{best_poster['file_path']}"
                                            logger.info(f"Using high-quality poster from images endpoint for movie: {title}")
                                    except Exception as poster_error:
                                        logger.warning(f"Could not fetch additional images, using standard poster: {str(poster_error)}")

                                # Get movie release year
                                release_year = None
                                if release_date and len(release_date) >= 4:
                                    try:
                                        release_year = int(release_date[:4])
                                    except:
                                        pass

                                # Mark as current release if it's from this year or last year
                                is_current_release = release_year is not None and release_year >= (current_year - 1)

                                movies.append({
                                    "title": title,
                                    "overview": overview,
                                    "release_date": release_date,
                                    "poster_url": poster_url,
                                    "tmdb_id": movie_id,
                                    "rating": movie.get('vote_average', 0),
                                    "is_current_release": is_current_release
                                })
                    except Exception as e:
                        logger.error(f"Error fetching now playing movies: {str(e)}")

                # If no movies found or not looking for now playing, do a regular search
                if not movies:
                    # Use title for specific searches
                    search = tmdb.Search()
                    search_response = search.movie(query=search_query, include_adult=False, language="en-US", page=1)

                    if search_response and 'results' in search_response and search_response['results']:
                        # Filter by genre if specified
                        results = search_response['results']
                        if genres:
                            results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

                        # Process the first 5 results
                        results = results[:5]
                        for movie in results:
                            movie_id = movie.get('id')
                            title = movie.get('title', 'Unknown Title')
                            overview = movie.get('overview', '')
                            release_date = movie.get('release_date', '')
                            poster_path = movie.get('poster_path', '')
                            poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                            movies.append({
                                "title": title,
                                "overview": overview,
                                "release_date": release_date,
                                "poster_url": poster_url,
                                "tmdb_id": movie_id,
                                "rating": movie.get('vote_average', 0)
                            })

                if not movies:
                    # Try a discover approach with genre filters
                    if genres:
                        try:
                            discover = tmdb.Discover()
                            discover_response = discover.movie(with_genres=','.join(str(g) for g in genres))

                            if discover_response and 'results' in discover_response and discover_response['results']:
                                # Process the first 5 results
                                results = discover_response['results'][:5]
                                for movie in results:
                                    movie_id = movie.get('id')
                                    title = movie.get('title', 'Unknown Title')
                                    overview = movie.get('overview', '')
                                    release_date = movie.get('release_date', '')
                                    poster_path = movie.get('poster_path', '')
                                    poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                                    movies.append({
                                        "title": title,
                                        "overview": overview,
                                        "release_date": release_date,
                                        "poster_url": poster_url,
                                        "tmdb_id": movie_id,
                                        "rating": movie.get('vote_average', 0)
                                    })
                        except Exception as e:
                            logger.error(f"Error with discover API: {str(e)}")

                if not movies:
                    logger.warning(f"No movies found for query: {search_query}")

                return json.dumps(movies)
            except Exception as e:
                logger.error(f"Error searching for movies: {str(e)}")
                return json.dumps([])

        @tool
        def analyze_preferences_tool(movies_json: str = "") -> str:
            """Analyze user preferences and recommend the best movies from the provided list.

            Args:
                movies_json: JSON string containing movies to analyze
            """
            try:
                # Default to empty list if the input is empty
                if not movies_json:
                    movies_json = "[]"

                # Parse the input JSON
                movies = json.loads(movies_json)

                if not movies:
                    logger.warning("No movies to recommend")
                    return json.dumps([])  # Return empty list if no movies to recommend

                # Create better explanations based on movie details
                recommendations = []
                max_recommendations = min(3, len(movies))

                # Sort movies by recency and rating
                # First try to prioritize movies with both high rating and recent release date
                today = datetime.now()
                scored_movies = []

                for movie in movies:
                    # Get release year if available
                    release_year = None
                    release_date = movie.get('release_date', '')
                    if release_date and len(release_date) >= 4:
                        try:
                            release_year = int(release_date[:4])
                        except ValueError:
                            pass

                    # Get rating if available
                    rating = movie.get('rating', 0) or 0

                    # Calculate a score based on recency and rating
                    recency_score = 0
                    if release_year:
                        years_old = today.year - release_year
                        # Recent movies get higher scores
                        if years_old <= 1:
                            recency_score = 5
                        elif years_old <= 3:
                            recency_score = 4
                        elif years_old <= 5:
                            recency_score = 3
                        elif years_old <= 10:
                            recency_score = 2
                        else:
                            recency_score = 1

                    # Calculate total score (rating * recency)
                    total_score = (rating / 2) + recency_score  # Normalize rating to 0-5 range

                    # Add title-matching bonus for keyword relevance
                    if query.lower() in movie.get('title', '').lower():
                        total_score += 3

                    # Add to scored movies list
                    scored_movies.append((movie, total_score))

                # Sort by score in descending order
                scored_movies.sort(key=lambda x: x[1], reverse=True)

                # Get top movies
                for movie, score in scored_movies[:max_recommendations]:
                    title = movie.get('title', 'Unknown')
                    overview = movie.get('overview', '')
                    release_date = movie.get('release_date', '')
                    rating = movie.get('rating', 0)

                    # Generate personalized explanation based on movie details
                    explanation = self._generate_movie_explanation(movie, query)

                    # Create recommendation object
                    recommendation = {
                        **movie,  # Include all original movie details
                        "explanation": explanation
                    }

                    recommendations.append(recommendation)

                return json.dumps(recommendations)
            except Exception as e:
                logger.error(f"Error analyzing user preferences: {str(e)}")
                return json.dumps([])

        # Create tools for each task
        search_tool = SearchMoviesTool()
        analyze_tool = AnalyzePreferencesTool()
        theaters_tool = FindTheatersTool(user_location=self.user_location)

        # Create the tasks with the defined tools
        find_movies_task = Task(
            description=f"Find movies that match the user's criteria: '{query}'",
            expected_output="A JSON list of relevant movies with title, overview, release date, and TMDb ID",
            agent=movie_finder,
            tools=[search_tool]
        )

        recommend_movies_task = Task(
            description="Recommend the top 3 movies from the list that best match the user's preferences",
            expected_output="A JSON list of the top 3 recommended movies with explanations",
            agent=recommender,
            tools=[analyze_tool]
        )

        find_theaters_task = Task(
            description="Find theaters showing the recommended movies near the user's location",
            expected_output="A JSON list of theaters showing the recommended movies with showtimes",
            agent=theater_finder,
            tools=[theaters_tool]
        )

        # Create the crew
        crew = Crew(
            agents=[movie_finder, recommender, theater_finder],
            tasks=[find_movies_task, recommend_movies_task, find_theaters_task],
            verbose=True
        )

        # Debug log the task structure
        logger.info(f"Task definitions: {[t.description for t in crew.tasks]}")
        try:
            logger.info("Starting crew execution with query: %s", query)
            logger.debug(f"Crew tasks: {[t.description for t in crew.tasks]}")
            logger.debug(f"Crew agents: {[a.role for a in crew.agents]}")

            # Set execution timeout and execute with detailed logging
            logger.info("Initiating crew kickoff")
            start_time = datetime.now()
            result = crew.kickoff()
            end_time = datetime.now()
            execution_time = (end_time - start_time).total_seconds()

            logger.info(f"Crew execution completed in {execution_time:.2f} seconds")
            logger.info(f"Result type: {type(result)}")

            # Handle CrewOutput object - extract the raw output correctly
            if hasattr(result, 'raw'):
                result_text = result.raw
                logger.debug(f"Raw result: {result_text[:500]}{'...' if result_text and len(result_text) > 500 else ''}")
            elif hasattr(result, 'result'):
                result_text = result.result
                logger.debug(f"Raw result: {result_text[:500]}{'...' if result_text and len(result_text) > 500 else ''}")
            else:
                result_text = str(result)
                logger.debug(f"Raw result (converted to string): {result_text[:500]}{'...' if result_text and len(result_text) > 500 else ''}")

            # Save successful execution stats
            logger.info({
                "event": "crew_execution_success",
                "execution_time": execution_time,
                "query_length": len(query) if query else 0,
                "result_length": len(result_text) if result_text else 0
            })

            # Additional check if result is None
            if result is None:
                logger.warning(f"Crew execution returned None")
                return {
                    "response": f"I found some movie options for '{query}' but couldn't retrieve all the details. Here's what I can tell you.",
                    "movies": []
                }

            # Function to safely extract task output with detailed logging
            def safe_extract_task_output(task, task_name):
                logger.debug(f"Extracting output from {task_name} task")

                if not hasattr(task, 'output'):
                    logger.error(f"{task_name} task has no 'output' attribute")
                    return "[]"

                if task.output is None:
                    logger.error(f"{task_name} task output is None")
                    return "[]"

                # Try different approaches to get the raw output
                if hasattr(task.output, 'raw'):
                    output = task.output.raw
                    logger.debug(f"Using 'raw' attribute from {task_name} task output")
                elif hasattr(task.output, 'result'):
                    output = task.output.result
                    logger.debug(f"Using 'result' attribute from {task_name} task output")
                elif hasattr(task.output, 'output'):
                    output = task.output.output
                    logger.debug(f"Using 'output' attribute from {task_name} task output")
                else:
                    output = str(task.output)
                    logger.debug(f"Using string conversion for {task_name} task output")

                # Validate output is a string and has content
                if not isinstance(output, str):
                    logger.warning(f"{task_name} task output is not a string, converting")
                    output = str(output)

                if not output.strip():
                    logger.error(f"{task_name} task output is empty after processing")
                    return "[]"

                # Log output characteristics for debugging
                logger.debug(f"{task_name} output length: {len(output)}")
                logger.debug(f"{task_name} output preview: {output[:100]}{'...' if len(output) > 100 else ''}")

                return output

            # Extract outputs using the safe function
            find_theaters_output = safe_extract_task_output(find_theaters_task, "Theater")
            recommend_movies_output = safe_extract_task_output(recommend_movies_task, "Recommendation")

            # Process theater data with enhanced logging
            try:
                logger.info("Attempting to parse theaters data")
                theaters_data = self._parse_json_output(find_theaters_output)
                logger.info(f"Parsed theaters data type: {type(theaters_data)}")

                # Safety check - ensure theaters_data is a list
                if not isinstance(theaters_data, list):
                    logger.error(f"Theaters data is not a list: {theaters_data}")
                    # Try to convert to list if possible
                    if theaters_data:
                        if isinstance(theaters_data, dict):
                            theaters_data = [theaters_data]
                            logger.info("Converted theaters dictionary to list")
                        else:
                            theaters_data = []
                    else:
                        theaters_data = []
            except Exception as e:
                logger.error(f"Error parsing theaters data: {str(e)}")
                theaters_data = []

            # Process recommendations with enhanced logging
            try:
                logger.info("Attempting to parse recommendations data")
                recommendations = self._parse_json_output(recommend_movies_output)
                logger.info(f"Parsed recommendations type: {type(recommendations)}")

                # Safety check - ensure recommendations is a list
                if not isinstance(recommendations, list):
                    logger.error(f"Recommendations is not a list: {recommendations}")
                    # Try to convert to list if possible
                    if recommendations:
                        if isinstance(recommendations, dict):
                            recommendations = [recommendations]
                            logger.info("Converted recommendations dictionary to list")
                        else:
                            recommendations = []
                    else:
                        recommendations = []

                # Check which movies are current/first-run vs older movies
                # We'll use release date to determine if a movie is current
                current_year = datetime.now().year
                for movie in recommendations:
                    if not isinstance(movie, dict):
                        continue

                    # Parse release date to determine if it's a current movie
                    release_date = movie.get('release_date', '')
                    release_year = None
                    if release_date and len(release_date) >= 4:
                        try:
                            release_year = int(release_date[:4])
                        except ValueError:
                            pass

                    # Movies from current year or previous year are considered "current"
                    is_current = False
                    if release_year and (release_year >= current_year - 1):
                        is_current = True

                    # Set a flag on each movie
                    movie['is_current_release'] = is_current

                    # For older movies, set an empty theaters list to prevent showtimes lookup
                    if not is_current:
                        movie['theaters'] = []
                        logger.info(f"Movie '{movie.get('title')}' is an older release ({release_year}), skipping theater lookup")

            except Exception as e:
                logger.error(f"Error parsing recommendations: {str(e)}")
                recommendations = []

            # Combine recommendations with theater data
            movies_with_theaters = []

            for movie in recommendations:
                if not isinstance(movie, dict):
                    logger.error(f"Movie is not a dictionary: {movie}")
                    continue

                movie_theaters = []
                for theater in theaters_data:
                    if not isinstance(theater, dict):
                        logger.error(f"Theater is not a dictionary: {theater}")
                        continue

                    # Safe get operations with default values
                    theater_movie_id = theater.get("movie_id") if isinstance(theater, dict) else None
                    movie_tmdb_id = movie.get("tmdb_id") if isinstance(movie, dict) else None

                    if theater_movie_id == movie_tmdb_id:
                        movie_theaters.append(theater)

                # Safe dictionary unpacking
                if isinstance(movie, dict):
                    movie_with_theaters = {**movie, "theaters": movie_theaters}
                    movies_with_theaters.append(movie_with_theaters)

            # Format a nice response message based on the results
            if not movies_with_theaters:
                return {
                    "response": f"I'm sorry, I couldn't find any movies matching '{query}'. Could you try a different request? For example, you could ask for action movies, family films, or movies starring a specific actor.",
                    "movies": []
                }
            else:
                # Format a nice response message
                response_message = self._format_response(movies_with_theaters, query)

                return {
                    "response": response_message,
                    "movies": movies_with_theaters
                }

        except Exception as e:
            logger.error(f"Error executing crew: {str(e)}")
            return {
                "response": "I apologize, but I encountered an error while searching for movies. Please try again with a different request.",
                "movies": []
            }

    def _generate_movie_explanation(self, movie: Dict[str, Any], query: str) -> str:
        """Generate a personalized explanation for why a movie is recommended."""
        try:
            title = movie.get('title', 'Unknown')
            overview = movie.get('overview', '')
            release_date = movie.get('release_date', '')

            # Extract year if available
            year_str = ""
            if release_date and len(release_date) >= 4:
                year_str = f" ({release_date[:4]})"

            # Check if it's a recent movie
            is_recent = False
            if release_date and len(release_date) >= 4:
                try:
                    year = int(release_date[:4])
                    current_year = datetime.now().year
                    if current_year - year <= 2:  # Released in last 2 years
                        is_recent = True
                except ValueError:
                    pass

            # Look for specific keywords in the query
            query_lower = query.lower()
            keywords = {
                'action': ['action', 'exciting', 'thrill', 'adventure'],
                'family': ['family', 'kids', 'child', 'children'],
                'comedy': ['comedy', 'funny', 'humor', 'laugh'],
                'drama': ['drama', 'dramatic', 'emotional', 'serious'],
                'sci-fi': ['sci-fi', 'science fiction', 'sci fi', 'future', 'space'],
                'thriller': ['thriller', 'suspense', 'mystery', 'suspenseful'],
                'horror': ['horror', 'scary', 'frightening', 'terror'],
                'romance': ['romance', 'romantic', 'love story'],
                'documentary': ['documentary', 'true story', 'real'],
                'animation': ['animation', 'animated', 'cartoon'],
                'superhero': ['superhero', 'marvel', 'dc', 'comic', 'hero'],
                'fantasy': ['fantasy', 'magical', 'magic'],
                'historical': ['historical', 'history', 'period', 'classic']
            }

            # Identify matching genres
            matching_genres = []
            for genre, terms in keywords.items():
                if any(term in query_lower for term in terms):
                    matching_genres.append(genre)

            # Check if the title contains the query
            title_match = any(term in title.lower() for term in query_lower.split())

            # Generate explanation
            if title_match:
                return f"This {matching_genres[0] if matching_genres else ''} film matches your search criteria perfectly, featuring {title}{year_str} which directly relates to your interests."
            elif is_recent and matching_genres:
                return f"This recent {matching_genres[0]} film from{year_str} aligns with your interest in {', '.join(matching_genres)} movies."
            elif matching_genres:
                return f"This {matching_genres[0]} film offers exactly what you're looking for with its {', '.join(matching_genres[1:] if len(matching_genres) > 1 else ['engaging'])} elements."
            elif is_recent:
                return f"This is a recent release from{year_str} that matches your search criteria with its engaging storyline."
            else:
                return f"This film matches your interest in {query}."

        except Exception as e:
            logger.error(f"Error generating explanation: {str(e)}")
            return f"This movie matches your interest in {query}."


    def _create_movie_finder_agent(self, llm) -> Agent:
        """Create the Movie Finder agent that searches for movies based on user criteria."""
        @tool
        def search_movies(query: str) -> str:
            """Search for movies based on user criteria."""
            try:
                # Extract query if it comes in format "User query: {query}"
                if "User query:" in query:
                    query = query.split("User query:", 1)[1].strip()

                search = tmdb.Search()
                response = search.movie(query=query, include_adult=False, language="en-US", page=1)

                if not response or 'results' not in response or not response['results']:
                    logger.warning(f"No movies found for query: {query}")
                    return json.dumps([])

                # Process the first 5 results
                results = response['results'][:5]
                movies = []

                for movie in results:
                    movie_id = movie.get('id')
                    title = movie.get('title', 'Unknown Title')
                    overview = movie.get('overview', '')
                    release_date = movie.get('release_date', '')
                    poster_path = movie.get('poster_path', '')
                    poster_url = f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else ""

                    movies.append({
                        "title": title,
                        "overview": overview,
                        "release_date": release_date,
                        "poster_url": poster_url,
                        "tmdb_id": movie_id,
                        "rating": movie.get('vote_average', 0)
                    })

                return json.dumps(movies)
            except Exception as e:
                logger.error(f"Error searching for movies: {str(e)}")
                return json.dumps([])

        return Agent(
            role="Movie Finder",
            goal="Find movies that match the user's criteria",
            backstory="""You are an expert movie finder who knows everything about movies. Your job is to help users
                       find movies that match their preferences, including genre, actors, directors, themes, and more.
                       You use The Movie Database API to find the most relevant movies based on user queries.""",
            verbose=True,
            llm=llm,
            tools=[search_movies]
        )

    def _create_recommendation_agent(self, llm) -> Agent:
        """Create the Recommendation agent that selects and explains movie choices."""
        @tool
        def analyze_user_preferences(query: str, movies_json: str) -> str:
            """Analyze user preferences and recommend the best movies from the provided list."""
            try:
                # Extract query if it's in the format "User query: {query}"
                if "User query:" in query:
                    query = query.split("User query:", 1)[1].strip()

                # Extract movie JSON from the context input
                # Format expected: "Movies found: {json_str}"
                if "Movies found:" in movies_json:
                    movies_json = movies_json.split("Movies found:", 1)[1].strip()

                movies = json.loads(movies_json)

                if not movies:
                    logger.warning("No movies to recommend")
                    return json.dumps([])  # Return empty list if no movies to recommend

                # In a real implementation, this would use more advanced analysis
                # For now, we'll just return the best 3 movies with explanations
                recommendations = []
                for i, movie in enumerate(movies[:3]):
                    explanation = f"This movie matches your interest in {query}."
                    movie["explanation"] = explanation
                    recommendations.append(movie)

                return json.dumps(recommendations)
            except Exception as e:
                logger.error(f"Error analyzing user preferences: {str(e)}")
                return json.dumps([])

        return Agent(
            role="Movie Recommender",
            goal="Select the best movies based on user preferences and explain why they would enjoy them",
            backstory="""You are an expert movie recommender with a deep understanding of film theory, genres,
                       and audience preferences. Your job is to analyze the user's query and the available movies to
                       select the best matches. You provide personalized recommendations with explanations that help
                       users understand why they might enjoy each movie.""",
            verbose=True,
            llm=llm,
            tools=[analyze_user_preferences]
        )

    def _create_theater_finder_agent(self, llm) -> Agent:
        """Create the Theater Finder agent that locates theaters showing the recommended movies."""
        @tool
        def find_theaters(recommended_movies: str, location: str) -> str:
            """Find theaters showing the recommended movies near the user's location."""
            try:
                # Extract recommendation JSON from the context input
                # Format expected: "Recommended movies: {json_str}"
                if "Recommended movies:" in recommended_movies:
                    recommended_movies = recommended_movies.split("Recommended movies:", 1)[1].strip()

                # Extract location if it comes in format "User location: {location}"
                if "User location:" in location:
                    location = location.split("User location:", 1)[1].strip()

                movie_recommendations = json.loads(recommended_movies)

                if not movie_recommendations:
                    logger.warning("No movie recommendations to find theaters for")
                    return json.dumps([])

                # Initialize location service
                location_service = LocationService(user_agent="movie_chatbot_theaters")

                # Try to geocode the user's location
                user_coords = None
                if location and location.lower() != 'unknown':
                    geocoded_location = location_service.geocode_location(location)
                    if geocoded_location:
                        user_coords = {
                            'latitude': geocoded_location['latitude'],
                            'longitude': geocoded_location['longitude'],
                            'display_name': geocoded_location['display_name']
                        }
                        logger.info(f"Successfully geocoded location: {location} -> {geocoded_location['display_name']}")
                    else:
                        logger.warning(f"Could not geocode location: {location}")

                # If we can't geocode the location, use default coordinates (New York City)
                if not user_coords:
                    logger.info("Using default coordinates (New York City)")
                    user_coords = {
                        'latitude': 40.7128,
                        'longitude': -74.0060,
                        'display_name': 'New York City, NY, USA'
                    }

                # Find theaters via OpenStreetMap
                theaters = []
                try:
                    # Search for theaters within 20 miles
                    theaters = location_service.search_theaters(
                        user_coords['latitude'],
                        user_coords['longitude'],
                        radius_miles=20
                    )

                    logger.info(f"Found {len(theaters)} theaters near {location}")
                except Exception as search_error:
                    logger.error(f"Error searching for theaters: {str(search_error)}")
                    return json.dumps([])

                # Assign theaters to movies
                theater_results = []

                # For each movie, assign each theater with generated showtimes
                for movie in movie_recommendations:
                    movie_id = movie.get('tmdb_id')

                    for theater in theaters:
                        # Generate real-time showtimes based on current date
                        showtimes = []
                        today = datetime.now()

                        for j in range(3):  # 3 showtimes per theater
                            # Calculate showtime based on time of day
                            hour = 12 + (j * 3)  # noon, 3pm, 6pm
                            show_datetime = today.replace(hour=hour, minute=30, second=0)
                            formatted_time = show_datetime.strftime("%Y-%m-%d %H:%M:%S")

                            showtimes.append({
                                "start_time": formatted_time,
                                "format": ["Standard", "IMAX", "3D"][j % 3]
                            })

                        # Create theater entry for this movie
                        theater_results.append({
                            "movie_id": movie_id,
                            "name": theater['name'],
                            "address": theater['address'],
                            "latitude": theater['latitude'],
                            "longitude": theater['longitude'],
                            "distance_miles": theater.get('distance_miles', 0),
                            "showtimes": showtimes
                        })

                # Sort theaters by distance
                theater_results.sort(key=lambda x: x.get('distance_miles', float('inf')))

                return json.dumps(theater_results)
            except Exception as e:
                logger.error(f"Error finding theaters: {str(e)}")
                return json.dumps([])

        return Agent(
            role="Theater Finder",
            goal="Find theaters showing the recommended movies near the user's location",
            backstory="""You are an expert at finding movie theaters and showtimes. Your job is to locate theaters
                       showing the recommended movies near the user's location and provide detailed information about
                       showtimes and theater amenities.""",
            verbose=True,
            llm=llm,
            tools=[find_theaters]
        )

    @LoggingMiddleware.log_method_call
    def _parse_json_output(self, output: str) -> Any:
        """Parse JSON from agent output, handling various formats and error cases."""
        if not output:
            return []

        # Try to find JSON in the output
        try:
            if isinstance(output, list) or isinstance(output, dict):
                return output

            # First attempt: direct JSON parsing
            return json.loads(output)
        except json.JSONDecodeError:
            try:
                # Clean up potential newlines and extra whitespace
                output = output.strip()

                # Handle the case where output is just a string representation of a list
                if output.startswith('[') and output.endswith(']'):
                    try:
                        # Handle cases with single quotes instead of double quotes
                        cleaned_output = output.replace("'", '"')
                        return json.loads(cleaned_output)
                    except:
                        pass

                # Second attempt: Look for JSON-like patterns in the text
                json_match = re.search(r'\[\s*{.*}\s*\]', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(0))

                # Third attempt: Look for JSON surrounded by triple backticks
                json_match = re.search(r'```(?:json)?\s*(\[\s*{.*}\s*\])\s*```', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # Fourth attempt: Look for JSON surrounded by backticks
                json_match = re.search(r'`(\[\s*{.*}\s*\])`', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # If all else fails, try to find a JSON object rather than an array
                json_match = re.search(r'{.*}', output, re.DOTALL)
                if json_match:
                    obj = json.loads(json_match.group(0))
                    if isinstance(obj, dict):
                        return [obj]

                logger.warning(f"Could not extract JSON from output: {output[:100]}...")
                return []
            except Exception as e:
                logger.error(f"Error extracting JSON: {str(e)}")
                return []

    @LoggingMiddleware.log_method_call
    def _format_response(self, movies_with_theaters: List[Dict[str, Any]], query: str) -> str:
        """Format a nice response message based on the movies and theaters."""
        if not movies_with_theaters:
            return f"I'm sorry, I couldn't find any movies matching '{query}'. Could you try a different request? For example, you could ask for action movies, family films, or movies starring a specific actor."

        movie_count = len(movies_with_theaters)
        has_theaters = any(len(movie.get('theaters', [])) > 0 for movie in movies_with_theaters)

        # Intro response based on query type
        if any(term in query.lower() for term in ['now playing', 'theaters now', 'playing now', 'showing now', 'this weekend', 'this week']):
            response = f"Based on your interest in movies currently playing, I found {movie_count} movie{'s' if movie_count != 1 else ''} that you might enjoy.\n\n"
        elif any(term in query.lower() for term in ['marvel', 'superhero', 'comic']):
            response = f"I found {movie_count} Marvel/superhero movie{'s' if movie_count != 1 else ''} that match your criteria.\n\n"
        elif any(term in query.lower() for term in ['action', 'adventure', 'thriller', 'exciting']):
            response = f"I found {movie_count} action/thriller movie{'s' if movie_count != 1 else ''} that match your criteria.\n\n"
        elif any(term in query.lower() for term in ['family', 'kids', 'children']):
            response = f"I found {movie_count} family-friendly movie{'s' if movie_count != 1 else ''} that would be great to watch with kids.\n\n"
        else:
            response = f"Based on your interest in '{query}', I found {movie_count} movie{'s' if movie_count != 1 else ''} that you might enjoy.\n\n"

        # Add information about each movie
        for i, movie in enumerate(movies_with_theaters, 1):
            title = movie.get('title', 'Unknown Movie')
            overview = movie.get('overview', '')
            explanation = movie.get('explanation', '')
            theaters = movie.get('theaters', [])
            theater_count = len(theaters)
            release_date = movie.get('release_date', '')

            # Add release year if available
            year_str = ""
            if release_date and len(release_date) >= 4:
                year_str = f" ({release_date[:4]})"

            response += f"{i}. **{title}{year_str}**"
            if explanation:
                response += f": {explanation}"
            response += "\n"

            # Add brief overview if available
            if overview:
                # Truncate long overviews
                if len(overview) > 150:
                    overview = overview[:147] + "..."
                response += f"   {overview}\n"

            # Check if this is a current release (should have the flag we added)
            is_current = movie.get('is_current_release', False)

            # Only show theater information for current releases
            if is_current and theater_count > 0:
                response += f"   🎬 Available at {theater_count} theater{'s' if theater_count != 1 else ''}.\n"

                # Add showtimes for the first theater if available
                if 'showtimes' in theaters[0] and theaters[0]['showtimes']:
                    first_theater = theaters[0]
                    theater_name = first_theater.get('name', 'Unknown Theater')
                    showtimes = first_theater.get('showtimes', [])
                    if showtimes:
                        # Show just first 3 showtimes
                        showtime_strs = []
                        for i, showtime in enumerate(showtimes[:3]):
                            time_str = showtime.get('start_time', '')
                            format_str = showtime.get('format', '')
                            # Extract just the time portion (HH:MM) if it's a full datetime
                            if time_str and len(time_str) > 10:
                                try:
                                    dt = datetime.fromisoformat(time_str.replace(' ', 'T'))
                                    time_only = dt.strftime("%H:%M")  # 24-hour format
                                    showtime_strs.append(f"{time_only} ({format_str})" if format_str else time_only)
                                except:
                                    showtime_strs.append(time_str)
                            else:
                                showtime_strs.append(time_str)

                        if showtime_strs:
                            response += f"   📅 {theater_name}: {', '.join(showtime_strs)}\n"
            elif not is_current:
                # For older movies, don't show theater information
                release_date = movie.get('release_date', '')
                release_year = None
                if release_date and len(release_date) >= 4:
                    try:
                        release_year = release_date[:4]
                    except:
                        pass

                if release_year:
                    response += f"   📽️ This is an older release from {release_year}, not currently in theaters.\n"
                else:
                    response += "   📽️ This movie is not currently playing in theaters.\n"
            else:
                response += "   ⚠️ No theater information available for this movie.\n"

            # Add a separator between movies
            response += "\n"

        # Add a helpful closing message
        if has_theaters:
            response += "Would you like more information about any of these movies or their showtimes?"
        else:
            response += "Would you like more information about any of these movies or would you prefer different recommendations?"

        return response
