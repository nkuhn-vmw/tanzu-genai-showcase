# Congress.gov API Chatbot Features

## Design

While iterating upon the design and development of this Congress.gov chatbot we balanced several functional and non-functional design criteria:

1. **Stale Data**: Modified API calls to consistently fetch and prioritize the most recent data
2. **Temperature Tuning**: Reduced the LLM temperature from 0.9 to 0.3 for more consistent, reliable outputs
3. **Enhanced API Support**: Expanded support for additional Congress.gov API endpoints
4. **Caching System**: Implemented a sophisticated caching mechanism to improve performance and data freshness
5. **Prompt Engineering**: Refined prompt strategies for more accurate and timely responses
6. **UI/UX Enhancements**: Added progress indicators and improved interface feedback
7. **Current Congress Knowledge**: Provided explicit knowledge about the current 119th Congress

## Technical Improvements

### 1. LLM Temperature Adjustment

Lowered the temperature parameter used in LLM calls from 0.9 to 0.3 to produce more consistent, factual responses with less creative variation.

```go
opts := []llms.CallOption{
    llms.WithModel(model),
    llms.WithTemperature(0.3), // Reduced from 0.9
    llms.WithMaxTokens(2048),
}
```

### 2. Enhanced Congress.gov API Client

Expanded the Congress API client with additional endpoints to support more comprehensive data retrieval:

- Added bill-related endpoints:
  - `GetBillActions` - For bill timeline and status information
  - `GetBillCosponsors` - For bill support information
  - `GetBillRelatedBills` - For finding similar legislation

- Added member-related endpoints:
  - `GetMemberSponsorship` - For member-sponsored legislation

- Added new data categories:
  - `SearchCommittees` and `GetCommittee` - For committee information
  - `SearchCongressionalRecord` - For debate proceedings and speeches
  - `SearchNominations` - For presidential nominations
  - `SearchHearings` - For congressional hearings

### 3. Intelligent Caching System

Implemented a thread-safe in-memory caching mechanism to:

- Reduce redundant API calls
- Improve response times
- Maintain data freshness with appropriate TTL (time-to-live)

```go
// Cache provides a simple in-memory caching mechanism
type Cache struct {
    data  map[string]cacheEntry
    mutex sync.RWMutex
}

type cacheEntry struct {
    data       map[string]interface{}
    expiration time.Time
}
```

### 4. Enhanced Prompt Engineering

Improved all prompts used for LLM interaction:

1. **System Prompt**: Enhanced with stronger guidance on data recency and accuracy
2. **API Planning Prompt**: Expanded to cover all available API endpoints with detailed selection criteria
3. **Response Interpretation Prompt**: Improved with stronger emphasis on dates and current status
4. **Direct Response Prompt**: Enhanced fallback responses to acknowledge limitations clearly

### 5. Result Sorting for Recency

Modified search endpoints to prioritize recent data via explicit sort parameters:

```go
params.Add("sort", "updateDate desc")  // Sort by most recent updates
params.Add("sort", "date desc")        // Sort by most recent date
```

### 6. Enhanced UI with Progress Indicators

Improved the user interface with loading indicators and disabled input during processing:

- Added a progress bar with animated fill to indicate processing status
- Implemented a spinning loader to provide visual feedback during API calls
- Disabled input fields and buttons during API requests to prevent duplicate submissions
- Added clear visual feedback when the interface is in a processing state

```javascript
function setProcessingState(isProcessing) {
    if (isProcessing) {
        // Disable input and buttons
        userInput.disabled = true;
        sendButton.disabled = true;
        clearButton.disabled = true;

        // Add visual indication that buttons are disabled
        sendButton.classList.add('opacity-50', 'cursor-not-allowed');
        clearButton.classList.add('opacity-50', 'cursor-not-allowed');

        // Show progress indicators
        progressContainer.classList.remove('hidden');

        // Animate progress bar
        progressBarFill.style.width = '5%';
        setTimeout(() => { progressBarFill.style.width = '30%'; }, 500);
        setTimeout(() => { progressBarFill.style.width = '70%'; }, 1500);
    } else {
        // Enable input and buttons, reset visual state
        // ...
    }
}
```

### 7. Current Congress Information

Added explicit knowledge about the current congressional session to the system prompt:

```
CURRENT CONGRESS INFORMATION:
- The current Congress is the 119th Congress (2025-2026)
- The previous Congress was the 118th Congress (2023-2024)
- The House of Representatives has 435 voting members
- The Senate has 100 members (2 from each state)
- The current Speaker of the House is Mike Johnson (as of April 2025)
- The current Senate Majority Leader is Chuck Schumer (as of April 2025)
- The current Senate Minority Leader is Mitch McConnell (as of April 2025)
```

This knowledge ensures the LLM will query for data from the current congress when not otherwise specified, significantly improving the relevance and currency of responses.

## Benefits

These improvements collectively result in:

1. **More Accurate and Current Information**: Users now receive the most up-to-date available data
2. **Consistent Responses**: The lower temperature setting produces more reliable, factually-grounded answers
3. **Broader Information Access**: The expanded API support allows for more comprehensive answers
4. **Improved Performance**: Caching reduces redundant API calls and improves response times
5. **Better User Experience**: More detailed and accurate responses with clear indications of data freshness

## Future Enhancements

Potential future improvements include:

1. Implementing proactive cache invalidation for critical data
2. Adding streaming responses for more interactive conversations
3. Expanding the error handling and retry mechanisms
4. Implementing a monitoring system for API call analytics
5. Adding automatic bill status tracking for important legislation
