package config

import (
	"encoding/json"
	"os"
	"strconv"

	"github.com/joho/godotenv"
)

// Config holds the application configuration
type Config struct {
	Port           int    `json:"port"`
	CongressAPIKey string `json:"congress_api_key"`
	LLMAPIKey      string `json:"llm_api_key"`
	LLMAPIURL      string `json:"llm_api_url"`
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

	// Check for Cloud Foundry service bindings (VCAP_SERVICES)
	if vcapServices := os.Getenv("VCAP_SERVICES"); vcapServices != "" {
		type serviceCredentials struct {
			APIKey string `json:"api_key"`
			URL    string `json:"url"`
		}

		type serviceInstance struct {
			Credentials serviceCredentials `json:"credentials"`
		}

		var services map[string][]serviceInstance
		if err := json.Unmarshal([]byte(vcapServices), &services); err == nil {
			// Look for LLM service
			// The service name might vary; adjust based on the actual GenAI tile service name
			for _, serviceName := range []string{"genai", "llm"} {
				if instances, ok := services[serviceName]; ok && len(instances) > 0 {
					config.LLMAPIKey = instances[0].Credentials.APIKey
					config.LLMAPIURL = instances[0].Credentials.URL
					break
				}
			}
		}
	}

	return config, nil
}
