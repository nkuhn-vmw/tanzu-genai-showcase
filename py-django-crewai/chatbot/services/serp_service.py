"""
SerpAPI service for the movie chatbot.
This module provides integration with SerpAPI for fetching real movie showtimes.
"""

import logging
import json
from typing import List, Dict, Any, Optional
from serpapi import GoogleSearch
from datetime import datetime, timedelta

# Configure logger
logger = logging.getLogger('chatbot.serp_service')

class SerpShowtimeService:
    """Service for fetching movie showtimes using SerpAPI."""

    def __init__(self, api_key: str):
        """Initialize the SerpAPI service.

        Args:
            api_key: SerpAPI API key
        """
        self.api_key = api_key

    def search_showtimes(self, movie_title: str, location: str, radius_miles: int = 25) -> List[Dict[str, Any]]:
        """Search for movie showtimes for a specific movie in a location.

        Args:
            movie_title: Title of the movie to search showtimes for
            location: Location to search showtimes in (city name or zip code)
            radius_miles: Search radius in miles (default: 25)

        Returns:
            List of theater dictionaries with showtimes
        """
        try:
            logger.info(f"Searching showtimes for '{movie_title}' in {location}")

            # Determine date range (today and the next 3 days)
            today = datetime.now()
            date_range = []
            for i in range(3):  # Today + next 2 days
                date = today + timedelta(days=i)
                # Format as YYYY-MM-DD
                date_range.append(date.strftime("%Y-%m-%d"))

            # Construct parameters for SerpAPI
            params = {
                "engine": "google_showtimes",
                "q": movie_title,
                "location": location,
                "hl": "en",
                "gl": "us",
                "api_key": self.api_key
            }

            # If radius_miles is provided, convert to km (SerpAPI uses km)
            if radius_miles:
                params["radius"] = int(radius_miles * 1.60934)  # miles to km

            # Execute the search
            search = GoogleSearch(params)
            results = search.get_dict()

            # Process and format the results
            theaters = self._parse_serp_results(results, movie_title)

            logger.info(f"Found {len(theaters)} theaters with showtimes for '{movie_title}' in {location}")
            return theaters

        except Exception as e:
            logger.error(f"Error searching showtimes: {str(e)}")
            return []

    def _parse_serp_results(self, results: Dict[str, Any], movie_title: str) -> List[Dict[str, Any]]:
        """Parse the SerpAPI results and format them for our application.

        Args:
            results: SerpAPI results dictionary
            movie_title: Title of the movie used for filtering results

        Returns:
            List of theater dictionaries with formatted showtimes
        """
        theaters = []

        try:
            # Check if we have any results
            if not results or 'showtimes' not in results:
                logger.warning(f"No showtimes found in SerpAPI results for '{movie_title}'")
                return []

            # Process each theater in the results
            for theater_data in results.get('showtimes', []):
                # Verify that this theater has the movie we're looking for
                if not self._theater_has_movie(theater_data, movie_title):
                    continue

                # Extract theater info
                name = theater_data.get('name', 'Unknown Theater')
                address = theater_data.get('address', '')

                # Get coordinates if available
                latitude = None
                longitude = None
                if 'gps_coordinates' in theater_data:
                    coords = theater_data.get('gps_coordinates', {})
                    latitude = coords.get('latitude')
                    longitude = coords.get('longitude')

                # Get distance if available
                distance_miles = None
                if 'distance' in theater_data:
                    distance_text = theater_data.get('distance', '')
                    # Extract numeric distance (e.g., "5.2 mi" -> 5.2)
                    try:
                        distance_miles = float(distance_text.split()[0])
                    except (ValueError, IndexError):
                        pass

                # Process movie showtimes
                showtimes = []
                for movie in theater_data.get('movies', []):
                    if self._normalize_title(movie.get('name', '')) == self._normalize_title(movie_title):
                        # Process showtimes for this movie
                        for showtime_data in movie.get('showtimes', []):
                            # Extract datetime
                            datetime_str = showtime_data.get('datetime')
                            if not datetime_str:
                                continue

                            # Extract format (e.g., "IMAX", "3D")
                            format_type = "Standard"
                            if 'amenities' in showtime_data:
                                amenities = showtime_data.get('amenities', [])
                                if amenities:
                                    if any('IMAX' in amenity for amenity in amenities):
                                        format_type = "IMAX"
                                    elif any('3D' in amenity for amenity in amenities):
                                        format_type = "3D"

                            showtime_info = {
                                "start_time": datetime_str,
                                "format": format_type
                            }
                            showtimes.append(showtime_info)

                # Only add theaters that have showtimes
                if showtimes:
                    theater_info = {
                        "name": name,
                        "address": address,
                        "latitude": latitude,
                        "longitude": longitude,
                        "distance_miles": distance_miles,
                        "showtimes": showtimes
                    }
                    theaters.append(theater_info)

        except Exception as parse_error:
            logger.error(f"Error parsing SerpAPI results: {str(parse_error)}")

        return theaters

    def _theater_has_movie(self, theater_data: Dict[str, Any], movie_title: str) -> bool:
        """Check if a theater has the movie we're looking for."""
        if 'movies' not in theater_data:
            return False

        movie_titles = [self._normalize_title(movie.get('name', '')) for movie in theater_data.get('movies', [])]
        return self._normalize_title(movie_title) in movie_titles

    def _normalize_title(self, title: str) -> str:
        """Normalize movie title for comparison by removing special characters and lowercasing."""
        # Remove special characters, spaces, and convert to lowercase
        import re
        normalized = re.sub(r'[^\w\s]', '', title.lower())
        normalized = normalized.replace(' ', '')
        return normalized
