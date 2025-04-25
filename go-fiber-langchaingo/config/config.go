package config

import (
	"encoding/json"
	"os"
	"strconv"
	"strings"

	"github.com/joho/godotenv"
)

// Config holds the application configuration
type Config struct {
	Port           int    `json:"port"`
	CongressAPIKey string `json:"congress_api_key"`
	LLMAPIKey      string `json:"llm_api_key"`
	LLMAPIURL      string `json:"llm_api_url"`
	LLMModel       string `json:"llm_model"`
	Environment    string `json:"environment"`
}

// LoadConfig loads configuration from environment variables
// and service bindings (when deployed on Tanzu)
func LoadConfig() (*Config, error) {
	// Load .env file if it exists (for local development)
	godotenv.Load()

	// Default values
	config := &Config{
		Port:        8080,
		Environment: "development",
	}

	// Override with environment variables
	if port, err := strconv.Atoi(os.Getenv("PORT")); err == nil {
		config.Port = port
	}

	if env := os.Getenv("ENV"); env != "" {
		config.Environment = env
	}

	config.CongressAPIKey = os.Getenv("CONGRESS_API_KEY")
	config.LLMAPIKey = os.Getenv("GENAI_API_KEY")
	config.LLMAPIURL = os.Getenv("GENAI_API_BASE_URL")
	config.LLMModel = os.Getenv("GENAI_MODEL")

	// Check for Cloud Foundry service bindings (VCAP_SERVICES)
	if vcapServices := os.Getenv("VCAP_SERVICES"); vcapServices != "" {
		// Parse VCAP_SERVICES as a generic JSON structure
		var services map[string][]map[string]interface{}
		if err := json.Unmarshal([]byte(vcapServices), &services); err == nil {
			// Look for GenAI service by tag, label, or name
			for serviceName, instances := range services {
				for _, instance := range instances {
					// Check for genai tag
					hasGenAITag := false
					if tags, ok := instance["tags"].([]interface{}); ok {
						for _, tag := range tags {
							if tagStr, ok := tag.(string); ok && containsIgnoreCase(tagStr, "genai") {
								hasGenAITag = true
								break
							}
						}
					}

					// Check for genai label
					hasGenAILabel := false
					if label, ok := instance["label"].(string); ok && containsIgnoreCase(label, "genai") {
						hasGenAILabel = true
					}

					// Check service name
					hasGenAIName := containsIgnoreCase(serviceName, "genai") ||
						containsIgnoreCase(serviceName, "llm")

					if hasGenAITag || hasGenAILabel || hasGenAIName {
						// Found a potential GenAI service, check for chat capability
						if credentials, ok := instance["credentials"].(map[string]interface{}); ok {
							// Check for model_capabilities
							hasChatCapability := false
							if capabilities, ok := credentials["model_capabilities"].([]interface{}); ok {
								for _, cap := range capabilities {
									if capStr, ok := cap.(string); ok && containsIgnoreCase(capStr, "chat") {
										hasChatCapability = true
										break
									}
								}
							}

							// If no capabilities specified or has chat capability
							if _, hasCapabilities := credentials["model_capabilities"]; !hasCapabilities || hasChatCapability {
								// Extract credentials with proper field mapping
								if apiKey, ok := credentials["api_key"].(string); ok {
									config.LLMAPIKey = apiKey
								}

								// Try different field names for API base URL
								if apiBase, ok := credentials["api_base"].(string); ok {
									config.LLMAPIURL = apiBase
								} else if url, ok := credentials["url"].(string); ok {
									config.LLMAPIURL = url
								} else if baseUrl, ok := credentials["base_url"].(string); ok {
									config.LLMAPIURL = baseUrl
								}

								// Try different field names for model
								if modelName, ok := credentials["model_name"].(string); ok {
									// If model_provider is available, prefix the model name
									if provider, ok := credentials["model_provider"].(string); ok {
										config.LLMModel = provider + "/" + modelName
									} else {
										config.LLMModel = modelName
									}
								} else if model, ok := credentials["model"].(string); ok {
									config.LLMModel = model
								}

								break
							}
						}
					}
				}
			}
		}
	}

	// Fallback to environment variables if not set from VCAP_SERVICES
	if config.LLMAPIKey == "" {
		config.LLMAPIKey = getFirstNonEmpty(os.Getenv("GENAI_API_KEY"), os.Getenv("LLM_API_KEY"), os.Getenv("API_KEY"))
	}
	if config.LLMAPIURL == "" {
		config.LLMAPIURL = getFirstNonEmpty(os.Getenv("GENAI_API_BASE_URL"), os.Getenv("LLM_API_BASE"), os.Getenv("API_BASE_URL"))
	}
	if config.LLMModel == "" {
		config.LLMModel = getFirstNonEmpty(os.Getenv("GENAI_MODEL"), os.Getenv("LLM_MODEL"), os.Getenv("MODEL_NAME"), "gpt-4o-mini")
	}

	return config, nil
}

// Helper function to check if a string contains another string, ignoring case
func containsIgnoreCase(s, substr string) bool {
	s, substr = strings.ToLower(s), strings.ToLower(substr)
	return strings.Contains(s, substr)
}

// Helper function to get the first non-empty string from a list
func getFirstNonEmpty(values ...string) string {
	for _, v := range values {
		if v != "" {
			return v
		}
	}
	return ""
}
