<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
// Change: Import RequestStack
use Symfony\Component\HttpFoundation\RequestStack;
// Remove: use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * LinkedIn API client for accessing LinkedIn profiles and company data
 */
class LinkedInApiClient
{
    private const API_BASE_URL = 'https://api.linkedin.com/v2';
    private const OAUTH_URL = 'https://www.linkedin.com/oauth/v2';

    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    // Change: Use RequestStack
    private RequestStack $requestStack;
    private ?HttpClientInterface $httpClient = null;

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger,
        // Change: Inject RequestStack instead of SessionInterface
        RequestStack $requestStack
    ) {
        $this->params = $params;
        $this->logger = $logger;
        // Change: Store RequestStack
        $this->requestStack = $requestStack;

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
        // Change: Use RequestStack to get session
        $session = $this->requestStack->getSession();
        $session->set('linkedin_oauth_state', $state);

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
        // Change: Use RequestStack to get session
        $session = $this->requestStack->getSession();

        // Verify state parameter
        $savedState = $session->get('linkedin_oauth_state');
        if (!$savedState || $savedState !== $state) {
            throw new \Exception('Invalid state parameter');
        }

        // Clear the state from session
        $session->remove('linkedin_oauth_state');

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

            // Store token in session temporarily after successful exchange
            $tokenData = $response->toArray();
            // Change: Use RequestStack to get session
            $session = $this->requestStack->getSession();
            $session->set('linkedin_access_token', $tokenData['access_token']);
            // Ensure 'expires_in' exists before using it
            $expiresIn = $tokenData['expires_in'] ?? 3600; // Default to 1 hour if missing
            $session->set('linkedin_expires_at', time() + $expiresIn);


            return $tokenData;

        } catch (\Exception $e) {
            $this->logger->error('LinkedIn API error during token exchange: ' . $e->getMessage(), ['response' => $e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface ? $e->getResponse()->getContent(false) : 'N/A']);
            throw new \Exception('Failed to obtain access token: ' . $e->getMessage());
        }
    }

    /**
     * Make an authenticated API request to LinkedIn
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param string $method HTTP method
     * @param array $params Query parameters
     * @param string|null $accessToken OAuth access token (optional, will try session if null)
     * @return array Response data
     * @throws \Exception If API request fails or no token found
     */
    public function request(string $endpoint, string $method = 'GET', array $params = [], string $accessToken = null): array
    {
        // Change: Try getting token from session if not provided
        if ($accessToken === null) {
             $accessToken = $this->getAccessTokenFromSession();
        }

        if (!$accessToken) {
            throw new \Exception('No valid LinkedIn access token available.');
        }

        $url = self::API_BASE_URL . $endpoint;

        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0', // Recommended by LinkedIn docs
                     'LinkedIn-Version' => '202305' // Example: Specify API version
                ]
            ];

            if ($method === 'GET' && !empty($params)) {
                $url .= '?' . http_build_query($params);
            } elseif (!empty($params)) {
                $options['json'] = $params;
            }

            $response = $this->httpClient->request($method, $url, $options);

            // Check for non-2xx status codes after the request
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $errorContent = $response->getContent(false); // Get content without throwing exception
                $this->logger->error("LinkedIn API request failed with status {$statusCode}", [
                    'url' => $url,
                    'method' => $method,
                    'response' => $errorContent
                ]);
                throw new \Exception("LinkedIn API request failed with status {$statusCode}: {$errorContent}");
            }

            return $response->toArray();
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
             $this->logger->error('LinkedIn API transport error: ' . $e->getMessage(), ['url' => $url, 'method' => $method]);
            throw new \Exception('LinkedIn API transport error: ' . $e->getMessage());
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) { // Catch broader HttpClient exceptions
            $this->logger->error('LinkedIn API request error: ' . $e->getMessage(), ['url' => $url, 'method' => $method]);
             // Check if it's an HTTP exception to get response details
             $responseContent = 'N/A';
             if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
                  try {
                       $responseContent = $e->getResponse()->getContent(false);
                  } catch (\Exception $innerEx) { /* Ignore if cannot get content */ }
             }
             $this->logger->error('LinkedIn API request details:', ['response_content' => $responseContent]);
            throw new \Exception('LinkedIn API request failed: ' . $e->getMessage());
        }
    }

    /**
      * Get the current LinkedIn access token from session if valid
      *
      * @return string|null Access token or null if not available/expired
      */
     private function getAccessTokenFromSession(): ?string
     {
         // Change: Use RequestStack to get session
         $session = $this->requestStack->getSession();
         $token = $session->get('linkedin_access_token');
         $expiresAt = $session->get('linkedin_expires_at');

         if ($token && $expiresAt && $expiresAt > time()) {
             return $token;
         }

         // Token is missing or expired
         if ($token && $expiresAt && $expiresAt <= time()) {
              $this->logger->info('LinkedIn access token expired.');
              $session->remove('linkedin_access_token');
              $session->remove('linkedin_expires_at');
         }

         return null;
     }


    // --- Methods below remain largely the same, just ensure they use $this->request() which now handles getting the token ---

    /**
     * Get the current user's LinkedIn profile
     *
     * @return array Profile data
     * @throws \Exception If request fails or no token available
     */
    public function getProfile(): array
    {
        // Request basic profile fields
        $basicProfileFields = 'id,firstName,lastName,profilePicture(displayImage~:playableStreams),headline,vanityName';
        $endpoint = '/me?projection=(' . $basicProfileFields . ')';

        try {
            $basicProfile = $this->request($endpoint); // Token fetched automatically

            // Get email address (separate call)
            $emailData = $this->request('/emailAddress?q=members&projection=(elements*(handle~))');

            // Add email to profile data
            if (isset($emailData['elements'][0]['handle~']['emailAddress'])) {
                $basicProfile['emailAddress'] = $emailData['elements'][0]['handle~']['emailAddress'];
            }

            return $this->formatProfileData($basicProfile);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn profile: ' . $e->getMessage());
            // Re-throw or return error structure
             return ['error' => 'Failed to get LinkedIn profile: ' . $e->getMessage()];
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
     * @param mixed $fieldData Localized field data (can be array or null)
     * @return string|null Extracted field value
     */
    private function getLocalizedField(mixed $fieldData): ?string
    {
         if (!is_array($fieldData)) {
              return null; // Return null if not an array
         }

        if (isset($fieldData['localized'])) {
             // Prefer locale from preferredLocale if available
            if (isset($fieldData['preferredLocale']['language']) && isset($fieldData['preferredLocale']['country'])) {
                $localeKey = $fieldData['preferredLocale']['language'] . '_' . $fieldData['preferredLocale']['country'];
                if (isset($fieldData['localized'][$localeKey])) {
                    return $fieldData['localized'][$localeKey];
                }
            }
            // Fallback to the first available locale
            return reset($fieldData['localized']) ?: null;
        }

        return null;
    }


    /**
     * Get the work experience of a LinkedIn user
     *
     * @return array Work experience data
     * @throws \Exception If request fails or no token available
     */
    public function getWorkExperience(): array
    {
        try {
            // LinkedIn API for positions requires specific projection
            $response = $this->request('/me/positions'); // Adjust endpoint if needed

            if (!isset($response['elements'])) { // Check based on actual API response structure
                return [];
            }

            $experiences = [];

            foreach ($response['elements'] as $position) {
                 // Check if 'company' key exists before accessing its sub-keys
                 $companyName = isset($position['company']['name']) ? $this->getLocalizedField($position['company']['name']) : 'Unknown Company';

                $experiences[] = [
                    'companyName' => $companyName,
                    'title' => $this->getLocalizedField($position['title'] ?? []),
                    'startDate' => $this->formatDate($position['timePeriod']['startDate'] ?? []), // Adjusted structure
                    'endDate' => $this->formatDate($position['timePeriod']['endDate'] ?? []), // Adjusted structure
                    'current' => !isset($position['timePeriod']['endDate']), // Check if endDate exists
                    'description' => $this->getLocalizedField($position['description'] ?? []),
                ];
            }

            return $experiences;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn work experience: ' . $e->getMessage());
            return []; // Return empty array on error
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
        if (empty($dateData) || !isset($dateData['year'])) {
            return null;
        }
        // Month might be optional
        $month = isset($dateData['month']) ? str_pad($dateData['month'], 2, '0', STR_PAD_LEFT) : '01'; // Default to Jan if month missing

        return $dateData['year'] . '-' . $month;
    }


    /**
     * Get company data from LinkedIn
     *
     * @param string $companyId LinkedIn company ID (URN format, e.g., urn:li:organization:12345)
     * @return array Company data
     * @throws \Exception If request fails or no token available
     */
    public function getCompany(string $companyId): array
    {
        // Ensure it's a URN or construct one if just ID is passed (needs context)
        if (!str_starts_with($companyId, 'urn:li:organization:')) {
            // Assuming a simple ID was passed, which might not be correct for V2 API
            $companyUrn = 'urn:li:organization:' . $companyId;
             $this->logger->warning("Assuming URN format for company ID: {$companyUrn}");
        } else {
            $companyUrn = $companyId;
        }

        // Encode the URN for the URL path
        $encodedUrn = urlencode($companyUrn);

        // Define the fields (projection) needed
         $projection = '(id,name,description,websiteUrl,industries,staffCount,headquarters,foundedOn,specialties)';


        try {
            // Use the encoded URN in the endpoint
            $response = $this->request("/organizations/{$encodedUrn}?projection={$projection}");

            // Safely access potential null values
            $foundedOn = $response['foundedOn'] ?? null;
            $foundedYear = ($foundedOn && isset($foundedOn['year'])) ? $foundedOn['year'] : null;


            return [
                'id' => $response['id'] ?? null,
                'name' => $this->getLocalizedField($response['name'] ?? []),
                'description' => $this->getLocalizedField($response['description'] ?? []),
                'website' => $response['websiteUrl'] ?? null,
                // Industries might be an array, handle accordingly
                'industry' => isset($response['industries'][0]) ? $this->getLocalizedField($response['industries'][0]) : null,
                'companySize' => $response['staffCount'] ?? null,
                'headquarters' => isset($response['headquarters']) ? json_encode($response['headquarters']) : null, // Complex object
                'foundedYear' => $foundedYear,
                // Specialties might be an array, handle accordingly
                'specialties' => isset($response['specialties']) ? array_map([$this, 'getLocalizedField'], $response['specialties']) : [],
                'rawData' => $response
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn company: ' . $e->getMessage());
            return ['error' => 'Failed to get LinkedIn company: ' . $e->getMessage()];
        }
    }

    /**
     * Get basic connection data for the current user
     *
     * @return array Connection data with count and sample connections
     * @throws \Exception If request fails or no token available
     */
    public function getConnections(): array
    {
        try {
            // LinkedIn API V2 doesn't provide a simple total count easily.
            // We fetch connections page by page. Let's get the first page.
            $params = ['q' => 'viewer', 'start' => 0, 'count' => 10]; // Get first 10 connections
            $connectionsResponse = $this->request('/connections', 'GET', $params);

            $connections = [];
            foreach ($connectionsResponse['elements'] ?? [] as $connectionUrn) {
                // To get details, you'd typically need another API call per connection URN
                // For simplicity here, we'll return just the URNs or basic info if available directly
                // NOTE: Getting full connection details usually requires different permissions.
                // This endpoint might only return URNs.
                 $connections[] = ['urn' => $connectionUrn]; // Placeholder
            }

             // Get total count if available in metadata (often requires specific permissions)
             $count = $connectionsResponse['paging']['total'] ?? count($connections); // Approximate count


            return [
                'count' => $count,
                'connections' => $connections // This might be just URNs depending on permissions
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get LinkedIn connections: ' . $e->getMessage());
            // Check if it's a permission error (e.g., 403 Forbidden)
            if ($e->getCode() === 403) {
                 $this->logger->warning("Permission denied for fetching LinkedIn connections. Check API scopes.");
                 return ['count' => 0, 'connections' => [], 'error' => 'Permission denied'];
            }
            return ['count' => 0, 'connections' => [], 'error' => 'Failed to get connections: ' . $e->getMessage()];
        }
    }

}