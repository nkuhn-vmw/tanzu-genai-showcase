<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * LinkedIn API client for accessing LinkedIn profiles and company data
 */
class LinkedInApiClient
{
    private const API_BASE_URL = 'https://api.linkedin.com/v2';
    private const OAUTH_URL = 'https://www.linkedin.com/oauth/v2';
    
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private SessionInterface $session;
    private ?HttpClientInterface $httpClient = null;
    
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    
    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger,
        SessionInterface $session
    ) {
        $this->params = $params;
        $this->logger = $logger;
        $this->session = $session;
        
        $this->initialize();
    }
    
    /**
     * Initialize the API client with configuration parameters
     */
    private function initialize(): void
    {
        $this->clientId = $this->params->get('linkedin_api.client_id');
        $this->clientSecret = $this->params->get('linkedin_api.client_secret');
        $this->redirectUri = $this->params->get('linkedin_api.redirect_uri');
        
        $this->httpClient = HttpClient::create();
    }
    
    /**
     * Get the authorization URL for LinkedIn OAuth
     *
     * @param array $scopes Requested permission scopes
     * @return string The authorization URL
     */
    public function getAuthorizationUrl(array $scopes = []): string
    {
        // Default scopes if none provided
        if (empty($scopes)) {
            $scopes = [
                'r_liteprofile',        // Basic profile info
                'r_emailaddress',       // Email address
                'w_member_social',      // Post, comment and like
                'r_organization_social' // Company page access
            ];
        }
        
        // Generate a random state parameter for security
        $state = bin2hex(random_bytes(16));
        $this->session->set('linkedin_oauth_state', $state);
        
        // Build authorization URL
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => implode(' ', $scopes)
        ];
        
        return self::OAUTH_URL . '/authorization?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     *
     * @param string $code The authorization code
     * @param string $state The state parameter to verify
     * @return array Access token data
     * @throws \Exception If state validation fails or API error occurs
     */
    public function getAccessToken(string $code, string $state): array
    {
        // Verify state parameter
        $savedState = $this->session->get('linkedin_oauth_state');
        if (!$savedState || $savedState !== $state) {
            throw new \Exception('Invalid state parameter');
        }
        
        // Clear the state from session
        $this->session->remove('linkedin_oauth_state');
        
        // Exchange code for token
        try {
            $response = $this->httpClient->request('POST', self::OAUTH_URL . '/accessToken', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => http_build_query([
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ])
            ]);
            
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn API error: ' . $e->getMessage());
            throw new \Exception('Failed to obtain access token: ' . $e->getMessage());
        }
    }
    
    /**
     * Make an authenticated API request to LinkedIn
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param string $method HTTP method
     * @param array $params Query parameters
     * @param string $accessToken OAuth access token
     * @return array Response data
     * @throws \Exception If API request fails
     */
    public function request(string $endpoint, string $method = 'GET', array $params = [], string $accessToken = null): array
    {
        if (!$accessToken) {
            throw new \Exception('No access token provided');
        }
        
        $url = self::API_BASE_URL . $endpoint;
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ]
            ];
            
            if ($method === 'GET' && !empty($params)) {
                $url .= '?' . http_build_query($params);
            } elseif (!empty($params)) {
                $options['json'] = $params;
            }
            
            $response = $this->httpClient->request($method, $url, $options);
            
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn API request error: ' . $e->getMessage());
            throw new \Exception('LinkedIn API request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the current user's LinkedIn profile
     *
     * @param string $accessToken OAuth access token
     * @return array Profile data
     */
    public function getProfile(string $accessToken): array
    {
        // Request basic profile fields
        $basicProfileFields = 'id,firstName,lastName,profilePicture(displayImage~:playableStreams),headline,vanityName';
        
        try {
            $basicProfile = $this->request('/me?projection=(' . $basicProfileFields . ')', 'GET', [], $accessToken);
            
            // Get email address (separate call)
            $emailData = $this->request('/emailAddress?q=members&projection=(elements*(handle~))', 'GET', [], $accessToken);
            
            // Add email to profile data
            if (isset($emailData['elements'][0]['handle~']['emailAddress'])) {
                $basicProfile['emailAddress'] = $emailData['elements'][0]['handle~']['emailAddress'];
            }
            
            return $this->formatProfileData($basicProfile);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn profile: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Format the LinkedIn profile data into a standardized structure
     *
     * @param array $rawProfileData Raw profile data from API
     * @return array Formatted profile data
     */
    private function formatProfileData(array $rawProfileData): array
    {
        $formattedData = [
            'linkedinId' => $rawProfileData['id'] ?? null,
            'firstName' => $this->getLocalizedField($rawProfileData['firstName'] ?? []),
            'lastName' => $this->getLocalizedField($rawProfileData['lastName'] ?? []),
            'headline' => $this->getLocalizedField($rawProfileData['headline'] ?? []),
            'vanityName' => $rawProfileData['vanityName'] ?? null,
            'profileUrl' => 'https://www.linkedin.com/in/' . ($rawProfileData['vanityName'] ?? $rawProfileData['id'] ?? ''),
            'email' => $rawProfileData['emailAddress'] ?? null,
            'pictureUrl' => null,
            'rawData' => $rawProfileData
        ];
        
        // Extract profile picture URL if available
        if (isset($rawProfileData['profilePicture']['displayImage~']['elements'])) {
            $pictures = $rawProfileData['profilePicture']['displayImage~']['elements'];
            if (!empty($pictures)) {
                // Get the highest resolution image
                $formattedData['pictureUrl'] = end($pictures)['identifiers'][0]['identifier'] ?? null;
            }
        }
        
        return $formattedData;
    }
    
    /**
     * Helper to extract localized field value
     *
     * @param array $fieldData Localized field data
     * @return string|null Extracted field value
     */
    private function getLocalizedField(array $fieldData): ?string
    {
        if (isset($fieldData['localized'])) {
            // Get the first locale value
            return reset($fieldData['localized']);
        }
        
        if (isset($fieldData['preferredLocale']) && isset($fieldData['preferredLocale']['country']) && isset($fieldData['preferredLocale']['language'])) {
            $locale = $fieldData['preferredLocale']['language'] . '_' . $fieldData['preferredLocale']['country'];
            return $fieldData['localized'][$locale] ?? null;
        }
        
        return null;
    }
    
    /**
     * Get the work experience of a LinkedIn user
     *
     * @param string $accessToken OAuth access token
     * @return array Work experience data
     */
    public function getWorkExperience(string $accessToken): array
    {
        try {
            $response = $this->request('/me?projection=(positions)', 'GET', [], $accessToken);
            
            if (!isset($response['positions'])) {
                return [];
            }
            
            $experiences = [];
            
            foreach ($response['positions']['elements'] ?? [] as $position) {
                $experiences[] = [
                    'companyName' => $this->getLocalizedField($position['company'] ?? []),
                    'title' => $this->getLocalizedField($position['title'] ?? []),
                    'startDate' => $this->formatDate($position['startDate'] ?? []),
                    'endDate' => $this->formatDate($position['endDate'] ?? []),
                    'current' => empty($position['endDate']),
                    'description' => $this->getLocalizedField($position['description'] ?? []),
                ];
            }
            
            return $experiences;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn work experience: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format LinkedIn date object (year+month)
     * 
     * @param array $dateData LinkedIn date data
     * @return string|null Formatted date
     */
    private function formatDate(array $dateData): ?string
    {
        if (empty($dateData) || !isset($dateData['year']) || !isset($dateData['month'])) {
            return null;
        }
        
        return $dateData['year'] . '-' . str_pad($dateData['month'], 2, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get company data from LinkedIn
     *
     * @param string $companyId LinkedIn company ID
     * @param string $accessToken OAuth access token
     * @return array Company data
     */
    public function getCompany(string $companyId, string $accessToken): array
    {
        try {
            $response = $this->request("/organizations/{$companyId}", 'GET', [], $accessToken);
            
            return [
                'id' => $response['id'] ?? null,
                'name' => $this->getLocalizedField($response['name'] ?? []),
                'description' => $this->getLocalizedField($response['description'] ?? []),
                'website' => $response['websiteUrl'] ?? null,
                'industry' => $this->getLocalizedField($response['industry'] ?? []),
                'companySize' => $response['staffCount'] ?? null,
                'headquarters' => $this->getLocalizedField($response['headquarters'] ?? []),
                'foundedYear' => $response['foundedYear'] ?? null,
                'specialties' => $response['specialties'] ?? [],
                'rawData' => $response
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn company: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get basic connection data for the current user
     * 
     * @param string $accessToken OAuth access token
     * @return array Connection data with count and sample connections
     */
    public function getConnections(string $accessToken): array
    {
        try {
            // First, get the total connection count
            $countResponse = $this->request('/connections?q=count', 'GET', [], $accessToken);
            $count = $countResponse['count'] ?? 0;
            
            // Then get a sample of recent connections for display
            $connectionsResponse = $this->request('/connections', 'GET', ['count' => 10, 'start' => 0], $accessToken);
            
            $connections = [];
            foreach ($connectionsResponse['elements'] ?? [] as $connection) {
                $connections[] = [
                    'id' => $connection['id'] ?? null,
                    'firstName' => $this->getLocalizedField($connection['firstName'] ?? []),
                    'lastName' => $this->getLocalizedField($connection['lastName'] ?? []),
                    'headline' => $this->getLocalizedField($connection['headline'] ?? []),
                    'pictureUrl' => $this->extractProfilePicture($connection),
                    'industry' => $this->getLocalizedField($connection['industry'] ?? []),
                    'connectionDegree' => $connection['connectionDegree'] ?? null,
                ];
            }
            
            return [
                'count' => $count,
                'connections' => $connections
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn connections: ' . $e->getMessage());
            return ['count' => 0, 'connections' => []];
        }
    }
    
    /**
     * Extract profile picture URL from connection data
     * 
     * @param array $connectionData Connection data from API
     * @return string|null Profile picture URL
     */
    private function extractProfilePicture(array $connectionData): ?string
    {
        if (isset($connectionData['profilePicture']['displayImage~']['elements'])) {
            $pictures = $connectionData['profilePicture']['displayImage~']['elements'];
            if (!empty($pictures)) {
                return end($pictures)['identifiers'][0]['identifier'] ?? null;
            }
        }
        
        return null;
    }
}
