"""
Tool for searching movies based on user criteria.
"""
import json
import logging
from datetime import datetime
from typing import Dict, Any
import tmdbsimple as tmdb
from crewai.tools import BaseTool
from pydantic import BaseModel, Field

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class SearchMoviesInput(BaseModel):
    """Input schema for SearchMoviesTool."""
    query: str = Field(default="", description="The search query for movies")

class SearchMoviesTool(BaseTool):
    """Tool for searching movies based on user criteria."""

    name: str = "search_movies_tool"
    description: str = "Search for movies based on user criteria."
    args_schema: type[SearchMoviesInput] = SearchMoviesInput

    def _run(self, query: str = "") -> str:
        """
        Search for movies based on user criteria.

        Args:
            query: The search query for movies

        Returns:
            JSON string containing movie results
        """
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

                            # Get full-size poster image
                            poster_url = ""
                            if poster_path:
                                poster_url = f"https://image.tmdb.org/t/p/original{poster_path}"

                                # Try to get additional images using the movie images endpoint
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

                            # Create movie dictionary
                            movies.append(self._create_movie_dict(
                                title=title,
                                overview=overview,
                                release_date=release_date,
                                poster_url=poster_url,
                                tmdb_id=movie_id,
                                rating=movie.get('vote_average', 0),
                                is_current_release=is_current_release
                            ))
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

                        # Create movie dictionary
                        movies.append(self._create_movie_dict(
                            title=title,
                            overview=overview,
                            release_date=release_date,
                            poster_url=poster_url,
                            tmdb_id=movie_id,
                            rating=movie.get('vote_average', 0)
                        ))

            # If still no movies and we have genres, try discover API
            if not movies and genres:
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

                            # Create movie dictionary
                            movies.append(self._create_movie_dict(
                                title=title,
                                overview=overview,
                                release_date=release_date,
                                poster_url=poster_url,
                                tmdb_id=movie_id,
                                rating=movie.get('vote_average', 0)
                            ))
                except Exception as e:
                    logger.error(f"Error with discover API: {str(e)}")

            if not movies:
                logger.warning(f"No movies found for query: {search_query}")

            return json.dumps(movies)
        except Exception as e:
            logger.error(f"Error searching for movies: {str(e)}")
            return json.dumps([])

    def _create_movie_dict(self, **kwargs) -> Dict[str, Any]:
        """
        Create a standardized movie dictionary with provided fields.

        Args:
            **kwargs: Movie fields including title, overview, etc.

        Returns:
            Standardized movie dictionary
        """
        # Start with default values
        movie_dict = {
            "title": "Unknown Title",
            "overview": "",
            "release_date": "",
            "poster_url": "",
            "tmdb_id": None,
            "rating": 0,
            "is_current_release": False
        }

        # Update with provided values
        movie_dict.update(kwargs)

        return movie_dict
