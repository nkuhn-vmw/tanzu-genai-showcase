<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Service\ApiClient\LinkedInApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service for LinkedIn integration business logic
 */
class LinkedInService
{
    private LinkedInApiClient $linkedInApiClient;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private SessionInterface $session;
    
    /**
     * Constructor
     */
    public function __construct(
        LinkedInApiClient $linkedInApiClient,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        RequestStack $requestStack
    ) {
        $this->linkedInApiClient = $linkedInApiClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->session = $requestStack->getSession();
    }
    
    /**
     * Get the LinkedIn authorization URL
     *
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        // Store the current executive profile ID in session if provided
        if ($executiveId = $this->session->get('current_executive_id')) {
            $this->session->set('linkedin_auth_executive_id', $executiveId);
        }
        
        return $this->linkedInApiClient->getAuthorizationUrl();
    }
    
    /**
     * Handle the OAuth callback and token exchange
     *
     * @param string $code Authorization code
     * @param string $state State value for CSRF protection
     * @return array Token data or error
     */
    public function handleCallback(string $code, string $state): array
    {
        try {
            // Exchange code for token
            $tokenData = $this->linkedInApiClient->getAccessToken($code, $state);
            
            // Store token in session temporarily (would use a more secure storage in production)
            $this->session->set('linkedin_access_token', $tokenData['access_token']);
            $this->session->set('linkedin_expires_at', time() + $tokenData['expires_in']);
            
            return [
                'success' => true,
                'token' => $tokenData
            ];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn callback error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if we have a valid LinkedIn access token
     *
     * @return bool True if we have a valid token
     */
    public function hasValidToken(): bool
    {
        $token = $this->session->get('linkedin_access_token');
        $expiresAt = $this->session->get('linkedin_expires_at');
        
        return $token && $expiresAt && $expiresAt > time();
    }
    
    /**
     * Get the current LinkedIn access token
     *
     * @return string|null Access token or null if not available
     */
    public function getAccessToken(): ?string
    {
        if (!$this->hasValidToken()) {
            return null;
        }
        
        return $this->session->get('linkedin_access_token');
    }
    
    /**
     * Get the current user's LinkedIn profile
     *
     * @return array Profile data or error
     */
    public function getCurrentUserProfile(): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'No valid access token'
            ];
        }
        
        try {
            $profileData = $this->linkedInApiClient->getProfile($token);
            
            if (isset($profileData['error'])) {
                return [
                    'success' => false,
                    'error' => $profileData['error']
                ];
            }
            
            return [
                'success' => true,
                'profile' => $profileData
            ];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn profile fetch error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get the work experience of the current user
     *
     * @return array Work experience data or error
     */
    public function getCurrentUserWorkExperience(): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'No valid access token'
            ];
        }
        
        try {
            $experiences = $this->linkedInApiClient->getWorkExperience($token);
            
            return [
                'success' => true,
                'experiences' => $experiences
            ];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn experience fetch error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get connections of the current user
     *
     * @return array Connections data or error
     */
    public function getCurrentUserConnections(): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'No valid access token'
            ];
        }
        
        try {
            $connections = $this->linkedInApiClient->getConnections($token);
            
            return [
                'success' => true,
                'connections' => $connections
            ];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn connections fetch error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an executive profile with LinkedIn data
     *
     * @param ExecutiveProfile $profile The executive profile to update
     * @param array $linkedinData LinkedIn profile data
     * @return bool True if successful
     */
    public function updateExecutiveWithLinkedInData(ExecutiveProfile $profile, array $linkedinData): bool
    {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return false;
            }
            
            // Update basic LinkedIn info
            $profile->setLinkedinId($linkedinData['linkedinId']);
            $profile->setLinkedinProfileUrl($linkedinData['profileUrl']);
            $profile->setProfilePictureUrl($linkedinData['pictureUrl']);
            
            // Store raw LinkedIn data
            $profile->setLinkedinData($linkedinData['rawData']);
            
            // Update profile with LinkedIn data if fields are empty
            if (empty($profile->getName()) && isset($linkedinData['firstName']) && isset($linkedinData['lastName'])) {
                $profile->setName($linkedinData['firstName'] . ' ' . $linkedinData['lastName']);
            }
            
            // Get work experiences
            $experiences = $this->linkedInApiClient->getWorkExperience($token);
            
            // Find current job for title
            $currentJob = null;
            foreach ($experiences as $experience) {
                if ($experience['current']) {
                    $currentJob = $experience;
                    break;
                }
            }
            
            // Update title if empty and found in LinkedIn
            if (empty($profile->getTitle()) && $currentJob && !empty($currentJob['title'])) {
                $profile->setTitle($currentJob['title']);
            }
            
            // Update previous companies if empty
            if (empty($profile->getPreviousCompanies())) {
                $previousCompanies = [];
                foreach ($experiences as $experience) {
                    if (!$experience['current'] && !empty($experience['companyName'])) {
                        $previousCompanies[] = $experience['companyName'];
                    }
                }
                
                if (!empty($previousCompanies)) {
                    $profile->setPreviousCompanies(implode(', ', $previousCompanies));
                }
            }
            
            // Get connections
            $connectionsData = $this->linkedInApiClient->getConnections($token);
            $profile->setConnectionCount($connectionsData['count'] ?? 0);
            
            // Update last synced timestamp
            $profile->setLastSynced(new \DateTimeImmutable());
            
            // Save changes
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating executive with LinkedIn data: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an executive profile from the session-stored LinkedIn access token
     *
     * @param ExecutiveProfile $profile The executive profile to update
     * @return array Result with success flag and message
     */
    public function syncExecutiveProfile(ExecutiveProfile $profile): array
    {
        try {
            // Get current user's profile
            $profileResult = $this->getCurrentUserProfile();
            
            if (!$profileResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch LinkedIn profile: ' . ($profileResult['error'] ?? 'Unknown error')
                ];
            }
            
            // Update the executive profile
            $updated = $this->updateExecutiveWithLinkedInData($profile, $profileResult['profile']);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update executive profile with LinkedIn data'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Successfully synced profile with LinkedIn',
                'profile' => $profile
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error syncing executive profile: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error syncing profile: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Find company connections for a specific company
     * 
     * @param Company $company The company to find connections for
     * @return array Company connections data
     */
    public function findCompanyConnections(Company $company): array
    {
        // This would typically involve more sophisticated logic to scan
        // across executive profiles and their connections for people at the target company
        // For this example, we'll use a simpler approach
        
        $executives = $company->getExecutiveProfiles();
        
        $connectionStats = [
            'totalConnections' => 0,
            'companyConnections' => 0,
            'executivesWithLinkedIn' => 0,
            'industry' => [
                'finance' => 0,
                'technology' => 0,
                'healthcare' => 0,
                'manufacturing' => 0,
                'retail' => 0,
                'other' => 0
            ]
        ];
        
        foreach ($executives as $executive) {
            if ($executive->getLinkedinId()) {
                $connectionStats['executivesWithLinkedIn']++;
                
                // Add their connection count
                $connectionStats['totalConnections'] += $executive->getConnectionCount() ?? 0;
                
                // Generate some random values for industry distribution
                // In a real implementation, this would come from actual LinkedIn data
                $connectionStats['industry']['finance'] += rand(5, 50);
                $connectionStats['industry']['technology'] += rand(10, 100);
                $connectionStats['industry']['healthcare'] += rand(5, 30);
                $connectionStats['industry']['manufacturing'] += rand(5, 40);
                $connectionStats['industry']['retail'] += rand(5, 25);
                $connectionStats['industry']['other'] += rand(10, 50);
            }
        }
        
        // Make up some company connections number - in reality this would
        // be based on actual LinkedIn data analysis
        $connectionStats['companyConnections'] = rand(5, 20);
        
        return $connectionStats;
    }
}
