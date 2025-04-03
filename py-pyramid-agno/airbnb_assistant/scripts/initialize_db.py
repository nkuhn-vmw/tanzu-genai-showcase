"""
Database initialization script

This script initializes the database for the Airbnb Assistant application.
It checks if the database already exists and handles reinitialization based on settings.
"""
import os
import sys
import logging
import argparse
import transaction
from pyramid.paster import get_appsettings, setup_logging
from sqlalchemy import create_engine, inspect
from sqlalchemy.exc import SQLAlchemyError

from ..models import Base, User, ChatSession, ChatMessage

log = logging.getLogger(__name__)

def db_exists(engine):
    """
    Check if database tables already exist

    Args:
        engine: SQLAlchemy engine

    Returns:
        bool: True if tables exist, False otherwise
    """
    try:
        inspector = inspect(engine)
        # Check if key tables exist
        return (
            'users' in inspector.get_table_names() and
            'chat_sessions' in inspector.get_table_names() and
            'chat_messages' in inspector.get_table_names()
        )
    except Exception as e:
        log.error(f"Error checking database existence: {e}")
        return False

def setup_models(dbsession):
    """
    Add or update models
    """
    # Check if admin user exists
    admin = dbsession.query(User).filter_by(username='admin').first()
    if admin is None:
        admin = User(username='admin', email='admin@example.com')
        dbsession.add(admin)
        log.info("Created admin user")

    # Create a demo user if it doesn't exist
    demo_user = dbsession.query(User).filter_by(username='demo').first()
    if demo_user is None:
        demo_user = User(username='demo', email='demo@example.com')
        dbsession.add(demo_user)
        log.info("Created demo user")


def parse_args(argv):
    """
    Parse command line arguments
    """
    parser = argparse.ArgumentParser()
    parser.add_argument(
        'config_uri',
        help='Configuration file, e.g., development.ini',
    )
    parser.add_argument(
        '--dryrun',
        action='store_true',
        help="Don't actually write anything to the database",
    )
    parser.add_argument(
        '--reinitialize',
        action='store_true',
        help="Drop and recreate all tables even if they already exist",
    )
    return parser.parse_args(argv[1:])


def main(argv=sys.argv):
    """
    Main entry point
    """
    args = parse_args(argv)
    setup_logging(args.config_uri)

    # Get settings without loading the entire application
    settings = get_appsettings(args.config_uri)

    # Get database URL from settings
    db_url = settings.get('sqlalchemy.url')
    if not db_url:
        log.error("No database URL found in settings")
        return 1

    # Create database engine
    engine = create_engine(db_url)

    # Create a session
    from sqlalchemy.orm import sessionmaker
    Session = sessionmaker(bind=engine)
    session = Session()

    # Check for reinitialize flag in environment variables or config settings
    env_reinitialize = os.environ.get('DB_REINITIALIZE', '').lower() in ('true', 't', 'yes', 'y', '1')
    config_reinitialize = settings.get('db.reinitialize', '').lower() in ('true', 't', 'yes', 'y', '1')
    should_reinitialize = args.reinitialize or env_reinitialize or config_reinitialize

    try:
        # Check if database already exists
        if db_exists(engine) and not should_reinitialize:
            log.info("Database already exists. Skipping initialization.")
            log.info("To reinitialize, run with --reinitialize flag, set DB_REINITIALIZE=true environment variable, or set db.reinitialize=true in config file.")
            return 0

        # Use transaction to safely manage changes
        with transaction.manager:
            if not args.dryrun:
                if should_reinitialize:
                    log.info("Reinitializing database (dropping all tables)...")
                    Base.metadata.drop_all(engine)

                log.info("Creating database tables...")
                Base.metadata.create_all(engine)

                log.info("Adding initial data...")
                setup_models(session)

                log.info("Database initialized successfully")
                transaction.commit()
            else:
                log.info("Dry run, not making any changes")
    except SQLAlchemyError as e:
        log.error(f"Database error: {e}")
        if session:
            session.rollback()
        return 1
    except Exception as e:
        log.error(f"Database initialization failed: {e}")
        if session:
            session.rollback()
        return 1
    finally:
        session.close()

    return 0


if __name__ == '__main__':
    sys.exit(main() or 0)
