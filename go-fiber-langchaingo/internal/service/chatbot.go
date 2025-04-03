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

YOUR CAPABILITIES:
1. You can search for and retrieve information about bills, amendments, and legislation
2. You can find information about members of Congress
3. You can provide summaries of bills

GUIDELINES:
- Be conversational and helpful, focusing on providing substantive information
- When you're asked a specific question that requires API data, try to use the appropriate API endpoint
- If specific details are missing (like dates or IDs), make reasonable assumptions based on context or provide the most recent/relevant information available
- If the API doesn't return useful results, provide general information based on your knowledge
- Avoid repeatedly asking for the same information
- Don't say "I need more information" unless absolutely necessary
- Don't make up fabricated details about specific bills or members
- Always maintain a helpful tone even when information is limited

EXAMPLES:
User: "Tell me about recent infrastructure bills"
Assistant: (Uses search_bills API with "infrastructure" as the query and returns information about the most recent bills)

User: "Who are the representatives from California?"
Assistant: (Uses search_members API with "California" as the query to find representatives from California)

User: "What did the Inflation Reduction Act do?"
Assistant: (Uses search_bills to find the Inflation Reduction Act and then get_bill_summary to provide details)
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
1. Search Bills - use when asking about legislation in general
2. Get Bill - use when asking about a specific bill identified by congress number and bill number
3. Get Bill Summary - use when asking for a summary of a specific bill
4. Search Members - use when asking about members of Congress in general
5. Get Member - use when asking about a specific member identified by bioguideId
6. Search Amendments - use when asking about amendments

GUIDELINES:
- Make reasonable assumptions when specific details are missing
- For general questions about bills or members, default to using search endpoints with appropriate keywords
- If asking about recent events without specific dates, assume the current Congress (119th)
- When in doubt, prefer providing a response using a search endpoint rather than asking for more information
- Only use "need_more_info" as a last resort when it's impossible to formulate any reasonable query

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
	case "search_members":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchMembers(query, 0, 5)
	case "get_member":
		bioguideId := apiPlan.Parameters["bioguideId"]
		apiResponse, apiErr = s.congressClient.GetMember(bioguideId)
	case "search_amendments":
		query := apiPlan.Parameters["query"]
		apiResponse, apiErr = s.congressClient.SearchAmendments(query, 0, 5)
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
1. Extract and summarize the most relevant information from the API response
2. Present information in a clear, conversational way that directly answers the user's question
3. If the API returned empty or limited results, still provide some helpful information:
   - Offer general information about the topic from your knowledge
   - Suggest related topics the user might be interested in
   - Do NOT just ask for more specific information or details
4. If multiple items were returned, highlight the most relevant ones rather than listing everything
5. Add relevant context that might help the user understand the information better
6. Be conversational and engaging - write like you're explaining to a person, not producing a technical report

Your response should be comprehensive and helpful, providing substantive information regardless of how complete the API response was.
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

I wasn't able to determine the exact API parameters needed to query the Congress.gov API for this specific question.
However, please provide a helpful and informative response using your general knowledge about Congress, legislation, or the topic.

You should:
1. Answer the user's question as best as possible with your general knowledge
2. Provide relevant context about the topic
3. Only if necessary, suggest what specific information would help provide a more detailed answer
4. Be conversational and straightforward, not apologetic

Respond directly to the user without mentioning the API or technical details.
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
