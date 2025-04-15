# Enhanced Logging Configuration

We've implemented a comprehensive logging system to provide more insight into the application's behavior. This document explains the enhanced logging configuration and how to use it.

## Log Features

The enhanced logging configuration includes:

1. **Detailed HTTP Request/Response Logging**
   - HTTP method, path, status code, and latency
   - Request headers (Content-Type, User-Agent)
   - Client IP address
   - Response body (truncated for large responses)
   - Error details if any

2. **API Request/Response Body Logging**
   - Complete request bodies for all API endpoints
   - Response bodies (truncated if over 1000 characters)
   - Status codes for all responses

3. **Panic Recovery and Stack Traces**
   - Automatic recovery from panics
   - Full stack traces for debugging
   - Detailed error reporting

4. **File-based Logging**
   - All logs stored in `logs/http.log`
   - Logs also displayed in the console

## Log Format

The main HTTP log format includes:

```
${time} | ${status} | ${latency} | ${method} ${path} | ${ip} | ${reqHeader:Content-Type} | ${reqHeader:User-Agent} | ${resBody} | ${error}
```

For API requests, an additional format is used:

```
REQUEST BODY [METHOD PATH]: JSON content
RESPONSE BODY [METHOD PATH] [Status: CODE]: JSON response
```

## Viewing Logs

### Console Output

All logs are printed to the console while the server is running.

### Log Files

Logs are also written to `logs/http.log` in the project root directory. You can view these logs with:

```bash
cat logs/http.log       # View entire log
tail -f logs/http.log   # Follow log in real-time
grep ERROR logs/http.log # Find error entries
```

## Interpreting Logs

### Request Processing Flow

A typical API request will generate multiple log entries:

1. Initial HTTP request log entry with method, path, and headers
2. REQUEST BODY log entry showing what was sent to the server
3. RESPONSE BODY log entry showing what was returned
4. Final HTTP request log with status code and latency

### Troubleshooting

When troubleshooting issues:

1. Look for non-200 status codes in the logs
2. Check request and response bodies for validation issues
3. Search for ERROR or PANIC entries
4. Examine stack traces for the source of any errors

## Congress.gov API Logging

For interactions with the Congress.gov API, examine the request and response logs to see:

1. Which endpoints are being called
2. What parameters are being sent
3. The response data received
4. Any errors in the API interactions

## LLM Interactions

You can trace the full conversation flow with the LLM by examining:

1. The request body sent to `/api/chat` (user's message)
2. The behind-the-scenes API planning and interpretation steps
3. The final response returned to the user

## Customizing Logging

To further customize the logging configuration, you can:

1. Modify the `Format` string in the logger configuration
2. Adjust the log output destination (file or console)
3. Change what types of requests or responses are logged
4. Add more middleware for additional logging capabilities

See the [Fiber Logger documentation](https://docs.gofiber.io/api/middleware/logger) for more options.
