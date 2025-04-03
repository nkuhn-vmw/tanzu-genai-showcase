from setuptools import setup, find_packages

setup(
    name='airbnb-assistant',
    version='0.1',
    description='Airbnb Search Assistant using Pyramid and Agno',
    author='',
    author_email='',
    url='',
    packages=find_packages(exclude=['ez_setup', 'tests']),
    include_package_data=True,
    install_requires=[
        "pyramid>=2.0.2",
        "agno>=1.0.8",
        "SQLAlchemy>=1.4.0",
        "alembic>=1.7.5",
        "pyramid_mako",
        "pyramid_debugtoolbar",
        "pyramid_tm",
        "psycopg2-binary",
        "waitress",
        "gunicorn",
        "python-dotenv",
        "cfenv",
        "requests",  # Added requests
    ],
    test_suite='pytest',
    tests_require=['pytest', 'webtest', 'coverage'],
    package_data={'airbnb_assistant': ['templates/*', 'static/*/*']},
    entry_points={
        'paste.app_factory': [
            'main = airbnb_assistant:main'
        ],
        'console_scripts': [
            'run_app = airbnb_assistant:run_app',
            'initialize_db = airbnb_assistant.scripts.initialize_db:main',
        ],
    },
    zip_safe=False,
)