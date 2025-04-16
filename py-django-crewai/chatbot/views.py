from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from .models import Conversation, Message, MovieRecommendation, Theater, Showtime
from .services.movie_crew import MovieCrewManager
import json
import logging
import traceback

logger = logging.getLogger('chatbot')

def index(request):
    """Render the chatbot interface."""
    # Create a new conversation or get the most recent one
    conversation_id = request.session.get('conversation_id')

    if conversation_id:
        try:
            conversation = Conversation.objects.get(id=conversation_id)
        except Conversation.DoesNotExist:
            conversation = Conversation.objects.create()
            request.session['conversation_id'] = conversation.id
    else:
        conversation = Conversation.objects.create()
        request.session['conversation_id'] = conversation.id

    # Get messages for the conversation
    messages = conversation.messages.all()

    # Get recommendations for the conversation
    recommendations = conversation.recommendations.all()

    # If no messages yet, add a welcome message
    if not messages:
        welcome_message = Message.objects.create(
            conversation=conversation,
            sender='bot',
            content="Hello! I'm your movie chatbot assistant. Tell me what kind of movie you're in the mood for, and I can recommend options and show you where they're playing nearby. For example, you could say 'I want to see a thriller' or 'Show me family movies playing this weekend'."
        )

    return render(request, 'chatbot/index.html', {
        'conversation': conversation,
        'messages': messages,
        'recommendations': recommendations,
    })

@csrf_exempt
def send_message(request):
    """Process a message sent by the user and return the chatbot's response."""
    if request.method == 'POST':
        try:
            logger.info("=== Processing new chat message ===")
            # Get conversation or create new one
            conversation_id = request.session.get('conversation_id')

            if not conversation_id:
                logger.info("Creating new conversation")
                conversation = Conversation.objects.create()
                request.session['conversation_id'] = conversation.id
                logger.info(f"New conversation created with ID: {conversation.id}")
            else:
                logger.info(f"Using existing conversation with ID: {conversation_id}")
                conversation = Conversation.objects.get(id=conversation_id)

            # Robust JSON parsing with comprehensive logging
            try:
                # First, log the raw request body
                raw_body = request.body.decode('utf-8')
                logger.debug(f"Raw request body: {raw_body}")
                logger.debug(f"Request method: {request.method}")
                logger.debug(f"Request content type: {request.content_type}")
                logger.debug(f"Request headers: {dict(request.headers)}")

                # Try parsing with multiple strategies
                try:
                    # First, standard json.loads
                    data = json.loads(raw_body)
                except json.JSONDecodeError:
                    # Handle common JSON parsing issues
                    try:
                        # Try parsing with single quotes or other variations
                        data = json.loads(raw_body.replace("'", '"'))
                    except Exception as parse_error:
                        # If all parsing fails, try extracting message by simple string operations
                        if raw_body.startswith('"') and raw_body.endswith('"'):
                            data = {"message": raw_body.strip('"')}
                            logger.warning("Received message as a raw string, converted to dictionary")
                        else:
                            logger.error(f"Failed to parse request body: {parse_error}")
                            return JsonResponse({
                                'status': 'error',
                                'message': 'Invalid request format. Could not parse message.'
                            }, status=400)

                # Ensure data is a dictionary
                if not isinstance(data, dict):
                    logger.error(f"Request data is not a dictionary after parsing: {data}")
                    return JsonResponse({
                        'status': 'error',
                        'message': 'Invalid request format'
                    }, status=400)

                # Extract user message with multiple fallback strategies
                user_message_text = (
                    data.get('message') or
                    data.get('text') or
                    data.get('query') or
                    ''
                )

                # Additional safety check to ensure it's a string
                if not isinstance(user_message_text, str):
                    logger.error(f"User message is not a string: {user_message_text}")
                    user_message_text = str(user_message_text)

                # Trim message to reasonable length
                user_message_text = user_message_text[:500]  # Limit to 500 characters

                logger.info(f"Processed user message: {user_message_text}")

                # Extract location with multiple keys
                location = (
                    data.get('location') or
                    data.get('city') or
                    data.get('loc') or
                    request.session.get('user_location') or
                    'Unknown'
                )
                request.session['user_location'] = location
                logger.info(f"User location: {location}")

            except Exception as parsing_error:
                logger.error(f"Comprehensive parsing error: {str(parsing_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Failed to process your request. Please try again.'
                }, status=400)

            # Save user message with additional logging
            try:
                user_message = Message.objects.create(
                    conversation=conversation,
                    sender='user',
                    content=user_message_text
                )
                logger.info(f"Saved user message: {user_message.id}")
            except Exception as save_error:
                logger.error(f"Error saving user message: {str(save_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Could not save your message. Please try again.'
                }, status=500)

            # Initialize the movie crew with robust configuration
            try:
                # Validate configuration before initialization
                if not settings.LLM_CONFIG.get('api_key'):
                    logger.error("Missing API key in LLM_CONFIG")
                    return JsonResponse({
                        'status': 'error',
                        'message': 'LLM API key is not configured. Please check your environment variables.'
                    }, status=500)

                logger.info(f"Initializing MovieCrewManager with model: {settings.LLM_CONFIG.get('model', 'gpt-4o-mini')}")
                logger.info(f"Base URL configured: {'Yes' if settings.LLM_CONFIG.get('base_url') else 'No'}")
                logger.info(f"TMDb API key configured: {'Yes' if settings.TMDB_API_KEY else 'No'}")
                logger.info(f"User location: {location}")

                movie_crew_manager = MovieCrewManager(
                    api_key=settings.LLM_CONFIG['api_key'],
                    base_url=settings.LLM_CONFIG.get('base_url'),
                    model=settings.LLM_CONFIG.get('model', 'gpt-4o-mini'),
                    tmdb_api_key=settings.TMDB_API_KEY,
                    user_location=location
                )
                logger.info("Movie crew manager initialized successfully")
            except Exception as init_error:
                logger.error(f"Error initializing movie crew manager: {str(init_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'error',
                    'message': 'Could not initialize movie assistant. Please try again.'
                }, status=500)

            # Process the message with extensive error handling
            try:
                # Prepare conversation history with clear logging
                conversation_messages = conversation.messages.all()
                logger.info(f"Found {conversation_messages.count()} messages in conversation history")

                conversation_history = [{
                    'sender': msg.sender,
                    'content': msg.content
                } for msg in conversation_messages]

                logger.debug(f"Conversation history preview: {conversation_history[-3:] if len(conversation_history) > 3 else conversation_history}")

                logger.info(f"Sending query to movie_crew_manager: '{user_message_text[:50]}{'...' if len(user_message_text) > 50 else ''}'")

                # Track processing time
                import time
                start_time = time.time()

                response_data = movie_crew_manager.process_query(
                    query=user_message_text,
                    conversation_history=conversation_history
                )

                processing_time = time.time() - start_time
                logger.info(f"Query processing completed in {processing_time:.2f} seconds")

                logger.info(f"Response data type: {type(response_data)}")
                logger.info(f"Response data keys: {response_data.keys() if isinstance(response_data, dict) else 'Not a dictionary'}")

                if isinstance(response_data, dict) and 'movies' in response_data:
                    logger.info(f"Received {len(response_data.get('movies', []))} movie recommendations")
            except Exception as process_error:
                logger.error(f"Error processing query: {str(process_error)}")
                logger.error(traceback.format_exc())
                # Instead of returning an error, we'll set response_data to a default value and continue
                response_data = {
                    'response': 'I encountered an issue while processing your request about movies. Please try asking in a different way or check back later.',
                    'movies': []
                }
                logger.info("Set default response_data after catching process_query exception")

            # Handle the case when response_data is None
            if response_data is None:
                logger.warning("response_data is None from movie_crew_manager.process_query")
                response_data = {
                    'response': 'I encountered an issue while searching for movies. Please try again or be more specific.',
                    'movies': []
                }

            # Save chatbot response
            try:
                # Safely extract response content
                bot_response = response_data.get('response', 'Sorry, I could not generate a response.')

                bot_message = Message.objects.create(
                    conversation=conversation,
                    sender='bot',
                    content=bot_response
                )
                logger.info(f"Saved bot message: {bot_message.id}")
            except Exception as save_error:
                logger.error(f"Error saving bot message: {str(save_error)}")
                logger.error(traceback.format_exc())
                bot_response = 'Sorry, I could not generate a response.'

            # Save movie recommendations with error handling
            try:
                # Safely extract movies, default to empty list
                movies = response_data.get('movies', [])
                if not isinstance(movies, list):
                    logger.warning(f"Movies data is not a list, type: {type(movies)}")
                    movies = []

                saved_movies = []
                for movie_index, movie_data in enumerate(movies):
                    logger.debug(f"Processing movie {movie_index+1}/{len(movies)}: {movie_data.get('title', 'Unknown')}")

                    # Validate required fields
                    if not movie_data.get('title'):
                        logger.warning(f"Movie {movie_index+1} missing title, using default")

                    # Robust data extraction with defaults
                    movie = MovieRecommendation.objects.create(
                        conversation=conversation,
                        title=movie_data.get('title', 'Unknown Movie'),
                        overview=movie_data.get('overview', ''),
                        poster_url=movie_data.get('poster_url', ''),
                        release_date=movie_data.get('release_date'),
                        tmdb_id=movie_data.get('tmdb_id'),
                        rating=movie_data.get('rating')
                    )
                    saved_movies.append(movie)

                    # Track theaters data
                    theaters_count = 0
                    showtimes_count = 0

                    # Save associated theaters and showtimes
                    if 'theaters' in movie_data and movie_data['theaters']:
                        theaters_data = movie_data['theaters']
                        logger.debug(f"Found {len(theaters_data)} theaters for movie: {movie.title}")

                        for theater_index, theater_data in enumerate(theaters_data):
                            logger.debug(f"Processing theater {theater_index+1}/{len(theaters_data)}: {theater_data.get('name', 'Unknown')}")

                            theater, created = Theater.objects.get_or_create(
                                name=theater_data.get('name', 'Unknown Theater'),
                                defaults={
                                    'address': theater_data.get('address', ''),
                                    'latitude': theater_data.get('latitude'),
                                    'longitude': theater_data.get('longitude')
                                }
                            )
                            theaters_count += 1

                            # Log whether we created a new theater or found existing
                            if created:
                                logger.debug(f"Created new theater: {theater.name}")
                            else:
                                logger.debug(f"Using existing theater: {theater.name}")

                            # Save showtimes
                            if 'showtimes' in theater_data:
                                showtime_data_list = theater_data['showtimes']
                                logger.debug(f"Found {len(showtime_data_list)} showtimes for theater: {theater.name}")

                                for showtime_data in showtime_data_list:
                                    Showtime.objects.create(
                                        movie=movie,
                                        theater=theater,
                                        start_time=showtime_data.get('start_time'),
                                        format=showtime_data.get('format', '')
                                    )
                                    showtimes_count += 1

                    logger.info(f"Movie '{movie.title}' saved with {theaters_count} theaters and {showtimes_count} showtimes")

                logger.info(f"Saved {len(saved_movies)} movie recommendations out of {len(movies)} received")
            except Exception as rec_error:
                logger.error(f"Error saving movie recommendations: {str(rec_error)}")
                logger.error(traceback.format_exc())

            # Return response with comprehensive error handling
            try:
                # Fetch recent recommendations
                recent_recs = conversation.recommendations.order_by('-created_at')[:5]

                recommendations_data = [{
                    'id': rec.id,
                    'title': rec.title,
                    'overview': rec.overview,
                    'poster_url': rec.poster_url,
                    'release_date': rec.release_date.isoformat() if rec.release_date else None,
                    'rating': float(rec.rating) if rec.rating else None,
                    'theaters': [{
                        'name': showtime.theater.name,
                        'address': showtime.theater.address,
                        'start_time': showtime.start_time.isoformat(),
                        'format': showtime.format
                    } for showtime in rec.showtimes.all()]
                } for rec in recent_recs]

                return JsonResponse({
                    'status': 'success',
                    'message': bot_response,
                    'recommendations': recommendations_data
                })
            except Exception as final_error:
                logger.error(f"Final response generation error: {str(final_error)}")
                logger.error(traceback.format_exc())
                return JsonResponse({
                    'status': 'success',
                    'message': bot_response,
                    'recommendations': []
                })

        except Exception as global_error:
            logger.error(f"Global error in send_message: {str(global_error)}")
            logger.error(traceback.format_exc())
            return JsonResponse({
                'status': 'error',
                'message': 'An unexpected error occurred. Please try again.'
            }, status=500)

    return JsonResponse({
        'status': 'error',
        'message': 'Invalid request method'
    }, status=400)

def reset_conversation(request):
    """Reset the conversation and start a new one."""
    if 'conversation_id' in request.session:
        del request.session['conversation_id']

    return redirect('index')
