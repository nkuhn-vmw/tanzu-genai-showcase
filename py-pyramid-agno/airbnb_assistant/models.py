"""
SQLAlchemy models for the Airbnb Assistant
"""
import datetime
from sqlalchemy import Column, Integer, String, Text, DateTime, ForeignKey, Boolean
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import scoped_session, sessionmaker

DBSession = scoped_session(sessionmaker())
Base = declarative_base()


class User(Base):
    """
    User model for storing user information
    """
    __tablename__ = 'users'

    id = Column(Integer, primary_key=True)
    username = Column(String(255), unique=True, nullable=False)
    email = Column(String(255), unique=True, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)


class ChatSession(Base):
    """
    Chat session model for storing chat history
    """
    __tablename__ = 'chat_sessions'

    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey('users.id'))
    session_id = Column(String(255), unique=True, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)


class ChatMessage(Base):
    """
    Chat message model for storing individual messages
    """
    __tablename__ = 'chat_messages'

    id = Column(Integer, primary_key=True)
    session_id = Column(Integer, ForeignKey('chat_sessions.id'))
    user_message = Column(Text, nullable=False)
    assistant_message = Column(Text, nullable=False)
    timestamp = Column(DateTime, default=datetime.datetime.now)
