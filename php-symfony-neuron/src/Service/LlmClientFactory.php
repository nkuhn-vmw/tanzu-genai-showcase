<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;

/**
 * Factory for creating the LLM client
 * This service handles the integration with the LLM API
 */
class LlmClientFactory
{
    private string $apiKey;
    private ?string $baseUrl;
    private string $model;
    private array $vcapServices;

    public function __construct(string $apiKey, ?string $baseUrl, string $model)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->model = $model;
        $this->vcapServices = $this->parseVcapServices();
    }

    /**
     * Get the API key
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getApiKey(): string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['api_key'])) {
            return $this->vcapServices['api_key'];
        }

        return $this->apiKey;
    }

    /**
     * Get the base URL
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getBaseUrl(): ?string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['url'])) {
            return $this->vcapServices['url'];
        }

        return $this->baseUrl;
    }

    /**
     * Get the model name
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getModel(): string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['model'])) {
            return $this->vcapServices['model'];
        }

        return $this->model;
    }

    /**
     * Create a new HTTP client
     */
    public function createHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ],
        ];

        if ($this->getBaseUrl()) {
            $options['base_uri'] = $this->getBaseUrl();
        }

        return HttpClient::create($options);
    }

    /**
     * Parse VCAP_SERVICES environment variable when running on Cloud Foundry
     */
    private function parseVcapServices(): array
    {
        $vcapServices = [];
        // Check both $_SERVER and $_ENV to be thorough
        $vcapServicesJson = $_SERVER['VCAP_SERVICES'] ?? $_ENV['VCAP_SERVICES'] ?? null;

        if (!$vcapServicesJson) {
            return $vcapServices;
        }

        try {
            // Use JSON_THROW_ON_ERROR for explicit error handling
            $services = json_decode($vcapServicesJson, true, 512, JSON_THROW_ON_ERROR);

            // Look for GenAI service
            foreach ($services as $serviceName => $serviceInstances) {
                // Look for service names containing 'genai' or 'llm'
                if (strpos(strtolower($serviceName), 'genai') !== false ||
                    strpos(strtolower($serviceName), 'llm') !== false) {
                    // Get the first instance (we expect only one binding)
                    if (!empty($serviceInstances[0]['credentials'])) {
                        $credentials = $serviceInstances[0]['credentials'];
                        // Map the credentials to our expected format
                        $vcapServices['api_key'] = $credentials['api_key'] ?? $credentials['apiKey'] ?? null;
                        $vcapServices['url'] = $credentials['url'] ?? $credentials['baseUrl'] ?? null;
                        $vcapServices['model'] = $credentials['model'] ?? null;
                        break;
                    }
                }
            }
        } catch (\JsonException $e) {
            // Log the error but don't crash
            error_log('Error parsing VCAP_SERVICES JSON: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Log the error but don't crash
            error_log('Error processing VCAP_SERVICES: ' . $e->getMessage());
        }

        return $vcapServices;
    }
}
