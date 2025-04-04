# LangChain + React News Aggregator

![Status](https://img.shields.io/badge/status-under%20development-darkred) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/js-langchain-react.yml/badge.svg)

## What is it?

This application is a news aggregation tool built with React and LangChain that can be deployed to Tanzu Platform for Cloud Foundry and integrate with LLM services through the GenAI tile. It allows users to search for news on any topic, retrieves recent articles with source links, and generates concise 25-word summaries using an LLM.

## Prerequisites

- Node.js 18+ and npm
- Cloud Foundry CLI
- Access to Tanzu Platform for Cloud Foundry with GenAI tile installed
- News API key (for development)

## How to Build

1. Clone the repository:
   ```
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/js-langchain-react
   ```

2. Install dependencies:
   ```
   npm install
   ```

3. Build for production:
   ```
   npm run build
   ```

This creates optimized production files in the `build` directory.

## How to Run Locally

1. Create a `.env` file with your API keys (for local development only):
   ```
   REACT_APP_API_BASE_URL=http://localhost:3001
   API_KEY=your_llm_api_key_here
   NEWS_API_KEY=your_news_api_key_here
   ```

2. Start the development servers:
   ```
   # Start the backend API server
   npm run server
   
   # In another terminal, start the React frontend
   npm start
   ```

3. Open your browser to `http://localhost:3000`

## How to Run on Cloud Foundry

1. Build the application:
   ```
   npm run build
   ```

2. Login to your Cloud Foundry instance:
   ```
   cf login -a API_ENDPOINT
   ```

3. Deploy the application:
   ```
   cf push
   ```

4. Bind to a GenAI service instance:
   ```
   cf create-service genai-service standard my-llm-service
   cf bind-service news-aggregator my-llm-service
   cf restage news-aggregator
   ```

## Tech Stack

- **React**: JavaScript library for building the user interface
- **Express**: Backend API server that processes requests
- **LangChain.js**: Framework for orchestrating LLM interactions
- **News API**: External API for fetching recent news articles
- **GenAI LLM Service**: Large language model service provided by Tanzu Platform for Cloud Foundry

## Project Structure

```
js-langchain-react/
├── public/                 # Static assets
├── src/                    # Source code
│   ├── components/         # React components
│   ├── api/                # API clients
│   ├── hooks/              # Custom React hooks
│   ├── services/           # Service integrations
│   ├── utils/              # Utility functions
│   ├── App.js              # Main application component
│   └── index.js            # Application entry point
├── server/                 # Express backend
│   ├── routes/             # API routes
│   ├── services/           # Service integrations
│   └── index.js            # Server entry point
├── .env.example            # Example environment variables
├── package.json            # Project dependencies
├── manifest.yml            # Cloud Foundry manifest
└── README.md               # Documentation
```

## Service Binding

The application uses the following approach to consume service credentials:

1. When deployed to Cloud Foundry, it automatically detects VCAP_SERVICES environment variables
2. It extracts LLM service credentials from the bound service instance
3. The credentials are securely used to authenticate with the LLM service

## Resources

- [LangChain Documentation](https://js.langchain.com/docs/)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [React Documentation](https://reactjs.org/docs/getting-started.html)
- [News API](https://newsapi.org/)
