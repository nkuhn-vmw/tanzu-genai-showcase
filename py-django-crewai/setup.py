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
        'crewai>=0.201.1',
        'langchain>=0.3.27',
        'langchain-openai>=0.3.15',
        'pydantic>=2.11.9',
        'dj-database-url>=2.3.0',
        'psycopg2-binary>=2.9.10'
    ],
    test_suite='pytest',
    tests_require=['pytest', 'webtest', 'coverage'],
    zip_safe=False,
)
