"""
Manager for the movie recommendation crew.
"""
import logging
import json
from datetime import datetime
from typing import List, Dict, Any, Optional, Union

import tmdbsimple as tmdb
from crewai import Task, Crew
from langchain_openai import ChatOpenAI

from .agents.movie_finder_agent import MovieFinderAgent
from .agents.recommendation_agent import RecommendationAgent
from .agents.theater_finder_agent import TheaterFinderAgent
from .tools.search_movies_tool import SearchMoviesTool
from .tools.analyze_preferences_tool import AnalyzePreferencesTool
from .tools.find_theaters_tool import FindTheatersTool
from .tools.enhance_images_tool import EnhanceMovieImagesTool
from .utils.logging_middleware import LoggingMiddleware
from .utils.json_parser import JsonParser
from .utils.response_formatter import ResponseFormatter

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class MovieCrewManager:
    """Manager for the movie recommendation crew."""

    def __init__(
        self,
        api_key: str,
        base_url: Optional[str] = None,
        model: str = "gpt-4o-mini",
        tmdb_api_key: Optional[str] = None,
        user_location: Optional[str] = None,
        user_ip: Optional[str] = None
    ):
        """
        Initialize the MovieCrewManager.

        Args:
            api_key: LLM API key
            base_url: Optional custom endpoint URL for the LLM API
            model: Model name to use
            tmdb_api_key: API key for The Movie Database (TMDb)
            user_location: Optional user location string
            user_ip: Optional user IP address for geolocation
        """
        self.api_key = api_key
        self.base_url = base_url
        self.model = model
        self.tmdb_api_key = tmdb_api_key
        self.user_location = user_location
        self.user_ip = user_ip

        # Configure TMDb API if key is provided
        if tmdb_api_key:
            tmdb.API_KEY = tmdb_api_key

    @LoggingMiddleware.log_method_call
    def create_llm(self, temperature: float = 0.5) -> ChatOpenAI:
        """
        Create an LLM instance with the specified configuration.

        Args:
            temperature: Temperature parameter for the LLM

        Returns:
            Configured ChatOpenAI instance
        """
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
            movie_finder = MovieFinderAgent.create(llm)

            logger.debug("Creating recommendation agent")
            recommender = RecommendationAgent.create(llm)

            logger.debug("Creating theater finder agent")
            theater_finder = TheaterFinderAgent.create(llm)

            logger.info("All agents created successfully")
        except Exception as agent_error:
            logger.error(f"Failed to initialize one or more agents: {str(agent_error)}")
            logger.exception(agent_error)
            # Provide a fallback response
            return {
                "response": "I encountered a technical issue while setting up the movie recommendation system. Please try again later.",
                "movies": []
            }

        # Create tools for each task
        search_tool = SearchMoviesTool()
        analyze_tool = AnalyzePreferencesTool()
        
        # Set up the theater finder tool with location
        theater_finder_tool = FindTheatersTool(user_location=self.user_location)
        theater_finder_tool.user_ip = self.user_ip
        
        # Set up the image enhancement tool
        enhance_images_tool = EnhanceMovieImagesTool(tmdb_api_key=self.tmdb_api_key)

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
            tools=[theater_finder_tool]
        )

        # Create the crew
        crew = Crew(
            agents=[movie_finder, recommender, theater_finder],
            tasks=[find_movies_task, recommend_movies_task, find_theaters_task],
            verbose=True
        )

        # Debug log the task structure
        logger.info(f"Task definitions: {[t.description for t in crew.tasks]}")

        # Execute the crew
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

            # Save successful execution stats
            logger.info({
                "event": "crew_execution_success",
                "execution_time": execution_time,
                "query_length": len(query) if query else 0,
                "result_length": len(str(result)) if result else 0
            })

            # Additional check if result is None
            if result is None:
                logger.warning(f"Crew execution returned None")
                return {
                    "response": f"I found some movie options for '{query}' but couldn't retrieve all the details. Here's what I can tell you.",
                    "movies": []
                }

            # Helper function to safely extract task output
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

            # Parse outputs using the JsonParser utility
            theaters_data = JsonParser.parse_json_output(find_theaters_output)
            recommendations = JsonParser.parse_json_output(recommend_movies_output)
            
            # Enhance movie data with high-quality images from TMDB
            if recommendations and self.tmdb_api_key:
                try:
                    # Convert recommendations to JSON string for the tool
                    recommendations_json = json.dumps(recommendations)
                    
                    # Run the enhancement tool
                    enhanced_json = enhance_images_tool._run(recommendations_json)
                    
                    # Parse the enhanced data
                    enhanced_recommendations = JsonParser.parse_json_output(enhanced_json)
                    
                    if enhanced_recommendations:
                        logger.info(f"Successfully enhanced {len(enhanced_recommendations)} movies with images")
                        recommendations = enhanced_recommendations
                    else:
                        logger.warning("Movie image enhancement returned empty results")
                except Exception as enhancement_error:
                    logger.error(f"Error enhancing movie images: {str(enhancement_error)}")
                    logger.exception(enhancement_error)
                    # Continue with unenhanced recommendations
            else:
                logger.info("Skipping image enhancement: No recommendations or TMDB API key")

            # Process recommendations for current releases
            self._process_current_releases(recommendations)

            # Combine recommendations with theater data
            movies_with_theaters = self._combine_movies_and_theaters(recommendations, theaters_data)

            # Generate response
            if not movies_with_theaters:
                return {
                    "response": f"I'm sorry, I couldn't find any movies matching '{query}'. Could you try a different request? For example, you could ask for action movies, family films, or movies starring a specific actor.",
                    "movies": []
                }
            else:
                # Format response using the ResponseFormatter utility
                response_message = ResponseFormatter.format_response(movies_with_theaters, query)

                return {
                    "response": response_message,
                    "movies": movies_with_theaters
                }

        except Exception as e:
            logger.error(f"Error executing crew: {str(e)}")
            logger.exception(e)
            return {
                "response": "I apologize, but I encountered an error while searching for movies. Please try again with a different request.",
                "movies": []
            }

    def _process_current_releases(self, recommendations: List[Dict[str, Any]]) -> None:
        """
        Process recommendations to identify current releases.

        Args:
            recommendations: List of movie recommendations
        """
        # Check which movies are current/first-run vs older movies
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

    def _combine_movies_and_theaters(self,
                                    recommendations: List[Dict[str, Any]],
                                    theaters_data: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Combine movie recommendations with theater data.

        Args:
            recommendations: List of movie recommendations
            theaters_data: List of theaters with showtimes

        Returns:
            Combined list of movies with theater information
        """
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

        return movies_with_theaters
