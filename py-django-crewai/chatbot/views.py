from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.conf import settings
from .models import Conversation, Message, MovieRecommendation, Theater, Showtime
from .services.movie_crew import MovieCrewManager
import json
import logging

logger = logging.getLogger(__name__)

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
            # Get conversation or create new one
            conversation_id = request.session.get('conversation_id')
            
            if not conversation_id:
                conversation = Conversation.objects.create()
                request.session['conversation_id'] = conversation.id
            else:
                conversation = Conversation.objects.get(id=conversation_id)
            
            # Parse the user message
            data = json.loads(request.body)
            user_message_text = data.get('message', '')
            
            # If location is provided, save it to the session
            if data.get('location'):
                request.session['user_location'] = data.get('location')
            
            # Save user message
            user_message = Message.objects.create(
                conversation=conversation,
                sender='user',
                content=user_message_text
            )
            
            # Initialize the movie crew with LLM config
            movie_crew_manager = MovieCrewManager(
                api_key=settings.LLM_CONFIG['api_key'],
                base_url=settings.LLM_CONFIG['base_url'],
                model=settings.LLM_CONFIG['model'],
                tmdb_api_key=settings.TMDB_API_KEY,
                user_location=request.session.get('user_location')
            )
            
            # Process the message with CrewAI
            response_data = movie_crew_manager.process_query(
                query=user_message_text,
                conversation_history=[{
                    'sender': msg.sender,
                    'content': msg.content
                } for msg in conversation.messages.all()]
            )
            
            # Save chatbot response
            bot_message = Message.objects.create(
                conversation=conversation,
                sender='bot',
                content=response_data['response']
            )
            
            # Save movie recommendations if any
            if 'movies' in response_data and response_data['movies']:
                for movie_data in response_data['movies']:
                    movie = MovieRecommendation.objects.create(
                        conversation=conversation,
                        title=movie_data['title'],
                        overview=movie_data.get('overview', ''),
                        poster_url=movie_data.get('poster_url'),
                        release_date=movie_data.get('release_date'),
                        tmdb_id=movie_data.get('tmdb_id'),
                        rating=movie_data.get('rating')
                    )
                    
                    # Save theater and showtime information if available
                    if 'theaters' in movie_data and movie_data['theaters']:
                        for theater_data in movie_data['theaters']:
                            theater, created = Theater.objects.get_or_create(
                                name=theater_data['name'],
                                defaults={
                                    'address': theater_data.get('address', ''),
                                    'latitude': theater_data.get('latitude'),
                                    'longitude': theater_data.get('longitude')
                                }
                            )
                            
                            if 'showtimes' in theater_data and theater_data['showtimes']:
                                for showtime_data in theater_data['showtimes']:
                                    Showtime.objects.create(
                                        movie=movie,
                                        theater=theater,
                                        start_time=showtime_data['start_time'],
                                        format=showtime_data.get('format', '')
                                    )
            
            # Return the response data
            return JsonResponse({
                'status': 'success',
                'message': bot_message.content,
                'recommendations': [{
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
                } for rec in conversation.recommendations.filter(id__in=[
                    movie['id'] for movie in response_data.get('movies', []) if 'id' in movie
                ])]
            })
        
        except Exception as e:
            logger.error(f"Error processing message: {str(e)}")
            return JsonResponse({
                'status': 'error',
                'message': 'Sorry, there was an error processing your request. Please try again.'
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
