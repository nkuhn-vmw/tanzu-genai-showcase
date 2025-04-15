package service

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/api"
	"github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/llm"
)

// ChatbotService handles the interaction between the user, LLM, and Congress.gov API
type ChatbotService struct {
	congressClient *api.CongressClient
	llmClient      *llm.LLMClient
}

// NewChatbotService creates a new ChatbotService
func NewChatbotService(congressClient *api.CongressClient, llmClient *llm.LLMClient) *ChatbotService {
	return &ChatbotService{
		congressClient: congressClient,
		llmClient:      llmClient,
	}
}

// Initialize sets up the LLM with system instructions
func (s *ChatbotService) Initialize() {
	// Define the system prompt for the LLM
	systemPrompt := `
You are a helpful assistant that provides information about the U.S. Congress, bills, amendments, and legislation.
You have access to the Congress.gov API to retrieve accurate and up-to-date information.

CURRENT CONGRESS INFORMATION:
- The current Congress is the 119th Congress (2025-2026)
- The previous Congress was the 118th Congress (2023-2024)
- The House of Representatives has 435 voting members
- The Senate has 100 members (2 from each state)
- The current Speaker of the House is Mike Johnson (as of April 2025)
- The current Senate Majority Leader is Chuck Schumer (as of April 2025)
- The current Senate Minority Leader is Mitch McConnell (as of April 2025)

YOUR CAPABILITIES:
1. You can search for and retrieve information about bills, amendments, and legislation
2. You can find information about members of Congress
3. You can provide summaries of bills
4. You can get details on congressional committees
5. You can retrieve information about congressional records
6. You can look up recent activities in Congress

GUIDELINES:
- Be precise and factual, focusing on providing accurate and current information
- When you're asked a specific question that requires API data, always use the appropriate API endpoint
- Always include dates or time periods in your responses to help users understand how recent the information is
- If specific details are missing, prioritize the 119th Congress (current) and current legislative session in your queries
- For queries where date ranges are relevant, try to use the most recent 30-day period if not specified
- If the API doesn't return useful results, acknowledge the limitations and provide the most accurate general information
- Never make up fabricated details about specific bills, amendments, or members
- Always check if there's a more specific API endpoint that could provide more accurate or recent data
- Include specific bill numbers, dates, and official titles when available to improve accuracy

ACCURACY GUIDELINES:
- Always verify bill status and actions using the most recent data available
- Be explicit about the recency of information ("As of April 2025..." or "According to recent data...")
- If information seems outdated, note this explicitly and suggest it may not reflect the current status
- For contentious topics, focus strictly on factual information from the API, not interpretations
- If data seems inconsistent, prioritize the most specific and detailed information source
- ALWAYS use "119" as the congress number for current Congress queries when not otherwise specified

EXAMPLES:
User: "Tell me about recent infrastructure bills"
Assistant: (Uses search_bills API with "infrastructure" as the query for the 119th Congress and returns information about the most recent bills, including their dates of introduction and current status)

User: "Who are the representatives from California?"
Assistant: (Uses search_members API with "California" as the query to find representatives from California in the 119th Congress)

User: "What did the Inflation Reduction Act do?"
Assistant: (Uses search_bills to find the Inflation Reduction Act from the 118th Congress, then get_bill_summary to provide details, including the passage date and implementation timeline)
`
	s.llmClient.ClearMessages()
	s.llmClient.AddSystemMessage(systemPrompt)
}

// ProcessUserQuery processes a user query and generates a response
func (s *ChatbotService) ProcessUserQuery(ctx context.Context, userQuery string) (string, error) {
	// Add user message to LLM
	s.llmClient.AddUserMessage(userQuery)

	// Analyze the user query to determine the appropriate API call
	apiPlanningPrompt := fmt.Sprintf(`
The user is asking: "%s"

Determine which Congress.gov API endpoint would be most appropriate to answer this question.
Available endpoints:
1. Search Bills - use when asking about legislation in general or specific bills by keyword
2. Get Bill - use when asking about a specific bill identified by congress number and bill number
3. Get Bill Summary - use when asking for a summary of a specific bill
4. Get Bill Actions - use when asking about status updates or timeline of a specific bill
5. Get Bill Cosponsors - use when asking about who supported a specific bill
6. Get Bill Related Bills - use when asking about bills similar to a specific bill
7. Search Members - use when asking about members of Congress in general
8. Get Member - use when asking about a specific member identified by bioguideId
9. Get Member Sponsorship - use when asking about bills a specific member has sponsored
10. Search Amendments - use when asking about amendments in general
11. Search Committees - use when asking about congressional committees in general
12. Get Committee - use when asking about a specific committee by ID
13. Search Congressional Record - use when asking about congressional debates, proceedings, or speeches
14. Search Nominations - use when asking about presidential nominations requiring Senate confirmation
15. Search Hearings - use when asking about congressional hearings

FRESHNESS GUIDELINES:
- ALWAYS prioritize getting the MOST RECENT data available
- For any search, default to the CURRENT Congress (119th) if not specified
- When dates/time periods are not specified, assume the user wants CURRENT information
- For bill status updates, ALWAYS query for the latest version/status
- If the user is asking about "recent" legislation, focus on bills from the last 30 days

QUERY FORMULATION GUIDELINES:
- Be specific and precise with search terms to get the most relevant results
- Avoid overly broad search terms that might return too many results
- Include key identifying information in queries (bill numbers, member names, etc.)
- For bill searches, include both common name and official designation if known
- When searching for members, include state information if available
- When querying for committees, include the chamber (House or Senate) if known
- For congressional record searches, include specific names or topics mentioned by the user

Format your response as JSON with the following structure:
{
    "endpoint": "name_of_endpoint",
    "parameters": {
        // Only include relevant parameters based on the endpoint
        "query": "search query", // For search endpoints (required for search endpoints)
        "congress": "congress number", // For bill endpoints (default to 119 if not specified)
        "billNumber": "bill number", // For bill endpoints (required for bill endpoints)
        "bioguideId": "member id" // For member endpoints (required for member endpoints)
    }
}

If you absolutely cannot determine any reasonable parameters, respond with:
{
    "endpoint": "need_more_info",
    "missing_info": "description of what information is needed"
}

Only respond with the JSON object and nothing else.
`, userQuery)

	// Reset the LLM for the planning step
	planningLLM := *s.llmClient
	planningLLM.ClearMessages()
	planningLLM.AddSystemMessage("You analyze user queries to determine which Congress.gov API to call.")
	planningLLM.AddUserMessage(apiPlanningPrompt)

	// Generate the API plan
	apiPlanJSON, err := planningLLM.GenerateResponse(ctx)
	if err != nil {
		return "", fmt.Errorf("failed to analyze query: %w", err)
	}

	// Parse the API plan
	var apiPlan struct {
		Endpoint    string            `json:"endpoint"`
		Parameters  map[string]string `json:"parameters"`
		MissingInfo string            `json:"missing_info"`
	}

	if err := json.Unmarshal([]byte(apiPlanJSON), &apiPlan); err != nil {
		// If JSON parsing fails, we'll ask the LLM to generate a direct response instead
		return s.generateDirectResponse(ctx, userQuery)
	}

	// Handle case where more information is needed
	if apiPlan.Endpoint == "need_more_info" {
		// Instead of just saying we need more info, use generateDirectResponse to give a better answer
		return s.generateDirectResponse(ctx, userQuery)
	}

	// Call the appropriate API based on the plan
	var apiResponse map[string]interface{}
	var apiErr error

	switch apiPlan.Endpoint {
	case "search_bills":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchBills(query, 0, 5)
	case "get_bill":
		congress := apiPlan.Parameters["congress"]
		billNumber := apiPlan.Parameters["billNumber"]
		apiResponse, apiErr = s.congressClient.GetBill(congress, billNumber)
	case "get_bill_summary":
		congress := apiPlan.Parameters["congress"]
		billNumber := apiPlan.Parameters["billNumber"]
		apiResponse, apiErr = s.congressClient.GetBillSummary(congress, billNumber)
	case "get_bill_actions":
		congress := apiPlan.Parameters["congress"]
		billNumber := apiPlan.Parameters["billNumber"]
		apiResponse, apiErr = s.congressClient.GetBillActions(congress, billNumber)
	case "get_bill_cosponsors":
		congress := apiPlan.Parameters["congress"]
		billNumber := apiPlan.Parameters["billNumber"]
		apiResponse, apiErr = s.congressClient.GetBillCosponsors(congress, billNumber)
	case "get_bill_related_bills":
		congress := apiPlan.Parameters["congress"]
		billNumber := apiPlan.Parameters["billNumber"]
		apiResponse, apiErr = s.congressClient.GetBillRelatedBills(congress, billNumber)
	case "search_members":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchMembers(query, 0, 5)
	case "get_member":
		bioguideId := apiPlan.Parameters["bioguideId"]
		apiResponse, apiErr = s.congressClient.GetMember(bioguideId)
	case "get_member_sponsorship":
		bioguideId := apiPlan.Parameters["bioguideId"]
		apiResponse, apiErr = s.congressClient.GetMemberSponsorship(bioguideId)
	case "search_amendments":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchAmendments(query, 0, 5)
	case "search_committees":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchCommittees(query, 0, 5)
	case "get_committee":
		committeeId := apiPlan.Parameters["committeeId"]
		apiResponse, apiErr = s.congressClient.GetCommittee(committeeId)
	case "search_congressional_record":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchCongressionalRecord(query, 0, 5)
	case "search_nominations":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchNominations(query, 0, 5)
	case "search_hearings":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchHearings(query, 0, 5)
	default:
		// If we don't recognize the endpoint, generate a direct response
		return s.generateDirectResponse(ctx, userQuery)
	}

	if apiErr != nil {
		return fmt.Sprintf("I encountered an error when trying to fetch information: %s", apiErr.Error()), nil
	}

	// Convert API response to JSON string for the LLM
	apiResponseJSON, err := json.MarshalIndent(apiResponse, "", "  ")
	if err != nil {
		return "", fmt.Errorf("failed to marshal API response: %w", err)
	}

	// Create a prompt for the LLM to interpret the API response
	interpretationPrompt := fmt.Sprintf(`
The user asked: "%s"

I called the Congress.gov API endpoint "%s" with the provided parameters and got this response:

%s

GUIDELINES FOR YOUR RESPONSE:
1. Extract and summarize the most relevant and RECENT information from the API response
2. ALWAYS check and include dates in your response to help users understand how current the information is
3. Present information in a clear, conversational way that directly answers the user's question
4. If the API returned empty or limited results, still provide helpful information:
   - Offer general information about the topic from your knowledge
   - Suggest related topics the user might be interested in
   - Acknowledge any limitations in the data's recency
   - Do NOT just ask for more specific information or details
5. If multiple items were returned, prioritize the most RECENT items first, then the most relevant ones
6. For information about bills or legislation:
   - Highlight the current status and most recent actions
   - Note when the bill was introduced and by whom
   - Include the latest update date and what that update was
7. For information about members:
   - Include which Congress they currently serve in
   - Note their committee assignments if available
   - Mention their current role/position
8. If data seems outdated, explicitly note this and suggest the information may have changed
9. Add relevant context that helps the user understand the information better
10. Be precise about facts and dates while maintaining an engaging, conversational style

Your response should be comprehensive, timely, and helpful, providing substantive information with a focus on currency and accuracy.
`, userQuery, apiPlan.Endpoint, string(apiResponseJSON))

	// Create a new message for the interpretation
	s.llmClient.AddUserMessage(interpretationPrompt)

	// Generate the final response
	finalResponse, err := s.llmClient.GenerateResponse(ctx)
	if err != nil {
		return "", fmt.Errorf("failed to generate final response: %w", err)
	}

	return finalResponse, nil
}

// generateDirectResponse generates a direct response from the LLM without using the API
func (s *ChatbotService) generateDirectResponse(ctx context.Context, userQuery string) (string, error) {
	// Create a more helpful prompt that encourages providing substantive information
	clarificationPrompt := fmt.Sprintf(`
The user asked: "%s"

I wasn't able to retrieve specific data from the Congress.gov API for this question.
Please provide a helpful and informative response using your general knowledge about Congress, legislation, or the topic.

GUIDELINES FOR YOUR DIRECT RESPONSE:
1. Answer the user's question as thoroughly as possible with your general knowledge
2. Always be clear about the currency of your information (e.g., "As of my last update...")
3. Focus on factual, verifiable information rather than speculation
4. Include relevant dates and timelines where appropriate
5. If discussing legislation or congressional actions:
   - Mention when it was proposed/passed if known
   - Note the Congress in which it occurred (e.g., "During the 118th Congress...")
   - Include sponsor names and party affiliations when relevant
6. If discussing members of Congress:
   - Note which Congress they serve(d) in
   - Include relevant committee assignments if known
   - Mention party affiliation and state
7. Be conversational and straightforward, not apologetic
8. If the topic requires very recent information that you may not have:
   - Acknowledge the limitation clearly
   - Provide the most recent information you do have
   - Suggest where the user might find more current information
9. Do not fabricate specific details about bills, votes, or members that you're unsure about

Respond directly to the user without mentioning the API, technical details, or the fact that this is a fallback response.
`, userQuery)

	// Add the clarification as a user message, not system message
	s.llmClient.AddUserMessage(clarificationPrompt)

	// Generate a direct response
	response, err := s.llmClient.GenerateResponse(ctx)
	if err != nil {
		return "", fmt.Errorf("failed to generate direct response: %w", err)
	}

	return response, nil
}

// GetConversationHistory returns the conversation history
func (s *ChatbotService) GetConversationHistory() []map[string]string {
	messages := s.llmClient.GetMessages()
	history := make([]map[string]string, 0, len(messages))

	for _, msg := range messages {
		// Default role
		role := "system"

		// Type assertions to determine message role
		if _, ok := msg.(llm.SystemChatMessage); ok {
			role = "system"
		} else if _, ok := msg.(llm.HumanChatMessage); ok {
			role = "user"
		} else if _, ok := msg.(llm.AIChatMessage); ok {
			role = "assistant"
		}

		history = append(history, map[string]string{
			"role":    role,
			"content": strings.TrimSpace(msg.GetContent()),
		})
	}

	return history
}

// ClearConversation clears the conversation history
func (s *ChatbotService) ClearConversation() {
	s.llmClient.ClearMessages()
	s.Initialize()
}
