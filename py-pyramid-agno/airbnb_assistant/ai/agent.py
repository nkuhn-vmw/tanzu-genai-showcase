"""
AI agent implementation using Agno for the Airbnb Assistant
"""
import logging
import os
import shutil
from typing import Dict, Any, Optional, List, Union

# Import Agno components with error handling
try:
    from agno.agent import Agent
    from agno.models.openai import OpenAIChat
    from agno.models.openai.like import OpenAILike
    # Use proper import locations for Agno components
    # RunResponse is likely part of the agent module
    AGNO_IMPORTED = True
except (ImportError, AttributeError) as e:
    logging.warning(f"Could not import Agno components: {e}. Using mock implementation.")
    AGNO_IMPORTED = False

from .tools import AirbnbTools
from .mcp.client import MCPAirbnbClient
from .mcp.stdio_client import MCPAirbnbStdioClient

log = logging.getLogger(__name__)

class AirbnbAssistantAgent:
    """
    Airbnb Assistant Agent implementation

    This class provides a wrapper around the Agno Agent to handle the Airbnb Assistant
    specific functionality. It creates and configures the agent and handles the
    processing of queries.
    """

    def __init__(self,
                api_key: str,
                model: str = "gpt-4",
                api_url: str = "https://api.openai.com/v1",
                mcp_url: Optional[str] = None,
                mcp_use_stdio: bool = False,
                mcp_command: Optional[str] = None,
                mcp_args: Optional[List[str]] = None):
        """
        Initialize the Airbnb Assistant Agent

        The agent can use environment variables with GENAI_ prefix to override settings:
        - GENAI_API_KEY: API key for the GenAI provider
        - GENAI_MODEL: Model ID to use
        - GENAI_API_URL: API URL for the GenAI provider
        - GENAI_PROVIDER: Provider type (openai, azure, anthropic, etc.)
        """
        # Use environment variables with GENAI_ prefix if available
        self.api_key = os.environ.get("GENAI_API_KEY", api_key)
        self.model = os.environ.get("GENAI_MODEL", model)
        self.api_url = os.environ.get("GENAI_API_URL", api_url)
        self.provider = os.environ.get("GENAI_PROVIDER", "").lower()
        self.mcp_url = mcp_url
        self.mcp_use_stdio = mcp_use_stdio
        self.mcp_command = mcp_command
        self.mcp_args = mcp_args
        self.agent = None
        self.mcp_client = None

        # If Agno is imported, create a real agent, otherwise use mock implementation
        if AGNO_IMPORTED:
            try:
                self.agent = self._create_agent()
            except Exception as e:
                log.error(f"Failed to create Agno agent: {e}")
                self.agent = None

    def _format_response_as_markdown(self, text: str) -> str:
        """
        Format the response text as proper markdown to improve readability

        Args:
            text: Raw response text from the LLM

        Returns:
            Formatted text with proper markdown
        """
        if not text:
            return ""

        import re
        import json

        # Check if the response contains structured listings data (either directly or in mentions)
        # and format it in a more readable way
        listings_data = []

        # First, handle the common case where listings are mentioned directly
        # Pattern could be "Here are some listings in..." or similar phrases
        if any(phrase in text.lower() for phrase in ["listing", "here are", "found these"]):
            # Look for listing patterns
            listing_blocks = re.findall(r'(\*\*[\w\s]+ [\w\s]+\*\*.*?)(?=\*\*[\w\s]+ [\w\s]+\*\*|\Z)', text, re.DOTALL)
            if not listing_blocks:
                # Try another pattern for numbered listings
                listing_blocks = re.findall(r'(\d+\.\s+[\w\s]+ [\w\s]+.*?)(?=\d+\.\s+|\Z)', text, re.DOTALL)

            if listing_blocks:
                # Enhanced formatting for listing blocks
                formatted_blocks = []
                for i, block in enumerate(listing_blocks):
                    # Clean up the block and add better spacing
                    block = block.strip()

                    # Make sure title is bold and on its own line
                    if not block.startswith('**'):
                        title_match = re.match(r'(?:\d+\.\s+)?(.+?)(?:\n|$)', block)
                        if title_match:
                            title = title_match.group(1).strip()
                            block = f"**{title}**\n" + block[len(title_match.group(0)):]

                    # Ensure bullet points for key features
                    features = ['Location', 'Price', 'Rating', 'Bedroom', 'Bathroom', 'Guest', 'Amenities']
                    for feature in features:
                        block = re.sub(
                            fr'(\n|^)(\s*)({feature}s?:?)(\s+)',
                            fr'\1\2* **{feature}s:** ',
                            block
                        )

                    # Ensure consistent formatting for amenities lists
                    block = re.sub(
                        r'(Amenities:.*?)((?:WiFi|Kitchen|Pool|Gym|Air conditioning|[\w\s]+)(?:,\s*[\w\s]+)*)',
                        lambda m: m.group(1) + "\n  * " + m.group(2).replace(", ", "\n  * "),
                        block,
                        flags=re.DOTALL
                    )

                    # Add divider between listings for clarity
                    formatted_blocks.append(f"### {i+1}. {block}\n")

                # Replace all listings with formatted ones and add a top-level heading
                text = "# Airbnb Listings Search Results\n\n" + "\n".join(formatted_blocks)

        # Handle URLs and images
        urls = {}
        url_pattern = r'\[(.*?)\]\((https?://[^\s)]+)\)'

        # Find all markdown links first
        for match in re.finditer(url_pattern, text):
            display_text = match.group(1)
            url = match.group(2)
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (display_text, url)
            text = text.replace(match.group(0), placeholder)

        # Fix Airbnb image URLs
        airbnb_image_url_pattern = r'(?<!\()(?<!\[)(https?://a0\.muscache\.com/[^\s)\]]+)(?!\))(?!\])'
        for match in re.finditer(airbnb_image_url_pattern, text):
            url = match.group(0)
            # Extract just the filename for display text
            filename = url.split('/')[-1].split('.')[0][:10] + '...'
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (f"Listing Image", url)
            text = text.replace(url, placeholder)

        # Fix any other raw URLs
        raw_url_pattern = r'(?<!\()(?<!\[)(https?://(?!a0\.muscache\.com)[^\s)\]]+)(?!\))(?!\])'
        for match in re.finditer(raw_url_pattern, text):
            url = match.group(0)
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (url, url)
            text = text.replace(url, placeholder)

        # Fix general markdown formatting issues
        text = re.sub(r'(^|\n)(\d+\.)(\s*\w)', r'\1\2 \3', text)  # Numbered lists
        text = re.sub(r'(^|\n)(\*)(\s*\w)', r'\1\2 \3', text)      # Bullet points
        text = re.sub(r'(^|\n)(#{1,6})([^\s#])', r'\1\2 \3', text) # Headers

        # Replace the URL placeholders back
        for placeholder, (display_text, url) in urls.items():
            # Check if this is an image URL from Airbnb
            if 'a0.muscache.com' in url and not url.endswith(('.pdf', '.doc', '.txt')):
                text = text.replace(placeholder, f"\n![{display_text}]({url})\n")
            else:
                text = text.replace(placeholder, f"[{display_text}]({url})")

        # Final cleanup - fix any duplicate blank lines
        text = re.sub(r'\n{3,}', '\n\n', text)

        # Log the formatted text for debugging
        log.debug(f"Formatted response text:\n{text}")

        return text

    def _create_agent(self):
        """
        Create and configure an Agno agent for the Airbnb Assistant

        Returns:
            Agent: Configured Agno agent
        """
        # Try to get provider-specific API key if GENAI_API_KEY is not set
        if self.api_key in [None, "", "your_api_key_here"] and self.provider:
            provider_env_key = f"{self.provider.upper()}_API_KEY"
            self.api_key = os.environ.get(provider_env_key, "")
            if self.api_key:
                log.info(f"Using API key from {provider_env_key} environment variable")

        if not self.api_key or self.api_key in ["your_api_key_here"]:
            log.warning("No API key provided for AI agent")
            return None

        try:
            # Determine if using OpenRouter or another OpenAI-compatible API
            is_openrouter = "openrouter.ai" in self.api_url
            is_compatible_api = self.provider in ["azure", "anyscale", "together", "groq", "cohere"] or \
                              any(name in self.api_url for name in ["azure", "anyscale", "together", "groq", "cohere"])

            # Log what provider we're using
            # Configure client parameters based on provider type
            client_params = {
                "id": self.model,
                "api_key": self.api_key
            }

            # Add any provider-specific headers if needed
            if self.provider == "openrouter" or "openrouter.ai" in self.api_url:
                client_params["default_headers"] = {
                    "HTTP-Referer": "https://airbnb-assistant.example.com",
                    "X-Title": "Airbnb Assistant"
                }

            # Add base_url if specified
            if self.api_url:
                client_params["base_url"] = self.api_url

            # Create appropriate model instance based on provider type
            if is_openrouter:
                log.info(f"Using OpenRouter with model: {self.model}")
                # Use OpenAILike for OpenRouter
                model = OpenAILike(**client_params)
            elif is_compatible_api:
                log.info(f"Using OpenAI-compatible provider ({self.provider or 'custom'}) with model: {self.model}")
                # Use OpenAILike for other compatible APIs
                model = OpenAILike(**client_params)
            else:
                log.info(f"Using OpenAI with model: {self.model}")
                # Create standard OpenAI model
                model = OpenAIChat(**client_params)

            # Create the appropriate MCP client based on configuration
            if self.mcp_use_stdio and self.mcp_command:
                log.info(f"Using stdio MCP client with command: {self.mcp_command}")
                self.mcp_client = MCPAirbnbStdioClient(
                    mcp_command=self.mcp_command,
                    mcp_args=self.mcp_args or []
                )
            else:
                log.info(f"Using HTTP MCP client with URL: {self.mcp_url}")
                self.mcp_client = MCPAirbnbClient(mcp_url=self.mcp_url)

            # Create the toolkit with the selected MCP client
            airbnb_toolkit = AirbnbTools(mcp_client=self.mcp_client)

            # Configure the agent with tools
            agent = Agent(
                model=model,
                description="""
                You are an Airbnb search assistant that helps users find accommodations.
                You can search for listings and provide detailed information about them.
                Be friendly, helpful, and concise in your responses.
                """,
                instructions=[
                    "Always ask for all the information you need before making a search, such as location, dates, and number of guests.",
                    "When displaying listing results, format them in a clear, organized way.",
                    "When showing listing details, highlight the key information like price, amenities, and host details.",
                    "Use headings to organize your responses.",
                    "Be concise and focused on relevant information.",
                    "Always use proper markdown formatting for listings and organize information in a clean, readable way."
                ],
                tools=[airbnb_toolkit],
                show_tool_calls=True,
                markdown=True
            )
            return agent
        except Exception as e:
            log.error(f"Error creating Agno agent: {e}")
            return None

    def process_query(self, message: str, session_id: Optional[str] = None) -> Dict[str, Any]:
        """
        Process a user query and return a response

        Args:
            message (str): User's message
            session_id (str): Session ID for continuity (optional)

        Returns:
            Dict: Dictionary containing the response and any context
        """
        try:
            log.info(f"Processing query: {message}")

            # If no valid agent is available or no API key, return mock response
            if self.agent is None or not self.api_key or self.api_key == "your_api_key_here":
                log.warning("Using mock response as no valid agent is available")
                mock_response = self._generate_mock_response(message)
                return {
                    "response": mock_response,
                    "context": {"mock": True}
                }

            # Run the agent to get a response using the proper Agno agent.run() method
            try:
                # Use the agent.run method
                response = self.agent.run(message)
                log.info(f"Agent generated a response of type: {type(response)}")

                # Get the response content - RunResponse object or string
                if isinstance(response, str):
                    # Direct string response
                    response_text = response
                    log.info("Response is a string type")
                else:
                    # RunResponse object
                    log.info(f"Response is an object with attributes: {dir(response)}")
                    response_text = response.content if hasattr(response, 'content') else str(response)
                    log.info(f"Extracted content: {response_text[:100]}...")

                # Format the response in markdown for better readability
                formatted_text = self._format_response_as_markdown(response_text)
                log.info(f"Formatted response to improve markdown readability")

                # Extract any context or metadata
                context = {}

                # For now, we'll use a simple detection of listing data
                if formatted_text and isinstance(formatted_text, str) and "listing" in formatted_text.lower():
                    context["type"] = "listing"
                    log.info("Detected listing data in response")

                # Make sure we have a string response for the frontend
                if not isinstance(formatted_text, str):
                    # Try to convert complex objects to string
                    try:
                        if hasattr(response, 'get_content_as_string'):
                            formatted_text = response.get_content_as_string()
                            log.info("Used get_content_as_string to convert response")
                        else:
                            import json
                            formatted_text = json.dumps(formatted_text) if formatted_text is not None else ""
                            log.info("Used json.dumps to convert response")
                    except Exception as e:
                        log.warning(f"Error converting response to string: {e}")
                        formatted_text = str(formatted_text)
                        log.info("Used str() to convert response after error")

                return {
                    "response": formatted_text,
                    "context": context
                }
            except Exception as agent_error:
                error_msg = str(agent_error)
                log.error(f"Agent error: {error_msg}")

                # Provide more specific feedback for known error types
                if "invalid_api_key" in error_msg.lower() or "incorrect api key" in error_msg.lower():
                    log.error("API key validation failed. Check that you've set the correct API key for your provider.")
                    if self.provider:
                        expected_env = f"GENAI_API_KEY or {self.provider.upper()}_API_KEY"
                        log.error(f"For {self.provider} provider, ensure {expected_env} is set correctly.")

                # Fall back to mock response
                log.info("Falling back to mock response due to agent error")
                mock_response = self._generate_mock_response(message)
                return {
                    "response": mock_response,
                    "context": {"mock": True, "error": error_msg}
                }

        except Exception as e:
            log.error(f"Error processing query: {e}")
            # Provide a user-friendly error message
            error_response = "I apologize, but I encountered an error while processing your request. Please try again later."
            return {
                "response": error_response,
                "context": {"error": str(e)}
            }

    def _generate_mock_response(self, message: str) -> str:
        """
        Generate a mock response for demonstration purposes

        Args:
            message (str): User's message

        Returns:
            str: Mock response with formatted listings
        """
        # Simple logic to detect search queries
        if any(keyword in message.lower() for keyword in ["find", "search", "looking for", "place", "stay", "accommodation"]):
            # Extract location from message
            location = "San Francisco"  # Default
            for city in ["san francisco", "new york", "miami", "los angeles", "chicago", "boston"]:
                if city in message.lower():
                    location = city.title()
                    break

            return f"""Sure! I can help with that. Could you please provide me with the following details?

1. Check-in Date
2. Check-out Date
3. Number of Guests
4. Any specific preferences or requirements (e.g., number of bedrooms, amenities, budget)?

Once I have this information, I'll search for suitable accommodations in {location}!"""

        # If it's a request for more details
        elif any(keyword in message.lower() for keyword in ["more", "detail", "tell me about", "listing"]):
            listing_id = "1001"  # Default
            for id_pattern in ["1001", "1002", "1003"]:
                if id_pattern in message:
                    listing_id = id_pattern
                    break

            return f"""Here are some great listings in San Francisco:

**Luxury Ocean View Condo**
• 2 bedrooms, 2 bathrooms
• $250 per night
• Accommodates up to 4 guests
• Amenities: WiFi, Pool, Kitchen, Gym, Air conditioning
• Superhost with 4.9 rating (85 reviews)
• Located in a beachfront property with direct beach access

**Cozy Downtown Apartment**
• 1 bedroom, 1 bathroom
• $120 per night
• Accommodates up to 2 guests
• Amenities: WiFi, Kitchen, Air conditioning
• 4.8 rating (120 reviews)
• Located in downtown area

Would you like more information about a specific listing?"""

        # Default response
        else:
            return """I'm your Airbnb search assistant. I can help you find accommodations and provide details about listings.

Try asking me something like:
• "Find me a place to stay in San Francisco"
• "I need a 2-bedroom apartment in New York for next week"
• "Tell me more about listing 1001"

How can I help you today?"""

    def __del__(self):
        """Clean up resources when the agent is destroyed"""
        if hasattr(self, 'mcp_client') and self.mcp_client and hasattr(self.mcp_client, 'stop_server'):
            try:
                self.mcp_client.stop_server()
            except:
                pass
