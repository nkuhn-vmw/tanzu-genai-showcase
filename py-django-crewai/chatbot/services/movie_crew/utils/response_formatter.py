"""
Response formatter for the movie crew.
"""
import logging
from datetime import datetime
from typing import List, Dict, Any

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class ResponseFormatter:
    """Formatter for response messages from the movie crew."""

    @staticmethod
    def format_response(movies_with_theaters: List[Dict[str, Any]], query: str) -> str:
        """
        Format a nice response message based on the movies and theaters.

        Args:
            movies_with_theaters: List of movie dictionaries with theater information
            query: The original user query

        Returns:
            Formatted response message
        """
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
                if theaters[0].get('showtimes') and len(theaters[0]['showtimes']) > 0:
                    first_theater = theaters[0]
                    theater_name = first_theater.get('name', 'Unknown Theater')
                    showtimes = first_theater.get('showtimes', [])
                    
                    # Show just first 3 showtimes
                    showtime_strs = []
                    for i, showtime in enumerate(showtimes[:3]):
                        time_str = showtime.get('start_time', '')
                        format_str = showtime.get('format', '')
                        # Extract just the time portion (HH:MM) if it's a full datetime
                        if time_str and len(time_str) > 10:
                            try:
                                dt = datetime.fromisoformat(time_str.replace(' ', 'T').replace('Z', '+00:00'))
                                time_only = dt.strftime("%H:%M")  # 24-hour format as requested
                                showtime_strs.append(f"{time_only} ({format_str})" if format_str else time_only)
                            except Exception as e:
                                logger.warning(f"Error parsing datetime '{time_str}': {str(e)}")
                                showtime_strs.append(time_str)
                        else:
                            showtime_strs.append(time_str)

                    if showtime_strs:
                        response += f"   📅 {theater_name}: {', '.join(showtime_strs)}\n"
                    else:
                        response += f"   📅 {theater_name}: Call theater for showtimes\n"
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
                    response += f"   📽️ This is a {release_year} release, not currently playing in theaters.\n"
                else:
                    response += "   📽️ This movie is not currently playing in theaters.\n"
            else:
                # Current release but no theaters found
                response += "   ⚠️ No theater information available for this current release. You may need to check local theater websites for showtimes.\n"

            # Add a separator between movies
            response += "\n"

        # Add a helpful closing message
        if has_theaters:
            response += "Would you like more information about any of these movies or their showtimes?"
        else:
            response += "Would you like more information about any of these movies or would you prefer different recommendations?"

        return response

    @staticmethod
    def generate_movie_explanation(movie: Dict[str, Any], query: str) -> str:
        """
        Generate a personalized explanation for why a movie is recommended.

        Args:
            movie: Movie dictionary with details
            query: Original user query

        Returns:
            Explanation string
        """
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
