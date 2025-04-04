# Local Development Guide

This document provides information about running the News Aggregator application in your local development environment.

## Development Setup

The application consists of two main parts:

1. A React frontend
2. An Express backend

When running locally, these two parts run as separate processes on different ports:

- Frontend: typically on port 3000
- Backend: on port 3001

## How to Run Locally

1. Install dependencies (if you haven't already):

   ```bash
   npm install
   ```

2. Create a `.env` file with your API keys:

   ```bash
   REACT_APP_API_BASE_URL=http://localhost:3001
   API_KEY=your_llm_api_key_here
   NEWS_API_KEY=your_news_api_key_here
   ```

3. Start the backend server in one terminal:

   ```bash
   npm run server
   ```

4. Start the React development server in another terminal:

   ```bash
   npm start
   ```

5. Open your browser to `http://localhost:3000`

## Environment Variables

The application uses different environment variables depending on whether it's running:

- Locally (development)
- In Cloud Foundry (production)

### Local Environment Variables

For local development, the key environment variables are:

| Variable | Purpose | Default |
|----------|---------|---------|
| `REACT_APP_API_BASE_URL` | URL for backend API | http://localhost:3001 |
| `API_KEY` | API key for the LLM service | None (required) |
| `NEWS_API_KEY` | API key for News API | None (required) |
| `PORT` | Port for backend server | 3001 |

### How the App Detects the Environment

The application now uses a smart detection system to determine if it's running in development or production:

1. It checks `window.location.hostname` and `window.location.port`
2. If running on localhost:3000 or localhost:3002 (React dev server ports), it uses the full backend URL
3. Otherwise, it uses relative URLs for API requests

This means you don't need to manually set NODE_ENV or other variables for local development - the standard Create React App and Express setup handles this automatically.

## Troubleshooting

### API Connection Issues

If the frontend can't connect to the backend:

1. Ensure both servers are running
2. Check that the REACT_APP_API_BASE_URL in .env matches your backend server address
3. Verify the backend server is listening on port 3001 (or update the URL accordingly)
4. Check browser console for CORS errors

### Missing API Keys

If you're seeing errors about invalid credentials:

1. Make sure your .env file contains valid API keys
2. Restart both the backend and frontend servers after updating .env
3. For the LLM service, check that your API key is active and has sufficient quota

### Build Issues

When building locally:

1. Environment variables prefixed with REACT_APP_ are available to the client-side code
2. The React build process uses NODE_ENV=production automatically
3. You don't need to set NODE_ENV manually when using npm scripts

## Workflow Tips

1. **Hot Reload**: The React development server supports hot reloading, so changes to React components will automatically update in your browser.

2. **Backend Changes**: If you make changes to the Express backend, you'll need to restart the server (Ctrl+C and npm run server again).

3. **Environment Variables**: Any changes to environment variables require restarting the servers to take effect.

4. **Debugging**:
   - Use React Developer Tools in your browser
   - Check the terminal running the backend for server-side logs
   - Use console.log in your React components for client-side debugging

### File Watcher Limit Issues

If you encounter the error: `ENOSPC: System limit for number of file watchers reached`, this is a common issue on Linux systems. The problem occurs because the React development server uses a lot of file watchers to monitor for file changes.

To fix this issue:

1. Run the included script:

   ```bash
   ./fix-watchers.sh
   ```

2. If you don't want to use the script, you can manually increase the file watcher limit:

   ```bash
   echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
   sudo sysctl -p
   ```

3. After increasing the limit, restart the React development server:

   ```bash
   npm start
   ```

This change will persist until the system is rebooted. To make it permanent, add the line `fs.inotify.max_user_watches=524288` to your `/etc/sysctl.conf` file.
