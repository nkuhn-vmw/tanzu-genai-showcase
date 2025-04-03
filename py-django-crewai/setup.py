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
        'python-dotenv>=1.1.0',
        'whitenoise>=6.9.0',
        'gunicorn>=23.0.0',
        'crewai>=0.108.0',
        'langchain>=0.3.22',
        'langchain-openai>=0.3.12',
        'pydantic>=2.11.2',
        'dj-database-url>=2.3.0',
        'psycopg2-binary>=2.9.10'
    ],
    test_suite='pytest',
    tests_require=['pytest', 'webtest', 'coverage'],
    zip_safe=False,
)
