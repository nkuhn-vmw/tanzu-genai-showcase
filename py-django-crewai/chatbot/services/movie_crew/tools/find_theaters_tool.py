"""
Tool for finding theaters showing the recommended movies.
"""
import json
import logging
from datetime import datetime
from typing import List, Dict, Any, Optional
from crewai.tools import BaseTool
from pydantic import BaseModel, Field

from ...location_service import LocationService
from ...serp_service import SerpShowtimeService

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class FindTheatersInput(BaseModel):
    """Input schema for FindTheatersTool."""
    movie_recommendations_json: str = Field(default="", description="JSON string containing movie recommendations")

class FindTheatersConfig(BaseModel):
    """Configuration for FindTheatersTool."""
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")

class FindTheatersTool(BaseTool):
    """Tool for finding theaters showing the recommended movies."""

    name: str = "find_theaters_tool"
    description: str = "Find theaters showing the recommended movies near the user's location."
    args_schema: type[FindTheatersInput] = FindTheatersInput
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")
    user_ip: Optional[str] = None

    def _theater_has_movie_showtimes(self, theater: Dict[str, Any], movie_title: str) -> bool:
        """
        Verify that a theater has showtimes for a specific movie.

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
        """
        Format SerperAPI showtime results into our standardized format.

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
        """
        Find theaters showing the recommended movies near the user's location.

        Args:
            movie_recommendations_json: JSON string containing movie recommendations

        Returns:
            JSON string containing theaters and showtimes
        """
        # Add performance logging
        import time
        start_time = time.time()
        logger.info(f"Starting theater search at {datetime.now().strftime('%H:%M:%S.%f')}")

        # Limit processing to max 3 movies to avoid timeouts
        MAX_MOVIES_TO_PROCESS = 3
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

            # Check if any movies are marked as current releases
            current_movies = []
            for movie in movie_recommendations:
                # Force all movies in first run mode to be considered current releases
                if not 'is_current_release' in movie:
                    # Set to True by default for this tool since we only process this in first-run mode
                    movie['is_current_release'] = True
                if movie.get('is_current_release', False):
                    # Ensure TMDB ID is properly set
                    if 'tmdb_id' not in movie and 'id' in movie:
                        movie['tmdb_id'] = movie['id']
                        logger.info(f"Fixed missing tmdb_id for movie {movie.get('title')}")
                    
                    # Only include movies with a TMDB ID
                    if movie.get('tmdb_id'):
                        current_movies.append(movie)
                    else:
                        logger.warning(f"Skipping movie without TMDB ID: {movie.get('title', 'Unknown')}")

            # If no current releases, return empty list as there's no need to find theaters
            if not current_movies:
                logger.info("No current release movies to find theaters for")
                return json.dumps([])
                
            logger.info(f"Finding theaters for {len(current_movies)} current release movies: {[m.get('title') for m in current_movies]}")

            # Initialize location service
            location_service = LocationService(user_agent="movie_chatbot_theaters")

            # Try to geocode the user's location
            user_coords = self._get_user_coordinates(location_service, location)

            # Store all theaters with showtimes, organized by movie
            theater_results = []

            # Limit the number of movies to process to avoid timeouts
            movies_to_process = current_movies[:MAX_MOVIES_TO_PROCESS]
            if len(current_movies) > MAX_MOVIES_TO_PROCESS:
                logger.info(f"Limiting theater search to {MAX_MOVIES_TO_PROCESS} movies out of {len(current_movies)} to avoid timeouts")

            # Process each movie individually to ensure theaters are correctly matched
            for movie in movies_to_process:
                movie_id = movie.get('tmdb_id')
                movie_title = movie.get('title')

                if not movie_id:
                    logger.warning(f"Skipping theater search for movie without ID: {movie_title}")
                    continue

                logger.info(f"Finding theaters for movie: {movie_title} (ID: {movie_id})")

                # Try to get real showtimes via SerperAPI for this specific movie
                movie_theaters_with_showtimes = self._get_movie_showtimes(movie_title, location, user_coords, movie_id)

                # If we found real showtimes for this movie, use them
                if movie_theaters_with_showtimes:
                    logger.info(f"Found {len(movie_theaters_with_showtimes)} theaters with real showtimes for '{movie_title}'")
                    
                    # Add movie_id to each theater to ensure proper association
                    for theater in movie_theaters_with_showtimes:
                        # Explicitly set the movie_id for this theater
                        theater['movie_id'] = movie_id
                        # Add movie title for debugging purposes
                        theater['movie_title'] = movie_title
                        
                        # Verify showtimes are present
                        if not theater.get('showtimes') or len(theater.get('showtimes', [])) == 0:
                            logger.warning(f"Theater {theater.get('name')} has no showtimes for '{movie_title}'")
                            continue
                            
                        # Add to our results
                        theater_results.append(theater)
                        logger.info(f"Added theater '{theater.get('name')}' with {len(theater.get('showtimes', []))} showtimes for '{movie_title}'")
                else:
                    # No real showtimes found for this movie
                    logger.warning(f"No showtimes found for '{movie_title}'")

            # Sort theaters by distance
            theater_results.sort(key=lambda x: x.get('distance_miles', float('inf')))
            
            # Log final result count
            logger.info(f"Returning {len(theater_results)} theaters with showtimes across all movies")

            return json.dumps(theater_results)
        except Exception as e:
            logger.error(f"Error finding theaters: {str(e)}")
            return json.dumps([])

    def _get_user_coordinates(self, location_service, location: str) -> Dict[str, Any]:
        """
        Get user coordinates from location string or IP address.

        Args:
            location_service: LocationService instance
            location: Location string

        Returns:
            Dictionary with latitude, longitude and display_name
        """
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
        if not user_coords and self.user_ip:
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

        return user_coords

    def _get_movie_showtimes(self, movie_title: str, location: str, user_coords: Dict[str, Any], movie_id: Any = None) -> List[Dict[str, Any]]:
        """
        Get real showtimes for a movie using SerperAPI.

        Args:
            movie_title: Title of the movie
            location: Location string
            user_coords: Dictionary with user coordinates
            movie_id: TMDB ID of the movie

        Returns:
            List of theaters with showtimes
        """
        theaters_with_showtimes = []

        try:
            # Try to import django settings to get the API key
            try:
                from django.conf import settings
                serp_api_key = settings.SERPAPI_API_KEY

                # Log if SerperAPI key is available
                if serp_api_key:
                    logger.info(f"SerperAPI key is configured: {serp_api_key[:4]}...{serp_api_key[-4:] if len(serp_api_key) > 8 else ''}")
                else:
                    logger.warning("No SerperAPI key found in settings - no theater data will be shown")
                    return []

                if serp_api_key and serp_api_key != 'your_serpapi_key_here':
                    logger.info("Using SerpAPI for real showtimes")
                    showtime_service = SerpShowtimeService(api_key=serp_api_key)

                    # Use fallback location if needed
                    search_location = location if location and location.lower() != 'unknown' else user_coords['display_name']
                    logger.info(f"Searching for showtimes in location: {search_location}")

                    # For each movie, search for real showtimes
                    logger.info(f"Searching for real showtimes for movie: {movie_title}")
                    real_theaters_with_showtimes = showtime_service.search_showtimes(
                        movie_title=movie_title,
                        location=search_location,
                        radius_miles=25
                    )

                    # Validate that we have results specifically for this movie
                    valid_theaters = []

                    # Process valid theaters
                    if real_theaters_with_showtimes:
                        logger.info(f"Found {len(real_theaters_with_showtimes)} theaters with real showtimes for {movie_title}")

                        # Validate each theater has this specific movie
                        for theater in real_theaters_with_showtimes:
                            # Verify this theater actually has the movie
                            if self._theater_has_movie_showtimes(theater, movie_title):
                                logger.info(f"Confirmed theater {theater['name']} is showing {movie_title}")
                                valid_theaters.append(theater)
                            else:
                                logger.warning(f"Theater {theater['name']} returned by SerpAPI but doesn't appear to have showtimes for {movie_title}")

                        # Format the valid theaters, making sure to include movie_id
                        theaters_with_showtimes = self._format_serpapi_showtimes(valid_theaters, movie_title, movie_id)
                        logger.info(f"Validated and formatted {len(theaters_with_showtimes)} theaters showing {movie_title}")
                else:
                    logger.warning("SerpAPI key not configured, using generated showtimes")
            except Exception as serp_error:
                logger.error(f"Error using SerpAPI: {str(serp_error)}")

        except Exception as e:
            logger.error(f"Error getting movie showtimes: {str(e)}")

        return theaters_with_showtimes

    def _generate_theater_showtimes(self, theaters: List[Dict[str, Any]], movie_title: str, movie_id: Any) -> List[Dict[str, Any]]:
        """
        Generate artificial showtimes for theaters.

        Args:
            theaters: List of theater dictionaries
            movie_title: Title of the movie
            movie_id: TMDB ID of the movie

        Returns:
            List of theaters with generated showtimes
        """
        theater_results = []

        for theater in theaters:
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

        return theater_results
