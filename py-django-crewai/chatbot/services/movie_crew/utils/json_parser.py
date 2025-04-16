"""
JSON parser utilities for the movie crew.
"""
import json
import re
import logging
from typing import Any

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class JsonParser:
    """Parser for JSON from agent output."""

    @staticmethod
    def parse_json_output(output: str) -> Any:
        """
        Parse JSON from agent output, handling various formats and error cases.

        Args:
            output: The output string to parse

        Returns:
            Parsed JSON data, or empty list if parsing fails
        """
        if not output:
            return []

        # Try to find JSON in the output
        try:
            if isinstance(output, list) or isinstance(output, dict):
                return output

            # First attempt: direct JSON parsing
            return json.loads(output)
        except json.JSONDecodeError:
            try:
                # Clean up potential newlines and extra whitespace
                output = output.strip()

                # Handle the case where output is just a string representation of a list
                if output.startswith('[') and output.endswith(']'):
                    try:
                        # Handle cases with single quotes instead of double quotes
                        cleaned_output = output.replace("'", '"')
                        return json.loads(cleaned_output)
                    except Exception:
                        pass

                # Second attempt: Look for JSON-like patterns in the text
                json_match = re.search(r'\[\s*{.*}\s*\]', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(0))

                # Third attempt: Look for JSON surrounded by triple backticks
                json_match = re.search(r'```(?:json)?\s*(\[\s*{.*}\s*\])\s*```', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # Fourth attempt: Look for JSON surrounded by backticks
                json_match = re.search(r'`(\[\s*{.*}\s*\])`', output, re.DOTALL)
                if json_match:
                    return json.loads(json_match.group(1))

                # If all else fails, try to find a JSON object rather than an array
                json_match = re.search(r'{.*}', output, re.DOTALL)
                if json_match:
                    obj = json.loads(json_match.group(0))
                    if isinstance(obj, dict):
                        return [obj]

                logger.warning(f"Could not extract JSON from output: {output[:100]}...")
                return []
            except Exception as e:
                logger.error(f"Error extracting JSON: {str(e)}")
                return []
