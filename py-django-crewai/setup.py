from setuptools import setup, find_packages

setup(
    name='movie-booking-bot',
    version='0.1.0',
    description='Movie Booking Assistant using Django and CrewAI',
    author='',
    author_email='',
    url='',
    packages=find_packages(exclude=['ez_setup', 'tests']),
    include_package_data=True,
    install_requires=[
        'python-dotenv>=1.1.1',
        'whitenoise>=6.11.0',
        'gunicorn>=23.0.0',
        'crewai>=0.203.1',
        'langchain>=1.0.0',
        'langchain-openai>=1.0.0',
        'pydantic>=2.12.3',
        'dj-database-url>=3.0.1',
        'psycopg2-binary>=2.9.11'
    ],
    test_suite='pytest',
    tests_require=['pytest', 'webtest', 'coverage'],
    zip_safe=False,
)
