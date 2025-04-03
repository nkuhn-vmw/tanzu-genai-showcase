# Event Recommendation Chatbot Frontend

This is the Angular frontend for the Event Recommendation Chatbot application.

## Overview

This frontend provides a user-friendly interface for interacting with the event recommendation chatbot. It allows users to:

- Chat with an AI assistant to find events
- View event recommendations based on city
- View city information

## Tech Stack

- Angular 17 (Standalone Components)
- TypeScript
- SCSS for styling

## Setup and Running

### Prerequisites

- Node.js 20.x or higher
- npm 10.x or higher

### Installation

1. Install dependencies:

```bash
npm install
```

2. Development server:

```bash
npm start
```

Navigate to `http://localhost:4200/` to see the application. The application will automatically reload if you change any of the source files.

### Building for production

```bash
npm run build
```

The build artifacts will be stored in the `dist/frontend/browser` directory.

## Integration with Backend

The frontend communicates with the Spring Boot backend API through HTTP. The API URL is configured in the environment files:

- `environment.ts` - Development configuration
- `environment.production.ts` - Production configuration

## Features

- Real-time chat interface
- Event card display
- City information display
- Responsive design

## Note on Maven Integration

When building through Maven:

1. The frontend Maven plugin installs Node.js and npm
2. It runs `npm install` to install dependencies
3. It runs `npm run build` to create a production build
4. The Maven resources plugin copies the built files to the Spring Boot static resources directory

This allows the frontend to be served directly from the Spring Boot application when deployed.
