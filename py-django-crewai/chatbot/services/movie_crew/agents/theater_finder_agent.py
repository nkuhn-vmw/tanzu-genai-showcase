"""
Theater Finder Agent for the movie crew.
"""
import logging
from typing import Dict, Any, Optional
from crewai import Agent
from langchain_openai import ChatOpenAI

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class TheaterFinderAgent:
    """Agent for finding theaters showing the recommended movies."""

    @staticmethod
    def create(llm: ChatOpenAI) -> Agent:
        """
        Create the Theater Finder agent.

        Args:
            llm: Language model to use for the agent

        Returns:
            CrewAI Agent instance
        """
        return Agent(
            role="Theater Finder",
            goal="Find theaters showing the recommended movies near the user's location",
            backstory="""You are an expert at finding movie theaters and showtimes. Your job is to locate theaters
                      showing the recommended movies near the user's location and provide detailed information about
                      showtimes and theater amenities. You leverage real-time data to provide accurate and up-to-date
                      information about movie screenings.""",
            verbose=True,
            llm=llm
        )
