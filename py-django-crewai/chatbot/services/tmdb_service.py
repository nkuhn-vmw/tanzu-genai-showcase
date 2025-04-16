"""
TMDB API Service for fetching movie data and images.
"""

import logging
import requests
import json
from typing import Dict, List, Optional, Any, Union
from urllib.parse import urljoin
import os

# Get the logger
logger = logging.getLogger('chatbot.tmdb_service')

class TMDBService:
    """Service for interacting with The Movie Database (TMDB) API."""
    
    # TMDB API base URL
    BASE_URL = "https://api.themoviedb.org/3/"
    
    # Image base URLs for different sizes
    IMAGE_BASE_URL = "https://image.tmdb.org/t/p/"
    POSTER_SIZES = {
        'small': 'w200',
        'medium': 'w500',
        'large': 'w780',
        'original': 'original'
    }
    
    def __init__(self, api_key: Optional[str] = None):
        """
        Initialize the TMDB service.
        
        Args:
            api_key: TMDB API key. If not provided, will try to get from environment.
        """
        # Get API key from parameter or environment
        self.api_key = api_key or os.environ.get('TMDB_API_KEY')
        
        if not self.api_key:
            logger.warning("No TMDB API key provided. API calls will fail.")
            
        # Configure session for requests
        self.session = requests.Session()
        
    def _make_request(self, endpoint: str, params: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """
        Make a request to the TMDB API.
        
        Args:
            endpoint: API endpoint path (without base URL)
            params: Optional query parameters
            
        Returns:
            JSON response as dictionary
            
        Raises:
            Exception: If the request fails
        """
        # Ensure endpoint doesn't start with a slash
        if endpoint.startswith('/'):
            endpoint = endpoint[1:]
            
        # Build full URL
        url = urljoin(self.BASE_URL, endpoint)
        
        # Prepare parameters with API key
        request_params = {'api_key': self.api_key}
        if params:
            request_params.update(params)
            
        try:
            response = self.session.get(url, params=request_params)
            response.raise_for_status()  # Raise exception for 4XX/5XX status codes
            return response.json()
        except requests.exceptions.RequestException as e:
            logger.error(f"Error making request to TMDB API: {str(e)}")
            logger.exception(e)
            raise
            
    def get_movie_details(self, movie_id: Union[str, int]) -> Dict[str, Any]:
        """
        Get detailed information about a movie.
        
        Args:
            movie_id: TMDB movie ID
            
        Returns:
            Movie details as dictionary
        """
        endpoint = f"movie/{movie_id}"
        try:
            return self._make_request(endpoint)
        except Exception as e:
            logger.error(f"Error getting movie details for ID {movie_id}: {str(e)}")
            return {}
            
    def get_movie_images(self, movie_id: Union[str, int]) -> Dict[str, Any]:
        """
        Get images for a movie.
        
        Args:
            movie_id: TMDB movie ID
            
        Returns:
            Dictionary containing posters, backdrops, and logos
        """
        endpoint = f"movie/{movie_id}/images"
        try:
            return self._make_request(endpoint)
        except Exception as e:
            logger.error(f"Error getting movie images for ID {movie_id}: {str(e)}")
            return {'posters': [], 'backdrops': [], 'logos': []}
            
    def get_collection_images(self, collection_id: Union[str, int]) -> Dict[str, Any]:
        """
        Get images for a collection (movie franchise).
        
        Args:
            collection_id: TMDB collection ID
            
        Returns:
            Dictionary containing posters and backdrops
        """
        endpoint = f"collection/{collection_id}/images"
        try:
            return self._make_request(endpoint)
        except Exception as e:
            logger.error(f"Error getting collection images for ID {collection_id}: {str(e)}")
            return {'posters': [], 'backdrops': []}
            
    def search_movies(self, query: str, page: int = 1, include_adult: bool = False) -> Dict[str, Any]:
        """
        Search for movies.
        
        Args:
            query: Search query
            page: Page number for pagination
            include_adult: Whether to include adult content
            
        Returns:
            Search results
        """
        endpoint = "search/movie"
        params = {
            'query': query,
            'page': page,
            'include_adult': include_adult,
            'language': 'en-US'
        }
        
        try:
            return self._make_request(endpoint, params)
        except Exception as e:
            logger.error(f"Error searching for movies with query '{query}': {str(e)}")
            return {'results': []}
            
    def get_now_playing(self, page: int = 1) -> Dict[str, Any]:
        """
        Get movies currently playing in theaters.
        
        Args:
            page: Page number for pagination
            
        Returns:
            List of movies now playing
        """
        endpoint = "movie/now_playing"
        params = {
            'page': page,
            'language': 'en-US'
        }
        
        try:
            return self._make_request(endpoint, params)
        except Exception as e:
            logger.error(f"Error getting now playing movies: {str(e)}")
            return {'results': []}
            
    def get_best_poster_url(self, movie_id: Union[str, int], size: str = 'original') -> str:
        """
        Get the best available poster URL for a movie.
        
        Args:
            movie_id: TMDB movie ID
            size: Desired poster size ('small', 'medium', 'large', 'original')
            
        Returns:
            URL to the best poster image, or empty string if none found
        """
        # Validate size parameter
        if size not in self.POSTER_SIZES:
            size = 'original'
            
        size_path = self.POSTER_SIZES[size]
        
        try:
            # Get movie images
            images = self.get_movie_images(movie_id)
            
            # If we have posters, return the first one (usually the primary poster)
            if images and 'posters' in images and images['posters']:
                poster = images['posters'][0]  # Use the first poster
                return f"{self.IMAGE_BASE_URL}{size_path}{poster['file_path']}"
                
            # If no posters in images response, try the movie details which should have a poster_path
            movie = self.get_movie_details(movie_id)
            if movie and 'poster_path' in movie and movie['poster_path']:
                return f"{self.IMAGE_BASE_URL}{size_path}{movie['poster_path']}"
                
            # No poster found
            return ""
            
        except Exception as e:
            logger.error(f"Error getting best poster URL for movie ID {movie_id}: {str(e)}")
            return ""
            
    def enhance_movie_data(self, movie_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Enhance movie data with additional information and high-quality images.
        
        Args:
            movie_data: Movie data dictionary with at least a tmdb_id field
            
        Returns:
            Enhanced movie data with additional fields
        """
        # Make a copy of the input data
        enhanced_data = dict(movie_data)
        
        # If no TMDB ID, we can't enhance
        if 'tmdb_id' not in enhanced_data or not enhanced_data['tmdb_id']:
            return enhanced_data
            
        movie_id = enhanced_data['tmdb_id']
        
        try:
            # Get movie details for additional information
            movie_details = self.get_movie_details(movie_id)
            
            # Add additional fields if available
            if movie_details:
                # Update poster with high-quality version
                if 'poster_path' in movie_details and movie_details['poster_path']:
                    enhanced_data['poster_url'] = f"{self.IMAGE_BASE_URL}original{movie_details['poster_path']}"
                    
                # Add backdrop if available
                if 'backdrop_path' in movie_details and movie_details['backdrop_path']:
                    enhanced_data['backdrop_url'] = f"{self.IMAGE_BASE_URL}original{movie_details['backdrop_path']}"
                    
                # Add genres if available
                if 'genres' in movie_details and movie_details['genres']:
                    enhanced_data['genres'] = [genre['name'] for genre in movie_details['genres']]
                    
                # Add more detailed information
                for field in ['tagline', 'runtime', 'vote_count', 'status', 'homepage']:
                    if field in movie_details and movie_details[field]:
                        enhanced_data[field] = movie_details[field]
                        
            # Try to get better poster from images endpoint
            images = self.get_movie_images(movie_id)
            if images and 'posters' in images and images['posters']:
                # Use the first poster (usually the primary one)
                enhanced_data['poster_url'] = f"{self.IMAGE_BASE_URL}original{images['posters'][0]['file_path']}"
                
                # Add additional poster URLs at different sizes
                enhanced_data['poster_urls'] = {
                    size: f"{self.IMAGE_BASE_URL}{self.POSTER_SIZES[size]}{images['posters'][0]['file_path']}"
                    for size in self.POSTER_SIZES
                }
                
                # Add a list of all poster URLs if more than one is available
                if len(images['posters']) > 1:
                    enhanced_data['all_posters'] = [
                        f"{self.IMAGE_BASE_URL}original{poster['file_path']}"
                        for poster in images['posters'][:5]  # Limit to first 5 posters
                    ]
                    
            return enhanced_data
            
        except Exception as e:
            logger.error(f"Error enhancing movie data for ID {movie_id}: {str(e)}")
            return enhanced_data
