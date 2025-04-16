"""
Tool for enhancing movie images using TMDB API.
"""
import json
import logging
from typing import Dict, List, Any
import tmdbsimple as tmdb
from crewai.tools import BaseTool
from pydantic import BaseModel, Field

from ...tmdb_service import TMDBService

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class EnhanceMoviesInput(BaseModel):
    """Input schema for EnhanceMovieImagesTool."""
    movies_json: str = Field(default="", description="JSON string containing movies to enhance with better images")

class EnhanceMovieImagesTool(BaseTool):
    """Tool for enhancing movie images with high-quality versions from TMDB."""
    
    name: str = "enhance_movie_images_tool"
    description: str = "Enhance movies with high-quality poster images and additional metadata from TMDB."
    args_schema: type[EnhanceMoviesInput] = EnhanceMoviesInput
    tmdb_api_key: str = Field(default="", description="TMDB API key")

    def _run(self, movies_json: str = "") -> str:
        """
        Enhance movies with high-quality images from TMDB.
        
        Args:
            movies_json: JSON string containing movies to enhance
            
        Returns:
            JSON string containing enhanced movies
        """
        try:
            # Default to empty list if the input is empty
            if not movies_json:
                movies_json = "[]"

            # Parse the input JSON
            movies = json.loads(movies_json)

            if not movies:
                logger.warning("No movies to enhance")
                return json.dumps([])  # Return empty list if no movies to enhance

            # Initialize TMDB service
            tmdb_service = TMDBService(api_key=self.tmdb_api_key)
            
            # Enhance each movie
            enhanced_movies = []
            for movie in movies:
                if not movie.get('tmdb_id'):
                    logger.warning(f"Movie {movie.get('title', 'Unknown')} has no TMDB ID, skipping enhancement")
                    enhanced_movies.append(movie)
                    continue
                
                try:
                    enhanced_movie = tmdb_service.enhance_movie_data(movie)
                    enhanced_movies.append(enhanced_movie)
                    logger.info(f"Enhanced movie {movie.get('title', 'Unknown')} with TMDB data")
                except Exception as movie_error:
                    logger.error(f"Error enhancing movie {movie.get('title', 'Unknown')}: {str(movie_error)}")
                    enhanced_movies.append(movie)  # Keep original movie data

            return json.dumps(enhanced_movies)
        except Exception as e:
            logger.error(f"Error enhancing movies with images: {str(e)}")
            logger.exception(e)
            return movies_json  # Return original data on error
