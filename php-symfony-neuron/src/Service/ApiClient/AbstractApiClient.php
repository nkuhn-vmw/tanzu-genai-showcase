<?php

namespace App\Service\ApiClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for API clients
 */
abstract class AbstractApiClient implements ApiClientInterface
{
    protected HttpClientInterface $httpClient;
    protected ParameterBagInterface $params;
    protected LoggerInterface $logger;
    protected string $apiKey;
    protected string $baseUrl;

    /**
     * Constructor
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->logger = $logger;
        $this->initialize();
    }

    /**
     * Initialize API client with configuration
     */
    abstract protected function initialize(): void;

    /**
     * Make API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param array $options Additional options for the HTTP client
     * @return array Response data
     * @throws \Exception If API request fails
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // Add API key to parameters if required
        if (!empty($this->apiKey)) {
            $params = array_merge($params, $this->getAuthParams());
        }
        
        $requestOptions = [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Symfony/NeuronAI Financial Research Application'
            ],
        ];
        
        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($params)) {
            $requestOptions['query'] = $params;
        }
        
        // Add JSON body for POST requests
        if ($method === 'POST' && !empty($params)) {
            $requestOptions['json'] = $params;
        }
        
        // Merge with additional options
        $requestOptions = array_merge($requestOptions, $options);
        
        try {
            $this->logger->info("Making {$method} request to {$url}", ['params' => $params]);
            $response = $this->httpClient->request($method, $url, $requestOptions);
            
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \Exception("API returned error: {$statusCode}");
            }
            
            $data = $response->toArray();
            return $data;
        } catch (\Exception $e) {
            $this->logger->error("API request failed: {$e->getMessage()}");
            
            // During development with no API keys, return mock data
            if (strpos($e->getMessage(), 'Invalid API key') !== false || 
                strpos($e->getMessage(), 'authentication required') !== false) {
                return $this->getMockData($endpoint, $params);
            }
            
            throw $e;
        }
    }
    
    /**
     * Get authentication parameters for the API
     * 
     * @return array Authentication parameters
     */
    abstract protected function getAuthParams(): array;
    
    /**
     * Get mock data for development when API keys are not available
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array Mock response data
     */
    abstract protected function getMockData(string $endpoint, array $params): array;
}
