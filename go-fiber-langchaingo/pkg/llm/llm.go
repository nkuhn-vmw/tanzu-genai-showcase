package llm

import (
	"context"
	"fmt"
	"os"

	"github.com/tmc/langchaingo/llms"
	"github.com/tmc/langchaingo/llms/openai"
)

// ChatMessage interface for all message types
type ChatMessage interface {
	GetContent() string
}

// SystemChatMessage represents a system message
type SystemChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m SystemChatMessage) GetContent() string {
	return m.Content
}

// HumanChatMessage represents a message from a human
type HumanChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m HumanChatMessage) GetContent() string {
	return m.Content
}

// AIChatMessage represents a message from an AI
type AIChatMessage struct {
	Content string
}

// GetContent returns the content of the message
func (m AIChatMessage) GetContent() string {
	return m.Content
}

// LLMClient is a client for interacting with LLMs through LangChainGo
type LLMClient struct {
	llm      llms.Model
	messages []ChatMessage
}

// NewLLMClient creates a new LLM client using the provided API key and URL
func NewLLMClient(apiKey, apiURL string) (*LLMClient, error) {
	// Note: We're using openai interface here, but GenAI might use a different interface
	// depending on what models are available in the GenAI tile.
	// This may need to be adjusted based on the specific LLM provided by the GenAI tile.
	client, err := openai.New(
		openai.WithToken(apiKey),
		openai.WithBaseURL(apiURL),
	)
	if err != nil {
		return nil, fmt.Errorf("failed to create LLM client: %w", err)
	}

	return &LLMClient{
		llm:      client,
		messages: []ChatMessage{},
	}, nil
}

// AddSystemMessage adds a system message to the conversation
func (c *LLMClient) AddSystemMessage(content string) {
	c.messages = append(c.messages, SystemChatMessage{
		Content: content,
	})
}

// AddUserMessage adds a user message to the conversation
func (c *LLMClient) AddUserMessage(content string) {
	c.messages = append(c.messages, HumanChatMessage{
		Content: content,
	})
}

// AddAssistantMessage adds an assistant message to the conversation
func (c *LLMClient) AddAssistantMessage(content string) {
	c.messages = append(c.messages, AIChatMessage{
		Content: content,
	})
}

// GenerateResponse generates a response from the LLM
func (c *LLMClient) GenerateResponse(ctx context.Context) (string, error) {
	// In langchaingo v0.1.13, we need to create a prompt from our messages
	var prompt string

	for _, msg := range c.messages {
		var rolePrefix string

		switch msg.(type) {
		case SystemChatMessage:
			rolePrefix = "System: "
		case HumanChatMessage:
			rolePrefix = "Human: "
		case AIChatMessage:
			rolePrefix = "AI: "
		}

		prompt += rolePrefix + msg.GetContent() + "\n"
	}

	// Add a final prompt for the AI to respond to
	prompt += "AI: "

	// Get model from environment variable or use default
	model := os.Getenv("LLM")
	if model == "" {
		model = "gpt-4o-mini" // Default model if LLM env var is not set
	}

	// Use options for a chat model
	opts := []llms.CallOption{
		llms.WithModel(model),
		llms.WithTemperature(0.3), // Lower temperature for more consistent, factual responses
		llms.WithMaxTokens(8192),
	}

	// Use the Call method which takes a prompt string
	resp, err := c.llm.Call(ctx, prompt, opts...)
	if err != nil {
		return "", fmt.Errorf("failed to generate response: %w", err)
	}

	c.AddAssistantMessage(resp)
	return resp, nil
}

// ClearMessages clears all messages in the conversation
func (c *LLMClient) ClearMessages() {
	c.messages = []ChatMessage{}
}

// GetMessages returns all messages in the conversation
func (c *LLMClient) GetMessages() []ChatMessage {
	return c.messages
}
